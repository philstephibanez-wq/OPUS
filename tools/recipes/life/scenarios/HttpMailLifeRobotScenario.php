<?php

declare(strict_types=1);

namespace Opus\Recipe\Life\Scenarios;

use ASAP\Recipe\RecipeAssertionFailedException;
use ASAP\Recipe\RecipeContext;
use ASAP\Recipe\RecipeInterface;

/**
 * PUBLIC LIFE RECIPE
 *
 * Role:
 *   Drive a real local HTTP + mail + LSTSAR lifecycle through PHP's built-in server.
 *
 * Responsibility:
 *   Exercise visible dashboard, public pages, ACL, forms, robot mailbox, and
 *   background LSTSAR scheduling through true HTTP GET/POST requests.
 *
 * Contract:
 *   No blank OK page. The browser page is a rich dashboard; pass/fail is based
 *   on deterministic HTTP/mail/LSTSAR assertions and report artifacts.
 */
final class HttpMailLifeRobotScenario implements RecipeInterface
{
    private const LOCALES = ['fr', 'en', 'es'];

    public function name(): string
    {
        return 'life_http_mail_robot';
    }

    /** @return string[] */
    public function run(RecipeContext $context): array
    {
        $sandbox = $context->sandbox('life_http_mail_robot');
        $this->prepareSandbox($context, $sandbox);
        $server = $this->startServer($context, $sandbox);

        try {
            $baseUrl = 'http://127.0.0.1:' . $server['port'];
            $this->waitForServer($baseUrl);
            $dashboardUrl = $baseUrl . '/fr/recipe-dashboard';
            $this->openBrowserIfRequested($dashboardUrl);

            $this->assertDashboard($context, $dashboardUrl);
            $this->movieBeat($sandbox, 'DASHBOARD_VISIBLE', 8);
            $this->assertPublicPages($context, $baseUrl);
            $this->movieBeat($sandbox, 'PUBLIC_ROUTES_FR_EN_ES', 24);
            $this->assertAclPages($context, $baseUrl);
            $this->movieBeat($sandbox, 'ACL_ANON_DENIED_ADMIN', 40);
            $this->assertForms($context, $baseUrl);
            $this->movieBeat($sandbox, 'FORM_VALID_INVALID_POST', 56);
            $this->assertMailLifecycle($context, $baseUrl, $sandbox);
            $this->movieBeat($sandbox, 'MAIL_ROBOT_RECEIVED', 72);
            $this->assertLstsarLifecycle($context, $baseUrl);
            $this->movieBeat($sandbox, 'LSTSAR_BACKGROUND_DONE', 88);
            $this->assertArtifacts($context, $sandbox);
            $this->movieBeat($sandbox, 'REPORTS_AND_ARTIFACTS_READY', 100);
            $this->holdBrowserForMovieIfRequested();
        } finally {
            $this->stopServer($server);
        }

        return [
            'OPUS_HTTP_DASHBOARD_VISIBLE_OK',
            'OPUS_HTTP_PUBLIC_ROUTES_OK',
            'OPUS_HTTP_ACL_OK',
            'OPUS_HTTP_FORM_OK',
            'OPUS_MAILPIT_OK',
            'OPUS_MAIL_ROBOT_OK',
            'OPUS_LIFE_HTTP_LSTSAR_OK',
            'OPUS_LIVE_MOVIE_DASHBOARD_OK',
            'OPUS_LIFE_HTTP_MAIL_ROBOT_OK',
        ];
    }

