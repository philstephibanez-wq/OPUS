<?php

declare(strict_types=1);

namespace ASAP\LSTSA;

use ASAP\Database\DatabaseConnectionsConfig;

/**
 * PUBLIC LSTSA DEFINITION
 *
 * Role:
 *   Hold one Load / Secure / Transform / Store / Archive contract.
 */
final class LstsaDefinition
{
    /**
     * @param array<string,LstsaFieldConstraint> $loadFields
     * @param array<string,LstsaFieldMapping> $mappings
     * @param array<string,int> $runtime
     */
    public function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly string $loadConnection,
        private readonly string $loadTable,
        private readonly array $loadFields,
        private readonly string $storeConnection,
        private readonly string $storeTable,
        private readonly string $storeMode,
        private readonly array $mappings,
        private readonly string $archiveMode,
        private readonly string $archivePath,
        private readonly ?string $archiveConnection = null,
        private readonly ?string $archiveTable = null,
        private readonly array $runtime = []
    ) {
        foreach ([$this->id, $this->version, $this->loadConnection, $this->loadTable, $this->storeConnection, $this->storeTable, $this->storeMode, $this->archiveMode, $this->archivePath] as $value) {
            if (trim($value) === '') {
                throw LstsaException::because('ASAP_LSTSA_DEFINITION_VALUE_EMPTY', $this->id);
            }
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $this->id)) {
            throw LstsaException::because('ASAP_LSTSA_ID_INVALID', $this->id);
        }

        if ($this->loadFields === []) {
            throw LstsaException::because('ASAP_LSTSA_LOAD_FIELDS_EMPTY', $this->id);
        }

        if ($this->mappings === []) {
            throw LstsaException::because('ASAP_LSTSA_MAPPINGS_EMPTY', $this->id);
        }

        if ($this->archiveMode !== 'append_only') {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_MODE_UNSUPPORTED', $this->archiveMode);
        }
    }

    public function id(): string { return $this->id; }
    public function version(): string { return $this->version; }
    public function loadConnection(): string { return $this->loadConnection; }
    public function loadTable(): string { return $this->loadTable; }
    public function storeConnection(): string { return $this->storeConnection; }
    public function storeTable(): string { return $this->storeTable; }
    public function storeMode(): string { return $this->storeMode; }
    public function archiveMode(): string { return $this->archiveMode; }
    public function archivePath(): string { return $this->archivePath; }
    public function archiveConnection(): ?string { return $this->archiveConnection; }
    public function archiveTable(): ?string { return $this->archiveTable; }

    /** @return array<string,LstsaFieldConstraint> */
    public function loadFields(): array { return $this->loadFields; }

    /** @return array<string,LstsaFieldMapping> */
    public function mappings(): array { return $this->mappings; }

    /** @return array<string,int> */
    public function runtime(): array { return $this->runtime; }

    public function assertConnections(DatabaseConnectionsConfig $connections): void
    {
        foreach ([$this->loadConnection, $this->storeConnection] as $name) {
            if (!$connections->has($name)) {
                throw LstsaException::because('ASAP_LSTSA_DATABASE_CONNECTION_MISSING', $name);
            }
        }

        if ($this->archiveConnection !== null && !$connections->has($this->archiveConnection)) {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_CONNECTION_MISSING', $this->archiveConnection);
        }
    }
}
