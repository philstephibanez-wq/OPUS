<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ScaffoldPlanBuilder;

$builder = new ScaffoldPlanBuilder();
$request = [
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
    'security_profiles' => [['id' => 'admin', 'permissions' => ['*']]],
    'workflows' => [],
];
$plan = $builder->build($request);

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

if (($plan['profiler']['enabled'] ?? null) !== true
    || ($plan['profiler']['mandatory'] ?? null) !== true
    || ($plan['profiler']['production_available'] ?? null) !== false
    || ($plan['profiler']['contract'] ?? null) !== 'OPUS_GENERATED_PROFILER_V1') {
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_PROFILER_NOT_MANDATORY\n");
    exit(1);
}

$validationCommands = is_array($plan['validation_commands'] ?? null) ? $plan['validation_commands'] : [];
if (!in_array('php tools/smoke_generated_opus_profiler.php', $validationCommands, true)) {
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_PROFILER_VALIDATION_MISSING\n");
    exit(1);
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

$disabledRequest = $request;
$disabledRequest['profiler'] = false;
try {
    $builder->build($disabledRequest);
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_PROFILER_DISABLE_NOT_REJECTED\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    if ($exception->getMessage() !== 'OWASYS_PROFILER_MANDATORY') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

$badRequest = $request;
$badRequest['id'] = 'bad_id';
$badRequest['slug'] = 'bad-id';
$badRequest['name'] = 'Bad OPUS Application';
$badRequest['root_path'] = 'sites/bad-id';
$badRequest['controllers'] = ['home'];
$badRequest['routes'] = [['id' => 'home.index', 'path' => '/', 'state' => 'home', 'controller' => 'home']];
$badRequest['security_profiles'] = [];
try {
    $builder->build($badRequest);
    fwrite(STDERR, "OWASYS_SCAFFOLD_PLAN_ID_ROOT_MISMATCH_NOT_REJECTED\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    if ($exception->getMessage() !== 'OWASYS_SITE_ROOT_MUST_MATCH_SITE_ID: sites/bad_id') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

echo "OWASYS_SCAFFOLD_PLAN_BUILDER_SMOKE_OK\n";
