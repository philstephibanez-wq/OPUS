<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessResourceInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function id(): string;
    public function type(): string;
    public function applicationId(): string;
    /** @return array<string,string> */
    public function toArray(): array;
}
