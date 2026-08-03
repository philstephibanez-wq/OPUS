<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

interface AccessAuthorizationRequestInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function provider(): string;
    public function subject(): string;
    public function permission(): AccessPermissionInterface;
    public function resource(): AccessResourceInterface;
    /** @return array<string,mixed> */
    public function toArray(): array;
}
