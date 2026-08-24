<?php
declare(strict_types=1);

namespace Opus\Fsm;

use InvalidArgumentException;
use Opus\Log\LoggerInterface;
use Opus\Profiler\ProfilerInterface;
use RuntimeException;
use Throwable;

/** Bounded in-process unicast signal network for autonomous OPUS EFSMs. */
final class FsmSignalBus implements FsmSignalBusInterface
{
    private const MESSAGE_CONTRACT = 'OPUS_FSM_SIGNAL_MESSAGE_V1';
    private const DELIVERY_CONTRACT = 'OPUS_FSM_SIGNAL_DELIVERY_RESULT_V1';
    private const MAX_CONTEXT_BYTES = 4096;
    private const MAX_CONTEXT_ITEMS = 64;

    /** @var array<string,callable> */
    private array $receivers = [];
    /** @var list<array<string,mixed>> */
    private array $queue = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null,
        private readonly int $maxQueue = 32
    ) {
        if ($maxQueue < 1 || $maxQueue > 256) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_QUEUE_LIMIT_INVALID');
        }
    }

    public function register(string $fsmId, callable $receiver): void
    {
        $fsmId = $this->fsmId($fsmId);
        if (isset($this->receivers[$fsmId])) {
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_TARGET_ALREADY_REGISTERED:' . $fsmId);
        }
        $this->receivers[$fsmId] = $receiver;
    }

    public function command(string $sourceFsm, string $targetFsm, string $signal, array $context = [], ?string $correlationId = null, ?string $causationId = null, int $ttl = 4): array
    {
        return $this->dispatch('command', $sourceFsm, $targetFsm, $signal, $context, $correlationId, $causationId, $ttl);
    }

    public function event(string $sourceFsm, string $targetFsm, string $signal, array $context = [], ?string $correlationId = null, ?string $causationId = null, int $ttl = 4): array
    {
        return $this->dispatch('event', $sourceFsm, $targetFsm, $signal, $context, $correlationId, $causationId, $ttl);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function dispatch(string $category, string $sourceFsm, string $targetFsm, string $signal, array $context, ?string $correlationId, ?string $causationId, int $ttl): array
    {
        $sourceFsm = $this->fsmId($sourceFsm);
        $targetFsm = $this->fsmId($targetFsm);
        $signal = $this->signal($signal);
        $context = $this->context($context);
        if ($sourceFsm === $targetFsm) {
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_SELF_DELIVERY_FORBIDDEN');
        }
        if ($ttl < 1 || $ttl > 16) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_TTL_INVALID');
        }
        $messageId = bin2hex(random_bytes(16));
        $correlationId = $this->messageId($correlationId ?? bin2hex(random_bytes(16)), 'OPUS_FSM_SIGNAL_BUS_CORRELATION_ID_INVALID');
        $causationId = $causationId === null ? null : $this->messageId($causationId, 'OPUS_FSM_SIGNAL_BUS_CAUSATION_ID_INVALID');
        if (count($this->queue) >= $this->maxQueue) {
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_QUEUE_FULL');
        }
        $message = [
            'contract' => self::MESSAGE_CONTRACT,
            'category' => $category,
            'message_id' => $messageId,
            'source_fsm' => $sourceFsm,
            'target_fsm' => $targetFsm,
            'signal' => $signal,
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'context' => $context,
            'ttl' => $ttl,
            'hop_count' => 0,
        ];
        $this->queue[] = $message;
        $this->record('message.enqueued', $message, 'success');
        $delivery = array_shift($this->queue);
        if (!is_array($delivery) || ($delivery['message_id'] ?? null) !== $messageId) {
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_QUEUE_CORRUPTED');
        }
        if ((int) $delivery['hop_count'] >= (int) $delivery['ttl']) {
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_TTL_EXHAUSTED');
        }
        $receiver = $this->receivers[$targetFsm] ?? null;
        if (!is_callable($receiver)) {
            $this->record('message.failed', $delivery, 'error');
            throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_TARGET_UNKNOWN:' . $targetFsm);
        }
        $delivery['hop_count'] = (int) $delivery['hop_count'] + 1;
        try {
            $receiverResult = $receiver($delivery);
            if (!is_array($receiverResult)) {
                throw new RuntimeException('OPUS_FSM_SIGNAL_BUS_RECEIVER_RESULT_INVALID:' . $targetFsm);
            }
            $this->record('message.delivered', $delivery, 'success');
            return [
                'contract' => self::DELIVERY_CONTRACT,
                'message' => $this->metadata($delivery),
                'receiver_result' => $receiverResult,
            ];
        } catch (Throwable $error) {
            $this->record('message.failed', $delivery, 'error', ['exception_class' => $error::class]);
            throw $error;
        }
    }

    private function fsmId(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}\/[a-z][a-z0-9_-]{0,63}$/D', $value) !== 1) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_FSM_ID_INVALID:' . $value);
        }
        return $value;
    }

    private function signal(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z][a-z0-9_:-]{0,127}$/D', $value) !== 1) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_SIGNAL_INVALID:' . $value);
        }
        return $value;
    }

    private function messageId(string $value, string $error): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-f0-9]{32,64}$/D', $value) !== 1) {
            throw new InvalidArgumentException($error);
        }
        return $value;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function context(array $context): array
    {
        $count = 0;
        $scan = function (mixed $value, int $depth = 0) use (&$scan, &$count): void {
            if ($depth > 4) {
                throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_CONTEXT_DEPTH_EXCEEDED');
            }
            if (is_array($value)) {
                foreach ($value as $key => $entry) {
                    if (++$count > self::MAX_CONTEXT_ITEMS) {
                        throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_CONTEXT_ITEMS_EXCEEDED');
                    }
                    if (!is_int($key) && preg_match('/password|secret|token|csrf|authorization|cookie|credential/i', (string) $key) === 1) {
                        throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_SENSITIVE_CONTEXT_FORBIDDEN:' . (string) $key);
                    }
                    $scan($entry, $depth + 1);
                }
                return;
            }
            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                return;
            }
            if (is_string($value) && strlen($value) <= 512) {
                return;
            }
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_CONTEXT_VALUE_INVALID');
        };
        $scan($context);
        try {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_CONTEXT_INVALID', 0, $error);
        }
        if (strlen($encoded) > self::MAX_CONTEXT_BYTES) {
            throw new InvalidArgumentException('OPUS_FSM_SIGNAL_BUS_CONTEXT_TOO_LARGE');
        }
        return $context;
    }

    /** @param array<string,mixed> $message @param array<string,mixed> $extra */
    private function record(string $name, array $message, string $status, array $extra = []): void
    {
        $metadata = array_replace($this->metadata($message), $extra);
        $traceId = $this->profiler?->getActiveTrace()?->getTraceId();
        if ($this->logger instanceof LoggerInterface) {
            if ($status === 'success') {
                $this->logger->info('fsm.network', $name, $metadata, $traceId);
            } else {
                $this->logger->error('fsm.network', $name, $metadata, $traceId);
            }
        }
        if ($this->profiler?->getActiveTrace() !== null) {
            $this->profiler->event('fsm.network', $name, $metadata, $status, null, $this->parentSpanId);
        }
    }

    /** @param array<string,mixed> $message @return array<string,mixed> */
    private function metadata(array $message): array
    {
        return [
            'contract' => (string) ($message['contract'] ?? ''),
            'category' => (string) ($message['category'] ?? ''),
            'message_id' => (string) ($message['message_id'] ?? ''),
            'source_fsm' => (string) ($message['source_fsm'] ?? ''),
            'target_fsm' => (string) ($message['target_fsm'] ?? ''),
            'signal' => (string) ($message['signal'] ?? ''),
            'correlation_id' => (string) ($message['correlation_id'] ?? ''),
            'causation_id' => is_string($message['causation_id'] ?? null) ? $message['causation_id'] : null,
            'ttl' => (int) ($message['ttl'] ?? 0),
            'hop_count' => (int) ($message['hop_count'] ?? 0),
        ];
    }
}
