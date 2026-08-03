<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessScopeInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function level(): string;
    public function applicationId(): string;
    public function resourceType(): ?string;
    public function resourceId(): ?string;
    public function contains(AccessResourceInterface $resource): bool;
    /** @return array<string,string|null> */
    public function toArray(): array;
}
