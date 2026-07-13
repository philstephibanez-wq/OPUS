<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteId = '__cli_fsm_missing_app';
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;

function opus_cli_fsm_smoke_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($itemPath);
        } else {
            @unlink($itemPath);
        }
    }
    @rmdir($path);
}

function opus_cli_fsm_smoke_exec(string $command): array
{
    $lines = [];
    exec($command . ' 2>&1', $lines, $code);
    return ['code' => (int) $code, 'output' => implode("\n", $lines)];
}

function opus_cli_fsm_smoke_write_json(string $path, array $data): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('OPUS_VALIDATE_SITE_CLI_FSM_DIR_CREATE_FAILED: ' . $directory);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

opus_cli_fsm_smoke_remove_tree($siteRoot);

try {
    $requiredDirectories = [
        'config',
        'application/default/acl',
        'application/default/helpers',
        'application/default/css',
        'application/default/javascript',
        'application/default/local',
        'application/default/models',
        'application/default/templates',
        'application/default/views',
        'www/asset/css',
        'www/asset/js',
        'www/asset/themes/starter/css',
    ];
    foreach ($requiredDirectories as $relative) {
        $directory = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('OPUS_VALIDATE_SITE_CLI_FSM_DIR_CREATE_FAILED: ' . $relative);
        }
    }

    opus_cli_fsm_smoke_write_json($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json', [
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'site_id' => $siteId,
        'site_name' => 'CLI FSM Missing Application',
        'role' => 'generated-opus-application',
        'public_root' => 'www',
        'application_root' => 'application',
        'default_root' => 'application/default',
        'asset_root' => 'www/asset',
    ]);
    opus_cli_fsm_smoke_write_json($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json', ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'routes' => []]);
    opus_cli_fsm_smoke_write_json($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'fsm.json', [
        'contract' => 'OPUS_FSM_REGISTRY_V1',
        'initial_state' => 'HOME',
        'states' => [['id' => 'HOME', 'controller' => 'home']],
        'transitions' => [],
    ]);
    file_put_contents($siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php', "<?php echo 'ok';\n");

    $bin = escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus');
    $negative = opus_cli_fsm_smoke_exec(PHP_BINARY . ' ' . $bin . ' validate:site ' . escapeshellarg($siteId));
    if ($negative['code'] === 0 || !str_contains($negative['output'], 'OPUS_SITE_GENERATED_APPLICATION_FSM_MISSING: ' . $siteId)) {
        fwrite(STDERR, $negative['output'] . "\n");
        throw new RuntimeException('OPUS_VALIDATE_SITE_CLI_FSM_NEGATIVE_FAILED');
    }

    foreach (['owasys', 'demo-app'] as $validSiteId) {
        $positive = opus_cli_fsm_smoke_exec(PHP_BINARY . ' ' . $bin . ' validate:site ' . escapeshellarg($validSiteId));
        if ($positive['code'] !== 0 || !str_contains($positive['output'], 'OPUS_VALIDATE_SITE_OK: ' . $validSiteId)) {
            fwrite(STDERR, $positive['output'] . "\n");
            throw new RuntimeException('OPUS_VALIDATE_SITE_CLI_FSM_POSITIVE_FAILED: ' . $validSiteId);
        }
    }
} finally {
    opus_cli_fsm_smoke_remove_tree($siteRoot);
}

if (file_exists($siteRoot)) {
    fwrite(STDERR, "OPUS_VALIDATE_SITE_CLI_FSM_CLEANUP_FAILED\n");
    exit(1);
}

echo "OPUS_VALIDATE_SITE_CLI_FSM_SMOKE_OK\n";
