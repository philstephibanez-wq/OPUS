<?php

declare(strict_types=1);

/**
 * P112Q3C public API coverage matrix generator.
 *
 * Public CLI tool.
 * Role:
 *   Scan the local Opus framework sources and build an observable matrix of
 *   public classes/interfaces/traits and public methods versus available test,
 *   smoke and recipe references.
 *
 * Contract:
 *   - read only source/test files;
 *   - never install dependencies;
 *   - never mutate framework code;
 *   - write reports only under var/reports/p112q3c_public_api_coverage;
 *   - never pretend that a textual reference is a proof of executed unit test;
 *   - strict mode must fail explicitly when public methods have no unit test
 *     reference.
 */
final class P112Q3CPublicApiCoverageMatrix
{
    private string $root;
    private string $frameworkRoot;
    private string $reportRoot;

    /** @var array<int,array<string,mixed>> */
    private array $symbols = [];

    /** @var array<int,array<string,mixed>> */
    private array $rows = [];

    /** @var array<string,string> */
    private array $unitCorpus = [];

    /** @var array<string,string> */
    private array $smokeCorpus = [];

    /** @var array<string,string> */
    private array $recipeCorpus = [];

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->frameworkRoot = $this->root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
        $this->reportRoot = $this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p112q3c_public_api_coverage';
    }

    /**
     * Public CLI entrypoint.
     *
     * @return int Process exit code.
     */
    public function run(): int
    {
        $this->assertDirectory($this->frameworkRoot, 'OPUS_FRAMEWORK_ROOT_MISSING');

        $this->symbols = $this->scanFrameworkSymbols();
        $this->unitCorpus = $this->loadCorpus([$this->root . DIRECTORY_SEPARATOR . 'tests']);
        $this->smokeCorpus = $this->loadCorpus([$this->root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smoke']);
        $this->recipeCorpus = $this->loadCorpus([$this->root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recipes']);
        $this->rows = $this->buildRows();

        $summary = $this->buildSummary();
        $paths = $this->writeReports($summary);

        echo 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_OK' . PHP_EOL;
        echo 'Symbols=' . (string) $summary['symbols'] . ' PublicMethods=' . (string) $summary['public_methods'] . PHP_EOL;
        echo 'UnitCandidates=' . (string) $summary['unit_candidate'] . ' IntegrationOnly=' . (string) $summary['integration_only'] . ' MissingTestReference=' . (string) $summary['missing_test_reference'] . PHP_EOL;
        echo 'JSON=' . $paths['json'] . PHP_EOL;
        echo 'MD=' . $paths['md'] . PHP_EOL;
        echo 'HTML=' . $paths['html'] . PHP_EOL;

        if ($this->isStrictMode() && $summary['missing_test_reference'] > 0) {
            fwrite(STDERR, 'P112Q3C_STRICT_FAILED: public methods without unit test reference: ' . (string) $summary['missing_test_reference'] . PHP_EOL);

            return 2;
        }

        return 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function scanFrameworkSymbols(): array
    {
        $files = $this->phpFiles($this->frameworkRoot);
        $symbols = [];

        foreach ($files as $file) {
            $content = $this->readFile($file);
            foreach ($this->parsePhpSymbols($content, $file) as $symbol) {
                $symbols[] = $symbol;
            }
        }

        usort($symbols, static fn (array $a, array $b): int => strcmp((string) $a['fqcn'], (string) $b['fqcn']));

        return $symbols;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parsePhpSymbols(string $source, string $file): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $symbols = [];
        $count = count($tokens);
        $classTokens = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait'];

        if (defined('T_ENUM')) {
            $classTokens[(int) constant('T_ENUM')] = 'enum';
        }

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($this->isToken($token, T_NAMESPACE)) {
                $namespace = $this->readNamespace($tokens, $i + 1);
                continue;
            }

            if (!is_array($token) || !isset($classTokens[$token[0]])) {
                continue;
            }

            if ($this->isClassNameSeparatorBefore($tokens, $i)) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if ($nameIndex === null || !$this->isToken($tokens[$nameIndex], T_STRING)) {
                continue;
            }

            $shortName = (string) $tokens[$nameIndex][1];
            $kind = $classTokens[$token[0]];
            $bodyStart = $this->findNextChar($tokens, $nameIndex + 1, '{');
            if ($bodyStart === null) {
                continue;
            }

            $relative = $this->relativePath($file);
            $methods = $this->parsePublicMethods($tokens, $bodyStart + 1, $kind);
            $symbols[] = [
                'kind' => $kind,
                'namespace' => $namespace,
                'short_name' => $shortName,
                'fqcn' => $namespace === '' ? $shortName : $namespace . '\\' . $shortName,
                'file' => $relative,
                'domain' => $this->domainFromPath($relative),
                'line' => is_array($token) ? (int) $token[2] : 0,
                'refbook_tag' => str_contains($source, 'OPUS_REFBOOK:'),
                'public_methods' => $methods,
            ];

            $i = $this->findMatchingBrace($tokens, $bodyStart) ?? $bodyStart;
        }

        return $symbols;
    }

    /**
     * @param array<int,mixed> $tokens
     * @return array<int,array<string,mixed>>
     */
    private function parsePublicMethods(array $tokens, int $start, string $kind): array
    {
        $methods = [];
        $depth = 1;
        $visibility = null;
        $lastDocCommentLine = null;
        $count = count($tokens);

        for ($i = $start; $i < $count && $depth > 0; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if ($depth !== 1) {
                continue;
            }

            if ($this->isToken($token, T_DOC_COMMENT)) {
                $lastDocCommentLine = (int) $token[2];
                continue;
            }

            if ($this->isToken($token, T_PUBLIC)) {
                $visibility = 'public';
                continue;
            }

            if ($this->isToken($token, T_PROTECTED)) {
                $visibility = 'protected';
                continue;
            }

            if ($this->isToken($token, T_PRIVATE)) {
                $visibility = 'private';
                continue;
            }

            if (!$this->isToken($token, T_FUNCTION)) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if ($nameIndex === null || $tokens[$nameIndex] === '&') {
                $nameIndex = $this->nextMeaningfulTokenIndex($tokens, ($nameIndex ?? $i) + 1);
            }

            if ($nameIndex === null || !$this->isToken($tokens[$nameIndex], T_STRING)) {
                $visibility = null;
                continue;
            }

            $methodVisibility = $visibility ?? ($kind === 'interface' ? 'public' : 'public');
            if ($methodVisibility === 'public') {
                $methodLine = (int) $tokens[$nameIndex][2];
                $methods[] = [
                    'name' => (string) $tokens[$nameIndex][1],
                    'line' => $methodLine,
                    'docblock' => $lastDocCommentLine !== null && $lastDocCommentLine <= $methodLine,
                ];
            }

            $visibility = null;
            $lastDocCommentLine = null;
        }

        return $methods;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildRows(): array
    {
        $rows = [];

        foreach ($this->symbols as $symbol) {
            foreach ($symbol['public_methods'] as $method) {
                $fqcn = (string) $symbol['fqcn'];
                $short = (string) $symbol['short_name'];
                $methodName = (string) $method['name'];
                $unit = $this->hasClassAndMethodReference($this->unitCorpus, $fqcn, $short, $methodName);
                $smoke = $this->hasClassOrMethodReference($this->smokeCorpus, $fqcn, $short, $methodName);
                $recipe = $this->hasClassOrMethodReference($this->recipeCorpus, $fqcn, $short, $methodName);
                $status = $unit ? 'UNIT_CANDIDATE' : (($smoke || $recipe) ? 'INTEGRATION_ONLY' : 'MISSING_TEST_REFERENCE');

                $rows[] = [
                    'domain' => $symbol['domain'],
                    'kind' => $symbol['kind'],
                    'symbol' => $fqcn,
                    'method' => $methodName,
                    'file' => $symbol['file'],
                    'line' => $method['line'],
                    'docblock' => $method['docblock'],
                    'refbook_tag' => $symbol['refbook_tag'],
                    'unit_candidate' => $unit,
                    'smoke_reference' => $smoke,
                    'recipe_reference' => $recipe,
                    'status' => $status,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['symbol'] . '::' . (string) $a['method'], (string) $b['symbol'] . '::' . (string) $b['method']);
        });

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSummary(): array
    {
        $summary = [
            'generated_at' => gmdate('c'),
            'symbols' => count($this->symbols),
            'public_methods' => count($this->rows),
            'unit_candidate' => 0,
            'integration_only' => 0,
            'missing_test_reference' => 0,
            'with_docblock' => 0,
            'with_refbook_tag' => 0,
            'by_domain' => [],
            'by_status' => [],
            'strict_mode' => $this->isStrictMode(),
            'note' => 'This matrix detects source references and coverage candidates. It is not a proof that every referenced test has asserted every behavior.',
        ];

        foreach ($this->rows as $row) {
            $status = (string) $row['status'];
            $domain = (string) $row['domain'];
            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;
            $summary['by_domain'][$domain][$status] = ($summary['by_domain'][$domain][$status] ?? 0) + 1;

            if ($status === 'UNIT_CANDIDATE') {
                $summary['unit_candidate']++;
            } elseif ($status === 'INTEGRATION_ONLY') {
                $summary['integration_only']++;
            } else {
                $summary['missing_test_reference']++;
            }

            if ($row['docblock']) {
                $summary['with_docblock']++;
            }

            if ($row['refbook_tag']) {
                $summary['with_refbook_tag']++;
            }
        }

        ksort($summary['by_domain']);
        ksort($summary['by_status']);

        return $summary;
    }

    /**
     * @param array<string,mixed> $summary
     * @return array<string,string>
     */
    private function writeReports(array $summary): array
    {
        if (!is_dir($this->reportRoot) && !mkdir($this->reportRoot, 0777, true) && !is_dir($this->reportRoot)) {
            throw new RuntimeException('P112Q3C_REPORT_ROOT_CREATE_FAILED: ' . $this->reportRoot);
        }

        $stamp = gmdate('Ymd_His');
        $payload = [
            'summary' => $summary,
            'rows' => $this->rows,
        ];

        $jsonPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_' . $stamp . '.json';
        $mdPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_' . $stamp . '.md';
        $htmlPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_' . $stamp . '.html';

        $this->writeFile($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        $this->writeFile($mdPath, $this->renderMarkdown($summary));
        $this->writeFile($htmlPath, $this->renderHtml($summary));

        foreach (['json' => $jsonPath, 'md' => $mdPath, 'html' => $htmlPath] as $type => $path) {
            $latest = $this->reportRoot . DIRECTORY_SEPARATOR . 'latest.' . $type;
            $this->writeFile($latest, $this->readFile($path));
        }

        return [
            'json' => $jsonPath,
            'md' => $mdPath,
            'html' => $htmlPath,
        ];
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function renderMarkdown(array $summary): string
    {
        $out = [];
        $out[] = '# P112Q3C â€” Opus Public API Coverage Matrix';
        $out[] = '';
        $out[] = 'Generated at: `' . (string) $summary['generated_at'] . '`';
        $out[] = '';
        $out[] = '## Contract note';
        $out[] = '';
        $out[] = 'This report detects unit/contract/recipe **coverage candidates** from local source references.';
        $out[] = 'It does not pretend that a textual reference proves behavioral assertion coverage.';
        $out[] = '';
        $out[] = '## Summary';
        $out[] = '';
        $out[] = '| Metric | Count |';
        $out[] = '|---|---:|';
        $out[] = '| Symbols | ' . (string) $summary['symbols'] . ' |';
        $out[] = '| Public methods | ' . (string) $summary['public_methods'] . ' |';
        $out[] = '| Unit candidates | ' . (string) $summary['unit_candidate'] . ' |';
        $out[] = '| Integration only | ' . (string) $summary['integration_only'] . ' |';
        $out[] = '| Missing test reference | ' . (string) $summary['missing_test_reference'] . ' |';
        $out[] = '| Methods with docblock | ' . (string) $summary['with_docblock'] . ' |';
        $out[] = '| Methods in RefBook-tagged source | ' . (string) $summary['with_refbook_tag'] . ' |';
        $out[] = '';
        $out[] = '## By domain';
        $out[] = '';
        $out[] = '| Domain | UNIT_CANDIDATE | INTEGRATION_ONLY | MISSING_TEST_REFERENCE |';
        $out[] = '|---|---:|---:|---:|';
        foreach ($summary['by_domain'] as $domain => $stats) {
            $out[] = '| ' . $this->escapeMd((string) $domain) . ' | ' . (string) ($stats['UNIT_CANDIDATE'] ?? 0) . ' | ' . (string) ($stats['INTEGRATION_ONLY'] ?? 0) . ' | ' . (string) ($stats['MISSING_TEST_REFERENCE'] ?? 0) . ' |';
        }
        $out[] = '';
        $out[] = '## Public method matrix';
        $out[] = '';
        $out[] = '| Status | Domain | Symbol | Method | Unit | Smoke | Recipe | File |';
        $out[] = '|---|---|---|---|---:|---:|---:|---|';
        foreach ($this->rows as $row) {
            $out[] = '| ' . $this->escapeMd((string) $row['status'])
                . ' | ' . $this->escapeMd((string) $row['domain'])
                . ' | `' . $this->escapeMd((string) $row['symbol']) . '`'
                . ' | `' . $this->escapeMd((string) $row['method']) . '`'
                . ' | ' . ($row['unit_candidate'] ? 'yes' : 'no')
                . ' | ' . ($row['smoke_reference'] ? 'yes' : 'no')
                . ' | ' . ($row['recipe_reference'] ? 'yes' : 'no')
                . ' | `' . $this->escapeMd((string) $row['file']) . ':' . (string) $row['line'] . '` |';
        }

        return implode(PHP_EOL, $out) . PHP_EOL;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function renderHtml(array $summary): string
    {
        $rows = '';
        foreach ($this->rows as $row) {
            $status = (string) $row['status'];
            $class = $status === 'UNIT_CANDIDATE' ? 'ok' : ($status === 'INTEGRATION_ONLY' ? 'warn' : 'miss');
            $rows .= '<tr class="' . $class . '"><td>' . $this->h($status) . '</td><td>' . $this->h((string) $row['domain']) . '</td><td><code>' . $this->h((string) $row['symbol']) . '</code></td><td><code>' . $this->h((string) $row['method']) . '</code></td><td>' . ($row['unit_candidate'] ? 'yes' : 'no') . '</td><td>' . ($row['smoke_reference'] ? 'yes' : 'no') . '</td><td>' . ($row['recipe_reference'] ? 'yes' : 'no') . '</td><td><code>' . $this->h((string) $row['file']) . ':' . $this->h((string) $row['line']) . '</code></td></tr>' . PHP_EOL;
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>P112Q3C Public API Coverage Matrix</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0b1220;color:#eef4ff;margin:0;padding:24px}h1{margin:0 0 8px}.muted{color:#9fb2d8}.cards{display:flex;gap:12px;flex-wrap:wrap;margin:22px 0}.card{border:1px solid #263957;border-radius:14px;padding:14px 18px;background:#101b2d}.card strong{display:block;font-size:26px}table{border-collapse:collapse;width:100%;background:#101b2d;border-radius:12px;overflow:hidden}th,td{border-bottom:1px solid #263957;padding:9px 10px;text-align:left;font-size:13px}th{background:#17243a}.ok td:first-child{color:#4ade80}.warn td:first-child{color:#facc15}.miss td:first-child{color:#fb7185}code{color:#fde68a}.note{border-left:4px solid #60a5fa;padding:10px 14px;background:#0f1a2c}</style></head><body>'
            . '<h1>P112Q3C â€” Opus Public API Coverage Matrix</h1><p class="muted">Generated at ' . $this->h((string) $summary['generated_at']) . '</p>'
            . '<div class="note">Coverage candidates are detected from local source references. This is not a proof that every behavior has an executed unit assertion.</div>'
            . '<div class="cards"><div class="card"><span>Symbols</span><strong>' . $this->h((string) $summary['symbols']) . '</strong></div><div class="card"><span>Public methods</span><strong>' . $this->h((string) $summary['public_methods']) . '</strong></div><div class="card"><span>Unit candidates</span><strong>' . $this->h((string) $summary['unit_candidate']) . '</strong></div><div class="card"><span>Integration only</span><strong>' . $this->h((string) $summary['integration_only']) . '</strong></div><div class="card"><span>Missing test reference</span><strong>' . $this->h((string) $summary['missing_test_reference']) . '</strong></div></div>'
            . '<table><thead><tr><th>Status</th><th>Domain</th><th>Symbol</th><th>Method</th><th>Unit</th><th>Smoke</th><th>Recipe</th><th>File</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '</body></html>' . PHP_EOL;
    }

    /**
     * @param string[] $roots
     * @return array<string,string>
     */
    private function loadCorpus(array $roots): array
    {
        $corpus = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            foreach ($this->phpFiles($root) as $file) {
                $corpus[$this->relativePath($file)] = $this->readFile($file);
            }
        }

        return $corpus;
    }

    /**
     * @param array<string,string> $corpus
     */
    private function hasClassAndMethodReference(array $corpus, string $fqcn, string $short, string $method): bool
    {
        foreach ($corpus as $content) {
            if (($this->containsSymbol($content, $fqcn) || str_contains($content, $short)) && str_contains($content, $method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,string> $corpus
     */
    private function hasClassOrMethodReference(array $corpus, string $fqcn, string $short, string $method): bool
    {
        foreach ($corpus as $content) {
            if ($this->containsSymbol($content, $fqcn) || str_contains($content, $short . '::') || str_contains($content, 'new ' . $short) || str_contains($content, $method)) {
                return true;
            }
        }

        return false;
    }

    private function containsSymbol(string $content, string $fqcn): bool
    {
        return str_contains($content, $fqcn) || str_contains($content, str_replace('\\', '\\\\', $fqcn));
    }

    /**
     * @return string[]
     */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function readNamespace(array $tokens, int $start): string
    {
        $parts = [];
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $token[1];
            } elseif ($token === '\\') {
                $parts[] = '\\';
            }
        }

        return trim(implode('', $parts), '\\');
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function isClassNameSeparatorBefore(array $tokens, int $index): bool
    {
        $prev = $this->previousMeaningfulTokenIndex($tokens, $index - 1);

        return $prev !== null && $tokens[$prev] === T_DOUBLE_COLON;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function previousMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function findNextChar(array $tokens, int $start, string $char): ?int
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            if ($tokens[$i] === $char) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private function findMatchingBrace(array $tokens, int $start): ?int
    {
        $depth = 0;
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            if ($tokens[$i] === '{') {
                $depth++;
            } elseif ($tokens[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function isToken(mixed $token, int $type): bool
    {
        return is_array($token) && $token[0] === $type;
    }

    private function domainFromPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $parts = explode('/', $normalized);
        $index = array_search('Opus', $parts, true);
        if ($index === false || !isset($parts[$index + 1])) {
            return 'UNKNOWN';
        }

        return strtoupper((string) $parts[$index + 1]);
    }

    private function relativePath(string $path): string
    {
        $root = $this->root . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    private function assertDirectory(string $path, string $code): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException($code . ': ' . $path);
        }
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('P112Q3C_FILE_READ_FAILED: ' . $path);
        }

        return $content;
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('P112Q3C_FILE_WRITE_FAILED: ' . $path);
        }
    }

    private function isStrictMode(): bool
    {
        return (string) getenv('OPUS_P112Q3C_STRICT') === '1';
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeMd(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}

$root = dirname(__DIR__, 2);

try {
    exit((new P112Q3CPublicApiCoverageMatrix($root))->run());
} catch (Throwable $exception) {
    fwrite(STDERR, 'P112Q3C_PUBLIC_API_COVERAGE_MATRIX_FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
