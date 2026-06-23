<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/../..');
if (!is_string($root)) {
    echo "CHECK_OPUS_ROOT=FAIL\n";
    exit(1);
}

define('ROOT', $root);

require_once $root . '/Opus/Exception.class.php';
require_once $root . '/Opus/Debug.class.php';
require_once $root . '/Opus/Fsm/Fsm.class.php';
require_once $root . '/Opus/Fsm/Boot.class.php';

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    echo $name . '=' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

echo "P4E_OPUS_BOOT_FSM_SMOKE\n";

$check('CHECK_BOOT_FSM_CLASS_EXISTS', class_exists('OPUS_FSM_Boot'));
$check('CHECK_BOOT_FSM_EXTENDS_REAL_FSM', is_subclass_of('OPUS_FSM_Boot', 'OPUS_FSM_Fsm'));
$check('CHECK_BOOT_CLASS_IS_NOT_ROOT_WRAPPER', is_file($root . '/Opus/Fsm/Boot.class.php'));

$fsm = new OPUS_FSM_Boot('p4e_boot_smoke_' . getmypid() . '_' . mt_rand(1000, 9999));
$check('CHECK_BOOT_INITIAL_STATE', $fsm->getCurrentState() === OPUS_FSM_Boot::STATE_BOOT_START);
$check('CHECK_BOOT_PROGRAM_CONFIGURABLE', count($fsm->getProgram()) >= 6);
$check('CHECK_BOOT_RUN_RETURNS_READY', $fsm->runBoot() === true);
$check('CHECK_BOOT_FINAL_STATE_READY', $fsm->getCurrentState() === OPUS_FSM_Boot::STATE_BOOT_READY);
$check('CHECK_BOOT_READY_MEMORY', $fsm->peek('boot_ready') === true);
$check('CHECK_BOOT_EXECUTED_STEPS', count($fsm->getExecutedSteps()) === 6);

$appFile = $root . '/Opus/Application.class.php';
$appSource = is_file($appFile) ? (string)file_get_contents($appFile) : '';
$check('CHECK_APPLICATION_FILE_EXISTS', $appSource !== '');
$check('CHECK_APPLICATION_HAS_BOOT_FSM_FIELD', strpos($appSource, '$_bootFsm') !== false);
$check('CHECK_APPLICATION_HAS_BOOT_FSM_INIT', strpos($appSource, '_initBootFsm') !== false);
$check('CHECK_APPLICATION_CALLS_BOOT_FSM', strpos($appSource, 'new OPUS_FSM_Boot') !== false);
$check('CHECK_APPLICATION_GUARDS_DISPATCH_WITH_BOOT_READY', strpos($appSource, 'BOOT_READY') !== false && strpos($appSource, 'Runtime dispatch is forbidden') !== false);
$check('CHECK_NO_ROOT_FSM_WRAPPER_USAGE_FOR_BOOT', strpos($appSource, 'new Opus\\Fsm') === false && strpos($appSource, 'new Fsm()') === false);

if ($failures > 0) {
    echo 'P4E_OPUS_BOOT_FSM_SMOKE_FAIL failures=' . $failures . PHP_EOL;
    exit(1);
}

echo "P4E_OPUS_BOOT_FSM_SMOKE_OK\n";
