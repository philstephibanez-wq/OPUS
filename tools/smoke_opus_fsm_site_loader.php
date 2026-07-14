<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Fsm\FsmSiteLoader;

$root = dirname(__DIR__);

$removeTree = static function (string $path): void {
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
};

$writeSiteConfig = static function (string $siteRoot, array $config): void {
    $configRoot = $siteRoot . DIRECTORY_SEPARATOR . 'config';
    if (!is_dir($configRoot) && !mkdir($configRoot, 0777, true) && !is_dir($configRoot)) {
        throw new RuntimeException('OPUS_FSM_SITE_LOADER_TMP_CONFIG_CREATE_FAILED');
    }
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || file_put_contents($configRoot . DIRECTORY_SEPARATOR . 'site.json', $encoded . "\n") === false) {
        throw new RuntimeException('OPUS_FSM_SITE_LOADER_TMP_SITE_CONFIG_WRITE_FAILED');
    }
};

$writeMinimalFsm = static function (string $siteRoot, string $siteId): void {
    $fsm = [
        'contract' => 'OPUS_APPLICATION_FSM_V1',
        'site_id' => $siteId,
        'initial_state' => 'home',
        'states' => [
            ['id' => 'home', 'route' => '/', 'state' => 'home'],
        ],
        'transitions' => [],
    ];
    $encoded = json_encode($fsm, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || file_put_contents($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json', $encoded . "\n") === false) {
        throw new RuntimeException('OPUS_FSM_SITE_LOADER_TMP_FSM_WRITE_FAILED');
    }
};

$makeStateTree = static function (string $siteRoot, bool $includeStates = true): void {
    $applicationRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application';
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'default', 0777, true);
    if ($includeStates) {
        mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'home', 0777, true);
    }
};

$expectRuntimeException = static function (callable $callback, string $expectedMessage): void {
    try {
        $callback();
        fwrite(STDERR, "OPUS_FSM_SITE_LOADER_EXCEPTION_NOT_THROWN: {$expectedMessage}\n");
        exit(1);
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== $expectedMessage) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            exit(1);
        }
    }
};

$demoResolved = FsmSiteLoader::resolve($root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app');
if (($demoResolved['site_id'] ?? null) !== 'demo-app' || ($demoResolved['fsm_relative_path'] ?? null) !== 'config/application.fsm.json') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_DEMO_RESOLUTION_INVALID\n");
    exit(1);
}

