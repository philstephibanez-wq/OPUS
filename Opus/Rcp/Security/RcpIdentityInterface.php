<?php
declare(strict_types=1);

namespace Opus\Rcp\Security;

interface RcpIdentityInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function subject(): string;
    /** @return list<string> */
    public function roles(): array;
    /** @return array<string,mixed> */
    public function toArray(): array;
}
