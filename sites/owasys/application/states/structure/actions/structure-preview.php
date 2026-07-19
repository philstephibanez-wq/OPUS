<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftPreviewConfirmation;
use Opus\Owasys\StructureDraftRepository;
use Opus\Owasys\StructureDraftWritePlanner;
use Opus\Template\ScoreTemplateRenderer;
use Owasys\Application\Configuration\SiteConfiguration;
use Owasys\Application\I18n\Translator;
use Owasys\Application\Session\SessionContext;

$siteRoot = dirname(__DIR__, 4);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'OWASYS_COMPOSER_AUTOLOAD_MISSING';
    exit;
}
require_once $autoload;

try {
    $configuration = SiteConfiguration::load($siteRoot);
    $authConfig = $configuration->auth();
    $session = new SessionContext((string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION'));
    $session->start();

    $requestedLocale = strtolower((string) ($_GET['lang'] ?? $session->locale($configuration->defaultLocale())));
    $translator = Translator::load(
        $siteRoot,
        $configuration->locales(),
        $configuration->defaultLocale(),
        $requestedLocale
    );
    $session->setLocale($translator->locale());
    $t = static fn(string $key): string => $translator->translate($key);
    $renderer = new ScoreTemplateRenderer($siteRoot . '/application/states/structure/templates');
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'OWASYS_STRUCTURE_PREVIEW_BOOTSTRAP_FAILED';
    exit;
}

$renderResult = static function (array $result, int $status = 200) use ($renderer): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $renderer->render('preview-result.score', ['preview' => $result]);
    exit;
};

$user = $session->user();
if ($user === null) {
    $renderResult([
        'success' => false,
        'title' => $t('draft.preview_result'),
        'message' => 'OWASYS_STRUCTURE_PREVIEW_AUTH_REQUIRED',
        'status' => 'unauthorized',
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => false,
        'files' => [],
        'has_files' => false,
        'collisions' => '',
        'has_collisions' => false,
    ], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $renderResult([
        'success' => false,
        'title' => $t('draft.preview_result'),
        'message' => 'OWASYS_STRUCTURE_PREVIEW_METHOD_INVALID',
        'status' => 'method_not_allowed',
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => false,
        'files' => [],
        'has_files' => false,
        'collisions' => '',
        'has_collisions' => false,
    ], 405);
}

$draftId = filter_var($_POST['owasys_draft_id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($draftId) || $draftId < 1) {
    $renderResult([
        'success' => false,
        'title' => $t('draft.preview_result'),
        'message' => 'OWASYS_STRUCTURE_PREVIEW_DRAFT_ID_INVALID',
        'status' => 'invalid_request',
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => false,
        'files' => [],
        'has_files' => false,
        'collisions' => '',
        'has_collisions' => false,
    ], 400);
}

$siteConfig = $configuration->site();
$registryConfig = is_array($siteConfig['registry'] ?? null) ? $siteConfig['registry'] : [];
$registrySeedRelative = trim(str_replace('\\', '/', (string) ($registryConfig['seed'] ?? 'config/registry.seed.json')), '/');
$registryDatabaseRelative = trim(str_replace('\\', '/', (string) ($registryConfig['runtime_database'] ?? 'var/registry/owasys.sqlite')), '/');
if ($registrySeedRelative === '' || str_contains($registrySeedRelative, '..') || $registryDatabaseRelative === '' || str_contains($registryDatabaseRelative, '..')) {
    $renderResult([
        'success' => false,
        'title' => $t('draft.preview_result'),
        'message' => 'OWASYS_STRUCTURE_PREVIEW_REGISTRY_PATH_INVALID',
        'status' => 'configuration_error',
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => false,
        'files' => [],
        'has_files' => false,
        'collisions' => '',
        'has_collisions' => false,
    ], 500);
}

try {
    $registry = RegistryRepository::forOwasysSite($siteRoot, $opusRoot, $registryDatabaseRelative);
    $registry->synchronize($siteRoot . '/' . $registrySeedRelative);
    $currentApp = $session->currentApplication() ?? $registry->currentApplication();
    if (!is_array($currentApp)) {
        throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_CURRENT_APPLICATION_MISSING');
    }

    $draftRepository = StructureDraftRepository::forRegistry($registry);
    $draft = null;
    foreach ($draftRepository->recentDrafts((string) ($currentApp['id'] ?? ''), 50) as $candidate) {
        if (is_array($candidate) && (int) ($candidate['id'] ?? 0) === $draftId) {
            $draft = $candidate;
            break;
        }
    }
    if (!is_array($draft)) {
        throw new RuntimeException('OWASYS_STRUCTURE_PREVIEW_DRAFT_MISSING');
    }

    $plan = StructureDraftWritePlanner::forOpusRoot($opusRoot)->planAddStateDraft($currentApp, $draft);
    $confirmation = null;
    if (($plan['status'] ?? null) === 'ready') {
        $confirmation = StructureDraftPreviewConfirmation::persist(
            $registry,
            $plan,
            (string) ($user['id'] ?? 'runtime')
        );
    }

    $files = [];
    foreach ((array) ($plan['files'] ?? []) as $file) {
        if (!is_array($file)) {
            continue;
        }
        $files[] = [
            'operation' => (string) ($file['operation'] ?? ''),
            'path' => (string) ($file['path'] ?? ''),
        ];
    }
    $collisions = array_map('strval', (array) ($plan['collisions'] ?? []));

    $renderResult([
        'success' => true,
        'title' => $t('draft.preview_result'),
        'message' => $t('draft.preview_status'),
        'status' => (string) ($plan['status'] ?? 'blocked'),
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => is_array($confirmation),
        'confirmed_label' => $t('draft.preview_confirmed'),
        'files' => $files,
        'has_files' => $files !== [],
        'collisions' => implode(', ', $collisions),
        'has_collisions' => $collisions !== [],
        'collisions_label' => $t('draft.preview_collisions'),
    ]);
} catch (Throwable $exception) {
    $renderResult([
        'success' => false,
        'title' => $t('draft.preview_result'),
        'message' => $t('draft.preview_error'),
        'status' => 'blocked',
        'disk_mutation' => $t('draft.disk_mutation_false'),
        'confirmed' => false,
        'files' => [],
        'has_files' => false,
        'collisions' => '',
        'has_collisions' => false,
    ], 409);
}
