<?php

declare(strict_types=1);

use Opus\Template\ScoreTemplateRenderer;

$root = dirname(__DIR__, 2);
$templateRoot = $root . '/tools/recipes/templates/p116b5_live';
$sourceJson = $root . '/var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.json';
$reportDir = $root . '/var/reports/p116b5';
$htmlReport = $reportDir . '/p116b5_legacy_live_score_recipe.html';
$emailHtmlReport = $reportDir . '/p116b5_legacy_live_score_recipe_email.html';
$jsonReport = $reportDir . '/p116b5_legacy_live_score_recipe.json';

if (!is_dir($reportDir) && !mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
    fwrite(STDERR, 'P116B5_REPORT_DIR_CREATE_FAILED=' . $reportDir . PHP_EOL);
    exit(1);
}

$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Opus\\';
        if (!str_starts_with($class, $prefix)) { return; }
        $relative = substr($class, strlen($prefix));
        $path = $root . '/framework/Opus/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) { require_once $path; }
    });
}

function p116b5_fail(string $code, string $detail = ''): never
{
    fwrite(STDERR, ($detail === '' ? $code : $code . ': ' . $detail) . PHP_EOL);
    exit(1);
}

function p116b5_assert(bool $condition, string $code, string $detail = ''): void
{
    if (!$condition) {
        p116b5_fail($code, $detail);
    }
}

function p116b5_run_p112q3b2(string $root): void
{
    $script = $root . '/tools/recipes/p112q3b2_secure_life_robotized_recipe.php';
    p116b5_assert(is_file($script), 'P116B5_P112Q3B2_SCRIPT_MISSING', $script);

    $previousMode = getenv('OPUS_P112Q3B2_MAIL_MODE');
    $previousRequired = getenv('OPUS_P112Q3B2_MAIL_REQUIRED');
    putenv('OPUS_P112Q3B2_MAIL_MODE=eml');
    putenv('OPUS_P112Q3B2_MAIL_REQUIRED=0');

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, $root);
    if (!is_resource($process)) {
        p116b5_fail('P116B5_P112Q3B2_PROCESS_OPEN_FAILED');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($previousMode === false) { putenv('OPUS_P112Q3B2_MAIL_MODE'); } else { putenv('OPUS_P112Q3B2_MAIL_MODE=' . $previousMode); }
    if ($previousRequired === false) { putenv('OPUS_P112Q3B2_MAIL_REQUIRED'); } else { putenv('OPUS_P112Q3B2_MAIL_REQUIRED=' . $previousRequired); }

    p116b5_assert($exitCode === 0, 'P116B5_P112Q3B2_RECIPE_FAILED', 'exit=' . (string)$exitCode . ' stdout=' . trim($stdout) . ' stderr=' . trim($stderr));
}

function p116b5_clean_label(string $user): string
{
    return match ($user) {
        'guest' => 'Invité',
        'editor' => 'Éditeur',
        'admin' => 'Administrateur',
        default => $user,
    };
}

function p116b5_initial(string $user): string
{
    return match ($user) {
        'guest' => 'I',
        'editor' => 'É',
        'admin' => 'A',
        default => strtoupper(substr($user, 0, 1)),
    };
}

function p116b5_clean_rights(array $row): string
{
    $user = (string)($row['user'] ?? '');
    $route = (string)($row['request_path'] ?? $row['route_path'] ?? '');
    $allowed = (bool)($row['expect_allowed'] ?? false);
    if (!$allowed) {
        return 'accès refusé par défaut contrôlé';
    }
    if ($user === 'guest') { return 'lecture publique uniquement'; }
    if ($user === 'editor') { return 'édition autorisée, administration refusée'; }
    if ($user === 'admin' && str_contains($route, '/admin')) { return 'administration complète'; }
    return 'accès autorisé contrôlé';
}

