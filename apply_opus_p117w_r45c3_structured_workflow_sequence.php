<?php
declare(strict_types=1);

const OPUS_P117W_R45C3_BASE = '058984bfb0229bf5f27c74cd2b59c6614bf74b4e';

$root = __DIR__;
$targets = [
    'sites/owasys-front/config/fsm.json',
    'sites/owasys-front/application/creation/controllers/CreationController.php',
];

function runCommand(array $command, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        $command,
        $descriptors,
        $pipes,
        $cwd,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        throw new RuntimeException('R45C3_PROCESS_START_FAILED');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'code' => $exitCode,
        'stdout' => is_string($stdout) ? trim($stdout) : '',
        'stderr' => is_string($stderr) ? trim($stderr) : '',
    ];
}

function replaceOnce(string $content, string $from, string $to, string $code): string
{
    $count = substr_count($content, $from);
    if ($count !== 1) {
        throw new RuntimeException($code . ':MATCHES=' . $count);
    }
    return str_replace($from, $to, $content);
}

function readRequired(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('R45C3_TARGET_MISSING:' . str_replace('\\', '/', $path));
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        throw new RuntimeException('R45C3_TARGET_READ_FAILED:' . str_replace('\\', '/', $path));
    }
    return $content;
}

function writeExact(string $path, string $content): void
{
    $written = file_put_contents($path, $content, LOCK_EX);
    if ($written !== strlen($content)) {
        throw new RuntimeException('R45C3_TARGET_WRITE_FAILED:' . str_replace('\\', '/', $path));
    }
}

$head = runCommand(['git', 'rev-parse', 'HEAD'], $root);
if ($head['code'] !== 0 || strtolower($head['stdout']) !== OPUS_P117W_R45C3_BASE) {
    throw new RuntimeException(
        'R45C3_BASE_MISMATCH:EXPECTED=' . OPUS_P117W_R45C3_BASE
        . ':ACTUAL=' . ($head['stdout'] !== '' ? $head['stdout'] : 'UNKNOWN')
    );
}

$status = runCommand(
    ['git', 'status', '--porcelain', '--', $targets[0], $targets[1]],
    $root
);
if ($status['code'] !== 0) {
    throw new RuntimeException('R45C3_GIT_STATUS_FAILED:' . $status['stderr']);
}
if ($status['stdout'] !== '') {
    throw new RuntimeException('R45C3_TARGETS_NOT_CLEAN');
}

$fsmPath = $root . '/' . $targets[0];
$controllerPath = $root . '/' . $targets[1];

$fsm = readRequired($fsmPath);
$controller = readRequired($controllerPath);

$fsm = replaceOnce(
    $fsm,
    '{"id":"structure","module":"structure","route":"structure","requires_auth":true,"requires_current_app":true,"title_key":"menu.structure","summary_key":"state.default.summary","navigation":{"visible":true,"order":20,"label":"menu.structure"}}',
    '{"id":"structure","module":"structure","route":"structure","requires_auth":true,"requires_current_app":true,"title_key":"menu.structure","summary_key":"state.default.summary","navigation":{"visible":true,"order":30,"label":"menu.structure"}}',
    'R45C3_FSM_STRUCTURE_ORDER_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"data","module":"data","route":"data","requires_auth":true,"requires_current_app":true,"title_key":"menu.data","summary_key":"state.default.summary","navigation":{"visible":true,"order":30,"label":"menu.data"}}',
    '{"id":"data","module":"data","route":"data","requires_auth":true,"requires_current_app":true,"title_key":"menu.data","summary_key":"state.default.summary","navigation":{"visible":true,"order":20,"label":"menu.data"}}',
    'R45C3_FSM_DATA_ORDER_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"workflows","module":"workflows","route":"workflows","requires_auth":true,"requires_current_app":true,"title_key":"menu.workflows","summary_key":"state.default.summary","navigation":{"visible":true,"order":40,"label":"menu.workflows"}}',
    '{"id":"workflows","module":"workflows","route":"workflows","requires_auth":true,"requires_current_app":true,"title_key":"menu.workflows","summary_key":"state.default.summary","navigation":{"visible":true,"order":50,"label":"menu.workflows"}}',
    'R45C3_FSM_WORKFLOWS_ORDER_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"security","module":"security","route":"security","requires_auth":true,"requires_current_app":true,"title_key":"menu.security","summary_key":"state.default.summary","navigation":{"visible":true,"order":50,"label":"menu.security"}}',
    '{"id":"security","module":"security","route":"security","requires_auth":true,"requires_current_app":true,"title_key":"menu.security","summary_key":"state.default.summary","navigation":{"visible":true,"order":40,"label":"menu.security"}}',
    'R45C3_FSM_SECURITY_ORDER_DRIFT'
);

