<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Declarative model-driven LSTSAR configuration.
 *
 * It is intentionally engine-agnostic: source and destination databases are
 * declared through ODBC datasource identifiers and OPUS TableModel identifiers.
 */
final class LstsarConfig
{
    public const CONTRACT = 'OPUS_LSTSAR_MODEL_DRIVEN_ODBC_CONFIG_V1';

    private string $runId;
    /** @var array<string,mixed> */
    private array $source;
    /** @var array<string,mixed> */
    private array $destination;
    /** @var array<string,string> */
    private array $mapping;
    /** @var array<string,mixed> */
    private array $security;
    /** @var array<string,mixed> */
    private array $transform;
    /** @var array<string,mixed> */
    private array $archive;
    /** @var array<string,mixed> */
    private array $report;
    /** @var array<string,mixed> */
    private array $metadata;

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $destination
     * @param array<string,string> $mapping
     * @param array<string,mixed> $security
     * @param array<string,mixed> $transform
     * @param array<string,mixed> $archive
     * @param array<string,mixed> $report
     * @param array<string,mixed> $metadata
     */
    private function __construct(string $runId, array $source, array $destination, array $mapping, array $security = [], array $transform = [], array $archive = [], array $report = [], array $metadata = [])
    {
        $runId = trim($runId);
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,120}$/', $runId)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_RUN_ID_INVALID: ' . $runId);
        }
        $this->assertEndpoint($source, 'SOURCE');
        $this->assertEndpoint($destination, 'DESTINATION');
        if ($mapping === []) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_MAPPING_EMPTY');
        }
        foreach ($mapping as $sourceField => $destinationField) {
            $this->assertFieldIdentifier((string) $sourceField, 'SOURCE_MAPPING');
            $this->assertFieldIdentifier((string) $destinationField, 'DESTINATION_MAPPING');
        }

        $this->runId = $runId;
        $this->source = $source;
        $this->destination = $destination;
        $this->mapping = $mapping;
        $this->security = $security;
        $this->transform = $transform;
        $this->archive = $archive;
        $this->report = $report;
        $this->metadata = $metadata;
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        if (($data['contract'] ?? '') !== self::CONTRACT) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_MODEL_DRIVEN_CONFIG_CONTRACT_INVALID');
        }

        return new self(
            (string) ($data['run_id'] ?? ''),
            isset($data['source']) && is_array($data['source']) ? $data['source'] : [],
            isset($data['destination']) && is_array($data['destination']) ? $data['destination'] : [],
            isset($data['mapping']) && is_array($data['mapping']) ? array_map('strval', $data['mapping']) : [],
            isset($data['security']) && is_array($data['security']) ? $data['security'] : [],
            isset($data['transform']) && is_array($data['transform']) ? $data['transform'] : [],
            isset($data['archive']) && is_array($data['archive']) ? $data['archive'] : [],
            isset($data['report']) && is_array($data['report']) ? $data['report'] : [],
            isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : []
        );
    }

    public function runId(): string
    {
        return $this->runId;
    }

    /** @return array<string,mixed> */
    public function source(): array
    {
        return $this->source;
    }

    /** @return array<string,mixed> */
    public function destination(): array
    {
        return $this->destination;
    }

    /** @return array<string,string> */
    public function mapping(): array
    {
        return $this->mapping;
    }

    /** @return array<string,mixed> */
    public function security(): array
    {
        return $this->security;
    }

    /** @return array<string,mixed> */
    public function transform(): array
    {
        return $this->transform;
    }

    /** @return array<string,mixed> */
    public function archive(): array
    {
        return $this->archive;
    }

    /** @return array<string,mixed> */
    public function report(): array
    {
        return $this->report;
    }

    /** @return array<string,mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'run_id' => $this->runId,
            'source' => $this->source,
            'destination' => $this->destination,
            'mapping' => $this->mapping,
            'security' => $this->security,
            'transform' => $this->transform,
            'archive' => $this->archive,
            'report' => $this->report,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string,mixed> $endpoint */
    private function assertEndpoint(array $endpoint, string $kind): void
    {
        foreach (['datasource', 'model'] as $key) {
            $value = trim((string) ($endpoint[$key] ?? ''));
            if ($value === '' || !preg_match('/^[a-zA-Z0-9_\-.]{1,120}$/', $value)) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_' . $kind . '_' . strtoupper($key) . '_INVALID: ' . $value);
            }
        }
        if (($endpoint['driver'] ?? 'odbc') !== 'odbc') {
            throw new \InvalidArgumentException('OPUS_LSTSAR_' . $kind . '_DRIVER_FORBIDDEN');
        }
    }

    private function assertFieldIdentifier(string $field, string $kind): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim($field))) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_' . $kind . '_FIELD_INVALID: ' . $field);
        }
    }
}
