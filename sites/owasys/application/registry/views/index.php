<?php
declare(strict_types=1);

/** @var callable $t */
/** @var list<array<string,mixed>> $entries */
/** @var array<string,mixed>|null $currentApp */
/** @var list<array<string,mixed>> $recentEvents */
/** @var array<string,mixed> $sync */
/** @var string|null $error */

$h = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<?php if (is_string($error) && $error !== ''): ?>
    <p class="ow-error"><?= $h($t($error)) ?></p>
<?php endif; ?>

<section class="ow-card ow-context-panel">
    <div class="ow-section-heading">
        <div>
            <h2><?= $h($t('registry.application_context')) ?></h2>
            <p class="ow-muted"><?= $h($t('registry.select_instruction')) ?></p>
        </div>

        <form method="post">
            <input type="hidden" name="owasys_action" value="create-new-app">
            <button class="ow-button" type="submit">
                <?= $h($t('registry.create_application')) ?>
            </button>
        </form>
    </div>

    <?php if (is_array($currentApp)): ?>
        <div class="ow-tags">
            <span><?= $h($t('common.id')) ?>: <?= $h((string) ($currentApp['id'] ?? '')) ?></span>
            <span><?= $h($t('common.kind')) ?>: <?= $h((string) ($currentApp['kind'] ?? '')) ?></span>
            <span><?= $h($t('common.root')) ?>: <?= $h((string) ($currentApp['root_path'] ?? '')) ?></span>
        </div>

        <form method="post" class="ow-inline-form">
            <input type="hidden" name="owasys_action" value="clear-app-context">
            <button class="ow-button ow-button-secondary" type="submit">
                <?= $h($t('registry.clear_current_context')) ?>
            </button>
        </form>
    <?php else: ?>
        <p><?= $h($t('registry.no_application_selected')) ?></p>
    <?php endif; ?>
</section>

<section class="ow-grid ow-registry-grid">
    <?php if ($entries === []): ?>
        <article class="ow-card">
            <h2><?= $h($t('registry.empty_title')) ?></h2>
            <p class="ow-muted"><?= $h($t('registry.empty_description')) ?></p>
        </article>
    <?php endif; ?>

    <?php foreach ($entries as $entry): ?>
        <?php
        $entryId = (string) ($entry['id'] ?? '');
        $isCurrent = is_array($currentApp)
            && (string) ($currentApp['id'] ?? '') === $entryId;
        ?>
        <article class="ow-card ow-registry-card<?= $isCurrent ? ' is-current' : '' ?>">
            <span class="ow-badge"><?= $h((string) ($entry['status'] ?? '')) ?></span>
            <h2><?= $h((string) ($entry['name'] ?? $entryId)) ?></h2>
            <p class="ow-muted"><?= $h((string) ($entry['root_path'] ?? '')) ?></p>

            <div class="ow-tags">
                <span><?= $h((string) ($entry['kind'] ?? '')) ?></span>
                <span><?= $h((string) ($entry['role'] ?? '')) ?></span>
                <span><?= $h((string) ($entry['default_locale'] ?? '')) ?></span>
                <span><?= $h((string) ($entry['theme'] ?? '')) ?></span>
            </div>

            <form method="post" class="ow-inline-form">
                <input type="hidden" name="owasys_action" value="select-app">
                <input type="hidden" name="owasys_app_id" value="<?= $h($entryId) ?>">
                <button class="ow-button" type="submit" <?= $isCurrent ? 'disabled' : '' ?>>
                    <?= $h(
                        $isCurrent
                            ? $t('registry.current_application')
                            : $t('registry.work_on_this_app')
                    ) ?>
                </button>
            </form>
        </article>
    <?php endforeach; ?>
</section>

<section class="ow-grid ow-runtime-grid">
    <article class="ow-card">
        <h2><?= $h($t('registry.runtime_sqlite')) ?></h2>
        <div class="ow-tags">
            <span><?= $h($t('registry.database')) ?>: <?= $h((string) ($sync['database'] ?? '')) ?></span>
            <span><?= $h($t('registry.sync_total')) ?>: <?= $h((string) ($sync['total'] ?? 0)) ?></span>
            <span><?= $h($t('registry.seed_imported')) ?>: <?= $h((string) ($sync['seed_imported'] ?? 0)) ?></span>
            <span><?= $h($t('registry.discovered_imported')) ?>: <?= $h((string) ($sync['discovered_imported'] ?? 0)) ?></span>
        </div>
    </article>

    <article class="ow-card">
        <h2><?= $h($t('registry.events.title')) ?></h2>

        <?php if ($recentEvents === []): ?>
            <p class="ow-muted"><?= $h($t('registry.events.empty')) ?></p>
        <?php else: ?>
            <ol class="ow-event-list">
                <?php foreach ($recentEvents as $event): ?>
                    <li>
                        <strong><?= $h((string) ($event['event_type'] ?? '')) ?></strong>
                        <span><?= $h((string) ($event['application_id'] ?? '')) ?></span>
                        <time><?= $h((string) ($event['created_at'] ?? '')) ?></time>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
</section>
