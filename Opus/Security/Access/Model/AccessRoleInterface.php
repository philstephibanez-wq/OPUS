<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessRoleInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function id(): string;

    /** @return list<string> */
    public function permissions(): array;

    /** @return list<string> */
    public function parents(): array;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
