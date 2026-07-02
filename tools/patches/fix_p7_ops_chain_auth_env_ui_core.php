<?php
declare(strict_types=1);

$root = getcwd();

function p7fix_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_FIX_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function p7fix_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_FIX_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$siteDir = $root . '/sites/opus-p7-ops';
$publicDir = $siteDir . '/public';
$configDir = $siteDir . '/config';
$logDir = $root . '/var/logs/opus_lstsar-manager';

foreach ([$siteDir, $publicDir, $configDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_FIX_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, 'P7_FIX_LOG_DIR_CREATE_FAILED: ' . $logDir . PHP_EOL);
    exit(1);
}

file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$prod = $configDir . '/environment.prod.example.php';
if (is_file($prod)) {
    $prodSource = p7fix_read($prod);
    if (!str_contains($prodSource, 'environment.prod.example.php')) {
        $prodSource = str_replace(
            "declare(strict_types=1);\n",
            "declare(strict_types=1);\n\n// environment.prod.example.php\n",
            $prodSource
        );
        p7fix_write($prod, $prodSource);
    }
}

$clockTargets = [
    $publicDir . '/language.php',
    $root . '/tools/patches/update_p7_ops_chain_auth_env_core.php',
    $root . '/tools/patches/fix_p7_ops_chain_auth_env_core.php',
    $root . '/tools/patches/update_p7_ops_en_navigation_profiler_text_core.php',
];

foreach ($clockTargets as $file) {
    if (!is_file($file)) {
        continue;
    }

    $source = p7fix_read($file);

    $source = str_replace("\$GLOBALS['p7ops_profiler_start_ns'] = hrtime(true);", "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);", $source);
    $source = str_replace("\$GLOBALS['p7ops_profiler_start_ns'] = microtime(true);", "\$GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);", $source);
    $source = str_replace('p7ops_profiler_start_ns', 'p7ops_profiler_start_microtime', $source);

    $source = str_replace(
        '$start = (int) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
        '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
        $source
    );

    $source = str_replace(
        '$start = (int) ($GLOBALS[\'p7ops_profiler_start_ns\'] ?? hrtime(true));',
        '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
        $source
    );

    $source = str_replace(
        '$start = (int) ($GLOBALS[\'p7ops_profiler_start_ns\'] ?? microtime(true));',
        '$start = (float) ($GLOBALS[\'p7ops_profiler_start_microtime\'] ?? microtime(true));',
        $source
    );

    $source = str_replace('round((hrtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $source);
    $source = str_replace('round((microtime(true) - $start) / 1000000, 3)', 'round((microtime(true) - $start) * 1000, 3)', $source);

    p7fix_write($file, $source);
}

$cssFile = $publicDir . '/ops-ui.css';
if (!is_file($cssFile)) {
    fwrite(STDERR, 'P7_FIX_CSS_MISSING: ' . $cssFile . PHP_EOL);
    exit(1);
}

$css = p7fix_read($cssFile);
if (!str_contains($css, 'P7_OPS_HEADER_NO_OVERLAP_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_HEADER_NO_OVERLAP_CORE */' . PHP_EOL;
    $css .= 'html,body{max-width:100%;overflow-x:hidden}' . PHP_EOL;
    $css .= '.ops-shell{box-sizing:border-box;width:min(1280px,calc(100vw - 2rem));max-width:calc(100vw - 2rem);margin-inline:auto;padding-inline:1rem}' . PHP_EOL;
    $css .= '.ops-panel,.ops-card,.ops-section,.ops-hero,.ops-header,.ops-site-header,.ops-topbar,.ops-toolbar,.ops-nav,.ops-navigation,.ops-language-selector{box-sizing:border-box;min-width:0;max-width:100%}' . PHP_EOL;
    $css .= '.ops-site-header,.ops-header,.ops-topbar,.ops-toolbar,.ops-nav,.ops-navigation,.ops-panel:first-child{display:flex!important;flex-wrap:wrap!important;align-items:center;gap:1rem;overflow:visible}' . PHP_EOL;
    $css .= '.ops-site-header>*,.ops-header>*,.ops-topbar>*,.ops-toolbar>*,.ops-nav>*,.ops-navigation>*,.ops-panel:first-child>*{min-width:0;max-width:100%}' . PHP_EOL;
    $css .= '.ops-language-selector,[class*="language-selector"],[class*="LanguageSelector"]{position:static!important;inset:auto!important;top:auto!important;right:auto!important;left:auto!important;bottom:auto!important;z-index:auto!important;transform:none!important;margin-left:auto;max-width:100%;width:auto;flex:0 1 auto}' . PHP_EOL;
    $css .= '.ops-language-selector form,.ops-language-selector label,.ops-language-selector select,[class*="language-selector"] form,[class*="language-selector"] label,[class*="language-selector"] select{max-width:100%;min-width:0}' . PHP_EOL;
    $css .= '.ops-language-selector select,[class*="language-selector"] select{width:auto;max-width:min(20rem,100%)}' . PHP_EOL;
    $css .= '.ops-main-nav,.ops-nav-links,.ops-tabs,.ops-menu,.ops-navbar{display:flex!important;flex-wrap:wrap!important;gap:.65rem;min-width:0;max-width:100%;flex:1 1 36rem}' . PHP_EOL;
    $css .= '.ops-main-nav a,.ops-nav-links a,.ops-tabs a,.ops-menu a,.ops-navbar a,.ops-action-button{white-space:nowrap}' . PHP_EOL;
    $css .= '.ops-panel:first-child .ops-language-selector,.ops-panel:first-child [class*="language-selector"]{margin-left:auto}' . PHP_EOL;
    $css .= '@media (max-width:1100px){.ops-shell{width:100%;max-width:100%;padding-inline:.75rem}.ops-language-selector,[class*="language-selector"]{order:10;margin-left:0;width:100%;flex:1 1 100%}.ops-language-selector select,[class*="language-selector"] select{width:100%;max-width:100%}.ops-main-nav,.ops-nav-links,.ops-tabs,.ops-menu,.ops-navbar{width:100%;flex:1 1 100%}}' . PHP_EOL;
    $css .= '@media (max-width:720px){.ops-site-header,.ops-header,.ops-topbar,.ops-toolbar,.ops-nav,.ops-navigation,.ops-panel:first-child{display:grid!important;grid-template-columns:1fr}.ops-main-nav a,.ops-nav-links a,.ops-tabs a,.ops-menu a,.ops-navbar a,.ops-action-button{white-space:normal;text-align:center}}' . PHP_EOL;
}

p7fix_write($cssFile, $css);

$readme = $siteDir . '/README.md';
$readmeSource = is_file($readme) ? p7fix_read($readme) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readmeSource, 'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE')) {
    $readmeSource .= PHP_EOL;
    $readmeSource .= '## P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE' . PHP_EOL . PHP_EOL;
    $readmeSource .= '- Fixes `environment.prod.example.php` smoke marker after the chain/auth/env delivery.' . PHP_EOL;
    $readmeSource .= '- Replaces profiler timing math with `microtime(true)` to avoid PHP/Windows float-to-int warnings.' . PHP_EOL;
    $readmeSource .= '- Prevents header navigation and language selector overlap.' . PHP_EOL;
    $readmeSource .= '- Keeps runtime logs under `var/logs/opus_lstsar-manager/` while excluding `.log` files from Git.' . PHP_EOL;
}
p7fix_write($readme, $readmeSource);

echo 'P7_OPS_CHAIN_AUTH_ENV_UI_FIX_CORE_UPDATED' . PHP_EOL;
