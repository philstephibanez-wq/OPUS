<?php
declare(strict_types=1);

/**
 * PUBLIC SMOKE SCRIPT
 *
 * Role:
 *   Validate the P113B7 RefBook theme selector patch without requiring UwAmp,
 *   Apache or Opus runtime bootstrap.
 *
 * Reads:
 *   - RefBook Twig layout and page templates.
 *   - RefBook CSS.
 *   - RefBook I18N JSON files.
 *   - ReferenceThemeService source.
 *
 * Writes:
 *   Nothing.
 *
 * Deletes:
 *   Nothing.
 *
 * Errors:
 *   Exits non-zero with an explicit P113B7_* message when a contract fragment is
 *   missing.
 *
 * Rollback:
 *   Restore previous full-files ZIP if any assertion fails after extraction.
 *
 * Version:
 *   P113B7_REFBOOK_THEME_SELECTOR
 */

$root = dirname(__DIR__, 2);

function p113b7_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, 'P113B7_FILE_MISSING=' . $path . PHP_EOL);
        exit(1);
    }

    return (string) file_get_contents($path);
}

function p113b7_assert_contains(string $haystack, string $needle, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, 'P113B7_MISSING_' . $label . '=' . $needle . PHP_EOL);
        exit(1);
    }
}

require_once $root . '/application/reference/Service/ReferenceThemeService.php';

$themeServiceClass = 'ASAPRefBook\\Reference\\Service\\ReferenceThemeService';
$themes = $themeServiceClass::SUPPORTED_THEMES;
if ($themeServiceClass::DEFAULT_THEME !== 'night') {
    fwrite(STDERR, 'P113B7_DEFAULT_THEME_INVALID' . PHP_EOL);
    exit(1);
}

if ($themes !== ['night', 'ocean', 'paper']) {
    fwrite(STDERR, 'P113B7_SUPPORTED_THEMES_INVALID=' . json_encode($themes) . PHP_EOL);
    exit(1);
}

foreach ($themes as $theme) {
    $service = new $themeServiceClass($theme);
    if ($service->theme() !== $theme || $service->bodyClass() !== 'theme-' . $theme) {
        fwrite(STDERR, 'P113B7_THEME_SERVICE_INVALID=' . $theme . PHP_EOL);
        exit(1);
    }
}

try {
    new $themeServiceClass('unknown');
    fwrite(STDERR, 'P113B7_THEME_UNSUPPORTED_NOT_THROWN' . PHP_EOL);
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'OPUS_REFBOOK_THEME_UNSUPPORTED=unknown')) {
        fwrite(STDERR, 'P113B7_THEME_UNSUPPORTED_ERROR_INVALID=' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

$controller = p113b7_read($root . '/application/reference/Controller/AbstractRefBookController.php');
p113b7_assert_contains($controller, 'ReferenceThemeService::DEFAULT_THEME', 'CONTROLLER_DEFAULT_THEME');
p113b7_assert_contains($controller, "\$data['theme']", 'CONTROLLER_THEME_VIEWMODEL');
p113b7_assert_contains($controller, "\$data['themes']", 'CONTROLLER_THEMES_VIEWMODEL');
p113b7_assert_contains($controller, "\$data['themeClass']", 'CONTROLLER_THEME_CLASS_VIEWMODEL');

$layout = p113b7_read($root . '/application/reference/templates/layout.twig');
if (!str_contains($layout, 'refbook.css?v=P113B7') && !str_contains($layout, 'refbook.css?v=P113B8')) {
    p113b7_fail('MISSING_CSS_CACHE_BUSTER=refbook.css?v=P113B7_OR_LATER');
}
p113b7_assert_contains($layout, '<body class="{{ themeClass }}">', 'BODY_THEME_CLASS');
p113b7_assert_contains($layout, 'theme-switcher segmented-switcher', 'THEME_SWITCHER');
p113b7_assert_contains($layout, 'language-switcher segmented-switcher', 'LANGUAGE_SWITCHER');
p113b7_assert_contains($layout, 'lang={{ code }}&theme={{ theme }}', 'LANGUAGE_PRESERVES_THEME');
p113b7_assert_contains($layout, 'lang={{ lang }}&theme={{ code }}', 'THEME_PRESERVES_LANGUAGE');
p113b7_assert_contains($layout, '&page={{ pageSlug }}', 'SELECTORS_PRESERVE_PAGE');

$css = p113b7_read($root . '/public/assets/css/refbook.css');
foreach (['theme-night', 'theme-ocean', 'theme-paper', '.theme-switcher', '.segmented-switcher'] as $needle) {
    p113b7_assert_contains($css, $needle, 'CSS_' . strtoupper(str_replace(['.', '-'], ['', '_'], $needle)));
}

foreach (['fr', 'en', 'es'] as $language) {
    $jsonPath = $root . '/content/refbook/i18n/' . $language . '.json';
    $data = json_decode(p113b7_read($jsonPath), true);
    if (!is_array($data) || !isset($data['labels']['theme']['short'])) {
        fwrite(STDERR, 'P113B7_I18N_THEME_LABELS_MISSING=' . $language . PHP_EOL);
        exit(1);
    }

    foreach ($themes as $theme) {
        if (!isset($data['labels']['theme'][$theme], $data['labels']['theme']['short'][$theme])) {
            fwrite(STDERR, 'P113B7_I18N_THEME_VALUE_MISSING=' . $language . ':' . $theme . PHP_EOL);
            exit(1);
        }
    }
}

foreach (glob($root . '/application/reference/templates/pages/*.twig') ?: [] as $template) {
    $content = p113b7_read($template);
    if (str_contains($content, '{{ basePath }}/?lang={{ lang }}&page=')) {
        fwrite(STDERR, 'P113B7_TEMPLATE_LINK_WITHOUT_THEME=' . $template . PHP_EOL);
        exit(1);
    }
}

echo 'P113B7_THEME_SELECTOR_SMOKE_OK' . PHP_EOL;
