<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

interface LocalPasswordCredentialResetterInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function reset(
        string $siteId,
        string $subject,
        string $password
    ): array;
}
