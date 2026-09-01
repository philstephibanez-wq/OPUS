<?php
declare(strict_types=1);

use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSignalBus;
use Opus\Fsm\FsmSiteLoader;
use Opus\Log\Logger;
use Opus\Profiler\ProfilerInterface;

/**
 * Coordinates autonomous OWASYS host EFSMs and the selected-application
 * navigation context without cross-application source substitution.
 */
final class OwasysContextRuntimeCoordinator implements
    OwasysContextRuntimeCoordinatorInterface
{
    private const NAVIGATION_FSM_ID = 'owasys-front/navigation';

    private readonly OwasysContextEfsmRegistry $registry;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(
        private readonly string $siteRoot,
        private readonly array $siteConfig,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        $this->registry = new OwasysContextEfsmRegistry();
    }

    public function enter(
        array $identity,
        string $contextId,
        string $applicationId = ''
    ): array {
        $contextId = strtolower(trim($contextId));
        $applicationId = strtolower(trim($applicationId));

        /*
         * Application is not an OWASYS host EFSM. It is a navigation context
         * whose semantic FSM belongs strictly to the selected application.
         * No host EFSM is loaded and no source is substituted.
         */
        if ($contextId === 'application') {
            if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
                throw new RuntimeException(
                    'OWASYS_CONTEXT_RUNTIME_APPLICATION_REQUIRED'
                );
            }

            $navigationRuntime = new OwasysNavigationRuntime(
                $this->siteRoot,
                $this->profiler,
                $this->parentSpanId
            );
            $navigationRuntime->synchronize('application');

            $this->record(
                'context.application.selected',
                [
                    'context_id' => 'application',
                    'application_id' => $applicationId,
                    'navigation_state' => $navigationRuntime->currentState(),
                ],
                'success'
            );

            return [
                'context_id' => 'application',
                'application_id' => $applicationId,
                'navigation_state' => $navigationRuntime->currentState(),
                'context_state' => '',
                'correlation_id' => '',
                'command_message_id' => '',
                'event_message_id' => '',
            ];
        }

        if (!$this->registry->isHostEfsm($contextId)) {
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_EFSM_UNKNOWN:' . $contextId
            );
        }

        $expectedNavigationState = $this->registry->navigationState($contextId);
        $navigationRuntime = new OwasysNavigationRuntime(
            $this->siteRoot,
            $this->profiler,
            $this->parentSpanId
        );
        $navigationRuntime->synchronize($expectedNavigationState);

        $contextFsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            $contextId,
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $contextStore = new FsmSessionStore(
            $this->registry->sessionKey($contextId)
        );
        $contextStore->restore($contextFsm);

        $bus = $this->signalBus();
        $contextFsmId = 'owasys-front/' . $contextId;

        $bus->register(
            $contextFsmId,
            static function (array $message) use (
                $contextFsm,
                $contextStore
            ): array {
                $transition = $contextFsm->transition(
                    $contextFsm->currentState(),
                    (string) ($message['signal'] ?? ''),
                    is_array($message['context'] ?? null)
                        ? $message['context']
                        : []
                );
                $contextStore->persist($contextFsm);
                return $transition;
            }
        );
        $navigationRuntime->register($bus);

        $roles = is_array($identity['roles'] ?? null)
            ? array_values(array_filter($identity['roles'], 'is_string'))
            : [];
        $payload = [
            'application_id' => $applicationId,
            'authenticated' => true,
            'roles' => $roles,
            'provider' => (string) ($identity['provider'] ?? ''),
        ];

        $command = $bus->command(
            self::NAVIGATION_FSM_ID,
            $contextFsmId,
            'enter_' . $contextId . '_context',
            $payload
        );
        $commandMeta = is_array($command['message'] ?? null)
            ? $command['message']
            : [];
        $correlationId = (string) ($commandMeta['correlation_id'] ?? '');
        $commandMessageId = (string) ($commandMeta['message_id'] ?? '');
        if ($correlationId === '' || $commandMessageId === '') {
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_COMMAND_METADATA_INVALID:' . $contextId
            );
        }

        $event = $bus->event(
            $contextFsmId,
            self::NAVIGATION_FSM_ID,
            $contextId . '_context_ready',
            ['context_state' => $contextFsm->currentState()],
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
            || $navigationRuntime->currentState() !== $expectedNavigationState) {
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_HANDSHAKE_INVALID:' . $contextId
            );
        }

        $this->record(
            'context.handshake',
            [
                'context_id' => $contextId,
                'navigation_state' => $navigationRuntime->currentState(),
                'context_state' => $contextFsm->currentState(),
                'correlation_id' => $correlationId,
                'command_message_id' => $commandMessageId,
                'event_message_id' => $eventMessageId,
            ],
            'success'
        );

        return [
            'context_id' => $contextId,
            'navigation_state' => $navigationRuntime->currentState(),
            'context_state' => $contextFsm->currentState(),
            'correlation_id' => $correlationId,
            'command_message_id' => $commandMessageId,
            'event_message_id' => $eventMessageId,
        ];
    }

    public function transition(
        string $contextId,
        string $signal,
        array $context = []
    ): string {
        $contextId = strtolower(trim($contextId));
        $signal = strtolower(trim($signal));
        if (!$this->registry->isHostEfsm($contextId)
            || preg_match('/^[a-z][a-z0-9_:-]{0,127}$/D', $signal) !== 1) {
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_TRANSITION_INVALID:'
                . $contextId . ':' . $signal
            );
        }
        $this->assertSafeContext($context);

        $fsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            $contextId,
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $store = new FsmSessionStore($this->registry->sessionKey($contextId));
        $store->restore($fsm);
        $from = $fsm->currentState();
        $transition = $fsm->transition($from, $signal, $context);
        $to = (string) ($transition['next_state'] ?? '');
        if ($to === '') {
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_TARGET_STATE_INVALID:' . $contextId
            );
        }
        $store->persist($fsm);
        $this->record(
            'context.transition',
            [
                'context_id' => $contextId,
                'signal' => $signal,
                'from_state' => $from,
                'to_state' => $to,
            ],
            'success'
        );
        return $to;
    }

    /** @param array<string,mixed> $context */
    private function assertSafeContext(array $context): void
    {
        $scan = static function (mixed $value) use (&$scan): void {
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $key => $entry) {
                if (!is_int($key)
                    && preg_match(
                        '/password|secret|token|csrf|authorization|cookie|credential/i',
                        (string) $key
                    ) === 1) {
                    throw new RuntimeException(
                        'OWASYS_CONTEXT_RUNTIME_SENSITIVE_CONTEXT_FORBIDDEN:'
                        . (string) $key
                    );
                }
                $scan($entry);
            }
        };
        $scan($context);
    }

    private function signalBus(): FsmSignalBus
    {
        $absolute = $this->logPath();
        return new FsmSignalBus(
            new Logger(dirname($absolute), basename($absolute)),
            $this->profiler,
            $this->parentSpanId
        );
    }

    /** @param array<string,mixed> $metadata */
    private function record(string $name, array $metadata, string $status): void
    {
        $absolute = $this->logPath();
        $logger = new Logger(dirname($absolute), basename($absolute));
        $traceId = $this->profiler?->getActiveTrace()?->getTraceId();
        if ($status === 'success') {
            $logger->info('fsm.network', $name, $metadata, $traceId);
        } else {
            $logger->error('fsm.network', $name, $metadata, $traceId);
        }
        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event(
                'fsm.network',
                $name,
                $metadata,
                $status,
                null,
                $this->parentSpanId
            );
        }
    }

    private function logPath(): string
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
            throw new RuntimeException(
                'OWASYS_CONTEXT_RUNTIME_LOG_CONFIG_INVALID'
            );
        }
        return $this->siteRoot . '/' . $relative;
    }
}
