<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer;

use Opus\Database\Odbc\OdbcDataSourceConfig;

/**
 * Data-driven registry of OPUS ODBC data sources visible to the explorer.
 */
final class OdbcExplorerDataSourceRegistry
{
    /** @var array<string,OdbcDataSourceConfig> */
    private array $configs;

    /**
     * @param list<OdbcDataSourceConfig> $configs
     */
    public function __construct(array $configs)
    {
        if ($configs === []) {
            throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_DATASOURCES_EMPTY');
        }

        $indexed = [];
        foreach ($configs as $config) {
            if (!$config instanceof OdbcDataSourceConfig) {
                throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_DATASOURCE_INVALID');
            }
            if (isset($indexed[$config->id()])) {
                throw new \InvalidArgumentException('OPUS_ODBC_EXPLORER_DATASOURCE_DUPLICATE: ' . $config->id());
            }
            $indexed[$config->id()] = $config;
        }

        $this->configs = $indexed;
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function fromArray(array $items): self
    {
        $configs = [];
        foreach ($items as $item) {
            $configs[] = OdbcDataSourceConfig::fromArray($item);
        }

        return new self($configs);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->configs);
    }

    public function config(string $id): OdbcDataSourceConfig
    {
        if (!isset($this->configs[$id])) {
            throw new \RuntimeException('OPUS_ODBC_EXPLORER_DATASOURCE_NOT_FOUND: ' . $id);
        }

        return $this->configs[$id];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            static fn (OdbcDataSourceConfig $config): array => $config->toArray(),
            $this->configs
        ));
    }
}
