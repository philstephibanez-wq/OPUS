<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\Crud;

/**
 * Driver-facing capability flags for guarded CRUD.
 */
final class OdbcCrudCapabilities
{
    private bool $insertSupported;
    private bool $updateSupported;
    private bool $deleteSupported;
    private bool $transactionsSupported;
    private bool $dryRunSupported;

    public function __construct(bool $insertSupported, bool $updateSupported, bool $deleteSupported, bool $transactionsSupported = false, bool $dryRunSupported = true)
    {
        $this->insertSupported = $insertSupported;
        $this->updateSupported = $updateSupported;
        $this->deleteSupported = $deleteSupported;
        $this->transactionsSupported = $transactionsSupported;
        $this->dryRunSupported = $dryRunSupported;
    }

    public static function guardedDefaults(): self
    {
        return new self(true, true, true, false, true);
    }

    public function supports(string $action): bool
    {
        $action = OdbcCrudAction::assertSupported($action);
        if ($action === OdbcCrudAction::INSERT) {
            return $this->insertSupported;
        }
        if ($action === OdbcCrudAction::UPDATE) {
            return $this->updateSupported;
        }

        return $this->deleteSupported;
    }

    public function assertSupports(string $action): void
    {
        $action = OdbcCrudAction::assertSupported($action);
        if (!$this->supports($action)) {
            throw new \RuntimeException('OPUS_ODBC_CRUD_CAPABILITY_UNSUPPORTED: ' . $action);
        }
    }

    public function transactionsSupported(): bool
    {
        return $this->transactionsSupported;
    }

    public function dryRunSupported(): bool
    {
        return $this->dryRunSupported;
    }

    /** @return array<string,bool> */
    public function toArray(): array
    {
        return [
            'insert' => $this->insertSupported,
            'update' => $this->updateSupported,
            'delete' => $this->deleteSupported,
            'transactions' => $this->transactionsSupported,
            'dry_run' => $this->dryRunSupported,
        ];
    }
}
