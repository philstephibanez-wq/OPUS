<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$run = static function (array $arguments) use ($root): void {
    $command = PHP_BINARY;
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);

    foreach ($output as $line) {
        echo $line . "\n";
    }

    if ($code !== 0) {
        fwrite(STDERR, 'OPUS_SMOKE_ALL_COMMAND_FAILED: ' . implode(' ', $arguments) . "\n");
        exit(1);
    }
};

$phpFiles = [
    'bin/opus',
    'Opus/Fsm/FsmProcessor.php',
    'Opus/Fsm/FsmSiteLoader.php',
    'Opus/Fsm/FsmActionDispatcher.php',
    'Opus/Owasys/RegistryRepository.php',
    'sites/owasys/application/default/local/fr.php',
    'sites/owasys/application/default/local/en.php',
    'tools/smoke_all_opus.php',
    'tools/smoke_opus_site_contract_eternal.php',
    'tools/smoke_opus_fsm_processor.php',
    'tools/smoke_opus_fsm_site_loader.php',
    'tools/smoke_opus_fsm_action_dispatcher.php',
    'tools/smoke_opus_fsm_transition_cli.php',
    'tools/smoke_opus_bin_fsm_transition.php',
    'tools/smoke_opus_validate_site_cli_fsm.php',
    'tools/smoke_owasys_i18n.php',
    'tools/smoke_owasys_runtime_fsm.php',
    'tools/smoke_owasys_runtime_fsm_http.php',
    'tools/smoke_owasys_runtime_context_sqlite.php',
    'tools/smoke_owasys_registry_sqlite.php',
    'tools/smoke_owasys_registry_naming.php',
    'tools/smoke_owasys_navigation_fsm.php',
    'tools/smoke_owasys_login_password.php',
    'tools/smoke_owasys_showcase_blueprint.php',
    'tools/smoke_owasys_scaffold_plan_builder.php',
    'tools/smoke_owasys_application_scaffold_writer.php',
    'tools/smoke_owasys_application_creator.php',
    'tools/smoke_owasys_bin_opus_create.php',
    'tools/smoke_owasys_application_exporter.php',
    'tools/smoke_owasys_bin_opus_export.php',
];

foreach ($phpFiles as $relativePath) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        fwrite(STDERR, 'OPUS_SMOKE_ALL_REQUIRED_FILE_MISSING: ' . $relativePath . "\n");
        exit(1);
    }
    $run(['-l', $path]);
}

$smokes = [
    ['tools/smoke_opus_site_contract_eternal.php'],
    ['tools/smoke_opus_fsm_processor.php'],
    ['tools/smoke_opus_fsm_site_loader.php'],
    ['tools/smoke_opus_fsm_action_dispatcher.php'],
    ['tools/smoke_opus_fsm_transition_cli.php'],
    ['tools/smoke_opus_bin_fsm_transition.php'],
    ['tools/smoke_opus_validate_site_cli_fsm.php'],
    ['tools/smoke_owasys_i18n.php'],
    ['tools/smoke_owasys_registry_sqlite.php'],
    ['tools/smoke_owasys_runtime_context_sqlite.php'],
    ['tools/smoke_owasys_registry_naming.php'],
    ['tools/smoke_owasys_navigation_fsm.php'],
    ['tools/smoke_owasys_runtime_fsm.php'],
    ['tools/smoke_owasys_login_password.php'],
    ['tools/smoke_owasys_showcase_blueprint.php'],
    ['tools/smoke_owasys_scaffold_plan_builder.php'],
    ['tools/smoke_owasys_application_scaffold_writer.php'],
    ['tools/smoke_owasys_application_creator.php'],
    ['tools/smoke_owasys_bin_opus_create.php'],
    ['tools/smoke_owasys_application_exporter.php'],
    ['tools/smoke_owasys_bin_opus_export.php'],
    ['bin/opus', 'validate:site', 'owasys'],
    ['bin/opus', 'validate:site', 'demo-app'],
];

foreach ($smokes as $smoke) {
    $arguments = [];
    foreach ($smoke as $index => $argument) {
        if ($index === 0) {
            $arguments[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $argument);
        } else {
            $arguments[] = $argument;
        }
    }
    $run($arguments);
}

echo "OWASYS_RUNTIME_FSM_HTTP_SMOKE_SEPARATE\n";
echo "OPUS_SMOKE_ALL_OK\n";
