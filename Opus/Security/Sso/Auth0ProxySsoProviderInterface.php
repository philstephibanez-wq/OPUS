<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

/** Contract for an identity asserted by a trusted Auth0 reverse proxy/bastion. */
interface Auth0ProxySsoProviderInterface extends
    SsoProviderInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
}