$fsm = replaceOnce(
    $fsm,
    '{"id":"t_select_app","from":"registry","signal":"select_app","next_state":"structure","guards":["app_exists"],"actions":["set_current_app"],"visual":true}',
    '{"id":"t_select_app","from":"registry","signal":"select_app","next_state":"data","guards":["app_exists"],"actions":["set_current_app"],"visual":true}',
    'R45C3_FSM_SELECT_APP_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_creation_created","from":"creation","signal":"application_created","next_state":"build","guards":["app_exists"],"actions":["set_current_app"],"visual":true}',
    '{"id":"t_creation_created","from":"creation","signal":"application_created","next_state":"data","guards":["app_exists"],"actions":["set_current_app"],"visual":true}',
    'R45C3_FSM_CREATION_CREATED_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_open_structure","from":"*","signal":"open_structure","next_state":"structure","guards":["current_app_required"]}',
    '{"id":"t_open_structure","from":"*","signal":"open_structure","next_state":"structure","guards":["current_app_required"],"visual":true,"visual_from":"data"}',
    'R45C3_FSM_OPEN_STRUCTURE_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_open_data","from":"*","signal":"open_data","next_state":"data","guards":["current_app_required"],"visual":true,"visual_from":"structure"}',
    '{"id":"t_open_data","from":"*","signal":"open_data","next_state":"data","guards":["current_app_required"]}',
    'R45C3_FSM_OPEN_DATA_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_open_workflows","from":"*","signal":"open_workflows","next_state":"workflows","guards":["current_app_required"],"visual":true,"visual_from":"data"}',
    '{"id":"t_open_workflows","from":"*","signal":"open_workflows","next_state":"workflows","guards":["current_app_required"],"visual":true,"visual_from":"security"}',
    'R45C3_FSM_OPEN_WORKFLOWS_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_open_security","from":"*","signal":"open_security","next_state":"security","guards":["current_app_required"],"visual":true,"visual_from":"workflows"}',
    '{"id":"t_open_security","from":"*","signal":"open_security","next_state":"security","guards":["current_app_required"],"visual":true,"visual_from":"structure"}',
    'R45C3_FSM_OPEN_SECURITY_DRIFT'
);
$fsm = replaceOnce(
    $fsm,
    '{"id":"t_open_source","from":"*","signal":"open_source","next_state":"source","guards":["current_app_required"],"visual":true,"visual_from":"security"}',
    '{"id":"t_open_source","from":"*","signal":"open_source","next_state":"source","guards":["current_app_required"],"visual":true,"visual_from":"workflows"}',
    'R45C3_FSM_OPEN_SOURCE_DRIFT'
);

$controller = replaceOnce(
    $controller,
    "\$this->redirect(\$locale, 'build');",
    "\$this->redirect(\$locale, 'data');",
    'R45C3_CREATION_REDIRECT_DRIFT'
);

$decoded = json_decode($fsm, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($decoded) || ($decoded['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
    throw new RuntimeException('R45C3_FSM_RESULT_INVALID');
}

writeExact($fsmPath, $fsm);
writeExact($controllerPath, $controller);

echo "OPUS_P117W_R45C3_APPLIED\n";
echo "FILES=2\n";
