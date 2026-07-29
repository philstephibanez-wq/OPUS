<?php
declare(strict_types=1);

namespace Opus\Api\Security;

use Opus\Http\Request;

interface RestRequestAuthenticatorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $server */
    public function authenticate(Request $request, array $server): RestIdentityInterface;
}
