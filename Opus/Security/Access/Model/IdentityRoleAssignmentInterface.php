<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface IdentityRoleAssignmentInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function provider(): string;
    public function subject(): string;
    public function roleId(): string;
    public function scope(): AccessScopeInterface;
    public function appliesTo(string $provider, string $subject, AccessResourceInterface $resource): bool;
    /** @return array<string,mixed> */
    public function toArray(): array;
}
