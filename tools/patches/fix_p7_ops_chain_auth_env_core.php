<?php
declare(strict_types=1);

$root = getcwd();

function p7fix_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function p7fix_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$prod = $root . '/sites/opus-p7-ops/config/environment.prod.example.php';
if (!is_file($prod)) {
    fwrite(STDERR, 'PROD_EXAMPLE_MISSING: ' . $prod . PHP_EOL);
    exit(1);
}

$prodSource = p7fix_read($prod);
if (!str_contains($prodSource, 'environment.prod.example.php')) {
    $prodSource = str_replace(
        "declare(strict_types=1);\n",
        "declare(strict_types=1);\n\n// environment.prod.example.php\n",
        $prodSource
    );
    p7fix_write($prod, $prodSource);
}

$files = [
    $root . '/sites/opus-p7-ops/public/language.php',
    $root . '/tools/patches/update_p7_ops_chain_auth_env_core.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }

    $source = p7fix_read($file);

    $source = str_replace(
        "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);",
        "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);",
        $source
    );

    $source = str_replace(
        '$start = (int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? hrtime(true));' . PHP_EOL . '        $durationMs = round((microtime(true) - $start) * 1000, 3);',
        '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));' . PHP_EOL . '        $durationMs = round((microtime(true) - $start) * 1000, 3);',
        $source
    );

    $source = str_replace(
        '$start = (int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? hrtime(true));' . PHP_EOL . '            $durationMs = round((microtime(true) - $start) * 1000, 3);',
        '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));' . PHP_EOL . '            $durationMs = round((microtime(true) - $start) * 1000, 3);',
        $source
    );

    $source = str_replace('p7ops_profiler_start_microtime', 'p7ops_profiler_start_microtime', $source);
    $source = str_replace('hrtime(true)', 'microtime(true)', $source);
    $source = str_replace('round((microtime(true) - $start) * 1000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $source);
    $source = str_replace('(int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true))', '(float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true))', $source);

    if ($file === $root . '/tools/patches/update_p7_ops_chain_auth_env_core.php'
        && str_contains($source, '$prodEnv = <<<\'PHP\'')
        && !str_contains($source, '// environment.prod.example.php')
    ) {
        $source = str_replace(
            "<?php\n" . "declare(strict_types=1);\n\n" . "return [\n    'environment' => 'prod',",
            "<?php\n" . "declare(strict_types=1);\n\n" . "// environment.prod.example.php\n\n" . "return [\n    'environment' => 'prod',",
            $source
        );
    }

    p7fix_write($file, $source);
}

$smoke = $root . '/tools/smokes/smoke_p7_ops_chain_auth_env_core.php';
if (is_file($smoke)) {
    $source = p7fix_read($smoke);
    if (!str_contains($source, 'P7_OPS_CHAIN_AUTH_ENV_FIX_CORE')) {
        $source = str_replace(
            "echo 'P7_OPS_CHAIN_AUTH_ENV_CORE_SMOKE' . PHP_EOL;",
            "echo 'P7_OPS_CHAIN_AUTH_ENV_CORE_SMOKE' . PHP_EOL;" . PHP_EOL . "echo 'P7_OPS_CHAIN_AUTH_ENV_FIX_CORE' . PHP_EOL;",
            $source
        );
        p7fix_write($smoke, $source);
    }
}

$readme = $root . '/sites/opus-p7-ops/README.md';
if (is_file($readme)) {
    $source = p7fix_read($readme);
    if (!str_contains($source, 'P7_OPS_CHAIN_AUTH_ENV_FIX_CORE')) {
        $source .= PHP_EOL;
        $source .= '## P7_OPS_CHAIN_AUTH_ENV_FIX_CORE' . PHP_EOL . PHP_EOL;
        $source .= '- Fixes the chain auth environment smoke marker for `environment.prod.example.php`.' . PHP_EOL;
        $source .= '- Replaces profiler clock math with `microtime(true)` to avoid float-to-int warnings on PHP/Windows.' . PHP_EOL;
        p7fix_write($readme, $source);
    }
}

echo 'P7_OPS_CHAIN_AUTH_ENV_FIX_CORE_UPDATED' . PHP_EOL;
