<?php
declare(strict_types=1);

use Opus\Api\Rest\RestClient;
use Opus\File\Json;
use Opus\Http\Response;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Csrf\CsrfTokenManager;
use Opus\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Frontend-only secured gateway for EFSM designer development operations.
 *
 * The browser never sends an authoritative draft definition or handler
 * catalog. Draft state is rebuilt server-side from canonical fsm.json plus
 * the bounded semantic command history. The handler catalog is derived from
 * the real PHP registrations by the trusted OWASYS front runtime.
 */
final class OwasysFsmDesignerGateway
{
    public const CSRF_SCOPE = 'owasys.fsm.designer';
    private const ENVELOPE_CONTRACT =
        'OWASYS_EFSM_DRAFT_COMMAND_ENVELOPE_V2';
    private const MAX_HISTORY_COMMANDS = 128;
    private const MAX_HISTORY_BYTES = 32768;
    private const MAX_COMMAND_BYTES = 16384;
    private const MAX_ENVELOPE_BYTES = 65536;

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
        $method = strtoupper(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
        );
        $commandRequested = (string) (
            $_POST['owasys_fsm_designer_command'] ?? ''
        ) === '1';
        $catalogRequested = (string) (
            $_POST['owasys_fsm_designer_catalog'] ?? ''
        ) === '1';

        if ($method !== 'POST'
            || (!$commandRequested && !$catalogRequested)
            || ($commandRequested && $catalogRequested)) {
            return false;
        }

        $this->sessionRuntime->start();
        $identity = $this->session->user();
        if (!is_array($identity)) {
            $this->respondError(
                'OWASYS_FSM_DESIGNER_AUTH_REQUIRED',
                401
            );
            return true;
        }
        if (!$this->security->isAllowed(
            $identity,
            'fsm',
            'update'
        )) {
            $this->respondError(
                'OPUS_ACL_DENIED:fsm:update',
                403
            );
            return true;
        }

        try {
            $this->csrf->assertValid(
                self::CSRF_SCOPE,
                trim((string) (
                    $_POST['csrf_token'] ?? ''
                ))
            );

            $handlerCatalog = (
                new OwasysFsmHandlerCatalog(
                    $this->siteRoot,
                    $this->session,
                    $this->security
                )
            )->snapshot();

            if ($catalogRequested) {
                $this->profiler?->event(
                    'fsm',
                    'designer.handler_catalog.served',
                    [
                        'guard_count' => count(
                            (array) ($handlerCatalog['guards'] ?? [])
                        ),
                        'action_count' => count(
                            (array) ($handlerCatalog['actions'] ?? [])
                        ),
                    ],
                    'success',
                    null,
                    $this->parentSpanId
                );
                $this->respondData($handlerCatalog);
                return true;
            }

            $baseHash = strtolower(trim((string) (
                $_POST['base_sha256'] ?? ''
            )));
            $historyJson = $_POST['history_json'] ?? null;
            $commandJson = $_POST['command_json'] ?? null;

            if (preg_match(
                '/^[a-f0-9]{64}$/D',
                $baseHash
            ) !== 1
                || !is_string($historyJson)
                || !is_string($commandJson)
                || strlen($historyJson)
                    > self::MAX_HISTORY_BYTES
                || strlen($commandJson)
                    > self::MAX_COMMAND_BYTES) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_REQUEST_INVALID'
                );
            }

            $history = Json::instance()->parse(
                $historyJson,
                'fsm-command-history'
            );
            $semanticCommand = Json::instance()->parse(
                $commandJson,
                'fsm-command'
            );
            if (!array_is_list($history)
                || count($history)
                    > self::MAX_HISTORY_COMMANDS
                || !is_array($semanticCommand)
                || array_is_list($semanticCommand)) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_COMMAND_HISTORY_INVALID'
                );
            }
            foreach ($history as $historicCommand) {
                if (!is_array($historicCommand)
                    || array_is_list($historicCommand)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_COMMAND_HISTORY_INVALID'
                    );
                }
            }

            $envelopeJson = Json::instance()->encode(
                [
                    'contract' => self::ENVELOPE_CONTRACT,
                    'history' => $history,
                    'command' => $semanticCommand,
                    'handler_catalog' => $handlerCatalog,
                ],
                false
            );
            if (strlen($envelopeJson)
                > self::MAX_ENVELOPE_BYTES) {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_COMMAND_ENVELOPE_TOO_LARGE'
                );
            }

            $actor = [
                'subject' => (string) (
                    $identity['subject'] ?? ''
                ),
                'roles' => is_array(
                    $identity['roles'] ?? null
                )
                    ? array_values(array_filter(
                        $identity['roles'],
                        'is_string'
                    ))
                    : [],
                'provider' => (string) (
                    $identity['provider'] ?? ''
                ),
            ];

            $this->profiler?->event(
                'fsm',
                'designer.command_envelope.forwarding',
                [
                    'site_id' => 'owasys-front',
                    'history_count' => count($history),
                    'request_bytes' =>
                        strlen($envelopeJson) + 2,
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
                '/api/v1/applications/owasys-front'
                    . '/fsm/drafts/commands',
                [
                    'base_sha256' => $baseHash,
                    /*
                     * V1 transported a browser-authored draft definition.
                     * V2 keeps the required transport field only as a sentinel;
                     * backend rejects anything other than this empty object.
                     */
                    'draft_json' => '{}',
                    'command_json' => $envelopeJson,
                ],
                $actor
            );

            $this->respondData($result);
            return true;
        } catch (Throwable $cause) {
            $code = $this->errorCode($cause);
            $status = str_contains(
                $code,
                'ACL_DENIED'
            )
                ? 403
                : (
                    str_contains(
                        $code,
                        'BASE_HASH_CONFLICT'
                    )
                        ? 409
                        : 422
                );
            $this->profiler?->event(
                'fsm',
                'designer.draft_command.failed',
                [
                    'error_code' => $code,
                    'http_status' => $status,
                ],
                'error',
                null,
                $this->parentSpanId
            );
            $this->respondError($code, $status);
            return true;
        }
    }

    private function respondData(array $data): void
    {
        Response::json([
            'contract' =>
                'OWASYS_EFSM_DESIGNER_FRONT_RESPONSE_V1',
            'ok' => true,
            'data' => $data,
        ], 200)->send();
    }

    private function respondError(
        string $code,
        int $status
    ): void {
        Response::json([
            'contract' =>
                'OWASYS_EFSM_DESIGNER_FRONT_RESPONSE_V1',
            'ok' => false,
            'error_code' => $code,
        ], $status)->send();
    }

    private function errorCode(Throwable $cause): string
    {
        $message = trim($cause->getMessage());
        return preg_match(
            '/^[A-Z0-9_:-]{3,240}$/D',
            $message
        ) === 1
            ? $message
            : 'OWASYS_FSM_DESIGNER_DRAFT_COMMAND_FAILED';
    }
}
