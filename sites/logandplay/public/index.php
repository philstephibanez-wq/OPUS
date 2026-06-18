<?php

declare(strict_types=1);

use LogAndPlay\OpusRuntime;
use Opus\Http\PublicResponse;

$projectRoot = dirname(__DIR__);

require_once $projectRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'OpusRuntime.php';

function public_error_response(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo $message . "\n";
}

try {
    OpusRuntime::boot($projectRoot);

    $supportedLanguages = ['fr', 'en', 'es', 'de', 'uk', 'it', 'pl', 'cs'];
    $languageNames = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'uk' => 'Українська',
        'it' => 'Italiano',
        'pl' => 'Polski',
        'cs' => 'Čeština',
    ];

    $requestedLanguage = $_GET['lang'] ?? 'fr';
    if (!is_string($requestedLanguage) || $requestedLanguage === '') {
        throw new RuntimeException('LOGANDPLAY_LANGUAGE_INVALID');
    }

    $language = strtolower(trim($requestedLanguage));

    if (!in_array($language, $supportedLanguages, true)) {
        $response = new PublicResponse(404, "Langue non supportée.\n", [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    } else {
        $contentFile = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'home.' . $language . '.json';

        if (!is_file($contentFile)) {
            throw new RuntimeException('LOGANDPLAY_LANGUAGE_CONTENT_MISSING: ' . $language);
        }

        $content = json_decode((string) file_get_contents($contentFile), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($content)) {
            throw new RuntimeException('LOGANDPLAY_LANGUAGE_CONTENT_INVALID: ' . $language);
        }

        $body = render_page($content, $language, $supportedLanguages, $languageNames);

        $response = new PublicResponse(200, $body, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    http_response_code($response->statusCode());
    foreach ($response->headers() as $name => $value) {
        header($name . ': ' . $value);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $response->body();
    }
} catch (Throwable $exception) {
    error_log('LOGANDPLAY_OPUS_PUBLIC_SITE_FAILURE: ' . $exception->getMessage());
    public_error_response(503, 'Site temporairement en préparation.');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string,mixed> $content
 * @param list<string> $supportedLanguages
 * @param array<string,string> $languageNames
 */
function render_page(array $content, string $language, array $supportedLanguages, array $languageNames): string
{
    $site = array_value($content, 'site');
    $ui = array_value($content, 'ui');
    $projects = array_value($content, 'projects');
    $principles = array_value($content, 'principles');
    $footer = array_value($content, 'footer');

    ob_start();
    ?>
<!doctype html>
<html lang="<?= e($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(string_value($site, 'title')) ?></title>
    <meta name="description" content="<?= e(string_value($site, 'intro')) ?>">
    <link rel="stylesheet" href="/assets/css/logandplay.css">
</head>
<body>
<div class="app-shell">
    <header class="app-header">
        <a class="brand" href="/?lang=<?= e($language) ?>" aria-label="Log&Play">
            <span class="brand__mark">L&P</span>
            <span>
                <strong>Log&Play</strong>
                <small><?= e(string_value($ui, 'ecosystem')) ?> · OPUS powered</small>
            </span>
        </a>

        <nav class="top-nav" aria-label="<?= e(string_value($ui, 'nav')) ?>">
            <a href="#ecosystem"><?= e(string_value($ui, 'ecosystem')) ?></a>
            <a href="#principles"><?= e(string_value($ui, 'principles')) ?></a>
            <a href="#status"><?= e(string_value($ui, 'status')) ?></a>
        </nav>

        <form class="language-selector language-selector--header" method="get" action="/" aria-label="<?= e(string_value($ui, 'languages')) ?>">
            <label for="language-select-top"><?= e(string_value($ui, 'languages')) ?></label>
            <select id="language-select-top" name="lang" onchange="this.form.submit()">
                <?php foreach ($supportedLanguages as $code): ?>
                    <option value="<?= e($code) ?>" <?= $code === $language ? 'selected' : '' ?>>
                        <?= e(strtoupper($code)) ?> · <?= e($languageNames[$code]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">OK</button>
        </form>
    </header>

    <aside class="side-nav">
        <div class="side-nav__section">
            <p><?= e(string_value($ui, 'nav')) ?></p>
            <a href="#top"><?= e(string_value($ui, 'overview')) ?></a>
            <a href="#ecosystem"><?= e(string_value($ui, 'projects')) ?></a>
            <a href="#principles"><?= e(string_value($ui, 'principles')) ?></a>
        </div>

        <div class="side-nav__section side-nav__section--language">
            <p><?= e(string_value($ui, 'languages')) ?></p>
            <form class="language-selector language-selector--side" method="get" action="/" aria-label="<?= e(string_value($ui, 'languages')) ?>">
                <label for="language-select-side"><?= e($languageNames[$language]) ?></label>
                <select id="language-select-side" name="lang" onchange="this.form.submit()">
                    <?php foreach ($supportedLanguages as $code): ?>
                        <option value="<?= e($code) ?>" <?= $code === $language ? 'selected' : '' ?>>
                            <?= e(strtoupper($code)) ?> · <?= e($languageNames[$code]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">OK</button>
            </form>
        </div>

        <div class="side-card" id="status">
            <span class="status-dot"></span>
            <strong><?= e(string_value($site, 'status')) ?></strong>
            <small><?= e(string_value($ui, 'asideNotice')) ?></small>
        </div>
    </aside>

    <main class="main-panel" id="top">
        <section class="hero">
            <p class="eyebrow"><?= e(string_value($site, 'kicker')) ?></p>
            <h1><?= e(string_value($site, 'title')) ?></h1>
            <p class="hero__headline"><?= e(string_value($site, 'headline')) ?></p>
            <p class="hero__intro"><?= e(string_value($site, 'intro')) ?></p>
        </section>

        <section class="projects" id="ecosystem" aria-label="<?= e(string_value($ui, 'projects')) ?>">
            <div class="section-heading">
                <p><?= e(string_value($ui, 'ecosystem')) ?></p>
                <h2><?= e(string_value($ui, 'projectsTitle')) ?></h2>
            </div>

            <div class="project-grid">
                <?php foreach ($projects as $project): ?>
                    <?php if (!is_array($project)) { throw new RuntimeException('LOGANDPLAY_PROJECT_INVALID'); } ?>
                    <article class="project-card" id="<?= e(strtolower(string_value($project, 'code'))) ?>-prochainement">
                        <div class="project-card__top">
                            <span class="project-card__code"><?= e(string_value($project, 'code')) ?></span>
                            <span class="project-card__status"><?= e(string_value($ui, 'coming')) ?></span>
                        </div>

                        <h3><?= e(string_value($project, 'title')) ?></h3>
                        <p class="project-card__subtitle"><?= e(string_value($project, 'subtitle')) ?></p>
                        <p class="project-card__description"><?= e(string_value($project, 'description')) ?></p>

                        <ul class="project-card__points">
                            <?php foreach (array_value($project, 'points') as $point): ?>
                                <?php if (!is_string($point)) { throw new RuntimeException('LOGANDPLAY_PROJECT_POINT_INVALID'); } ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="principles" id="principles">
            <div class="section-heading">
                <p><?= e(string_value($ui, 'principles')) ?></p>
                <h2><?= e(string_value($ui, 'principlesTitle')) ?></h2>
            </div>

            <div class="principles__list">
                <?php foreach ($principles as $principle): ?>
                    <?php if (!is_string($principle)) { throw new RuntimeException('LOGANDPLAY_PRINCIPLE_INVALID'); } ?>
                    <p><?= e($principle) ?></p>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="app-footer">
        <strong><?= e(string_value($footer, 'baseline')) ?></strong>
        <span><?= e(string_value($ui, 'footerNotice')) ?></span>
    </footer>
</div>
</body>
</html>
    <?php
    $body = ob_get_clean();

    if (!is_string($body)) {
        throw new RuntimeException('LOGANDPLAY_RENDER_FAILED');
    }

    return $body;
}

/** @param array<string,mixed> $data */
function string_value(array $data, string $key): string
{
    $value = $data[$key] ?? null;
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('LOGANDPLAY_STRING_VALUE_INVALID: ' . $key);
    }

    return $value;
}

/** @param array<string,mixed> $data */
function array_value(array $data, string $key): array
{
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
        throw new RuntimeException('LOGANDPLAY_ARRAY_VALUE_INVALID: ' . $key);
    }

    return $value;
}
