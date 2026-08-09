<?php
declare(strict_types=1);

/** Transactional target-security mutation boundary owned by OWASYS back. */
interface OwasysSecurityMutationServiceInterface
{
    /**
     * @param array<string,mixed> $site
     * @param array<string,mixed> $acl
     * @param array<string,mixed> $sso
     * @return array<string,bool>
     */
    public function capabilities(
        string $siteId,
        array $site,
        array $acl,
        array $sso
    ): array;

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function preview(
        string $siteId,
        array $actor,
        array $request
    ): array;

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function commit(
        string $siteId,
        array $actor,
        array $request
    ): array;
}