    private function prepareSandbox(RecipeContext $context, string $sandbox): void
    {
        foreach (['public', 'http', 'mail', 'dashboard', 'reports'] as $dir) {
            $path = $sandbox . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
                throw RecipeAssertionFailedException::because('OPUS_HTTP_MAIL_ROBOT_DIR_CREATE_FAILED', $path);
            }
        }

        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'routes.xml', <<<'XML'
<routes>
  <route name="dashboard" path="/recipe-dashboard"><target controllerClass="RecipeDashboardController" action="show" /></route>
  <route name="state" path="/recipe-state"><target controllerClass="RecipeDashboardController" action="state" /></route>
  <route name="public" path="/public"><target controllerClass="PublicController" action="show" /></route>
  <route name="admin" path="/admin"><target controllerClass="AdminController" action="show" /></route>
  <route name="form_submit" path="/form-submit"><target controllerClass="FormController" action="submit" /></route>
  <route name="mail_send" path="/mail-send"><target controllerClass="MailController" action="send" /></route>
  <route name="mail_inbox" path="/mail-inbox"><target controllerClass="MailController" action="inbox" /></route>
  <route name="lstsar_schedule" path="/lstsar-schedule"><target controllerClass="LstsarController" action="schedule" /></route>
  <route name="lstsar_status" path="/lstsar-status"><target controllerClass="LstsarController" action="status" /></route>
</routes>
XML);
        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'security.xml', '<security></security>');
        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'http' . DIRECTORY_SEPARATOR . 'transcript.jsonl', '');
        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'mail' . DIRECTORY_SEPARATOR . 'inbox.jsonl', '');
        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'state.json', json_encode([
            'marker' => 'OPUS_RECIPE_DASHBOARD_RICH_OK',
            'movie_marker' => 'OPUS_LIVE_MOVIE_DASHBOARD_OK',
            'run_id' => $context->runId(),
            'status' => 'RUNNING',
            'current_actor' => 'RecipeDirectorRobot',
            'current_action' => 'BOOTSTRAP',
            'progress' => 0,
            'scenarios' => ['HTTP', 'ACL', 'I18N', 'FORM', 'MAIL', 'LSTSAR'],
            'timeline' => [
                ['id' => 'dashboard', 'label' => 'Dashboard visible', 'status' => 'pending'],
                ['id' => 'public', 'label' => 'HTTP public FR/EN/ES', 'status' => 'pending'],
                ['id' => 'acl', 'label' => 'ACL anonymous/admin/denied', 'status' => 'pending'],
                ['id' => 'form', 'label' => 'POST form valid/invalid', 'status' => 'pending'],
                ['id' => 'mail', 'label' => 'Mailpit SMTP send + real inbox receive', 'status' => 'pending'],
                ['id' => 'lstsar', 'label' => 'LSTSAR background run', 'status' => 'pending'],
                ['id' => 'reports', 'label' => 'Reports and artifacts', 'status' => 'pending'],
            ],
            'events' => [['event' => 'movie:BOOTSTRAP', 'at' => date('c'), 'data' => ['progress' => 0]]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->writeRouter($context, $sandbox, $sandbox . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'router.php');
    }

    /** @return array{process:resource,pipes:array<int,mixed>,port:int,stdout:string,stderr:string} */
    private function startServer(RecipeContext $context, string $sandbox): array
    {
        $public = $sandbox . DIRECTORY_SEPARATOR . 'public';
        $router = $public . DIRECTORY_SEPARATOR . 'router.php';
        $stdout = $sandbox . DIRECTORY_SEPARATOR . 'http_server.stdout.log';
        $stderr = $sandbox . DIRECTORY_SEPARATOR . 'http_server.stderr.log';

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $port = random_int(49152, 65000);
            $command = $this->quote(PHP_BINARY) . ' -d display_errors=1 -S 127.0.0.1:' . $port
                . ' -t ' . $this->quote($public) . ' ' . $this->quote($router);
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'ab'], 2 => ['file', $stderr, 'ab']], $pipes, $context->rootPath());
            if (!is_resource($process)) {
                continue;
            }
            try {
                $this->waitForServer('http://127.0.0.1:' . $port);
                return ['process' => $process, 'pipes' => $pipes, 'port' => $port, 'stdout' => $stdout, 'stderr' => $stderr];
            } catch (\Throwable) {
                $this->stopServer(['process' => $process, 'pipes' => $pipes, 'port' => $port, 'stdout' => $stdout, 'stderr' => $stderr]);
            }
        }

        throw RecipeAssertionFailedException::because('OPUS_HTTP_MAIL_ROBOT_SERVER_START_FAILED', $stderr);
    }

    private function waitForServer(string $baseUrl): void
    {
        $deadline = microtime(true) + 10.0;
        do {
            $response = $this->request($baseUrl . '/health');
            if ($response['status'] === 200 && str_contains($response['body'], 'OPUS_HTTP_MAIL_ROBOT_HEALTH_OK')) {
                return;
            }
            usleep(150000);
        } while (microtime(true) < $deadline);
        throw RecipeAssertionFailedException::because('OPUS_HTTP_MAIL_ROBOT_HEALTH_TIMEOUT', $baseUrl);
    }

    private function assertDashboard(RecipeContext $context, string $dashboardUrl): void
    {
        $response = $this->request($dashboardUrl);
        $context->assert($response['status'] === 200, 'OPUS_HTTP_DASHBOARD_STATUS_INVALID', (string)$response['status']);
        foreach (['OPUS_RECIPE_DASHBOARD_RICH_OK', 'OPUS_LIVE_MOVIE_DASHBOARD_OK', 'Robot actors', 'HTTP transcript', 'MailRobot', 'OPUS_REAL_MAILPIT_SMTP_OK', 'LSTSAR', 'Scenario matrix', 'Live timeline'] as $needle) {
            $context->assert(str_contains($response['body'], $needle), 'OPUS_HTTP_DASHBOARD_CONTENT_MISSING', $needle);
        }
    }

    private function assertPublicPages(RecipeContext $context, string $baseUrl): void
    {
        $expected = ['fr' => 'Livre de référence ASAP', 'en' => 'Opus Reference Book', 'es' => 'Libro de referencia ASAP'];
        foreach (self::LOCALES as $locale) {
            $response = $this->request($baseUrl . '/' . $locale . '/public');
            $context->assert($response['status'] === 200, 'OPUS_HTTP_PUBLIC_STATUS_INVALID', $locale . ':' . (string)$response['status']);
            $context->assert(str_contains($response['body'], 'data-locale="' . $locale . '"'), 'OPUS_HTTP_PUBLIC_LOCALE_MISSING', $locale);
            $context->assert(str_contains($response['body'], $expected[$locale]), 'OPUS_HTTP_PUBLIC_I18N_MISSING', $locale);
        }
    }

    private function assertAclPages(RecipeContext $context, string $baseUrl): void
    {
        $anonymous = $this->request($baseUrl . '/fr/admin');
        $context->assert($anonymous['status'] === 403, 'OPUS_HTTP_ACL_ANONYMOUS_ADMIN_NOT_FORBIDDEN', (string)$anonymous['status']);
        $denied = $this->request($baseUrl . '/fr/admin', 'GET', ['X-ASAP-Role' => 'denied']);
        $context->assert($denied['status'] === 403, 'OPUS_HTTP_ACL_DENIED_ADMIN_NOT_FORBIDDEN', (string)$denied['status']);
        $admin = $this->request($baseUrl . '/fr/admin', 'GET', ['X-ASAP-Role' => 'admin']);
        $context->assert($admin['status'] === 200, 'OPUS_HTTP_ACL_ADMIN_NOT_ALLOWED', (string)$admin['status']);
        $context->assert(str_contains($admin['body'], 'data-role="admin"'), 'OPUS_HTTP_ACL_ADMIN_ROLE_MISSING');
    }

    private function assertForms(RecipeContext $context, string $baseUrl): void
    {
        $valid = $this->request($baseUrl . '/fr/form-submit', 'POST', ['X-ASAP-Role' => 'admin'], 'name=Alice&email=alice%40example.org');
        $context->assert($valid['status'] === 200, 'OPUS_HTTP_FORM_VALID_STATUS_INVALID', (string)$valid['status']);
        $context->assert(str_contains($valid['body'], 'OPUS_FORM_VALID_OK'), 'OPUS_HTTP_FORM_VALID_MARKER_MISSING');
        $invalid = $this->request($baseUrl . '/fr/form-submit', 'POST', ['X-ASAP-Role' => 'admin'], 'name=&email=bad-address');
        $context->assert($invalid['status'] === 422, 'OPUS_HTTP_FORM_INVALID_STATUS_INVALID', (string)$invalid['status']);
        $context->assert(str_contains($invalid['body'], 'OPUS_FORM_INVALID_OK'), 'OPUS_HTTP_FORM_INVALID_MARKER_MISSING');
    }

    private function assertMailLifecycle(RecipeContext $context, string $baseUrl, string $sandbox): void
    {
        $subject = 'Opus Recipe Mail ' . $context->runId();
        $body = 'OPUS_MAIL_BODY_OK ' . $context->runId();
        $post = http_build_query(['to' => 'robot@example.org', 'subject' => $subject, 'body' => $body], '', '&', PHP_QUERY_RFC3986);
        $send = $this->request($baseUrl . '/fr/mail-send', 'POST', ['X-ASAP-Role' => 'admin'], $post);
        $context->assert($send['status'] === 202, 'OPUS_MAIL_SEND_STATUS_INVALID', (string)$send['status'] . ' :: ' . $send['body']);
        $context->assert(str_contains($send['body'], 'OPUS_MAIL_SEND_OK'), 'OPUS_MAIL_SEND_MARKER_MISSING');

        $deadline = microtime(true) + 10.0;
        $inbox = ['status' => 0, 'body' => ''];
        do {
            $inbox = $this->request($baseUrl . '/fr/mail-inbox?subject=' . rawurlencode($subject), 'GET', ['X-ASAP-Role' => 'admin']);
            if ($inbox['status'] === 200 && str_contains($inbox['body'], 'OPUS_MAILPIT_RECEIVED_OK')) {
                break;
            }
            usleep(250000);
        } while (microtime(true) < $deadline);

        $context->assert($inbox['status'] === 200, 'OPUS_MAIL_INBOX_STATUS_INVALID', (string)$inbox['status'] . ' :: ' . $inbox['body']);
        foreach (['OPUS_MAILPIT_RECEIVED_OK', 'robot@example.org', $subject, $body] as $needle) {
            $context->assert(str_contains($inbox['body'], $needle), 'OPUS_MAILPIT_CONTENT_MISSING', $needle);
        }
        $context->assert(is_file($sandbox . DIRECTORY_SEPARATOR . 'mail' . DIRECTORY_SEPARATOR . 'inbox.jsonl'), 'OPUS_MAILPIT_INBOX_ARTIFACT_MISSING');
    }

    private function assertLstsarLifecycle(RecipeContext $context, string $baseUrl): void
    {
        $started = microtime(true);
        $scheduled = $this->request($baseUrl . '/fr/lstsar-schedule', 'POST', ['X-ASAP-Role' => 'admin'], 'schedule=1');
        $elapsed = microtime(true) - $started;
        $context->assert($scheduled['status'] === 202, 'OPUS_HTTP_LSTSAR_SCHEDULE_STATUS_INVALID', (string)$scheduled['status']);
        $context->assert($elapsed < 2.0, 'OPUS_HTTP_LSTSAR_SCHEDULE_BLOCKED', number_format($elapsed, 4, '.', '') . 's');
        $payload = json_decode($scheduled['body'], true);
        $context->assert(is_array($payload), 'OPUS_HTTP_LSTSAR_SCHEDULE_JSON_INVALID');
        $context->assert(($payload['status'] ?? null) === \ASAP\Lstsa\LstsaRunStatus::PENDING, 'OPUS_HTTP_LSTSAR_NOT_PENDING');
        $runId = (string)($payload['run_id'] ?? '');
        $targetDb = (string)($payload['target_db'] ?? '');
        $context->assert($runId !== '' && $targetDb !== '', 'OPUS_HTTP_LSTSAR_PAYLOAD_INCOMPLETE');

        $store = new \ASAP\Lstsa\LstsaRunStore($context->rootPath());
        $done = (new \ASAP\Lstsa\LstsaRunner($store))->runOnce('http_mail_life_robot_background_runner');
        $context->assert(is_array($done) && ($done['run_id'] ?? null) === $runId, 'OPUS_HTTP_LSTSAR_RUN_ID_MISMATCH');
        $context->assert(($done['status'] ?? null) === \ASAP\Lstsa\LstsaRunStatus::DONE, 'OPUS_HTTP_LSTSAR_NOT_DONE');

        $status = $this->request($baseUrl . '/fr/lstsar-status?run_id=' . rawurlencode($runId), 'GET', ['X-ASAP-Role' => 'admin']);
        $context->assert($status['status'] === 200, 'OPUS_HTTP_LSTSAR_STATUS_HTTP_INVALID', (string)$status['status']);
        $statusPayload = json_decode($status['body'], true);
        $context->assert(is_array($statusPayload) && ($statusPayload['status'] ?? null) === \ASAP\Lstsa\LstsaRunStatus::DONE, 'OPUS_HTTP_LSTSAR_STATUS_NOT_DONE');
        $target = new \PDO('sqlite:' . $targetDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $count = (int)$target->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $context->assert($count === 2, 'OPUS_HTTP_LSTSAR_TARGET_ROW_COUNT_INVALID', (string)$count);
    }

    private function assertArtifacts(RecipeContext $context, string $sandbox): void
    {
        foreach (['http/transcript.jsonl', 'mail/inbox.jsonl', 'dashboard/state.json'] as $relative) {
            $path = $sandbox . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $context->assert(is_file($path) && filesize($path) > 0, 'OPUS_HTTP_MAIL_LIFE_ARTIFACT_MISSING_OR_EMPTY', $relative);
        }
        file_put_contents($sandbox . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'http_mail_life_summary.md', '# HTTP Mail Life Robot' . PHP_EOL . PHP_EOL . 'OPUS_LIVE_MOVIE_DASHBOARD_OK' . PHP_EOL . 'OPUS_LIFE_HTTP_MAIL_ROBOT_OK' . PHP_EOL);
        $context->diagnostic('HTTP_MAIL_LIFE_SANDBOX=' . $sandbox);
    }

    private function movieBeat(string $sandbox, string $action, int $progress): void
    {
        $statePath = $sandbox . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'state.json';
        $state = is_file($statePath) ? json_decode((string)file_get_contents($statePath), true) : [];
        if (!is_array($state)) {
            $state = [];
        }
        $state['status'] = $progress >= 100 ? 'DONE' : 'RUNNING';
        $state['current_actor'] = $this->actorForAction($action);
        $state['current_action'] = $action;
        $state['progress'] = $progress;
        $state['events'][] = ['event' => 'movie:' . $action, 'at' => date('c'), 'data' => ['progress' => $progress]];
        if (isset($state['timeline']) && is_array($state['timeline'])) {
            foreach ($state['timeline'] as $index => $step) {
                if (!is_array($step)) {
                    continue;
                }
                $stepProgress = (int)floor(($index + 1) * (100 / max(1, count($state['timeline']))));
                if ($progress >= $stepProgress) {
                    $state['timeline'][$index]['status'] = 'done';
                } elseif (($state['timeline'][$index]['status'] ?? null) !== 'done') {
                    $state['timeline'][$index]['status'] = $stepProgress - $progress <= 16 ? 'running' : 'pending';
                }
            }
        }
        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ((string)getenv('OPUS_RECIPE_OPEN_BROWSER') === '1') {
            usleep(850000);
        }
    }

    private function actorForAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'PUBLIC') => 'AnonymousVisitor',
            str_contains($action, 'ACL') => 'AdminUser / DeniedUser',
            str_contains($action, 'FORM') => 'AdminUser',
            str_contains($action, 'MAIL') => 'MailRobot',
            str_contains($action, 'LSTSAR') => 'SchedulerRobot / BackgroundRunnerRobot',
            str_contains($action, 'REPORT') => 'MaintenanceRobot',
            default => 'RecipeDirectorRobot',
        };
    }

    private function holdBrowserForMovieIfRequested(): void
    {
        if ((string)getenv('OPUS_RECIPE_OPEN_BROWSER') !== '1') {
            return;
        }
        sleep((int)((string)getenv('OPUS_RECIPE_BROWSER_FINAL_HOLD_SECONDS') ?: '8'));
    }

    /** @return array{status:int,body:string,headers:string[]} */
    private function request(string $url, string $method = 'GET', array $headers = [], string $body = ''): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        if ($method === 'POST') {
            $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $streamContext = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headerLines), 'content' => $body, 'ignore_errors' => true, 'timeout' => 5]]);
        $responseBody = @file_get_contents($url, false, $streamContext);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                $status = (int)$matches[1];
                break;
            }
        }
        return ['status' => $status, 'body' => is_string($responseBody) ? $responseBody : '', 'headers' => $responseHeaders];
    }

    private function openBrowserIfRequested(string $url): void
    {
        if ((string)getenv('OPUS_RECIPE_OPEN_BROWSER') !== '1') {
            return;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            @pclose(@popen('start "" ' . escapeshellarg($url), 'r'));
            sleep((int)((string)getenv('OPUS_RECIPE_BROWSER_HOLD_SECONDS') ?: '1'));
            return;
        }
        $opener = trim((string)@shell_exec('command -v xdg-open 2>/dev/null'));
        if ($opener !== '') {
            @pclose(@popen(escapeshellarg($opener) . ' ' . escapeshellarg($url) . ' >/dev/null 2>&1 &', 'r'));
            sleep(2);
        }
    }

    /** @param array{process:resource,pipes:array<int,mixed>,port:int,stdout:string,stderr:string} $server */
    private function stopServer(array $server): void
    {
        foreach ($server['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        if (is_resource($server['process'])) {
            @proc_terminate($server['process']);
            @proc_close($server['process']);
        }
    }

    private function quote(string $value): string
    {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }

    private function writeRouter(RecipeContext $context, string $sandbox, string $routerPath): void
    {
        $rootExport = var_export($context->rootPath(), true);
        $sandboxExport = var_export($sandbox, true);
        $router = <<<'ROUTER'
<?php

declare(strict_types=1);

$projectRoot = __PROJECT_ROOT__;
$sandbox = __SANDBOX__;

spl_autoload_register(static function (string $class) use ($projectRoot): void {
    $prefix = 'Opus\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    $path = $projectRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) { require_once $path; }
});

function robot_path(string $relative): string { global $sandbox; return $sandbox . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative); }
function robot_jsonl(string $relative, array $payload): void { file_put_contents(robot_path($relative), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND); }
function robot_jsonl_read(string $relative): array { $path = robot_path($relative); if (!is_file($path)) { return []; } $rows = []; foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) { $decoded = json_decode($line, true); if (is_array($decoded)) { $rows[] = $decoded; } } return $rows; }
function robot_send(\ASAP\Http\Response $response): void { $response->send(); }
function robot_update_state(string $event, array $data = []): void {
    $path = robot_path('dashboard/state.json');
    $state = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
    if (!is_array($state)) { $state = []; }
    $state['current_action'] = $event;
    $state['current_actor'] = $data['role'] ?? $state['current_actor'] ?? 'RobotActor';
    $state['last_event_at'] = date('c');
    $state['events'][] = ['event' => $event, 'at' => date('c'), 'data' => $data];
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function robot_role(): string { return strtolower((string)($_SERVER['HTTP_X_OPUS_ROLE'] ?? 'anonymous')); }
function robot_request_log(string $route, int $status, array $extra = []): void {
    robot_jsonl('http/transcript.jsonl', array_merge(['route' => $route, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET', 'uri' => $_SERVER['REQUEST_URI'] ?? '/', 'role' => robot_role(), 'status' => $status, 'at' => date('c')], $extra));
    robot_update_state('http:' . $route, ['status' => $status] + $extra);
}
function robot_mailpit_http_base(): string { return rtrim((string)(getenv('OPUS_RECIPE_MAILPIT_HTTP') ?: 'http://127.0.0.1:8025'), '/'); }
function robot_mailpit_smtp_host(): string { return (string)(getenv('OPUS_RECIPE_MAILPIT_SMTP_HOST') ?: '127.0.0.1'); }
function robot_mailpit_smtp_port(): int { return (int)((string)(getenv('OPUS_RECIPE_MAILPIT_SMTP_PORT') ?: '1025')); }
function robot_http_json(string $url): array {
    $body = @file_get_contents($url);
    if (!is_string($body)) { return ['ok' => false, 'error' => 'HTTP_READ_FAILED', 'url' => $url]; }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'JSON_DECODE_FAILED', 'body' => $body];
}
function robot_smtp_expect($socket, string $expected, string $step): void {
    $line = fgets($socket, 4096);
    if (!is_string($line) || !str_starts_with($line, $expected)) { throw new RuntimeException('OPUS_MAILPIT_SMTP_UNEXPECTED_' . $step . '=' . trim((string)$line)); }
}
function robot_smtp_cmd($socket, string $command, string $expected, string $step): void {
    fwrite($socket, $command . "\r\n");
    robot_smtp_expect($socket, $expected, $step);
}
function robot_mailpit_smtp_send(string $to, string $subject, string $body): void {
    $host = robot_mailpit_smtp_host(); $port = robot_mailpit_smtp_port();
    $socket = @fsockopen($host, $port, $errno, $errstr, 5.0);
    if (!is_resource($socket)) { throw new RuntimeException('OPUS_MAILPIT_SMTP_CONNECT_FAILED=' . $host . ':' . $port . ' :: ' . $errstr); }
    stream_set_timeout($socket, 5);
    robot_smtp_expect($socket, '220', 'BANNER');
    robot_smtp_cmd($socket, 'HELO opus-recipe.local', '250', 'HELO');
    robot_smtp_cmd($socket, 'MAIL FROM:<opus-recipe@example.org>', '250', 'MAIL_FROM');
    robot_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', '250', 'RCPT_TO');
    robot_smtp_cmd($socket, 'DATA', '354', 'DATA');
    $message = "From: Opus Recipe <opus-recipe@example.org>\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $subject . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
        . str_replace(["\r", "\n."], ["", "\n.."], $body) . "\r\n.";
    robot_smtp_cmd($socket, $message, '250', 'BODY');
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
}
function robot_mailpit_messages(): array {
    $json = robot_http_json(robot_mailpit_http_base() . '/api/v1/messages?limit=200');
    if (isset($json['messages']) && is_array($json['messages'])) { return $json['messages']; }
    if (isset($json['Messages']) && is_array($json['Messages'])) { return $json['Messages']; }
    return [];
}
function robot_mailpit_text_for(array $message): string {
    $id = (string)($message['ID'] ?? $message['Id'] ?? $message['id'] ?? '');
    if ($id === '') { return json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''; }
    $detail = robot_http_json(robot_mailpit_http_base() . '/api/v1/message/' . rawurlencode($id));
    return json_encode([$message, $detail], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}
function robot_mailpit_find_subject(string $subject): ?array {
    foreach (robot_mailpit_messages() as $message) {
        if (!is_array($message)) { continue; }
        $raw = robot_mailpit_text_for($message);
        if (str_contains($raw, $subject)) { return ['summary' => $message, 'raw' => $raw]; }
    }
    return null;
}

try {
    $request = \ASAP\Http\Request::fromGlobals();
    if ($request->path === '/health') { robot_send(\ASAP\Http\Response::json(['ok' => true, 'marker' => 'OPUS_HTTP_MAIL_ROBOT_HEALTH_OK'])); return; }

    $parts = explode('/', trim($request->path, '/'));
    $locale = strtolower((string)($parts[0] ?? ''));
    if (!in_array($locale, ['fr', 'en', 'es'], true)) { robot_send(\ASAP\Http\Response::html('OPUS_HTTP_MAIL_ROBOT_LOCALE_NOT_FOUND', 404)); return; }

    $site = new \ASAP\Site\SiteDefinition('recipe_' . $locale, '/' . $locale, robot_path('routes.xml'), robot_path('security.xml'));
    $match = \ASAP\Routing\Router::fromXml(robot_path('routes.xml'))->match($request, $site);
    $role = robot_role();

    $acl = new \ASAP\Acl\AccessControl(
        [new \ASAP\Acl\RoleDefinition('anonymous'), new \ASAP\Acl\RoleDefinition('admin'), new \ASAP\Acl\RoleDefinition('denied')],
        [new \ASAP\Acl\ResourceDefinition('public'), new \ASAP\Acl\ResourceDefinition('admin'), new \ASAP\Acl\ResourceDefinition('form'), new \ASAP\Acl\ResourceDefinition('mail'), new \ASAP\Acl\ResourceDefinition('lstsa')],
        [new \ASAP\Acl\PrivilegeDefinition('view'), new \ASAP\Acl\PrivilegeDefinition('submit'), new \ASAP\Acl\PrivilegeDefinition('send'), new \ASAP\Acl\PrivilegeDefinition('schedule'), new \ASAP\Acl\PrivilegeDefinition('status')],
        [
            new \ASAP\Acl\AccessRule('anonymous', 'public', 'view', true),
            new \ASAP\Acl\AccessRule('admin', 'public', 'view', true),
            new \ASAP\Acl\AccessRule('admin', 'admin', 'view', true),
            new \ASAP\Acl\AccessRule('admin', 'form', 'submit', true),
            new \ASAP\Acl\AccessRule('admin', 'mail', 'send', true),
            new \ASAP\Acl\AccessRule('admin', 'mail', 'view', true),
            new \ASAP\Acl\AccessRule('admin', 'lstsa', 'schedule', true),
            new \ASAP\Acl\AccessRule('admin', 'lstsa', 'status', true),
        ]
    );
    $resource = match ($match->name) { 'dashboard', 'state', 'public' => 'public', 'admin' => 'admin', 'form_submit' => 'form', 'mail_send', 'mail_inbox' => 'mail', default => 'lstsa' };
    $privilege = match ($match->name) { 'form_submit' => 'submit', 'mail_send' => 'send', 'mail_inbox', 'admin', 'dashboard', 'state', 'public' => 'view', 'lstsar_schedule' => 'schedule', default => 'status' };
    if (!$acl->decide($role, $resource, $privilege)->allowed()) { robot_request_log($match->name, 403); robot_send(\ASAP\Http\Response::html('OPUS_HTTP_ROBOT_FORBIDDEN', 403)); return; }

    if ($match->name === 'dashboard') {
        $state = is_file(robot_path('dashboard/state.json')) ? (string)file_get_contents(robot_path('dashboard/state.json')) : '{}';
        $html = <<<'HTML'
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Opus Global Life Recipe Movie</title>
<style>
:root{--bg:#08111f;--panel:#0f172a;--line:#334155;--text:#e5e7eb;--muted:#94a3b8;--ok:#86efac;--run:#60a5fa;--warn:#facc15;--fail:#fb7185}
*{box-sizing:border-box}body{font-family:Arial,sans-serif;background:radial-gradient(circle at top left,#1e3a8a 0,#08111f 36%,#020617 100%);color:var(--text);margin:0;overflow-x:hidden}
header{padding:24px 28px;background:rgba(15,23,42,.92);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5;box-shadow:0 12px 40px rgba(0,0,0,.35)}
h1{margin:0 0 8px 0;font-size:32px;letter-spacing:.06em}.marker{font-family:Consolas,monospace;color:var(--ok);font-weight:700}.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px;padding:20px}.card{background:rgba(15,23,42,.88);border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.22)}
.card h2{margin:0 0 12px 0}.progress{height:18px;background:#020617;border:1px solid var(--line);border-radius:999px;overflow:hidden}.bar{height:100%;width:0;background:linear-gradient(90deg,#22c55e,#60a5fa,#a78bfa);transition:width .35s ease}.stage{display:flex;gap:10px;align-items:center;margin:9px 0;padding:9px;border:1px solid #1e293b;border-radius:12px;background:#08111f}.dot{width:14px;height:14px;border-radius:50%;background:#475569}.stage.running .dot{background:var(--run);box-shadow:0 0 18px var(--run);animation:pulse .55s infinite alternate}.stage.done .dot{background:var(--ok)}.stage.pending{opacity:.55}
.log{height:260px;overflow:auto;font-family:Consolas,monospace;font-size:13px;background:#020617;border:1px solid #1e293b;border-radius:12px;padding:12px;white-space:pre-wrap}.actor{font-size:22px;color:var(--run);font-weight:700}.action{font-family:Consolas,monospace;color:var(--ok)}.ticker{display:inline-block;min-width:44px;color:var(--warn)}
.scan{position:fixed;inset:0;pointer-events:none;background:linear-gradient(180deg,transparent,rgba(96,165,250,.06),transparent);height:140px;animation:scan 2s linear infinite;z-index:4}.pill{display:inline-block;margin:4px 7px 4px 0;padding:7px 10px;border-radius:999px;border:1px solid #475569;background:#0b1220}.ok{color:var(--ok)}code{color:#93c5fd}pre{margin:0}.full{grid-column:1/-1}
@keyframes pulse{from{transform:scale(.8);opacity:.6}to{transform:scale(1.25);opacity:1}}@keyframes scan{from{top:-160px}to{top:100%}}@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
<script>
let tick=0;
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function renderTimeline(items){return (items||[]).map(s=>`<div class="stage ${esc(s.status||'pending')}"><span class="dot"></span><strong>${esc(s.label||s.id)}</strong><span>${esc(s.status||'pending')}</span></div>`).join('');}
function renderRows(rows){return (rows||[]).slice(-14).reverse().map(r=>`[${esc(r.at||'')}] ${esc(r.method||'')} ${esc(r.uri||r.route||'')} role=${esc(r.role||'')} status=${esc(r.status||'')} ${esc(r.run_id||'')}`).join('\n');}
async function refresh(){tick++;document.getElementById('ticker').textContent='●'.repeat((tick%4)+1);try{const x=await fetch('/__LOCALE__/recipe-state?ts='+Date.now());const j=await x.json();const s=j.state||j;document.getElementById('actor').textContent=s.current_actor||'RobotActor';document.getElementById('action').textContent=s.current_action||'BOOT';document.getElementById('progressText').textContent=(s.progress||0)+'%';document.getElementById('bar').style.width=(s.progress||0)+'%';document.getElementById('timeline').innerHTML=renderTimeline(s.timeline||[]);document.getElementById('httpLog').textContent=renderRows(j.http_transcript||[]);document.getElementById('mailLog').textContent=renderRows(j.mail_inbox||[])+'\n--- MAILPIT API ---\n'+renderRows(j.mailpit_messages||[]);document.getElementById('eventLog').textContent=(s.events||[]).slice(-14).reverse().map(e=>`[${esc(e.at)}] ${esc(e.event)} ${JSON.stringify(e.data||{})}`).join('\n');document.getElementById('raw').textContent=JSON.stringify(j,null,2);}catch(e){document.getElementById('eventLog').textContent='waiting server '+e;}}
setInterval(refresh,350);window.onload=refresh;
</script>
</head>
<body>
<div class="scan"></div>
<header><h1>Opus Global Life Recipe Movie Dashboard</h1><p><strong class="marker">OPUS_RECIPE_DASHBOARD_RICH_OK</strong> · <strong class="marker">OPUS_LIVE_MOVIE_DASHBOARD_OK</strong> <span id="ticker" class="ticker">●</span></p></header>
<section class="grid">
<div class="card"><h2>Live director</h2><p>Actor: <span id="actor" class="actor">RecipeDirectorRobot</span></p><p>Action: <span id="action" class="action">BOOTSTRAP</span></p><div class="progress"><div id="bar" class="bar"></div></div><p>Progress: <strong id="progressText">0%</strong></p></div>
<div class="card"><h2>Robot actors</h2><span class="pill">AnonymousVisitor</span><span class="pill">AdminUser</span><span class="pill">DeniedUser</span><span class="pill">MailRobot</span><span class="pill">SchedulerRobot</span><span class="pill">BackgroundRunnerRobot</span><span class="pill">MaintenanceRobot</span></div>
<div class="card"><h2>Scenario matrix</h2><ul><li>HTTP public FR/EN/ES</li><li>ACL anonymous/admin/denied</li><li>Form valid/invalid POST</li><li>MailRobot send/receive</li><li>LSTSAR schedule/background/status</li></ul></div>
<div class="card"><h2>Live timeline</h2><div id="timeline"></div></div>
<div class="card"><h2>HTTP transcript</h2><div id="httpLog" class="log">waiting...</div></div>
<div class="card"><h2>MailRobot / Mailpit réel</h2><p><strong class="marker">OPUS_REAL_MAILPIT_SMTP_OK</strong></p><p>SMTP <code>127.0.0.1:1025</code> · API <code>127.0.0.1:8025</code></p><div id="mailLog" class="log">waiting...</div></div>
<div class="card"><h2>LSTSAR</h2><p>Queue through HTTP, execution by background runner only.</p><p class="ok">Queue → Pending → Runner → Done/Fail visible in event stream.</p></div>
<div class="card"><h2>Event stream</h2><div id="eventLog" class="log">waiting...</div></div>
<div class="card full"><h2>Raw live state</h2><pre id="raw" class="log">__STATE__</pre></div>
</section>
</body>
</html>
HTML;
        $html = str_replace(['__LOCALE__', '__STATE__'], [$locale, htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')], $html);
        robot_request_log('dashboard', 200);
        robot_send(\ASAP\Http\Response::html($html)); return;
    }

    if ($match->name === 'state') {
        $state = json_decode((string)file_get_contents(robot_path('dashboard/state.json')), true);
        if (!is_array($state)) { $state = []; }
        robot_request_log('state', 200);
        robot_send(\ASAP\Http\Response::json([
            'state' => $state,
            'http_transcript' => robot_jsonl_read('http/transcript.jsonl'),
            'mail_inbox' => robot_jsonl_read('mail/inbox.jsonl'),
            'mailpit_messages' => robot_mailpit_messages(),
        ])); return;
    }

    if ($match->name === 'public' || $match->name === 'admin') {
        $i18n = new \ASAP\I18n\I18n($projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'i18n', $locale);
        $title = $i18n->translate('opus.reference');
        $html = '<!doctype html><html lang="' . htmlspecialchars($locale) . '"><body><main data-route="' . htmlspecialchars($match->name) . '" data-locale="' . htmlspecialchars($locale) . '" data-role="' . htmlspecialchars($role) . '"><h1>' . htmlspecialchars($title) . '</h1><p>OPUS_HTTP_REAL_PAGE_OK</p></main></body></html>';
        robot_request_log($match->name, 200, ['locale' => $locale, 'role' => $role]); robot_send(\ASAP\Http\Response::html($html)); return;
    }

    if ($match->name === 'form_submit') {
        parse_str((string)file_get_contents('php://input'), $input);
        $definition = new \ASAP\Form\FormDefinition('recipe_contact', [new \ASAP\Form\FormField('name', 'text', true), new \ASAP\Form\FormField('email', 'email', true)]);
        $result = (new \ASAP\Form\FormValidator())->validate(new \ASAP\Form\SubmittedForm($definition, array_map('strval', $input)));
        if (!$result->isValid()) { robot_request_log('form_submit', 422); robot_send(\ASAP\Http\Response::json(['ok' => false, 'marker' => 'OPUS_FORM_INVALID_OK'], 422)); return; }
        robot_request_log('form_submit', 200); robot_send(\ASAP\Http\Response::json(['ok' => true, 'marker' => 'OPUS_FORM_VALID_OK'])); return;
    }

    if ($match->name === 'mail_send') {
        parse_str((string)file_get_contents('php://input'), $input);
        $mail = new \ASAP\Mail\Mail((string)($input['to'] ?? ''), (string)($input['subject'] ?? ''), (string)($input['body'] ?? ''));
        robot_mailpit_smtp_send($mail->to, $mail->subject, $mail->body);
        robot_jsonl('mail/inbox.jsonl', ['transport' => 'mailpit_smtp', 'to' => $mail->to, 'subject' => $mail->subject, 'body' => $mail->body, 'sent_at' => date('c'), 'marker' => 'OPUS_REAL_MAILPIT_SMTP_OK']);
        robot_request_log('mail_send', 202, ['transport' => 'mailpit_smtp', 'to' => $mail->to, 'subject' => $mail->subject]); robot_send(\ASAP\Http\Response::json(['ok' => true, 'marker' => 'OPUS_MAIL_SEND_OK', 'transport' => 'mailpit_smtp'], 202)); return;
    }

    if ($match->name === 'mail_inbox') {
        $subject = (string)($_GET['subject'] ?? '');
        $found = $subject !== '' ? robot_mailpit_find_subject($subject) : null;
        if ($subject !== '' && $found === null) { robot_request_log('mail_inbox', 404, ['subject' => $subject]); robot_send(\ASAP\Http\Response::json(['ok' => false, 'marker' => 'OPUS_MAILPIT_NOT_RECEIVED_YET', 'subject' => $subject], 404)); return; }
        if ($found !== null) { robot_jsonl('mail/inbox.jsonl', ['transport' => 'mailpit_api', 'subject' => $subject, 'received_at' => date('c'), 'marker' => 'OPUS_MAILPIT_RECEIVED_OK']); }
        robot_request_log('mail_inbox', 200, ['subject' => $subject, 'transport' => 'mailpit_api']); robot_send(\ASAP\Http\Response::json(['ok' => true, 'marker' => 'OPUS_MAILPIT_RECEIVED_OK', 'message' => $found, 'mailpit_messages' => robot_mailpit_messages()])); return;
    }

    if ($match->name === 'lstsar_schedule') {
        $sourceDb = robot_path('http_lstsar_source_' . bin2hex(random_bytes(4)) . '.sqlite');
        $targetDb = robot_path('http_lstsar_target_' . bin2hex(random_bytes(4)) . '.sqlite');
        $source = new \PDO('sqlite:' . $sourceDb, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $source->exec('CREATE TABLE raw_users (email TEXT NOT NULL, status TEXT NOT NULL)');
        $source->exec("INSERT INTO raw_users (email, status) VALUES ('Http.One@Example.Org', 'active'), ('Http.Two@Example.Org', 'inactive')");
        $source = null;
        $run = (new \ASAP\Lstsa\LstsaScheduler(new \ASAP\Lstsa\LstsaRunStore($projectRoot)))->enqueueDatabaseStagingSmokeRun($sourceDb, $targetDb);
        robot_request_log('lstsar_schedule', 202, ['run_id' => $run['run_id']]); robot_send(\ASAP\Http\Response::json(['ok' => true, 'run_id' => $run['run_id'], 'status' => $run['status'], 'source_db' => $sourceDb, 'target_db' => $targetDb], 202)); return;
    }

    if ($match->name === 'lstsar_status') {
        $runId = (string)($_GET['run_id'] ?? '');
        if ($runId === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $runId) !== 1) { robot_send(\ASAP\Http\Response::json(['ok' => false, 'error' => 'OPUS_RUN_ID_INVALID'], 400)); return; }
        $run = (new \ASAP\Lstsa\LstsaRunStore($projectRoot))->readRun($runId);
        robot_request_log('lstsar_status', 200, ['run_id' => $runId, 'status' => $run['status']]); robot_send(\ASAP\Http\Response::json(['ok' => true, 'run_id' => $run['run_id'], 'status' => $run['status'], 'current_step' => $run['current_step']])); return;
    }

    robot_send(\ASAP\Http\Response::html('OPUS_HTTP_MAIL_ROBOT_ROUTE_UNHANDLED', 500));
} catch (\Throwable $exception) {
    robot_jsonl('http/transcript.jsonl', ['route' => 'exception', 'status' => 500, 'error' => $exception->getMessage(), 'at' => date('c')]);
    robot_send(\ASAP\Http\Response::json(['ok' => false, 'error' => $exception->getMessage()], 500));
}
ROUTER;
        $router = str_replace(['__PROJECT_ROOT__', '__SANDBOX__'], [$rootExport, $sandboxExport], $router);
        file_put_contents($routerPath, $router);
    }
}
