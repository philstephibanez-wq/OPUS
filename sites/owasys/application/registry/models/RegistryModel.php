<?php
declare(strict_types=1);

final class OwasysRegistryModel
{
    private readonly OwasysRegistryRepository $repository;
    private readonly string $seedFile;

    public function __construct(
        string $siteRoot,
        string $opusRoot,
        string $databaseRelative = 'var/registry/owasys.sqlite',
        string $seedRelative = 'config/registry.seed.json'
    ) {
        $this->repository = OwasysRegistryRepository::forSite(
            $siteRoot,
            $opusRoot,
            $databaseRelative
        );
        $this->seedFile = rtrim($siteRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                trim($seedRelative, '/')
            );
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
    public function find(string $applicationId): ?array
    {
        foreach ($this->entries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $applicationId) {
                return $entry;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $application */
    public function setCurrent(
        array $application,
        string $actorId
    ): void {
        $this->repository->setCurrentApplication(
            $application,
            $actorId
        );
    }

    public function clear(string $actorId): void
    {
        $this->repository->clearCurrentApplication($actorId);
    }

    public function startCreation(string $actorId): void
    {
        $this->repository->startCreationFlow($actorId);
    }
}