$demoSiteConfig = is_array($demoResolved['site_config'] ?? null) ? $demoResolved['site_config'] : [];
if (($demoSiteConfig['states_root'] ?? null) !== 'application/states' || ($demoSiteConfig['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_DEMO_STATE_TREE_CONTRACT_INVALID\n");
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

$owasysSiteConfig = is_array($owasysResolved['site_config'] ?? null) ? $owasysResolved['site_config'] : [];
if (($owasysSiteConfig['states_root'] ?? null) !== 'application/states' || ($owasysSiteConfig['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_OWASYS_STATE_TREE_CONTRACT_INVALID\n");
    exit(1);
}

$owasysProcessor = FsmSiteLoader::processorForSite($root, 'owasys');
$changeApp = $owasysProcessor->transition('security', 'change_app');
if (($changeApp['to_state'] ?? null) !== 'registry') {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_OWASYS_WILDCARD_INVALID\n");
    exit(1);
}

$expectRuntimeException(
    static fn () => FsmSiteLoader::processorForSite($root, '../bad'),
    'OPUS_FSM_SITE_ID_INVALID: ../bad'
);

$tmpRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'fsm-site-loader-bad';
$removeTree($tmpRoot);

try {
    $badStateRoot = $tmpRoot . DIRECTORY_SEPARATOR . 'bad-state-root';
    mkdir($badStateRoot, 0777, true);
    $makeStateTree($badStateRoot);
    $writeSiteConfig($badStateRoot, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'bad-state-root',
        'states_root' => 'application/home',
        'dispatch_model' => 'state-first',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($badStateRoot),
        'OPUS_FSM_SITE_STATES_ROOT_INVALID: bad-state-root'
    );

    $badDispatch = $tmpRoot . DIRECTORY_SEPARATOR . 'bad-dispatch';
    mkdir($badDispatch, 0777, true);
    $makeStateTree($badDispatch);
    $writeSiteConfig($badDispatch, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'bad-dispatch',
        'states_root' => 'application/states',
        'dispatch_model' => 'controller-first',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($badDispatch),
        'OPUS_FSM_SITE_DISPATCH_MODEL_INVALID: bad-dispatch'
    );

    $missingStates = $tmpRoot . DIRECTORY_SEPARATOR . 'missing-states';
    mkdir($missingStates, 0777, true);
    $makeStateTree($missingStates, false);
    $writeSiteConfig($missingStates, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'missing-states',
        'states_root' => 'application/states',
        'dispatch_model' => 'state-first',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($missingStates),
        'OPUS_FSM_SITE_STATES_DIRECTORY_MISSING: missing-states'
    );

    $legacyRoot = $tmpRoot . DIRECTORY_SEPARATOR . 'legacy-root';
    mkdir($legacyRoot, 0777, true);
    $makeStateTree($legacyRoot);
    mkdir($legacyRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'home', 0777, true);
    $writeSiteConfig($legacyRoot, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'legacy-root',
        'states_root' => 'application/states',
        'dispatch_model' => 'state-first',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($legacyRoot),
        'OPUS_FSM_SITE_LEGACY_STATE_ROOT_PRESENT: legacy-root:application/home'
    );

    $badGenerated = $tmpRoot . DIRECTORY_SEPARATOR . 'bad-generated-app';
    mkdir($badGenerated, 0777, true);
    $makeStateTree($badGenerated);
    $writeSiteConfig($badGenerated, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'bad-generated-app',
        'role' => 'generated-opus-application',
        'states_root' => 'application/states',
        'dispatch_model' => 'state-first',
        'application_fsm' => 'config/fsm.json',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($badGenerated),
        'OPUS_FSM_GENERATED_APPLICATION_POINTER_INVALID: bad-generated-app'
    );

    $missingGeneratedFsm = $tmpRoot . DIRECTORY_SEPARATOR . 'missing-generated-fsm';
    mkdir($missingGeneratedFsm, 0777, true);
    $makeStateTree($missingGeneratedFsm);
    $writeSiteConfig($missingGeneratedFsm, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'missing-generated-fsm',
        'role' => 'generated-opus-application',
        'states_root' => 'application/states',
        'dispatch_model' => 'state-first',
        'application_fsm' => 'config/application.fsm.json',
    ]);
    $expectRuntimeException(
        static fn () => FsmSiteLoader::resolve($missingGeneratedFsm),
        'OPUS_FSM_GENERATED_APPLICATION_FSM_MISSING: missing-generated-fsm'
    );

    $validGenerated = $tmpRoot . DIRECTORY_SEPARATOR . 'valid-generated-fsm';
    mkdir($validGenerated, 0777, true);
    $makeStateTree($validGenerated);
    $writeSiteConfig($validGenerated, [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => 'valid-generated-fsm',
        'role' => 'generated-opus-application',
        'states_root' => 'application/states',
        'dispatch_model' => 'state-first',
        'application_fsm' => 'config/application.fsm.json',
    ]);
    $writeMinimalFsm($validGenerated, 'valid-generated-fsm');
    $validResolved = FsmSiteLoader::resolve($validGenerated);
    if (($validResolved['fsm_relative_path'] ?? null) !== 'config/application.fsm.json') {
        fwrite(STDERR, "OPUS_FSM_SITE_LOADER_VALID_GENERATED_RESOLUTION_INVALID\n");
        exit(1);
    }
} finally {
    $removeTree($tmpRoot);
}

if (file_exists($tmpRoot)) {
    fwrite(STDERR, "OPUS_FSM_SITE_LOADER_TMP_CLEANUP_FAILED\n");
    exit(1);
}

echo "OPUS_FSM_SITE_LOADER_SMOKE_OK\n";
