<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Fsm\FsmSiteLoader;

$root = dirname(__DIR__);

$demoResolved = FsmSiteLoader::resolve($root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app');
if (($demoResolved['site_id'] ?? null) !== 'demo-app' || ($demoResolved['fsm_relative_path'] ?? null) !== 'config/application.fsm.json') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_DEMO_RESOLUTION_INVALID\n");
    exit(1);
}

$demoProcessor = FsmSiteLoader::processorForSite($root, 'demo-app');
$demoTransition = $demoProcessor->transition('home', 'open_articles');
if (($demoTransition['to_state'] ?? null) !== 'articles' || ($demoTransition['action'] ?? null) !== 'render_route') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_DEMO_TRANSITION_INVALID\n");
    exit(1);
}

$owasysResolved = FsmSiteLoader::resolve($root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys');
if (($owasysResolved['site_id'] ?? null) !== 'owasys' || ($owasysResolved['fsm_relative_path'] ?? null) !== 'config/owasys-navigation.fsm.json') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_OWASYS_RESOLUTION_INVALID\n");
    exit(1);
}

$owasysProcessor = FsmSiteLoader::processorForSite($root, 'owasys');
$changeApp = $owasysProcessor->transition('security', 'change_app');
if (($changeApp['to_state'] ?? null) !== 'registry') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_OWASYS_WILDCARD_INVALID\n");
    exit(1);
}

try {
    FsmSiteLoader::processorForSite($root, '../bad');
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_BAD_SITE_ID_NOT_REJECTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'OPUS_FSM_SITE_ID_INVALID: ../bad') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

$badSiteRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'fsm-site-loader-bad';
if (is_dir($badSiteRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($badSiteRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($badSiteRoot);
}
mkdir($badSiteRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
file_put_contents($badSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json', json_encode([
    'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    'site_id' => 'bad-generated-app',
    'role' => 'generated-opus-application',
    'application_fsm' => 'config/fsm.json',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

try {
    FsmSiteLoader::resolve($badSiteRoot);
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_BAD_GENERATED_APP_NOT_REJECTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'OPUS_FSM_GENERATED_APPLICATION_POINTER_INVALID: bad-generated-app') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
} finally {
    @unlink($badSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json');
    @rmdir($badSiteRoot . DIRECTORY_SEPARATOR . 'config');
    @rmdir($badSiteRoot);
}

echo "OPUS_FSM_SITE_LOADER_SMOKE_OK\n";
