<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$bootstrap = $site . '/application/default/bootstrap.php';
$layout = $site . '/application/default/layouts/main.php';
$menuFile = $site . '/application/default/navigation/menu.php';
$localesFile = $site . '/application/default/local/locales.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([$bootstrap, $layout, $menuFile, $localesFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        $fail('OWASYS_SERVER_LAYOUT_MIGRATION_REQUIRED_FILE_MISSING:' . $requiredFile);
    }
}

$source = file_get_contents($bootstrap);
if (!is_string($source) || $source === '') {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_BOOTSTRAP_READ_FAILED');
}
if (!str_contains($source, '<aside class="ow-sidebar">')) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_SIDEBAR_MARKER_MISSING');
}
if (!str_contains($source, "echo '<!doctype html>'")) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_DOCUMENT_ECHO_MARKER_MISSING');
}

$source = str_replace(
    "$locales = array_values(array_filter((array) ($siteConfig['locales'] ?? ['fr']), 'is_string'));",
    "$localeLabels = require $siteRoot . '/application/default/local/locales.php';\nif (!is_array($localeLabels) || $localeLabels === []) {\n    throw new RuntimeException('OWASYS_LOCALE_REGISTRY_INVALID');\n}\n$locales = array_keys($localeLabels);",
    $source,
    $localeReplacementCount
);
if ($localeReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_LOCALE_REPLACEMENT_INVALID');
}

$source = str_replace(
    "    return is_string($value) && $value !== '' ? $value : $key;",
    "    return is_string($value) && $value !== '' ? $value : '[[' . $key . ']]';",
    $source,
    $fallbackReplacementCount
);
if ($fallbackReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_I18N_FALLBACK_REPLACEMENT_INVALID');
}

$menuPattern = <<<'REGEX'
~\$menu = \[\];\Rforeach \(\$routesConfig\['routes'\] as \$candidateRoute\) \{.*?\R\}\Rusort\(\$menu, static fn \(array \$a, array \$b\): int => \(\(int\) \(\$a\['order'\] \?\? 0\)\) <=> \(\(int\) \(\$b\['order'\] \?\? 0\)\)\);~s
REGEX;
$source = preg_replace(
    $menuPattern,
    "$menu = require $siteRoot . '/application/default/navigation/menu.php';\nif (!is_array($menu)) {\n    throw new RuntimeException('OWASYS_NAVIGATION_DEFINITION_INVALID');\n}\nusort($menu, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));",
    $source,
    1,
    $menuReplacementCount
);
if (!is_string($source) || $menuReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_MENU_REPLACEMENT_INVALID');
}

$layoutStartPattern = <<<'REGEX'
~\$body = '<div class="ow-shell"><aside class="ow-sidebar">';.*?\$body \.= '</nav></aside><main class="ow-main"><header class="ow-topbar"><div><span class="ow-pill">' \. \$h\(\$pageTitle\) \. '</span><h1>' \. \$h\(\$pageTitle\) \. '</h1><p class="ow-muted">' \. \$h\(\$pageSummary\) \. '</p></div></header>';~s
REGEX;
$replacement = <<<'PHP'
$contentHtml = '<main class="ow-main"><header class="ow-topbar"><div><span class="ow-pill">' . $h($pageTitle) . '</span><h1>' . $h($pageTitle) . '</h1><p class="ow-muted">' . $h($pageSummary) . '</p></div></header>';
PHP;
$source = preg_replace($layoutStartPattern, $replacement, $source, 1, $layoutStartReplacementCount);
if (!is_string($source) || $layoutStartReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_SIDEBAR_REMOVAL_INVALID');
}

$source = str_replace('$body .=', '$contentHtml .=', $source);
$source = str_replace("$body .= '</main></div>';", "$contentHtml .= '</main>';", $source, $closingReplacementCount);
if ($closingReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_MAIN_CLOSING_REPLACEMENT_INVALID');
}

$documentPattern = <<<'REGEX'
~echo '<!doctype html>'.*?\. '</body></html>';\s*$~s
REGEX;
$source = preg_replace(
    $documentPattern,
    "require $siteRoot . '/application/default/layouts/main.php';\n",
    $source,
    1,
    $documentReplacementCount
);
if (!is_string($source) || $documentReplacementCount !== 1) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_DOCUMENT_REPLACEMENT_INVALID');
}

if (str_contains($source, 'ow-sidebar') || str_contains($source, "echo '<!doctype html>'")) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_FORBIDDEN_MARKER_REMAINS');
}

$temporary = $bootstrap . '.layout-' . bin2hex(random_bytes(8)) . '.tmp';
if (file_put_contents($temporary, $source, LOCK_EX) === false) {
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_TEMP_WRITE_FAILED');
}

$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporary) . ' 2>&1', $output, $code);
if ($code !== 0) {
    @unlink($temporary);
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_PHP_LINT_FAILED:' . implode('|', $output));
}
if (!rename($temporary, $bootstrap)) {
    @unlink($temporary);
    $fail('OWASYS_SERVER_LAYOUT_MIGRATION_ATOMIC_REPLACE_FAILED');
}

fwrite(STDOUT, "OWASYS_SERVER_LAYOUT_MIGRATION_OK\n");
