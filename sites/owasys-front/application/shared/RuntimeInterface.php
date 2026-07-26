<?php
declare(strict_types=1);

interface OwasysProcessRuntimeInterface
{
    public function mode(): string;
    public function run(): void;
    public function fail(Throwable $error, string $traceId): void;
}
