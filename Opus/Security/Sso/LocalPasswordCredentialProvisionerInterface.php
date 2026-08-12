<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

interface LocalPasswordCredentialProvisionerInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * @param list<string> $roles
     * @return array<string,mixed>
     */
    public function provision(
        string $siteId,
        string $subject,
        string $password,
        array $roles = []
    ): array;
}
