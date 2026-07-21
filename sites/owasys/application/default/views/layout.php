<?php
declare(strict_types=1);

/** @var callable $t */
/** @var array<string,mixed> $siteConfig */
/** @var array<string,mixed>|null $user */
/** @var array<string,mixed>|null $currentApp */
/** @var string $content */
/** @var string $activeRoute */
/** @var string $module */
/** @var string $titleKey */
/** @var string $summaryKey */
/** @var string $locale */
/** @var string $basePath */

$h = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$routeUrl = static function (string $targetLocale, string $route) use ($basePath): string {
    return $basePath . '/' . rawurlencode($targetLocale) . '/' . ltrim($route, '/');
};

$assetUrl = static fn (string $path): string => $basePath . '/asset/' . ltrim($path, '/');

$localeNames = [
    'bg' => 'Български',
    'hr' => 'Hrvatski',
    'cs' => 'Čeština',
    'da' => 'Dansk',
    'nl' => 'Nederlands',
    'en' => 'English',
    'et' => 'Eesti',
    'fi' => 'Suomi',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'el' => 'Ελληνικά',
    'hu' => 'Magyar',
    'ga' => 'Gaeilge',
    'it' => 'Italiano',
    'lv' => 'Latviešu',
    'lt' => 'Lietuvių',
    'mt' => 'Malti',
    'pl' => 'Polski',
    'pt' => 'Português',
    'ro' => 'Română',
    'sk' => 'Slovenčina',
    'sl' => 'Slovenščina',
    'es' => 'Español',
    'sv' => 'Svenska',
    'uk' => 'Українська',
];

$configuredLocales = array_values(
    array_filter((array) ($siteConfig['locales'] ?? []), 'is_string')
);

$menu = [
    ['route' => 'applications', 'key' => 'menu.applications'],
    ['route' => 'structure', 'key' => 'menu.structure'],
    ['route' => 'data', 'key' => 'menu.data'],
    ['route' => 'workflows', 'key' => 'menu.workflows'],
    ['route' => 'security', 'key' => 'menu.security'],
    ['route' => 'source', 'key' => 'menu.source'],
    ['route' => 'build', 'key' => 'menu.build'],
];

$isAuthenticated = is_array($user);
$pageTitle = $t($titleKey);
$pageSummary = $t($summaryKey);
?>
<!doctype html>
<html lang="<?= $h($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $h($pageTitle) ?> — <?= $h($t('brand.name')) ?></title>
    <link rel="stylesheet" href="<?= $h($assetUrl('css/owasys.css')) ?>">
    <link rel="stylesheet" href="<?= $h($assetUrl('themes/owasys/css/theme.css')) ?>">
</head>
<body data-opus-application="owasys" data-opus-module="<?= $h($module) ?>">
<div class="ow-shell ow-shell-horizontal-navigation">
    <header class="ow-global-header">
        <a class="ow-global-header-identity" href="<?= $h($routeUrl($locale, $isAuthenticated ? 'applications' : 'login')) ?>">
            <strong><?= $h($t('brand.name')) ?></strong>
            <span><?= $h($t('brand.subtitle')) ?></span>
        </a>

        <div class="ow-global-header-actions">
            <?php if ($isAuthenticated): ?>
                <div class="ow-global-current-app">
                    <small><?= $h($t('registry.current_application')) ?></small>
                    <strong>
                        <?= $h(
                            is_array($currentApp)
                                ? (string) ($currentApp['name'] ?? $currentApp['id'] ?? $t('common.unknown'))
                                : $t('registry.none_selected')
                        ) ?>
                    </strong>
                    <a href="<?= $h($routeUrl($locale, 'applications')) ?>">
                        <?= $h($t('registry.change_application')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <label class="ow-locale-switcher">
                <span class="ow-visually-hidden"><?= $h($t('language.selector')) ?></span>
                <select
                    aria-label="<?= $h($t('language.selector')) ?>"
                    onchange="window.location.href=this.value"
                >
                    <?php foreach ($configuredLocales as $code): ?>
                        <?php if (!isset($localeNames[$code])) { continue; } ?>
                        <option
                            value="<?= $h($routeUrl($code, $activeRoute)) ?>"
                            <?= $code === $locale ? 'selected' : '' ?>
                        >
                            <?= $h($localeNames[$code]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="ow-global-auth-status">
                <?php if ($isAuthenticated): ?>
                    <p>
                        <strong><?= $h((string) ($user['label'] ?? $user['id'] ?? $t('common.unknown'))) ?></strong>
                        <span>· <?= $h((string) ($user['profile'] ?? '')) ?></span>
                    </p>
                    <a href="<?= $h($routeUrl($locale, 'account/password')) ?>">
                        <?= $h($t('menu.account')) ?>
                    </a>
                    <a href="<?= $h($routeUrl($locale, 'logout')) ?>">
                        <?= $h($t('auth.logout')) ?>
                    </a>
                <?php else: ?>
                    <a href="<?= $h($routeUrl($locale, 'login')) ?>">
                        <?= $h($t('auth.login')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($isAuthenticated): ?>
        <nav class="ow-global-nav" aria-label="<?= $h($t('navigation.main')) ?>">
            <?php foreach ($menu as $item): ?>
                <?php $isActive = $activeRoute === $item['route']; ?>
                <a
                    class="ow-global-nav-link<?= $isActive ? ' is-active' : '' ?>"
                    href="<?= $h($routeUrl($locale, $item['route'])) ?>"
                    <?= $isActive ? 'aria-current="page"' : '' ?>
                >
                    <?= $h($t($item['key'])) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <main class="ow-main">
        <header class="ow-topbar">
            <div>
                <span class="ow-pill"><?= $h($t('brand.name')) ?></span>
                <h1><?= $h($pageTitle) ?></h1>
                <p class="ow-muted"><?= $h($pageSummary) ?></p>
            </div>
        </header>

        <?= $content ?>
    </main>
</div>
</body>
</html>
