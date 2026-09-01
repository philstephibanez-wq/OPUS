<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSignalBus;
use Opus\Fsm\FsmSiteLoader;
use Opus\Log\Logger;
use Opus\Profiler\ProfilerInterface;

/** Coordinates Navigation/Security without either EFSM mutating the other directly. */
final class OwasysSecurityRuntimeCoordinator implements OwasysSecurityRuntimeCoordinatorInterface
{
    private const SECURITY_SESSION_KEY = 'opus.fsm.owasys-front.security';
    private const NAVIGATION_FSM_ID = 'owasys-front/navigation';
    private const SECURITY_FSM_ID = 'owasys-front/security';

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
    }

    public function enter(array $identity, string $applicationId): array
    {
        $applicationId = strtolower(trim($applicationId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_RUNTIME_APPLICATION_INVALID');
        }

        $navigationRuntime = new OwasysNavigationRuntime(
            $this->siteRoot,
            $this->profiler,
            $this->parentSpanId
        );
        $navigationRuntime->synchronize('security');

        $securityFsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            'security',
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $securityStore = new FsmSessionStore(self::SECURITY_SESSION_KEY);
        $securityStore->restoreCompatible($securityFsm);
        $securityState = $this->synchronizeSecurity($securityFsm, $securityStore, $identity);

        $context = new OwasysSecurityContext();
        $context->synchronize($identity, $applicationId, $securityState);
        $bus = $this->signalBus();

        $bus->register(
            self::SECURITY_FSM_ID,
            static function (array $message) use (
                $securityFsm,
                $securityStore,
                $context
            ): array {
                $result = $securityFsm->transition(
                    $securityFsm->currentState(),
                    (string) ($message['signal'] ?? ''),
                    [
                        'security_context' => $context->snapshot(),
                        'message_id' => (string) ($message['message_id'] ?? ''),
                        'correlation_id' => (string) ($message['correlation_id'] ?? ''),
                    ]
                );
                $securityStore->persist($securityFsm);
                return $result;
            }
        );
        $navigationRuntime->register($bus);

        $command = $bus->command(
            self::NAVIGATION_FSM_ID,
            self::SECURITY_FSM_ID,
            'enter_security_context',
            [
                'application_id' => $context->applicationId(),
                'authenticated' => $context->authenticated(),
                'roles' => $context->roles(),
                'provider' => $context->provider(),
            ]
        );
        $commandMeta = is_array($command['message'] ?? null)
            ? $command['message']
            : [];
        $correlationId = (string) ($commandMeta['correlation_id'] ?? '');
        $commandMessageId = (string) ($commandMeta['message_id'] ?? '');
        if ($correlationId === '' || $commandMessageId === '') {
            throw new RuntimeException('OWASYS_SECURITY_COMMAND_DELIVERY_METADATA_INVALID');
        }

        $event = $bus->event(
            self::SECURITY_FSM_ID,
            self::NAVIGATION_FSM_ID,
            'security_context_ready',
            [
                'application_id' => $context->applicationId(),
                'security_state' => $securityFsm->currentState(),
            ],
            $correlationId,
            $commandMessageId
        );
        $eventMeta = is_array($event['message'] ?? null)
            ? $event['message']
            : [];
        $eventMessageId = (string) ($eventMeta['message_id'] ?? '');
        if ((string) ($eventMeta['correlation_id'] ?? '') !== $correlationId
            || (string) ($eventMeta['causation_id'] ?? '') !== $commandMessageId
            || $eventMessageId === ''
            || $navigationRuntime->currentState() !== 'security'
            || $securityFsm->currentState() !== 'authenticated') {
            throw new RuntimeException('OWASYS_SECURITY_CONTEXT_HANDSHAKE_INVALID');
        }

        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event(
                'fsm.network',
                'security_context.handshake',
                [
                    'navigation_state' => $navigationRuntime->currentState(),
                    'security_state' => $securityFsm->currentState(),
                    'correlation_id' => $correlationId,
                    'command_message_id' => $commandMessageId,
                    'event_message_id' => $eventMessageId,
                    'application_id' => $context->applicationId(),
                ],
                'success',
                null,
                $this->parentSpanId
            );
        }

        return [
            'navigation_state' => $navigationRuntime->currentState(),
            'security_state' => $securityFsm->currentState(),
            'correlation_id' => $correlationId,
            'command_message_id' => $commandMessageId,
            'event_message_id' => $eventMessageId,
        ];
    }

    /**
     * Executes the real fresh-auth operation inside the autonomous Security
     * EFSM lifecycle. The credential itself never enters SecurityContext,
     * Logger, Profiler or the EFSM signal bus.
     *
     * @param array<string,mixed> $identity
     * @param callable():mixed $operation
     */
    public function reauthenticate(
        array $identity,
        string $applicationId,
        callable $operation
    ): mixed {
        $applicationId = strtolower(trim($applicationId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_REAUTH_APPLICATION_INVALID'
            );
        }

        $navigationRuntime = new OwasysNavigationRuntime(
            $this->siteRoot,
            $this->profiler,
            $this->parentSpanId
        );
        $navigationRuntime->synchronize('security');

        $securityFsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            'security',
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $securityStore = new FsmSessionStore(
            self::SECURITY_SESSION_KEY
        );
        $securityStore->restoreCompatible($securityFsm);
        if ($securityFsm->currentState() !== 'authenticated') {
            throw new RuntimeException(
                'OWASYS_SECURITY_REAUTH_SECURITY_STATE_INVALID:'
                . $securityFsm->currentState()
            );
        }

        $context = new OwasysSecurityContext();
        $context->synchronize(
            $identity,
            $applicationId,
            $securityFsm->currentState()
        );
        $transitionContext = [
            'security_context' => $context->snapshot(),
            'application_id' => $applicationId,
            'is_authenticated' => true,
        ];
        $started = $securityFsm->transition(
            'authenticated',
            'reauth_required',
            $transitionContext
        );
        $securityState = (string) ($started['next_state'] ?? '');
        if ($securityState !== 'reauthenticating') {
            throw new RuntimeException(
                'OWASYS_SECURITY_REAUTH_ENTRY_STATE_INVALID:'
                . $securityState
            );
        }
        $context->setRuntimeState($securityState);
        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event(
                'fsm.security',
                'security_context.reauthentication.required',
                [
                    'application_id' => $applicationId,
                    'navigation_state' => $navigationRuntime->currentState(),
                    'security_state' => $securityState,
                ],
                'success',
                null,
                $this->parentSpanId
            );
        }

        try {
            $result = $operation();
        } catch (\Throwable $error) {
            $failed = $securityFsm->transition(
                'reauthenticating',
                'reauthentication_failed',
                [
                    'security_context' => $context->snapshot(),
                    'application_id' => $applicationId,
                    'exception_class' => get_class($error),
                ]
            );
            $securityState = (string) ($failed['next_state'] ?? '');
            if ($securityState !== 'authenticated'
                || $navigationRuntime->currentState() !== 'security') {
                throw new RuntimeException(
                    'OWASYS_SECURITY_REAUTH_FAILURE_STATE_INVALID',
                    0,
                    $error
                );
            }
            $securityStore->persist($securityFsm);
            $context->setRuntimeState($securityState);
            if ($this->profiler?->getActiveTrace() !== null) {
                $this->profiler->event(
                    'fsm.security',
                    'security_context.reauthentication.failed',
                    [
                        'application_id' => $applicationId,
                        'navigation_state' => $navigationRuntime->currentState(),
                        'security_state' => $securityState,
                        'exception_class' => get_class($error),
                    ],
                    'error',
                    null,
                    $this->parentSpanId
                );
            }
            throw $error;
        }

        $succeeded = $securityFsm->transition(
            'reauthenticating',
            'reauthentication_succeeded',
            [
                'security_context' => $context->snapshot(),
                'application_id' => $applicationId,
            ]
        );
        $securityState = (string) ($succeeded['next_state'] ?? '');
        if ($securityState !== 'authenticated'
            || $navigationRuntime->currentState() !== 'security') {
            throw new RuntimeException(
                'OWASYS_SECURITY_REAUTH_SUCCESS_STATE_INVALID'
            );
        }
        $securityStore->persist($securityFsm);
        $context->setRuntimeState($securityState);
        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event(
                'fsm.security',
                'security_context.reauthentication.succeeded',
                [
                    'application_id' => $applicationId,
                    'navigation_state' => $navigationRuntime->currentState(),
                    'security_state' => $securityState,
                ],
                'success',
                null,
                $this->parentSpanId
            );
        }

        return $result;
    }

    /** @param array<string,mixed> $identity */
    private function synchronizeSecurity(
        FsmProcessor $fsm,
        FsmSessionStore $store,
        array $identity
    ): string {
        $context = [
            'identity' => [
                'subject' => (string) ($identity['subject'] ?? ''),
                'roles' => is_array($identity['roles'] ?? null)
                    ? array_values($identity['roles'])
                    : [],
                'provider' => (string) ($identity['provider'] ?? ''),
            ],
            'is_authenticated' => true,
        ];
        $current = $fsm->currentState();
        if ($current === 'anonymous') {
            $current = (string) (
                $fsm->transition(
                    'anonymous',
                    'login_requested',
                    $context
                )['next_state'] ?? ''
            );
        }
        if ($current === 'authenticating') {
            $current = (string) (
                $fsm->transition(
                    'authenticating',
                    'authentication_succeeded',
                    $context
                )['next_state'] ?? ''
            );
        }
        if ($current !== 'authenticated') {
            throw new RuntimeException(
                'OWASYS_SECURITY_RUNTIME_SYNC_STATE_INVALID:' . $current
            );
        }
        $store->persist($fsm);
        return $current;
    }

    private function signalBus(): FsmSignalBus
    {
        $development = is_array($this->siteConfig['development_server'] ?? null)
            ? $this->siteConfig['development_server']
            : [];
        $diagnostics = is_array($development['diagnostics'] ?? null)
            ? $development['diagnostics']
            : [];
        $relative = trim(str_replace(
            '\\',
            '/',
            (string) ($diagnostics['log'] ?? '')
        ), '/');
        if ($relative === ''
            || str_contains($relative, '..')
            || preg_match('/^[A-Za-z]:\//', $relative) === 1) {
            throw new RuntimeException('OWASYS_FSM_SIGNAL_BUS_LOG_CONFIG_INVALID');
        }
        $absolute = $this->siteRoot . '/' . $relative;
        return new FsmSignalBus(
            new Logger(dirname($absolute), basename($absolute)),
            $this->profiler,
            $this->parentSpanId
        );
    }
}
