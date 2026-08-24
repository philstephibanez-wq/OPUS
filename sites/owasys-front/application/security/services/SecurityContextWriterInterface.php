<?php
declare(strict_types=1);

/** Writer contract owned by the OWASYS Security runtime. */
interface OwasysSecurityContextWriterInterface extends OwasysSecurityContextInterface
{
    /** @param array<string,mixed> $identity */
    public function synchronize(array $identity, string $applicationId, string $runtimeState): void;
    public function setRuntimeState(string $runtimeState): void;
}
