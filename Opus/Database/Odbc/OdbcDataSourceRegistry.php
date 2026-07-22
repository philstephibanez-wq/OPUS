<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

final class OdbcDataSourceRegistry
    implements OdbcDataSourceRegistryInterface
{
    public const CONTRACT = 'OPUS_ODBC_DATASOURCE_REGISTRY_V1';

    /** @var array<string,OdbcDataSourceConfig> */
    private array $sources = [];

    /**
     * @param list<array<string,mixed>> $sources
     */
    public function __construct(array $sources)
    {
        foreach ($sources as $source) {
            $configuration = OdbcDataSourceConfig::fromArray($source);
            $id = $configuration->id();

            if (isset($this->sources[$id])) {
                throw new \InvalidArgumentException(
                    'OPUS_ODBC_DATASOURCE_DUPLICATE: ' . $id
                );
            }

            $this->sources[$id] = $configuration;
        }

        if ($this->sources === []) {
            throw new \InvalidArgumentException(
                'OPUS_ODBC_DATASOURCE_REGISTRY_EMPTY'
            );
        }
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new \RuntimeException(
                'OPUS_ODBC_DATASOURCE_FILE_MISSING: ' . $file
            );
        }

        $extension = strtolower(
            pathinfo($file, PATHINFO_EXTENSION)
        );

        if ($extension === 'php') {
            $data = require $file;
        } elseif ($extension === 'json') {
            $data = json_decode(
                (string) file_get_contents($file),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } else {
            throw new \RuntimeException(
                'OPUS_ODBC_DATASOURCE_FILE_FORMAT_INVALID: ' . $file
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                'OPUS_ODBC_DATASOURCE_FILE_INVALID: ' . $file
            );
        }

        $sources = $data['sources'] ?? $data;

        if (!is_array($sources)) {
            throw new \RuntimeException(
                'OPUS_ODBC_DATASOURCE_LIST_INVALID: ' . $file
            );
        }

        return new self(array_values($sources));
    }

    public function has(string $id): bool
    {
        return isset($this->sources[$id]);
    }

    public function get(string $id): OdbcDataSourceConfig
    {
        if (!$this->has($id)) {
            throw new \OutOfBoundsException(
                'OPUS_ODBC_DATASOURCE_NOT_FOUND: ' . $id
            );
        }

        return $this->sources[$id];
    }

    /**
     * @return list<OdbcDataSourceConfig>
     */
    public function all(): array
    {
        return array_values($this->sources);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function describe(): array
    {
        return array_map(
            static fn (OdbcDataSourceConfig $source): array =>
                $source->toArray(),
            $this->all()
        );
    }
}
