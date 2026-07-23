<?php
declare(strict_types=1);

namespace Opus\Console\Application;

interface ApplicationCommandDispatcherInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function supports(string $command): bool;

    /**
     * @param list<string> $arguments
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public function execute(string $command, array $arguments, array $request): array;
}
