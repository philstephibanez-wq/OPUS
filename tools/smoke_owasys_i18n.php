<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$localRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local';
$frFile = $localRoot . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $localRoot . DIRECTORY_SEPARATOR . 'en.php';

foreach ([$siteFile, $frontFile, $frFile, $enFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_I18N_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
}

foreach ([$frFile, $enFile, $frontFile] as $file) {
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_I18N_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

$site = json_decode((string) file_get_contents($siteFile), true);
if (!is_array($site) || ($site['default_locale'] ?? null) !== 'fr' || !in_array('en', (array) ($site['locales'] ?? []), true)) {
    fwrite(STDERR, "OWASYS_I18N_SITE_LOCALES_INVALID\n");
    exit(1);
}

$requiredKeys = [
    'brand.name',
    'brand.subtitle',
    'menu.home',
    'menu.applications',
    'menu.structure',
    'menu.data',
    'menu.workflows',
    'menu.security',
    'menu.build',
    'auth.sign_in',
    'auth.sign_in_description',
    'auth.username',
    'auth.password_field',
    'auth.current_password',
    'auth.new_password',
    'auth.confirm_new_password',
    'auth.change_password',
    'auth.password_change_required',
    'auth.error.required_credentials',
    'auth.error.invalid_credentials',
    'auth.error.new_password_too_short',
    'registry.current_application',
    'registry.you_are_working_on',
    'registry.application_context',
    'registry.application_tree',
    'registry.create_new_application',
    'registry.work_on_this_app',
    'registry.runtime_sqlite',
    'registry.events.title',
    'registry.events.select_app',
    'registry.events.logout',
    'mermaid.title',
    'common.contracts',
    'common.next_actions',
    'state.default.summary',
];

foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, "OWASYS_I18N_MESSAGES_INVALID: {$locale}\n");
        exit(1);
    }
    foreach ($requiredKeys as $key) {
        if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
            fwrite(STDERR, "OWASYS_I18N_KEY_MISSING: {$locale}:{$key}\n");
            exit(1);
        }
    }
}

$front = (string) file_get_contents($frontFile);
foreach ([
    '$messages =',
    '$t = static function',
    "application/default/local",
    "registry.current_application",
    "registry.you_are_working_on",
    "registry.work_on_this_app",
    "registry.events.title",
    "auth.sign_in",
    "auth.change_password",
    "common.contracts",
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_I18N_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$forbiddenFrontLiterals = [
    '>Sign in<',
    '>Change password<',
    'Username<input',
    'Password<input',
    'Current password<input',
    'New password<input',
    'Confirm new password<input',
    'Runtime user store missing. Run',
    'YOU ARE WORKING ON</small>',
    'Current application</small>',
    'Create new application</button>',
    'Work on this app</button>',
    'Recent runtime events</h2>',
    'Configuration through standard OPUS application folders',
];
foreach ($forbiddenFrontLiterals as $literal) {
    if (str_contains($front, $literal)) {
        fwrite(STDERR, "OWASYS_I18N_HARDCODED_UI_LITERAL_PRESENT: {$literal}\n");
        exit(1);
    }
}

echo "OWASYS_I18N_SMOKE_OK\n";
