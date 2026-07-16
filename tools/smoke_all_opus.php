<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$run = static function (array $arguments) use ($root): void {
    $command = PHP_BINARY;
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    echo 'OPUS_SMOKE_ALL_RUNNING: ' . implode(' ', $arguments) . "\n";

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
    'Opus/Owasys/ApplicationInspector.php',
    'Opus/Owasys/StructureDraftRepository.php',
    'Opus/Owasys/StructureDraftApplier.php',
    'Opus/Owasys/StructureDraftWritePlanner.php',
    'Opus/Owasys/StructureDraftPreviewConfirmation.php',
    'Opus/Owasys/ScaffoldPlanBuilder.php',
    'Opus/Owasys/ApplicationScaffoldWriter.php',
    'Opus/Owasys/GeneratedProfilerWriter.php',
    'Opus/Owasys/ApplicationCreator.php',
    'sites/owasys/www/structure-preview.php',
    'tools/smoke_all_opus.php',
    'tools/smoke_opus_site_contract_eternal.php',
    'tools/smoke_opus_fsm_processor.php',
    'tools/smoke_opus_fsm_site_loader.php',
    'tools/smoke_opus_fsm_action_dispatcher.php',
    'tools/smoke_opus_fsm_transition_cli.php',
    'tools/smoke_opus_bin_fsm_transition.php',
    'tools/smoke_opus_validate_site_cli_fsm.php',
    'tools/smoke_owasys_i18n.php',
    'tools/smokes/smoke_owasys_i18n_complete.php',
    'tools/smoke_owasys_global_header.php',
    'tools/smoke_owasys_runtime_fsm.php',
    'tools/smoke_owasys_runtime_fsm_http.php',
    'tools/smoke_owasys_runtime_context_sqlite.php',
    'tools/smoke_owasys_application_inspector.php',
    'tools/smoke_owasys_structure_actions.php',
    'tools/smoke_owasys_structure_drafts.php',
    'tools/smoke_owasys_structure_draft_ui.php',
    'tools/smoke_owasys_structure_draft_apply.php',
    'tools/smoke_owasys_structure_write_plan.php',
    'tools/smoke_owasys_structure_preview.php',
    'tools/smoke_owasys_structure_draft_apply_ui_http.php',
    'tools/smoke_owasys_registry_sqlite.php',
    'tools/smoke_owasys_registry_naming.php',
    'tools/smoke_owasys_navigation_fsm.php',
    'tools/smoke_owasys_login_password.php',
    'tools/smoke_owasys_showcase_blueprint.php',
    'tools/smoke_owasys_scaffold_plan_builder.php',
    'tools/smoke_owasys_application_scaffold_writer.php',
    'tools/smoke_owasys_application_creator.php',
    'tools/smoke_generated_opus_profiler.php',
    'tools/smoke_owasys_bin_opus_create.php',
    'tools/smoke_owasys_application_exporter.php',
    'tools/smoke_owasys_bin_opus_export.php',
];

$owasysLocales = [
    'bg', 'hr', 'cs', 'da', 'nl', 'en', 'et', 'fi', 'fr', 'de', 'el', 'hu', 'ga',
    'it', 'lv', 'lt', 'mt', 'pl', 'pt', 'ro', 'sk', 'sl', 'es', 'sv', 'uk',
];
foreach ($owasysLocales as $locale) {
    $phpFiles[] = 'sites/owasys/application/default/local/' . $locale . '.php';
}

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
    ['tools/smokes/smoke_owasys_i18n_complete.php'],
    ['tools/smoke_owasys_global_header.php'],
    ['tools/smoke_owasys_registry_sqlite.php'],
    ['tools/smoke_owasys_runtime_context_sqlite.php'],
    ['tools/smoke_owasys_application_inspector.php'],
    ['tools/smoke_owasys_structure_actions.php'],
    ['tools/smoke_owasys_structure_drafts.php'],
    ['tools/smoke_owasys_structure_draft_ui.php'],
    ['tools/smoke_owasys_structure_draft_apply.php'],
    ['tools/smoke_owasys_structure_write_plan.php'],
    ['tools/smoke_owasys_structure_preview.php'],
    ['tools/smoke_owasys_registry_naming.php'],
    ['tools/smoke_owasys_navigation_fsm.php'],
    ['tools/smoke_owasys_runtime_fsm.php'],
    ['tools/smoke_owasys_login_password.php'],
    ['tools/smoke_owasys_showcase_blueprint.php'],
    ['tools/smoke_owasys_scaffold_plan_builder.php'],
    ['tools/smoke_owasys_application_scaffold_writer.php'],
    ['tools/smoke_owasys_application_creator.php'],
    ['tools/smoke_generated_opus_profiler.php'],
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

echo "OWASYS_STRUCTURE_DRAFT_APPLY_UI_HTTP_SMOKE_SEPARATE\n";
echo "OWASYS_RUNTIME_FSM_HTTP_SMOKE_SEPARATE\n";
echo "OPUS_SMOKE_ALL_OK\n";
