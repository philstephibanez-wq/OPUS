<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessRuleInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function id(): string;
    public function effect(): string;
    public function roleId(): string;
    public function permissionId(): string;
    public function scope(): AccessScopeInterface;
    /** @return array<string,mixed> */
    public function toArray(): array;
}
