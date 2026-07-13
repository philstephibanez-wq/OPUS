<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ScaffoldPlanBuilder;

$builder = new ScaffoldPlanBuilder();
$plan = $builder->build([
    'id' => 'demo-app',
    'slug' => 'demo-app',
    'name' => 'Demo OPUS Application',
    'kind' => 'fullstack',
    'root_path' => 'sites/demo-app',
    'blueprint' => 'opus-site-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home', 'articles'],
    'routes' => [
        ['id' => 'home.index', 'path' => '/', 'state' => 'home', 'controller' => 'home'],
        ['id' => 'articles.index', 'path' => '/articles', 'state' => 'articles', 'controller' => 'articles'],
    ],
    'datasources' => [],
    'security_profiles' => [
        ['id' => 'admin', 'permissions' => ['*']],
    ],
    'workflows' => [],
]);

$required = [
    'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    'owasys_contract' => 'OWASYS_SCAFFOLD_PLAN_V1',
    'site_id' => 'demo-app',
    'site_root' => 'sites/demo-app',
    'dispatch_model' => 'state-first',
];
foreach ($required as $key => $expected) {
    if (($plan[$key] ?? null) !== $expected) {
        fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_FIELD_INVALID: {$key}\n");
        exit(1);
    }
}

$directories = $plan['directories'] ?? [];
if (!is_array($directories) || !in_array('sites/demo-app/application/default/templates', $directories, true) || !in_array('sites/demo-app/application/states/home/views', $directories, true) || !in_array('sites/demo-app/www/asset/themes/starter/css', $directories, true)) {
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_REQUIRED_DIRECTORIES_MISSING\n");
    exit(1);
}

$files = $plan['files'] ?? [];
if (!is_array($files)) {
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_FILES_INVALID\n");
    exit(1);
}
$filePaths = array_map(static fn (array $file): string => (string) ($file['path'] ?? ''), $files);
foreach (['sites/demo-app/config/site.json', 'sites/demo-app/config/routes.json', 'sites/demo-app/config/application.fsm.json', 'sites/demo-app/config/fsm.json', 'sites/demo-app/www/index.php', 'sites/demo-app/application/states/home/views/index.php'] as $path) {
    if (!in_array($path, $filePaths, true)) {
        fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_REQUIRED_FILE_MISSING: {$path}\n");
        exit(1);
    }
}

foreach (array_merge($directories, $filePaths) as $path) {
    foreach (['/public/', '/src/', '/resources/', '/application/home/'] as $forbidden) {
        if (str_contains('/' . $path . '/', $forbidden)) {
            fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_FORBIDDEN_PATH: {$path}\n");
            exit(1);
        }
    }
}

try {
    $builder->build([
        'id' => 'bad_id',
        'slug' => 'bad-id',
        'name' => 'Bad OPUS Application',
        'kind' => 'fullstack',
        'root_path' => 'sites/bad-id',
        'blueprint' => 'opus-site-standard',
        'default_locale' => 'fr',
        'theme' => 'starter',
        'controllers' => ['home'],
        'routes' => [['id' => 'home.index', 'path' => '/', 'state' => 'home', 'controller' => 'home']],
        'datasources' => [],
        'security_profiles' => [],
        'workflows' => [],
    ]);
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_ID_ROOT_MISMATCH_NOT_REJECTED\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    if ($exception->getMessage() !== 'OWASYS_SITE_ROOT_MUST_MATCH_SITE_ID: sites/bad_id') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

echo "OWASYS_SCAFFOLD_PLAN_BUILDER_SMOKE_OK\n";
