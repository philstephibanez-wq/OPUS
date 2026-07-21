<?php
declare(strict_types=1);

/** @var callable $t */
/** @var string|null $error */
?>
<section class="ow-panel ow-auth-panel">
    <header>
        <span class="ow-badge"><?= htmlspecialchars($t('menu.account'), ENT_QUOTES, 'UTF-8') ?></span>
        <h1><?= htmlspecialchars($t('auth.change_password'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($t('auth.change_password_description'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <?php if (is_string($error) && $error !== ''): ?>
        <p class="ow-error"><?= htmlspecialchars($t($error), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="owasys_action" value="change-password">

        <label>
            <?= htmlspecialchars($t('auth.current_password'), ENT_QUOTES, 'UTF-8') ?>
            <input type="password" name="owasys_current_password" autocomplete="current-password" required>
        </label>

        <label>
            <?= htmlspecialchars($t('auth.new_password'), ENT_QUOTES, 'UTF-8') ?>
            <input type="password" name="owasys_new_password" autocomplete="new-password" minlength="10" required>
        </label>

        <label>
            <?= htmlspecialchars($t('auth.confirm_new_password'), ENT_QUOTES, 'UTF-8') ?>
            <input type="password" name="owasys_confirm_password" autocomplete="new-password" minlength="10" required>
        </label>

        <button type="submit"><?= htmlspecialchars($t('auth.change_password'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</section>
