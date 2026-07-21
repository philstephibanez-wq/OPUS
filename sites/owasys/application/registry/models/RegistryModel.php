<?php
declare(strict_types=1);

use Opus\Owasys\RegistryRepository;

final class OwasysRegistryModel
{
    private readonly RegistryRepository $repository;
    private readonly string $seedFile;

    public function __construct(
        string $siteRoot,
        string $opusRoot,
        string $databaseRelative = 'var/registry/owasys.sqlite',
        string $seedRelative = 'config/registry.seed.json'
    ) {
        $this->repository = RegistryRepository::forOwasysSite(
            $siteRoot,
            $opusRoot,
            $databaseRelative
        );
        $this->seedFile = rtrim($siteRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, trim($seedRelative, '/'));
    }

    /** @return array<string,mixed> */
    public function synchronize(): array
    {
        return $this->repository->synchronize($this->seedFile);
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return $this->repository->entries();
    }

    /** @return list<array<string,mixed>> */
    public function recentEvents(int $limit = 8): array
    {
        return $this->repository->recentEvents($limit);
    }

    /** @return array<string,mixed>|null */
    public function select(string $applicationId, string $actorId): ?array
    {
        foreach ($this->entries() as $entry) {
            if ((string) ($entry['id'] ?? '') !== $applicationId) {
                continue;
            }

            $this->repository->setCurrentApplication($entry, $actorId);
            $_SESSION['owasys_current_app'] = $entry;

            return $entry;
        }

        return null;
    }

    public function clear(string $actorId): void
    {
        $this->repository->clearCurrentApplication($actorId);
        unset($_SESSION['owasys_current_app']);
    }

    public function startCreation(string $actorId): void
    {
        $this->clear($actorId);
        $this->repository->startCreationFlow($actorId);
    }
}
