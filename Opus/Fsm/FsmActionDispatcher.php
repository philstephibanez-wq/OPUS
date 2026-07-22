<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use RuntimeException;

/**
 * Dispatches actions emitted by OPUS FSM transitions.
 *
 * The dispatcher is intentionally explicit: every action returned by the FSM
 * processor must have a registered handler. Unknown actions are refused instead
 * of being ignored, so application behaviour remains contract-driven.
 */
final class FsmActionDispatcher implements FsmActionDispatcherInterface
{
    private const RESULT_CONTRACT = 'OPUS_FSM_ACTION_DISPATCH_RESULT_V1';
    private const PROCESSOR_RESULT_CONTRACT = 'OPUS_FSM_PROCESSOR_RESULT_V1';

    /** @var array<string,callable> */
    private array $handlers = [];

    /**
     * @param array<string,callable> $handlers
     */
    public function __construct(array $handlers = [])
    {
        foreach ($handlers as $action => $handler) {
            $this->register((string) $action, $handler);
        }
    }

    /**
     * Registers one explicit action handler.
     */
    public function register(string $action, callable $handler): void
    {
        $this->assertActionName($action);
        $this->handlers[$action] = $handler;
    }

    /**
     * Checks whether a handler exists for the given action.
     */
    public function hasHandler(string $action): bool
    {
        return isset($this->handlers[$action]);
    }

    /**
     * Dispatches all actions declared by a transition result.
     *
     * Handler signature:
     *   function (string $action, array $transitionResult, array $context, FsmActionDispatcher $dispatcher): mixed
     *
     * @param array<string,mixed> $transitionResult Result returned by FsmProcessor::transition().
     * @param array<string,mixed> $context Runtime facts made available to handlers.
     * @return array<string,mixed>
     */
    public function dispatch(array $transitionResult, array $context = []): array
    {
        $this->assertProcessorResult($transitionResult);

        $actions = $this->actionsFromResult($transitionResult);
        $executed = [];

        foreach ($actions as $action) {
            if (!isset($this->handlers[$action])) {
                throw new RuntimeException('OPUS_FSM_ACTION_HANDLER_MISSING: ' . $action);
            }

            $handlerResult = ($this->handlers[$action])($action, $transitionResult, $context, $this);
            $executed[] = [
                'action' => $action,
                'status' => 'ok',
                'result' => $handlerResult,
            ];
        }

        return [
            'contract' => self::RESULT_CONTRACT,
            'from_state' => (string) $transitionResult['from_state'],
            'event' => (string) $transitionResult['event'],
            'to_state' => (string) $transitionResult['to_state'],
            'transition_id' => (string) ($transitionResult['transition_id'] ?? ''),
            'actions' => $actions,
            'executed' => $executed,
            'count' => count($executed),
        ];
    }

    /**
     * Dispatches a transition result and returns the final target state id.
     *
     * @param array<string,mixed> $transitionResult
     * @param array<string,mixed> $context
     */
    public function dispatchAndReturnState(array $transitionResult, array $context = []): string
    {
        $this->dispatch($transitionResult, $context);
        return (string) $transitionResult['to_state'];
    }

    /**
     * @param array<string,mixed> $transitionResult
     * @return list<string>
     */
    private function actionsFromResult(array $transitionResult): array
    {
        $actions = $transitionResult['actions'] ?? [];
        if (is_string($actions) && $actions !== '') {
            return [$actions];
        }
        if (!is_array($actions)) {
            throw new InvalidArgumentException('OPUS_FSM_ACTIONS_INVALID');
        }

        $normalized = [];
        foreach ($actions as $action) {
            if (!is_string($action) || $action === '') {
                throw new InvalidArgumentException('OPUS_FSM_ACTION_INVALID');
            }
            $this->assertActionName($action);
            $normalized[] = $action;
        }

        return $normalized;
    }

    /** @param array<string,mixed> $transitionResult */
    private function assertProcessorResult(array $transitionResult): void
    {
        if (($transitionResult['contract'] ?? null) !== self::PROCESSOR_RESULT_CONTRACT) {
            throw new InvalidArgumentException('OPUS_FSM_PROCESSOR_RESULT_CONTRACT_INVALID');
        }

        foreach (['from_state', 'event', 'to_state'] as $field) {
            if (!isset($transitionResult[$field]) || !is_string($transitionResult[$field]) || $transitionResult[$field] === '') {
                throw new InvalidArgumentException('OPUS_FSM_PROCESSOR_RESULT_FIELD_INVALID: ' . $field);
            }
        }
    }

    private function assertActionName(string $action): void
    {
        if (!preg_match('/^[a-z][a-z0-9_:-]*$/', $action)) {
            throw new InvalidArgumentException('OPUS_FSM_ACTION_NAME_INVALID: ' . $action);
        }
    }
}
