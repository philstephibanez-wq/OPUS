<?php
declare(strict_types=1);

use Opus\Api\Rest\RestClient;
use Opus\Http\Response;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Csrf\CsrfTokenManager;
use Opus\Security\Csrf\CsrfTokenManagerInterface;

/** Frontend-only secured gateway for EFSM designer draft commands. */
final class OwasysFsmDesignerGateway
{
    public const CSRF_SCOPE = 'owasys.fsm.designer';

    private readonly CsrfTokenManagerInterface $csrf;

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly OwasysSessionRuntimeInterface $sessionRuntime,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null,
        ?CsrfTokenManagerInterface $csrf = null
    ) {
        $this->csrf = $csrf ?? new CsrfTokenManager();
    }

    public function handleIfRequested(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST'
            || (string) ($_POST['owasys_fsm_designer_command'] ?? '') !== '1') {
            return false;
        }

        $this->sessionRuntime->start();
        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->respondError('OWASYS_FSM_DESIGNER_AUTH_REQUIRED', 401);
            return true;
        }
        if (!$this->security->isAllowed($identity, 'fsm', 'update')) {
            $this->respondError('OPUS_ACL_DENIED:fsm:update', 403);
            return true;
        }

        try {
            $this->csrf->assertValid(
                self::CSRF_SCOPE,
                trim((string) ($_POST['csrf_token'] ?? ''))
            );
            $baseHash = strtolower(trim((string) ($_POST['base_sha256'] ?? '')));
            $draftJson = $_POST['draft_json'] ?? null;
            $commandJson = $_POST['command_json'] ?? null;
            if (preg_match('/^[a-f0-9]{64}$/D', $baseHash) !== 1
                || !is_string($draftJson)
                || !is_string($commandJson)
                || strlen($draftJson) > 2097152
                || strlen($commandJson) > 65536) {
                throw new RuntimeException('OWASYS_FSM_DESIGNER_REQUEST_INVALID');
            }

            $actor = [
                'subject' => (string) ($identity['subject'] ?? ''),
                'roles' => is_array($identity['roles'] ?? null)
                    ? array_values(array_filter($identity['roles'], 'is_string'))
                    : [],
                'provider' => (string) ($identity['provider'] ?? ''),
            ];
            $this->profiler?->event(
                'fsm',
                'designer.draft_command.forwarding',
                [
                    'site_id' => 'owasys-front',
                    'request_bytes' => strlen($draftJson) + strlen($commandJson),
                ],
                'success',
                null,
                $this->parentSpanId
            );
            $result = RestClient::fromConfig(
                $this->siteRoot . '/config/rest-api.json',
                $this->profiler
            )->request(
                'POST',
                '/api/v1/applications/owasys-front/fsm/drafts/commands',
                [
                    'base_sha256' => $baseHash,
                    'draft_json' => $draftJson,
                    'command_json' => $commandJson,
                ],
                $actor
            );
            Response::json([
                'contract' => 'OWASYS_EFSM_DESIGNER_FRONT_RESPONSE_V1',
                'ok' => true,
                'data' => $result,
            ], 200)->send();
            return true;
        } catch (Throwable $cause) {
            $code = $this->errorCode($cause);
            $status = str_contains($code, 'ACL_DENIED')
                ? 403
                : (str_contains($code, 'BASE_HASH_CONFLICT') ? 409 : 422);
            $this->profiler?->event(
                'fsm',
                'designer.draft_command.failed',
                ['error_code' => $code, 'http_status' => $status],
                'error',
                null,
                $this->parentSpanId
            );
            $this->respondError($code, $status);
            return true;
        }
    }

    private function respondError(string $code, int $status): void
    {
        Response::json([
            'contract' => 'OWASYS_EFSM_DESIGNER_FRONT_RESPONSE_V1',
            'ok' => false,
            'error_code' => $code,
        ], $status)->send();
    }

    private function errorCode(Throwable $cause): string
    {
        $message = trim($cause->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED';
    }
}