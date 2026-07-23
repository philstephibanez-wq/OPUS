<?php
declare(strict_types=1);

namespace Opus\Rcp\Security;

use Opus\Http\Request;

interface RcpRequestAuthenticatorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $payload @param array<string,mixed> $server */
    public function authenticate(Request $request, array $payload, array $server): RcpIdentityInterface;
}
