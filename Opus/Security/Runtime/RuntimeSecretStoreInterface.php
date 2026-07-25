<?php
declare(strict_types=1);

namespace Opus\Security\Runtime;

interface RuntimeSecretStoreInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * @param array<string,string> $bindings environment name => secret alias
     * @return array<string,string> environment name => secret value
     */
    public function ensure(array $bindings): array;
}
