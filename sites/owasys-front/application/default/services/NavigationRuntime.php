<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSessionStore;
use Opus\Fsm\FsmSignalBus;
use Opus\Fsm\FsmSiteLoader;
use Opus\Profiler\ProfilerInterface;

/** Canonical runtime authority for the dedicated OWASYS Navigation EFSM. */
final class OwasysNavigationRuntime implements OwasysNavigationRuntimeInterface
{
    public const SESSION_KEY = 'opus.fsm.owasys-front.navigation';
    public const FSM_ID = 'owasys-front/navigation';

    /** @var array<string,string> */
    private const REQUESTED_TO_NAVIGATION = [
        'registry' => 'registry',
        'application' => 'application',
        'data' => 'data',
        'structure' => 'navigation',
        'navigation' => 'navigation',
        'security' => 'security',
        'source' => 'source',
        'build' => 'build',
    ];

    /** @var array<string,string> */
    private const OPEN_SIGNALS = [
        'registry' => 'open_applications',
        'application' => 'open_application',
        'data' => 'open_data',
        'navigation' => 'open_navigation',
        'security' => 'open_security',
        'source' => 'open_source',
        'build' => 'open_build',
    ];

    private ?FsmProcessor $fsm = null;
    private ?FsmSessionStore $store = null;

    public function __construct(
        private readonly string $siteRoot,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
    }

    public function synchronize(string $requestedState): string
    {
        $requestedState = strtolower(trim($requestedState));
        $target = self::REQUESTED_TO_NAVIGATION[$requestedState] ?? '';
        if ($target === '') {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_RUNTIME_REQUESTED_STATE_UNKNOWN:' . $requestedState
            );
        }

        $fsm = FsmSiteLoader::processorForSiteRootEfsm(
            $this->siteRoot,
            'navigation',
            [],
            $this->profiler,
            $this->parentSpanId
        );
        $store = new FsmSessionStore(self::SESSION_KEY);
        $store->restoreCompatible($fsm);
        $from = $fsm->currentState();

        if ($from !== $target) {
            $signal = self::OPEN_SIGNALS[$target] ?? '';
            if ($signal === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_RUNTIME_SIGNAL_UNRESOLVED:' . $target
                );
            }
            $transition = $fsm->transition(
                $from,
                $signal,
                [
                    'requested_state' => $requestedState,
                    'navigation_state' => $target,
                    'runtime_authority' => 'dedicated-navigation-efsm',
                ]
            );
            if ((string) ($transition['next_state'] ?? '') !== $target
                || $fsm->currentState() !== $target) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_RUNTIME_SYNC_TARGET_INVALID:' . $target
                );
            }
            $store->persist($fsm);
        }

        $this->fsm = $fsm;
        $this->store = $store;
        $this->profiler?->event(
            'fsm.navigation',
            'navigation.synchronized',
            [
                'requested_state' => $requestedState,
                'from_state' => $from,
                'navigation_state' => $fsm->currentState(),
                'runtime_authority' => 'dedicated-navigation-efsm',
            ],
            'success',
            null,
            $this->parentSpanId
        );

        return $fsm->currentState();
    }

    public function register(FsmSignalBus $bus): void
    {
        if (!$this->fsm instanceof FsmProcessor
            || !$this->store instanceof FsmSessionStore) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_RUNTIME_NOT_SYNCHRONIZED'
            );
        }
        $fsm = $this->fsm;
        $store = $this->store;
        $bus->register(
            self::FSM_ID,
            static function (array $message) use ($fsm, $store): array {
                $context = is_array($message['context'] ?? null)
                    ? $message['context']
                    : [];
                $context['message_id'] = (string) ($message['message_id'] ?? '');
                $context['correlation_id'] = (string) (
                    $message['correlation_id'] ?? ''
                );
                $context['runtime_authority'] = 'dedicated-navigation-efsm';
                $result = $fsm->transition(
                    $fsm->currentState(),
                    (string) ($message['signal'] ?? ''),
                    $context
                );
                $store->persist($fsm);
                return $result;
            }
        );
    }

    public function currentState(): string
    {
        if (!$this->fsm instanceof FsmProcessor) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_RUNTIME_NOT_SYNCHRONIZED'
            );
        }
        return $this->fsm->currentState();
    }
}
