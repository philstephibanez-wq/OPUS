<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessPermissionInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function id(): string;
    public function resourceType(): string;
    public function action(): string;
    /** @return array<string,string> */
    public function toArray(): array;
}
