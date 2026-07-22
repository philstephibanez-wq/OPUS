<?php
declare(strict_types=1);

namespace Opus\Security\Identity;

/**
 * Contract for an authenticated or anonymous OPUS identity.
 */
interface IdentityContextInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function subject(): string;

    public function isAnonymous(): bool;

    /** @return list<string> */
    public function roles(): array;

    /** @return list<string> */
    public function scopes(): array;

    /** @return array<string,mixed> */
    public function claims(): array;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
