<?php
declare(strict_types=1);

namespace Opus\Security\Acl;

/**
 * Contract interface for Opus\Security\Acl\AclPolicy.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface AclPolicyInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param list<string> $roles */
    public function decide(array $roles, string $resource, string $action = 'open'): AclDecision;
}
