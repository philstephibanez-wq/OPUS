<?php
declare(strict_types=1);

/*
 * Opus RefBook source manifest builder.
 */

$asapRoot = $argv[1] ?? dirname(__DIR__, 2);
$refbookRoot = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--refbook=')) {
        $refbookRoot = substr($arg, 10);
    }
}

$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
$outputRoot = $asapRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'refbook';

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, 'OPUS_REFBOOK_MANIFEST_FRAMEWORK_ROOT_NOT_FOUND=' . $frameworkRoot . PHP_EOL);
    exit(1);
}

if (!is_dir($outputRoot) && !mkdir($outputRoot, 0775, true) && !is_dir($outputRoot)) {
    fwrite(STDERR, 'OPUS_REFBOOK_MANIFEST_OUTPUT_ROOT_CREATE_FAILED=' . $outputRoot . PHP_EOL);
    exit(1);
}

$symbols = [];
foreach (phpFiles($frameworkRoot) as $file) {
    $content = (string)file_get_contents($file);
    $symbol = symbolOf($content);
    if ($symbol === null) {
        continue;
    }

    $tag = extractRefbookTag($content);
    if ($tag === null) {
        continue;
    }

    $symbols[] = [
        'symbol' => $symbol['symbol'],
        'name' => $symbol['name'],
        'namespace' => $symbol['namespace'],
        'kind' => $symbol['kind'],
        'file' => relativePath($asapRoot, $file),
        'domain' => $tag['domain'],
        'role' => $tag['role'],
        'contract' => normalizeList($tag['contract']),
        'examples' => normalizeList($tag['examples'] ?? []),
        'diagrams' => normalizeList($tag['diagrams'] ?? []),
        'methods' => publicMethods($content),
    ];
}

usort($symbols, static fn(array $a, array $b): int => $a['symbol'] <=> $b['symbol']);

$domains = [];
foreach ($symbols as $symbol) {
    $domain = $symbol['domain'];
    if (!isset($domains[$domain])) {
        $domains[$domain] = ['name' => $domain, 'symbols' => []];
    }
    $domains[$domain]['symbols'][] = $symbol['symbol'];
}
ksort($domains);

$manifest = [
    'schema' => 'OPUS_REFBOOK_SOURCE_MANIFEST_V1',
    'generated_at' => date(DATE_ATOM),
    'symbol_count' => count($symbols),
    'domain_count' => count($domains),
    'domains' => array_values($domains),
    'symbols' => $symbols,
];

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, 'OPUS_REFBOOK_MANIFEST_JSON_ENCODE_FAILED' . PHP_EOL);
    exit(1);
}

$output = $outputRoot . DIRECTORY_SEPARATOR . 'api_reference.generated.json';
file_put_contents($output, $json . PHP_EOL);

if ($refbookRoot !== null) {
    $dataRoot = $refbookRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'reference' . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataRoot) && !mkdir($dataRoot, 0775, true) && !is_dir($dataRoot)) {
        fwrite(STDERR, 'OPUS_REFBOOK_MANIFEST_REFBOOK_DATA_ROOT_CREATE_FAILED=' . $dataRoot . PHP_EOL);
        exit(1);
    }
    file_put_contents($dataRoot . DIRECTORY_SEPARATOR . 'api_reference.generated.json', $json . PHP_EOL);
}

echo 'OPUS_REFBOOK_MANIFEST_SCHEMA=OPUS_REFBOOK_SOURCE_MANIFEST_V1' . PHP_EOL;
echo 'OPUS_REFBOOK_MANIFEST_DOMAINS=' . count($domains) . PHP_EOL;
echo 'OPUS_REFBOOK_MANIFEST_SYMBOLS=' . count($symbols) . PHP_EOL;
echo 'OPUS_REFBOOK_MANIFEST_OUTPUT=' . $output . PHP_EOL;
exit(0);

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

/** @return array{namespace:string,name:string,kind:string,symbol:string}|null */
function symbolOf(string $content): ?array
{
    if (preg_match('/^\s*namespace\s+([^;{]+)[;{]/m', $content, $ns) !== 1) {
        return null;
    }
    if (preg_match('/^\s*(?:abstract\s+|final\s+)?(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $name) !== 1) {
        return null;
    }
    return [
        'namespace' => trim($ns[1]),
        'name' => $name[2],
        'kind' => $name[1],
        'symbol' => trim($ns[1]) . '\\' . $name[2],
    ];
}

/** @return array<string,mixed>|null */
function extractRefbookTag(string $content): ?array
{
    if (preg_match('/OPUS_REFBOOK:\s*(.*?)END_OPUS_REFBOOK/s', $content, $m) !== 1) {
        return null;
    }
    $parsed = parseBlock(trim($m[1]));
    foreach (['domain', 'role', 'contract'] as $required) {
        if (!array_key_exists($required, $parsed)) {
            fwrite(STDERR, 'OPUS_REFBOOK_MANIFEST_TAG_MISSING_FIELD=' . $required . PHP_EOL);
            exit(1);
        }
    }
    return $parsed;
}

/** @return array<string,mixed> */
function parseBlock(string $block): array
{
    $result = [];
    $currentListKey = null;
    foreach (preg_split('/\R/', $block) ?: [] as $line) {
        $line = preg_replace('/^\s*\*\s?/', '', $line) ?? $line;
        $line = rtrim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*?)\s*$/', $line, $m) === 1) {
            $key = $m[1];
            $value = $m[2];
            if ($value === '') {
                $result[$key] = [];
                $currentListKey = $key;
            } else {
                $result[$key] = $value;
                $currentListKey = null;
            }
            continue;
        }
        if ($currentListKey !== null && preg_match('/^\s*-\s+(.+?)\s*$/', $line, $m) === 1) {
            $result[$currentListKey][] = $m[1];
        }
    }
    return $result;
}

/** @return list<string> */
function normalizeList(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_map('strval', $value));
    }
    return [$value];
}

/** @return list<array{name:string,signature:string,static:bool}> */
function publicMethods(string $content): array
{
    $methods = [];
    if (preg_match_all('/^\s*public\s+(static\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(([^)]*)\)/m', $content, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $m) {
            $methods[] = [
                'name' => $m[2],
                'signature' => 'public ' . ($m[1] !== '' ? 'static ' : '') . 'function ' . $m[2] . '(' . trim(preg_replace('/\s+/', ' ', $m[3]) ?? $m[3]) . ')',
                'static' => trim($m[1]) !== '',
            ];
        }
    }
    return $methods;
}

function relativePath(string $root, string $file): string
{
    $rootReal = realpath($root) ?: $root;
    $fileReal = realpath($file) ?: $file;
    $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/') . '/';
    $fileNorm = str_replace('\\', '/', $fileReal);
    return str_starts_with($fileNorm, $rootNorm) ? substr($fileNorm, strlen($rootNorm)) : $fileNorm;
}
