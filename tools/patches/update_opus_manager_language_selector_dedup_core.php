<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE
 *
 * Supprime la répétition visuelle de la langue.
 * Le sélecteur suffit.
 */

$root = getcwd();

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$abstractFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'AbstractOpusManagerController.php';
$signInFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'SignInController.php';
$cssFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'opus-manager-ui.css';
$docFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP.md';
$scopeFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';

foreach ([$abstractFile, $signInFile, $cssFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_lang_dedup_read(string $file): string
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        fwrite(STDERR, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function opus_lang_dedup_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$abstract = opus_lang_dedup_read($abstractFile);
$abstract = str_replace(
    ". '<div class=\"om-env\"><span>Langue : ' . \$this->h(OpusManagerI18n::languageName(\$lang)) . '</span>' . \$profiler . \$auth . \$langForm . '</div></header>'",
    ". '<div class=\"om-env\">' . \$profiler . \$auth . \$langForm . '</div></header>'",
    $abstract
);
if (!str_contains($abstract, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE')) {
    $abstract = str_replace(
        'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE',
        'OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE',
        $abstract
    );
}
opus_lang_dedup_write($abstractFile, $abstract);

$signin = opus_lang_dedup_read($signInFile);
$signin = str_replace(
    ". '<div class=\"om-auth-badges\"><span>Langue : ' . \$this->h(OpusManagerI18n::languageName(\$lang)) . '</span>' . \$prodLock . '</div></section>'",
    ". '<div class=\"om-auth-badges\">' . \$prodLock . '</div></section>'",
    $signin
);
if (!str_contains($signin, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE')) {
    $signin = str_replace(
        'OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE',
        'OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE',
        $signin
    );
}
opus_lang_dedup_write($signInFile, $signin);

$css = opus_lang_dedup_read($cssFile);
if (!str_contains($css, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE */' . PHP_EOL;
    $css .= '.om-auth-badges:empty{display:none}.om-env .om-lang{margin-top:.15rem}' . PHP_EOL;
}
opus_lang_dedup_write($cssFile, $css);

$doc = <<<'MD'
# OPUS Manager — Language selector dedup

Contrat : `OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE`

## Décision UX

Quand le sélecteur de langue est visible, il suffit.

Le shell OPUS Manager ne doit pas afficher simultanément :

```text
Langue : Français
[select Français — FR]
```

## Correction

- Suppression du badge statique `Langue : ...` dans le shell.
- Suppression du badge statique `Langue : ...` sur Sign in.
- Conservation du sélecteur de langue.
- Conservation de la langue dans les URLs et le contexte.

## Règle

Le choix de langue doit être porté par le selecteur, pas répété en texte statique juste au-dessus.
MD;

opus_lang_dedup_write($docFile, $doc . PHP_EOL);

$scope = is_file($scopeFile) ? opus_lang_dedup_read($scopeFile) : '# OPUS P7 — portée de clôture finale' . PHP_EOL;
if (!str_contains($scope, 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- Le sélecteur de langue suffit.' . PHP_EOL;
    $scope .= '- Le shell OPUS Manager ne doit pas répéter `Langue : ...` quand le selecteur est visible.' . PHP_EOL;
    $scope .= '- Sign in conserve le selecteur, sans badge langue redondant.' . PHP_EOL;
}
opus_lang_dedup_write($scopeFile, $scope);

echo 'OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE_OK' . PHP_EOL;
