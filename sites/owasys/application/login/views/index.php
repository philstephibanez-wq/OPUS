<?php
declare(strict_types=1);

/** @var callable $t */
/** @var string|null $error */
?>
<section class="ow-panel ow-auth-panel">
    <header>
        <span class="ow-badge"><?= htmlspecialchars($t('auth.password'), ENT_QUOTES, 'UTF-8') ?></span>
        <h1><?= htmlspecialchars($t('auth.sign_in'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($t('auth.sign_in_description'), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <?php if (is_string($error) && $error !== ''): ?>
        <p class="ow-error"><?= htmlspecialchars($t($error), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="owasys_action" value="password-signin">

        <label>
            <?= htmlspecialchars($t('auth.username'), ENT_QUOTES, 'UTF-8') ?>
            <input name="owasys_username" autocomplete="username" required>
        </label>

        <label>
            <?= htmlspecialchars($t('auth.password_field'), ENT_QUOTES, 'UTF-8') ?>
            <input type="password" name="owasys_password" autocomplete="current-password" required>
        </label>

        <button type="submit"><?= htmlspecialchars($t('auth.login'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
</section>
