<?php
declare(strict_types=1);

namespace Opus\Security\Acl;

use Opus\File\StructuredFileLoader;
use Opus\File\StructuredFileLoaderInterface;

final class AclPolicy implements AclPolicyInterface
{
    /** @var array<string,mixed> */
    private array $policy;

    public function __construct(string $policyFile, ?StructuredFileLoaderInterface $loader = null)
    {
        $decoded = ($loader ?? StructuredFileLoader::instance())->read($policyFile);
        if (($decoded['contract'] ?? null) !== 'OPUS_ACL_POLICY_V1' || ($decoded['default'] ?? null) !== 'deny') {
            throw new \RuntimeException('OPUS_ACL_POLICY_INVALID:' . $policyFile);
        }
        $this->policy = $decoded;
    }

    public function decide(array $roles, string $resource, string $action = 'open'): AclDecision
    {
        if ($resource === '' || $action === '') throw new \RuntimeException('OPUS_ACL_TARGET_INVALID');
        $rules = is_array($this->policy['roles'] ?? null) ? $this->policy['roles'] : [];
        foreach (array_values(array_unique($roles)) as $role) {
            if (!is_string($role) || $role === '') continue;
            foreach ((array) ($rules[$role] ?? []) as $grant) {
                if (!is_string($grant)) continue;
                if ($grant === '*:*' || $grant === $resource . ':*' || $grant === $resource . ':' . $action) {
                    return new AclDecision(true, 'OPUS_ACL_ALLOWED', $resource, $action);
                }
            }
        }
        return new AclDecision(false, 'OPUS_ACL_DENIED', $resource, $action);
    }
}
