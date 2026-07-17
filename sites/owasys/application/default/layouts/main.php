<?php
declare(strict_types=1);

if (!isset($h, $t, $link, $asset) || !is_callable($h) || !is_callable($t) || !is_callable($link) || !is_callable($asset)) {
    throw new RuntimeException('OWASYS_LAYOUT_CONTEXT_INVALID');
}
?>
<!doctype html>
<html lang="<?= $h($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $h($pageTitle) ?> — <?= $h($t('brand.name')) ?></title>
    <link rel="stylesheet" href="<?= $h($asset('/asset/css/owasys.css')) ?>">
    <link rel="stylesheet" href="<?= $h($asset('/asset/themes/owasys/css/theme.css')) ?>">
</head>
<body data-opus-dispatch="state-first" data-opus-state="<?= $h($state) ?>">
<header class="ow-global-header" data-context="OWASYS_GLOBAL_HEADER">
    <a class="ow-global-header-identity" href="<?= $h($link('/')) ?>">
        <strong><?= $h($t('brand.name')) ?></strong>
        <span><?= $h($t('brand.subtitle')) ?></span>
    </a>
    <div class="ow-global-header-actions" data-context="OWASYS_GLOBAL_HEADER_ACTIONS">
        <?php if ($isAuthenticated): ?>
            <section class="ow-global-current-app" data-context="OWASYS_GLOBAL_CURRENT_APPLICATION">
                <small><?= $h($t('registry.current_application')) ?></small>
                <?php if (is_array($currentApp)): ?>
                    <strong><?= $h((string) ($currentApp['name'] ?? $currentApp['id'] ?? $t('common.unknown'))) ?></strong>
                    <span><?= $h((string) ($currentApp['kind'] ?? $t('common.unknown'))) ?> · <?= $h((string) ($currentApp['root_path'] ?? $t('common.unknown'))) ?></span>
                <?php else: ?>
                    <strong><?= $h($t('registry.none_selected')) ?></strong>
                    <span><?= $h($t('registry.choose_in_registry')) ?></span>
                <?php endif; ?>
                <a href="<?= $h($link('/applications')) ?>"><?= $h($t('registry.change_application')) ?></a>
            </section>
        <?php endif; ?>

        <section class="ow-global-auth-status">
            <?php if ($isAuthenticated): ?>
                <span class="ow-auth-dot" aria-hidden="true"></span>
                <strong><?= $h((string) ($user['label'] ?? $t('common.unknown'))) ?></strong>
                <small><?= $h($t('common.profile')) ?>: <?= $h((string) ($user['profile'] ?? $t('common.unknown'))) ?></small>
                <?php if ($mustChangePassword): ?>
                    <small class="ow-auth-warning"><?= $h($t('auth.password_change_required')) ?></small>
                <?php endif; ?>
                <a href="<?= $h($link('/account/password')) ?>"><?= $h($t('auth.password')) ?></a>
                <a href="<?= $h($link('/logout')) ?>"><?= $h($t('auth.logout')) ?></a>
            <?php else: ?>
                <span class="ow-auth-dot is-off" aria-hidden="true"></span>
                <strong><?= $h($t('auth.not_signed_in')) ?></strong>
                <small><?= $h($t('auth.session_inactive')) ?></small>
                <a href="<?= $h($link('/login')) ?>"><?= $h($t('auth.login')) ?></a>
            <?php endif; ?>
        </section>

        <form method="get" class="ow-locale-switcher" data-context="OWASYS_LOCALE_SWITCHER">
            <?php foreach ($_GET as $queryKey => $queryValue): ?>
                <?php if ($queryKey !== 'lang' && is_scalar($queryValue)): ?>
                    <input type="hidden" name="<?= $h((string) $queryKey) ?>" value="<?= $h((string) $queryValue) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <label>
                <span class="ow-visually-hidden"><?= $h($t('common.language')) ?></span>
                <select name="lang" aria-label="<?= $h($t('common.language')) ?>" onchange="this.form.submit()">
                    <?php foreach ($localeLabels as $localeCode => $localeLabel): ?>
                        <option value="<?= $h((string) $localeCode) ?>" lang="<?= $h((string) $localeCode) ?>"<?= $localeCode === $locale ? ' selected' : '' ?>><?= $h((string) $localeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit"><?= $h($t('common.apply')) ?></button></noscript>
            </label>
        </form>
    </div>
</header>

<?php if ($isAuthenticated): ?>
<nav class="ow-global-nav" data-context="OWASYS_GLOBAL_NAVIGATION" aria-label="<?= $h($t('brand.name')) ?>">
    <?php foreach ($menu as $item): ?>
        <?php
        $itemPath = (string) ($item['path'] ?? '#');
        $labelKey = (string) ($item['label_key'] ?? '');
        ?>
        <a class="ow-global-nav-link"<?= $itemPath === $path ? ' aria-current="page"' : '' ?> href="<?= $h($link($itemPath)) ?>"><?= $h($t($labelKey)) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?= $contentHtml ?>

<script src="<?= $h($asset('/asset/js/owasys.js')) ?>"></script>
<script src="<?= $h($asset('/asset/themes/owasys/js/theme.js')) ?>"></script>
</body>
</html>