function p116b5_form_title(string $route): string
{
    return match ($route) {
        '/fr/contact' => 'Contact public',
        '/es/editor/form' => 'Formulario editor',
        '/en/admin/settings' => 'Admin settings',
        default => 'Formulaire',
    };
}

function p116b5_form_value(string $route, string $fallback): string
{
    return match ($route) {
        '/fr/contact' => 'Bonjour Opus',
        '/es/editor/form' => 'Edición segura',
        '/en/admin/settings' => 'secure-by-design',
        default => $fallback,
    };
}

function p116b5_view_data(array $payload, array $mail): array
{
    $rows = [];
    $navCards = [];
    $formCards = [];
    foreach (($payload['scenarios'] ?? []) as $sourceRow) {
        if (!is_array($sourceRow)) { continue; }
        $route = (string)($sourceRow['request_path'] ?? $sourceRow['route_path'] ?? '');
        $user = (string)($sourceRow['user'] ?? '');
        $kind = (string)($sourceRow['kind'] ?? 'navigation');
        $method = strtoupper((string)($sourceRow['request_method'] ?? $sourceRow['method'] ?? 'GET'));
        $passed = (bool)($sourceRow['passed'] ?? false);
        $expected = (bool)($sourceRow['expect_allowed'] ?? false) ? 'ALLOWED' : 'DENIED';
        $observed = (bool)($sourceRow['observed_allowed'] ?? false) ? 'ALLOWED' : 'DENIED';
        $language = strtoupper((string)($sourceRow['language'] ?? ''));
        $label = p116b5_clean_label($user);
        $row = [
            'label' => $label,
            'language' => $language,
            'method' => $method,
            'kind' => $kind,
            'route' => $route,
            'expected' => $expected,
            'observed' => $observed,
            'status' => $passed ? 'OK' : 'FAIL',
            'status_class' => $passed ? 'ok' : 'fail',
            'status_color' => $passed ? '#15803d' : '#be123c',
        ];
        $rows[] = $row;
        if ($kind === 'navigation' && (bool)($sourceRow['expect_allowed'] ?? false) === true && in_array($user, ['guest', 'editor', 'admin'], true)) {
            $navCards[] = [
                'initial' => p116b5_initial($user),
                'label' => $label,
                'language' => $language,
                'rights' => p116b5_clean_rights($sourceRow),
                'route' => $route,
            ];
        }
        if ($kind === 'form' && (bool)($sourceRow['expect_allowed'] ?? false) === true) {
            $field = (string)($sourceRow['form_field'] ?? 'message');
            $formCards[] = [
                'title' => p116b5_form_title($route),
                'language' => $language,
                'field' => $field,
                'value' => p116b5_form_value($route, (string)($sourceRow['form_value'] ?? '')),
                'route' => $route,
            ];
        }
    }

    p116b5_assert($rows !== [], 'P116B5_SOURCE_SCENARIOS_EMPTY');

    return [
        'generated_at' => gmdate('c'),
        'score_status' => 'OK',
        'mail' => $mail,
        'rows' => $rows,
        'nav_cards' => $navCards,
        'form_cards' => $formCards,
    ];
}

