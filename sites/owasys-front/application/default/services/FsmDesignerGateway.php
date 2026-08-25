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
    private const MAX_LAYOUT_GEOMETRY_BYTES = 262144;

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
        $handlerRequested = (string) (
            $_POST['owasys_fsm_designer_handler'] ?? ''
        ) === '1';
        $layoutRequested = (string) (
            $_POST['owasys_action'] ?? ''
        ) === 'persist-fsm-layout'
            && trim((string) ($_POST['efsm_id'] ?? '')) !== '';
        $requestCount = (int) $commandRequested
            + (int) $catalogRequested
            + (int) $handlerRequested
            + (int) $layoutRequested;

        if ($method !== 'POST' || $requestCount !== 1) {
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
        $currentApp = $this->session->currentApp();
        $targetEfsmId = strtolower(trim((string) (
            $_POST['efsm_id'] ?? ''
        )));
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $targetEfsmId) !== 1) {
            $this->respondError(
                'OWASYS_FSM_DESIGNER_EFSM_REQUIRED',
                409
            );
            return true;
        }
        $contextRegistry = new OwasysContextEfsmRegistry();
        $hostTarget = $contextRegistry->isHostEfsm($targetEfsmId);
        $targetSiteId = $hostTarget
            ? 'owasys-front'
            : strtolower(trim((string) (
                $currentApp['id'] ?? ''
            )));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $targetSiteId) !== 1) {
            $this->respondError(
                'OWASYS_FSM_DESIGNER_APPLICATION_REQUIRED',
                409
            );
            return true;
        }
        $aclResource = $hostTarget ? 'owasys' : 'fsm';
        $aclAction = $hostTarget ? 'modify' : 'update';
        if (!$this->security->isAllowed(
            $identity,
            $aclResource,
            $aclAction
        )) {
            $this->respondError(
                'OPUS_ACL_DENIED:' . $aclResource . ':' . $aclAction,
                403
            );
            return true;
        }

        try {
            if ($catalogRequested && $targetSiteId !== 'owasys-front') {
                $this->respondData([
                    'contract' => OwasysFsmHandlerCatalog::CONTRACT,
                    'application_id' => $targetSiteId,
                    'authoring_supported' => false,
                    'managed_source_path' => '',
                    'managed_source_sha256' => '',
                    'guards' => [],
                    'actions' => [],
                ]);
                return true;
            }
            if ($handlerRequested && $targetSiteId !== 'owasys-front') {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_HANDLER_AUTHORING_UNAVAILABLE:'
                    . $targetSiteId
                );
            }
            if (!$catalogRequested) {
                $this->csrf->assertValid(
                    self::CSRF_SCOPE,
                    trim((string) (
                        $_POST['csrf_token'] ?? ''
                    ))
                );
            }

            if ($layoutRequested) {
                $expectedHash = strtolower(trim((string) (
                    $_POST['expected_definition_sha256'] ?? ''
                )));
                $layoutAction = trim((string) (
                    $_POST['opus_fsm_layout_action'] ?? ''
                ));
                $geometryJson = $_POST['opus_fsm_layout_geometry'] ?? null;
                $stateId = trim((string) (
                    $_POST['opus_fsm_layout_state'] ?? ''
                ));
                $markerId = trim((string) (
                    $_POST['opus_fsm_layout_marker'] ?? ''
                ));
                $x = $_POST['opus_fsm_layout_x'] ?? null;
                $y = $_POST['opus_fsm_layout_y'] ?? null;

                if (preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
                    || !in_array(
                        $layoutAction,
                        ['save-state', 'save-signal', 'save-marker'],
                        true
                    )
                    || !is_string($geometryJson)
                    || $geometryJson === ''
                    || strlen($geometryJson)
                        > self::MAX_LAYOUT_GEOMETRY_BYTES) {
                    throw new RuntimeException(
                        'OWASYS_FSM_LAYOUT_REQUEST_INVALID'
                    );
                }
                if ($layoutAction === 'save-state'
                    && (preg_match(
                        '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D',
                        $stateId
                    ) !== 1
                        || !is_numeric($x)
                        || !is_numeric($y))) {
                    throw new RuntimeException(
                        'OWASYS_FSM_LAYOUT_STATE_REQUEST_INVALID'
                    );
                }
                if ($layoutAction === 'save-marker'
                    && preg_match(
                        '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D',
                        $markerId
                    ) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_FSM_LAYOUT_MARKER_REQUEST_INVALID'
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
                $layoutRequest = [
                    'expected_definition_sha256' => $expectedHash,
                    'layout_action' => $layoutAction,
                    'geometry_json' => $geometryJson,
                ];
                if ($layoutAction === 'save-state') {
                    $layoutRequest['state_id'] = $stateId;
                    $layoutRequest['x'] = (string) $x;
                    $layoutRequest['y'] = (string) $y;
                } elseif ($layoutAction === 'save-marker') {
                    $layoutRequest['marker_id'] = $markerId;
                }
                $result = RestClient::fromConfig(
                    $this->siteRoot . '/config/rest-api.json',
                    $this->profiler
                )->request(
                    'PUT',
                    '/api/v1/applications/'
                        . rawurlencode($targetSiteId)
                        . '/fsm/layouts/'
                        . rawurlencode($targetEfsmId),
                    $layoutRequest,
                    $actor
                );
                if (($result['contract'] ?? null)
                        !== 'OWASYS_EFSM_LAYOUT_WRITE_RESULT_V1'
                    || (string) ($result['application_id'] ?? '')
                        !== $targetSiteId
                    || (string) ($result['efsm_id'] ?? '')
                        !== $targetEfsmId
                    || !is_array($result['layout'] ?? null)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_LAYOUT_RESULT_INVALID'
                    );
                }
                $this->profiler?->event(
                    'fsm',
                    'designer.layout.persisted',
                    [
                        'site_id' => $targetSiteId,
                        'efsm_id' => $targetEfsmId,
                        'layout_action' => $layoutAction,
                        'layout_path' => (string) (
                            $result['layout_path'] ?? ''
                        ),
                    ],
                    'success',
                    null,
                    $this->parentSpanId
                );
                $this->respondData($result);
                return true;
            }

            $handlerCatalog = (
                new OwasysFsmHandlerCatalog(
                    $this->siteRoot,
                    $this->session,
                    $this->security
                )
            )->snapshot();
            $handlerCatalog['application_id'] = $targetSiteId;
            $handlerCatalog['authoring_supported'] = true;

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

            if ($handlerRequested) {
                $kind = strtolower(trim((string) (
                    $_POST['handler_kind'] ?? ''
                )));
                $handlerId = trim((string) (
                    $_POST['handler_id'] ?? ''
                ));
                $mode = strtolower(trim((string) (
                    $_POST['handler_mode'] ?? ''
                )));
                $handlerCode = $_POST['handler_code'] ?? null;
                if (!in_array($kind, ['guard', 'action'], true)
                    || preg_match(
                        '/^[a-z][a-z0-9_:-]{0,127}$/D',
                        $handlerId
                    ) !== 1
                    || !in_array($mode, ['create', 'update'], true)
                    || !is_string($handlerCode)
                    || $handlerCode === ''
                    || strlen($handlerCode) > 16384
                    || ($kind === 'guard'
                        && str_starts_with($handlerId, 'acl:'))) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_HANDLER_REQUEST_INVALID'
                    );
                }

                $entries = is_array(
                    $handlerCatalog[$kind . 's'] ?? null
                ) ? $handlerCatalog[$kind . 's'] : [];
                $existing = null;
                foreach ($entries as $entry) {
                    if (is_array($entry)
                        && (string) ($entry['id'] ?? '') === $handlerId) {
                        $existing = $entry;
                        break;
                    }
                }
                if ($mode === 'create' && is_array($existing)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_HANDLER_ALREADY_EXISTS:'
                        . $handlerId
                    );
                }
                if ($mode === 'update'
                    && (!is_array($existing)
                        || ($existing['managed'] ?? false) !== true)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_HANDLER_NOT_MANAGED:'
                        . $handlerId
                    );
                }

                $sourceHash = strtolower(trim((string) (
                    $handlerCatalog['managed_source_sha256'] ?? ''
                )));
                if (preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_FSM_DESIGNER_HANDLER_SOURCE_HASH_INVALID'
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

                $result = RestClient::fromConfig(
                    $this->siteRoot . '/config/rest-api.json',
                    $this->profiler
                )->request(
                    'PUT',
                    '/api/v1/applications/' . rawurlencode(
                        $targetSiteId
                    ) . '/fsm/handlers',
                    [
                        'kind' => $kind,
                        'handler_id' => $handlerId,
                        'mode' => $mode,
                        'expected_source_sha256' => $sourceHash,
                        'handler_code' => $handlerCode,
                    ],
                    $actor
                );

                $this->profiler?->event(
                    'fsm',
                    'designer.handler_source.written',
                    [
                        'kind' => $kind,
                        'handler_id' => $handlerId,
                        'mode' => $mode,
                        'source_sha256' => (string) (
                            $result['source_sha256'] ?? ''
                        ),
                    ],
                    'success',
                    null,
                    $this->parentSpanId
                );
                $this->respondData($result);
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
            $operation = trim((string) (
                $semanticCommand['operation'] ?? ''
            ));
            if ($targetSiteId !== 'owasys-front'
                && $operation === 'transition.handlers.update') {
                throw new RuntimeException(
                    'OWASYS_FSM_DESIGNER_HANDLER_BINDING_UNAVAILABLE:'
                    . $targetSiteId
                );
            }
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
                    'efsm_id' => $targetEfsmId,
                    'history' => $history,
                    'command' => $semanticCommand,
                    'handler_catalog' =>
                        $operation === 'transition.handlers.update'
                            ? $handlerCatalog
                            : null,
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
                    'site_id' => $targetSiteId,
                    'efsm_id' => $targetEfsmId,
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
                '/api/v1/applications/'
                    . rawurlencode($targetSiteId)
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
                    (str_contains(
                        $code,
                        'BASE_HASH_CONFLICT'
                    ) || str_contains(
                        $code,
                        'DEFINITION_HASH_CONFLICT'
                    ))
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
            'csrf_token' => $this->csrf->issue(self::CSRF_SCOPE),
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
            'csrf_token' => $this->csrf->issue(self::CSRF_SCOPE),
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
