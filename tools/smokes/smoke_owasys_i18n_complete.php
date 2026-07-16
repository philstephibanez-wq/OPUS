<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$site = $root . '/sites/owasys';
$siteConfigFile = $site . '/config/site.json';
$localeRoot = $site . '/application/default/local';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (!is_file($siteConfigFile)) {
    $fail('OWASYS_I18N_SITE_CONFIG_MISSING');
}

$config = json_decode((string) file_get_contents($siteConfigFile), true);
if (!is_array($config)) {
    $fail('OWASYS_I18N_SITE_CONFIG_INVALID');
}

$expectedLocales = [
    'bg', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'hu', 'ga',
    'it', 'lv', 'lt', 'mt', 'pl', 'pt', 'ro', 'sk', 'sl', 'es', 'sv', 'uk',
];
$configuredLocales = array_values(array_filter((array) ($config['locales'] ?? []), 'is_string'));
if ($configuredLocales !== $expectedLocales) {
    $fail('OWASYS_I18N_LOCALE_REGISTRY_INVALID');
}

$canonicalFile = $localeRoot . '/en.php';
if (!is_file($canonicalFile)) {
    $fail('OWASYS_I18N_CANONICAL_LOCALE_MISSING');
}
$canonical = require $canonicalFile;
if (!is_array($canonical) || $canonical === []) {
    $fail('OWASYS_I18N_CANONICAL_LOCALE_INVALID');
}
$canonicalKeys = array_keys($canonical);
sort($canonicalKeys);

foreach ($expectedLocales as $locale) {
    $file = $localeRoot . '/' . $locale . '.php';
    if (!is_file($file)) {
        $fail('OWASYS_I18N_LOCALE_MISSING:' . $locale);
    }
    $messages = require $file;
    if (!is_array($messages)) {
        $fail('OWASYS_I18N_LOCALE_INVALID:' . $locale);
    }
    $keys = array_keys($messages);
    sort($keys);
    if ($keys !== $canonicalKeys) {
        $missing = array_values(array_diff($canonicalKeys, $keys));
        $extra = array_values(array_diff($keys, $canonicalKeys));
        $fail('OWASYS_I18N_KEYSET_MISMATCH:' . $locale . ':missing=' . implode(',', $missing) . ':extra=' . implode(',', $extra));
    }

    $identicalToEnglish = 0;
    foreach ($canonical as $key => $englishValue) {
        $value = $messages[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $fail('OWASYS_I18N_EMPTY_VALUE:' . $locale . ':' . $key);
        }
        if ($value === $key) {
            $fail('OWASYS_I18N_KEY_ECHO:' . $locale . ':' . $key);
        }
        if ($locale !== 'en' && $value === $englishValue) {
            $identicalToEnglish++;
        }
        if (strlen($value) > 320) {
            $fail('OWASYS_I18N_VALUE_TOO_LONG:' . $locale . ':' . $key);
        }
    }
    if ($locale !== 'en' && $identicalToEnglish > 24) {
        $fail('OWASYS_I18N_ENGLISH_FALLBACK_SUSPECTED:' . $locale . ':identical=' . $identicalToEnglish);
    }
}

$jsFiles = [
    $site . '/www/asset/js/owasys.js',
    $site . '/www/asset/themes/owasys/js/theme.js',
];
$forbiddenLiterals = [
    'Afficher le mot de passe',
    'Masquer le mot de passe',
    'Prévisualiser le plan serveur',
    'Preview server plan',
    'Langues UE + ukrainien',
    "currentLocale === 'en'",
];
foreach ($jsFiles as $jsFile) {
    if (!is_file($jsFile)) {
        $fail('OWASYS_I18N_JS_MISSING:' . basename($jsFile));
    }
    $source = (string) file_get_contents($jsFile);
    foreach ($forbiddenLiterals as $literal) {
        if (str_contains($source, $literal)) {
            $fail('OWASYS_I18N_HARDCODED_LITERAL:' . basename($jsFile) . ':' . $literal);
        }
    }
}

echo 'OWASYS_I18N_COMPLETE_SMOKE_OK' . PHP_EOL;
