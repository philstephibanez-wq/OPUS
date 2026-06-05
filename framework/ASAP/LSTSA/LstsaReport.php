<?php

declare(strict_types=1);

namespace ASAP\LSTSA;

/**
 * PUBLIC LSTSA REPORT
 *
 * Role:
 *   Describe one LSTSA run in a stable, archivable format.
 */
final class LstsaReport
{
    /** @var array<string,int> */
    private array $counters = [
        'loaded' => 0,
        'accepted' => 0,
        'transformed' => 0,
        'stored' => 0,
        'rejected' => 0,
        'errors' => 0,
    ];

    /** @var list<string> */
    private array $messages = [];

    private ?string $finishedAt = null;
    private string $status = 'RUNNING';

    public function __construct(
        private readonly string $lstsaId,
        private readonly string $lstsaVersion,
        private readonly string $runId,
        private readonly string $startedAt,
        private readonly string $configHash
    ) {
        foreach ([$this->lstsaId, $this->lstsaVersion, $this->runId, $this->startedAt, $this->configHash] as $value) {
            if (trim($value) === '') {
                throw LstsaException::because('ASAP_LSTSA_REPORT_VALUE_EMPTY');
            }
        }
    }

    public static function create(string $lstsaId, string $lstsaVersion, string $runId, string $configSnapshot): self
    {
        return new self(
            $lstsaId,
            $lstsaVersion,
            $runId,
            gmdate('c'),
            hash('sha256', $configSnapshot)
        );
    }

    public function addCounter(string $name, int $value): void
    {
        if (!array_key_exists($name, $this->counters)) {
            throw LstsaException::because('ASAP_LSTSA_REPORT_COUNTER_UNKNOWN', $name);
        }

        if ($value < 0) {
            throw LstsaException::because('ASAP_LSTSA_REPORT_COUNTER_NEGATIVE', $name);
        }

        $this->counters[$name] += $value;
    }

    public function addMessage(string $message): void
    {
        $message = trim($message);
        if ($message !== '') {
            $this->messages[] = $message;
        }
    }

    public function finish(string $status): void
    {
        $allowed = ['OK', 'PARTIAL', 'FAILED', 'REJECTED', 'QUARANTINED', 'CANCELLED', 'TIMEOUT_EXCEEDED'];
        if (!in_array($status, $allowed, true)) {
            throw LstsaException::because('ASAP_LSTSA_REPORT_STATUS_INVALID', $status);
        }

        $this->status = $status;
        $this->finishedAt = gmdate('c');
    }

    public function runId(): string
    {
        return $this->runId;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'lstsa_id' => $this->lstsaId,
            'lstsa_version' => $this->lstsaVersion,
            'run_id' => $this->runId,
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'config_hash' => $this->configHash,
            'counters' => $this->counters,
            'messages' => $this->messages,
        ];
    }

    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw LstsaException::because('ASAP_LSTSA_REPORT_JSON_ENCODE_FAILED');
        }

        return $json . "\n";
    }

    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = '# LSTSA run report';
        $lines[] = '';
        foreach ($this->toArray() as $key => $value) {
            if (is_array($value)) {
                $lines[] = '## ' . $key;
                foreach ($value as $itemKey => $itemValue) {
                    $lines[] = '- ' . $itemKey . ': ' . (is_scalar($itemValue) ? (string) $itemValue : json_encode($itemValue));
                }
                $lines[] = '';
                continue;
            }

            $lines[] = '- ' . $key . ': ' . ($value === null ? '' : (string) $value);
        }

        return implode("\n", $lines) . "\n";
    }
}
