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
    private const NAVIGATION_SESSION_KEY = 'opus.fsm.owasys-front';
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

        $navigationFsm = FsmSiteLoader::processorForSiteRoot(
            $this->siteRoot,
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $navigationStore = new FsmSessionStore(self::NAVIGATION_SESSION_KEY);
        $navigationStore->restore($navigationFsm);
        if ($navigationFsm->currentState() !== 'security') {
            throw new RuntimeException(
                'OWASYS_SECURITY_RUNTIME_NAVIGATION_STATE_INVALID:'
                . $navigationFsm->currentState()
            );
        }

        $securityFsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            'security',
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $securityStore = new FsmSessionStore(self::SECURITY_SESSION_KEY);
        $securityStore->restore($securityFsm);
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
        $bus->register(
            self::NAVIGATION_FSM_ID,
            static function (array $message) use (
                $navigationFsm,
                $navigationStore,
                $context
            ): array {
                $result = $navigationFsm->transition(
                    $navigationFsm->currentState(),
                    (string) ($message['signal'] ?? ''),
                    [
                        'security_context' => $context->snapshot(),
                        'message_id' => (string) ($message['message_id'] ?? ''),
                        'correlation_id' => (string) ($message['correlation_id'] ?? ''),
                    ]
                );
                $navigationStore->persist($navigationFsm);
                return $result;
            }
        );

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
            || $navigationFsm->currentState() !== 'security'
            || $securityFsm->currentState() !== 'authenticated') {
            throw new RuntimeException('OWASYS_SECURITY_CONTEXT_HANDSHAKE_INVALID');
        }

        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event(
                'fsm.network',
                'security_context.handshake',
                [
                    'navigation_state' => $navigationFsm->currentState(),
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
            'navigation_state' => $navigationFsm->currentState(),
            'security_state' => $securityFsm->currentState(),
            'correlation_id' => $correlationId,
            'command_message_id' => $commandMessageId,
            'event_message_id' => $eventMessageId,
        ];
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
