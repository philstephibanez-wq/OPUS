<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationFileEditor;
use Opus\Owasys\RepositoryInspector;

$root = dirname(__DIR__);
$relativeRoot = 'var/smoke/owasys-source-editor';
$applicationRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
};

$removeTree($applicationRoot);
foreach (['config', 'application/states/home/views', 'application/states/home/templates', 'www/asset/js', 'var/auth'] as $directory) {
    $path = $applicationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fwrite(STDERR, "OWASYS_SOURCE_GIT_SMOKE_DIRECTORY_FAILED\n");
        exit(1);
    }
}

file_put_contents($applicationRoot . '/config/site.json', "{\n  \"contract\": \"TEST\"\n}\n");
file_put_contents($applicationRoot . '/application/states/home/views/index.php', "<?php\ndeclare(strict_types=1);\nreturn ['title' => 'Before'];\n");
file_put_contents($applicationRoot . '/application/states/home/templates/index.score', "<h1>Before</h1>\n");
file_put_contents($applicationRoot . '/www/asset/js/app.js', "console.log('before');\n");
file_put_contents($applicationRoot . '/var/auth/local-users.json', "{}\n");

try {
    $editor = new ApplicationFileEditor($root);
    $files = $editor->listFiles($relativeRoot);
    $paths = array_column($files, 'path');
    foreach (['config/site.json', 'application/states/home/views/index.php', 'application/states/home/templates/index.score', 'www/asset/js/app.js'] as $required) {
        if (!in_array($required, $paths, true)) {
            throw new RuntimeException('OWASYS_EDITOR_REQUIRED_FILE_NOT_LISTED:' . $required);
        }
    }
    if (in_array('var/auth/local-users.json', $paths, true)) {
        throw new RuntimeException('OWASYS_EDITOR_SECRET_FILE_EXPOSED');
    }

    $read = $editor->read($relativeRoot, 'application/states/home/views/index.php');
    if (($read['contract'] ?? null) !== ApplicationFileEditor::CONTRACT || !is_string($read['sha256'] ?? null)) {
        throw new RuntimeException('OWASYS_EDITOR_READ_CONTRACT_INVALID');
    }

    $newPhp = "<?php\ndeclare(strict_types=1);\nreturn ['title' => 'After'];\n";
    $preview = $editor->preview($relativeRoot, 'application/states/home/views/index.php', $newPhp);
    if (($preview['changed'] ?? false) !== true || ($preview['disk_mutation'] ?? true) !== false || !str_contains((string) ($preview['diff'] ?? ''), "After")) {
        throw new RuntimeException('OWASYS_EDITOR_PREVIEW_INVALID');
    }
    if (!str_contains((string) file_get_contents($applicationRoot . '/application/states/home/views/index.php'), 'Before')) {
        throw new RuntimeException('OWASYS_EDITOR_PREVIEW_MUTATED_DISK');
    }

    $write = $editor->write($relativeRoot, 'application/states/home/views/index.php', $newPhp, (string) $read['sha256']);
    if (($write['disk_mutation'] ?? false) !== true || ($write['atomic'] ?? false) !== true) {
        throw new RuntimeException('OWASYS_EDITOR_WRITE_INVALID');
    }
    if (!str_contains((string) file_get_contents($applicationRoot . '/application/states/home/views/index.php'), 'After')) {
        throw new RuntimeException('OWASYS_EDITOR_WRITE_MISSING');
    }

    try {
        $editor->write($relativeRoot, 'application/states/home/views/index.php', $newPhp, (string) $read['sha256']);
        throw new RuntimeException('OWASYS_EDITOR_CONCURRENT_MODIFICATION_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'OWASYS_EDITOR_CONCURRENT_MODIFICATION') {
            throw $exception;
        }
    }

    foreach ([
        ['../composer.json', 'OWASYS_EDITOR_PATH_FORBIDDEN'],
        ['var/auth/local-users.json', 'OWASYS_EDITOR_PATH_FORBIDDEN'],
    ] as [$path, $expected]) {
        try {
            $editor->read($relativeRoot, $path);
            throw new RuntimeException('OWASYS_EDITOR_FORBIDDEN_PATH_NOT_REJECTED:' . $path);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== $expected) {
                throw $exception;
            }
        }
    }

    try {
        $editor->preview($relativeRoot, 'config/site.json', '{invalid');
        throw new RuntimeException('OWASYS_EDITOR_INVALID_JSON_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'OWASYS_EDITOR_JSON_INVALID') {
            throw $exception;
        }
    }

    try {
        $editor->preview($relativeRoot, 'application/states/home/views/index.php', "<?php syntax error");
        $badRead = $editor->read($relativeRoot, 'application/states/home/views/index.php');
        $editor->write($relativeRoot, 'application/states/home/views/index.php', "<?php syntax error", (string) $badRead['sha256']);
        throw new RuntimeException('OWASYS_EDITOR_INVALID_PHP_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'OWASYS_EDITOR_PHP_SYNTAX_INVALID') {
            throw $exception;
        }
    }

    $inspector = new RepositoryInspector($root);
    $inspection = $inspector->inspect('sites/demo-app', 3);
    if (($inspection['contract'] ?? null) !== RepositoryInspector::CONTRACT
        || ($inspection['capabilities']['arbitrary_commands'] ?? true) !== false
        || ($inspection['capabilities']['write_operations'] ?? true) !== false
        || !array_key_exists('clean', $inspection)
        || !is_array($inspection['changes'] ?? null)
        || !is_array($inspection['history'] ?? null)) {
        throw new RuntimeException('OWASYS_GIT_INSPECTION_INVALID');
    }

    try {
        $inspector->inspect('../outside');
        throw new RuntimeException('OWASYS_GIT_TRAVERSAL_NOT_REJECTED');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'OWASYS_GIT_APPLICATION_ROOT_INVALID') {
            throw $exception;
        }
    }
} catch (Throwable $exception) {
    $removeTree($applicationRoot);
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$removeTree($applicationRoot);
echo "OWASYS_SOURCE_GIT_CORE_SMOKE_OK\n";
