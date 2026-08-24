<?php
declare(strict_types=1);

/** Read-only SecurityContext projection consumed outside Security ownership. */
interface OwasysSecurityContextInterface
{
    /** @return array<string,mixed> */
    public function snapshot(): array;
    public function authenticated(): bool;
    /** @return list<string> */
    public function roles(): array;
    public function provider(): string;
    public function applicationId(): string;
    public function runtimeState(): string;
}
