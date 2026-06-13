<?php
declare(strict_types=1);

/*
 * P112Q2T — Apply OPUS_REFBOOK baseline tags to every Opus framework symbol.
 *
 * Safety:
 * - Tags are normal comments, not PHPDoc docblocks.
 * - If a PHPDoc class block exists, the tag block is inserted before it so
 *   Reflection/doc tools keep the original docblock attached to the class.
 * - Existing OPUS_REFBOOK tags are preserved.
 */

$asapRoot = getenv('OPUS_ROOT') ?: 'H:\\ASAP';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--asap=')) {
        $asapRoot = substr($arg, 7);
    }
}

$asapRoot = require_dir($asapRoot, 'P112Q2T_OPUS_ROOT_NOT_FOUND');
$frameworkRoot = require_dir($asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus', 'P112Q2T_FRAMEWORK_ROOT_NOT_FOUND');

$files = phpFiles($frameworkRoot);
$tagged = 0;
$skipped = 0;

foreach ($files as $file) {
    $content = (string)file_get_contents($file);

    if (str_contains($content, 'OPUS_REFBOOK:')) {
        $skipped++;
        continue;
    }

    $symbol = symbolInfo($content);
    if ($symbol === null) {
        continue;
    }

    $domain = domainFromNamespace($symbol['namespace']);
    $tag = buildTagBlock($symbol, $domain);

    $updated = insertTagBeforeSymbol($content, $symbol['name'], $tag);
    if ($updated === $content) {
        fail('P112Q2T_TAG_INSERT_FAILED=' . $file);
    }

    file_put_contents($file, $updated);
    $tagged++;
}

writeToolDoc($asapRoot, $tagged, $skipped);
ensureGitignore($asapRoot, '/var/generated/');

echo 'P112Q2T_TAGGED=' . $tagged . PHP_EOL;
echo 'P112Q2T_SKIPPED_EXISTING=' . $skipped . PHP_EOL;
echo 'P112Q2T_ALL_SOURCE_TAGS_BASELINE_OK' . PHP_EOL;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function require_dir(string $path, string $code): string
{
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        fail($code . '=' . $path);
    }
    return rtrim($real, DIRECTORY_SEPARATOR);
}

/** @return list<string> */
function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $info) {
        if ($info instanceof SplFileInfo && $info->isFile() && strtolower($info->getExtension()) === 'php') {
            $files[] = $info->getPathname();
        }
    }
    sort($files);
    return $files;
}

/** @return array{namespace:string,name:string,kind:string}|null */
function symbolInfo(string $content): ?array
{
    if (preg_match('/^\s*namespace\s+([^;{]+)[;{]/m', $content, $ns) !== 1) {
        return null;
    }

    if (preg_match('/^\s*(?:abstract\s+|final\s+)?(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $m) !== 1) {
        return null;
    }

    return [
        'namespace' => trim($ns[1]),
        'kind' => $m[1],
        'name' => $m[2],
    ];
}

function domainFromNamespace(string $namespace): string
{
    $parts = explode('\\', $namespace);
    $domain = $parts[1] ?? 'Core';
    return strtoupper($domain);
}

/** @param array{namespace:string,name:string,kind:string} $symbol */
function buildTagBlock(array $symbol, string $domain): string
{
    $humanKind = $symbol['kind'];
    $name = $symbol['name'];
    $domainLower = strtolower($domain);

    $lines = [
        '/*',
        ' * OPUS_REFBOOK:',
        ' *   domain: ' . $domain,
        ' *   role: ' . ucfirst($humanKind) . ' ' . $name . ' belongs to the ' . $domain . ' Opus framework domain.',
        ' *   contract:',
        ' *     - keeps responsibility limited to the ' . $domain . ' domain',
        ' *     - exposes explicit behavior for the RefBook extractor',
        ' *     - must not rely on silent fallback behavior',
        ' *   examples:',
        ' *     - ' . $domainLower . '-overview',
        ' *   diagrams:',
        ' *     - ' . $domainLower . '-runtime',
        ' * END_OPUS_REFBOOK',
        ' */',
        '',
    ];

    return implode(PHP_EOL, $lines);
}

function insertTagBeforeSymbol(string $content, string $className, string $tag): string
{
    $declarationPattern = '/^(\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+' . preg_quote($className, '/') . '\b.*)$/m';

    if (preg_match($declarationPattern, $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return $content;
    }

    $declarationStart = $match[0][1];
    $before = substr($content, 0, $declarationStart);

    if (preg_match('/\/\*\*[\s\S]*?\*\/\s*$/', $before, $doc, PREG_OFFSET_CAPTURE) === 1) {
        $insertAt = $doc[0][1];
        return substr($content, 0, $insertAt) . $tag . substr($content, $insertAt);
    }

    return substr($content, 0, $declarationStart) . $tag . substr($content, $declarationStart);
}

function writeToolDoc(string $asapRoot, int $tagged, int $skipped): void
{
    $doc = <<<'MD'
# P112Q2T — Opus RefBook All Source Tags Baseline

## Objectif

Ajouter une balise `OPUS_REFBOOK` baseline à tous les symboles du framework Opus.

## Règles

- Les balises sont dans les sources Opus.
- Les `.md` ne remplacent pas les balises de génération de documentation.
- Les balises baseline sont volontairement génériques.
- L'enrichissement détaillé se fera ensuite domaine par domaine.
- Les commentaires sont lintés via `php -l` sur tout le framework.

## Sécurité syntaxe

Les blocs `OPUS_REFBOOK` sont des commentaires normaux, insérés avant le PHPDoc existant si présent.
Cela évite de casser l'association du PHPDoc natif avec la classe.

MD;

    $doc .= '- Tagged during apply: `' . $tagged . '`' . PHP_EOL;
    $doc .= '- Already tagged: `' . $skipped . '`' . PHP_EOL;

    writeFile($asapRoot . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P112Q2T_OPUS_REFBOOK_ALL_SOURCE_TAGS_BASELINE.md', $doc);
}

function ensureGitignore(string $root, string $entry): void
{
    $file = $root . DIRECTORY_SEPARATOR . '.gitignore';
    $content = is_file($file) ? (string)file_get_contents($file) : '';
    $lines = preg_split('/\R/', $content) ?: [];
    if (!in_array($entry, $lines, true)) {
        file_put_contents($file, rtrim($content) . PHP_EOL . $entry . PHP_EOL);
    }
}

function writeFile(string $file, string $content): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail('P112Q2T_DOC_DIR_CREATE_FAILED=' . $dir);
    }
    if (file_put_contents($file, $content) === false) {
        fail('P112Q2T_DOC_WRITE_FAILED=' . $file);
    }
}