function p116b5_smtp_send(string $to, string $from, string $subject, string $htmlBody): array
{
    $host = trim((string)getenv('OPUS_P112Q3B2_SMTP_HOST'));
    $port = (int)(getenv('OPUS_P112Q3B2_SMTP_PORT') ?: 1025);
    if ($host === '') { $host = '127.0.0.1'; }
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 5);
    if (!is_resource($socket)) {
        return ['status' => 'FAILED', 'reason' => 'SMTP_CONNECT_FAILED', 'host' => $host, 'port' => $port, 'error' => $errstr];
    }
    $read = static function () use ($socket): string { $line = fgets($socket); return is_string($line) ? $line : ''; };
    $write = static function (string $command) use ($socket): void { fwrite($socket, $command . "\r\n"); };
    $read();
    $write('HELO localhost'); $read();
    $write('MAIL FROM:<' . $from . '>'); $read();
    $write('RCPT TO:<' . $to . '>'); $read();
    $write('DATA'); $read();
    $write('From: ' . $from . "\r\n" . 'To: ' . $to . "\r\n" . 'Subject: ' . $subject . "\r\n" . 'MIME-Version: 1.0' . "\r\n" . 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n" . $htmlBody . "\r\n.");
    $dataResponse = $read();
    $write('QUIT');
    fclose($socket);
    if (str_starts_with($dataResponse, '250')) {
        return ['status' => 'DELIVERED_TO_MAILPIT', 'reason' => 'SMTP_ACCEPTED_BY_LOCAL_MAILPIT', 'host' => $host, 'port' => $port, 'to' => $to, 'from' => $from];
    }
    return ['status' => 'FAILED', 'reason' => 'SMTP_DATA_NOT_ACCEPTED', 'host' => $host, 'port' => $port, 'response' => trim($dataResponse), 'to' => $to, 'from' => $from];
}

p116b5_assert(class_exists(ScoreTemplateRenderer::class), 'P116B5_SCORE_TEMPLATE_RENDERER_NOT_LOADABLE');
p116b5_assert(is_dir($templateRoot), 'P116B5_TEMPLATE_ROOT_MISSING', $templateRoot);

p116b5_run_p112q3b2($root);
p116b5_assert(is_file($sourceJson), 'P116B5_SOURCE_JSON_MISSING', $sourceJson);
$payload = json_decode((string)file_get_contents($sourceJson), true);
p116b5_assert(is_array($payload), 'P116B5_SOURCE_JSON_INVALID', $sourceJson);
p116b5_assert(($payload['matrix_ok'] ?? false) === true, 'P116B5_SOURCE_MATRIX_NOT_OK');

$to = trim((string)getenv('OPUS_P112Q3B2_REPORT_EMAIL_TO'));
$from = trim((string)getenv('OPUS_P112Q3B2_REPORT_EMAIL_FROM'));
if ($from === '') { $from = 'opus-recipes@localhost'; }
p116b5_assert($to !== '', 'P116B5_REPORT_EMAIL_TO_MISSING');

$renderer = new ScoreTemplateRenderer($templateRoot);
$preMail = ['status' => 'NOT_SENT_YET', 'reason' => 'SCORE_MAIL_AFTER_RENDER', 'to' => $to, 'from' => $from];
$view = p116b5_view_data($payload, $preMail);
$html = $renderer->render('browser_report.score', $view);
$emailHtml = $renderer->render('email_report.score', $view);
$mail = p116b5_smtp_send($to, $from, 'Opus P116B5 live ScoreTemplate recipe report', $emailHtml);
$view = p116b5_view_data($payload, $mail);
$html = $renderer->render('browser_report.score', $view);
$emailHtml = $renderer->render('email_report.score', $view);

file_put_contents($htmlReport, $html);
file_put_contents($emailHtmlReport, $emailHtml);
file_put_contents($jsonReport, json_encode([
    'id' => 'P116B5_LEGACY_LIVE_SCORE_RECIPE',
    'status' => $mail['status'] === 'DELIVERED_TO_MAILPIT' ? 'OK' : 'FAILED',
    'generated_at' => gmdate('c'),
    'source' => $sourceJson,
    'mail' => $mail,
    'reports' => ['html' => $htmlReport, 'email_html' => $emailHtmlReport, 'json' => $jsonReport],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

p116b5_assert(($mail['status'] ?? '') === 'DELIVERED_TO_MAILPIT', 'P116B5_SCORE_MAIL_NOT_DELIVERED', json_encode($mail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

echo 'P116B5_LEGACY_LIVE_SCORE_RECIPE_OK' . PHP_EOL;
echo 'P116B5_REPORT=' . $jsonReport . PHP_EOL;
echo 'P116B5_HTML=' . $htmlReport . PHP_EOL;
