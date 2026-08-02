<?php
declare(strict_types=1);

namespace Opus\Security\Acl;

use Opus\File\StructuredFileLoader;
use Opus\File\StructuredFileLoaderInterface;
use Opus\Profiler\ProfilerInterface;

final class AclPolicy implements AclPolicyInterface
{
    /** @var array<string,mixed> */
    private array $policy;

    public function __construct(
        string $policyFile,
        ?StructuredFileLoaderInterface $loader = null,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
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
        $effectiveRoles = array_values(array_unique(array_filter(
            $roles,
            static fn (mixed $role): bool => is_string($role) && $role !== ''
        )));
        foreach ($effectiveRoles as $role) {
            if (!is_string($role) || $role === '') continue;
            foreach ((array) ($rules[$role] ?? []) as $grant) {
                if (!is_string($grant)) continue;
                if ($grant === '*:*' || $grant === $resource . ':*' || $grant === $resource . ':' . $action) {
                    $decision = new AclDecision(true, 'OPUS_ACL_ALLOWED', $resource, $action);
                    $this->profileDecision($decision, $effectiveRoles, $role . ':' . $grant);

                    return $decision;
                }
            }
        }
        $decision = new AclDecision(false, 'OPUS_ACL_DENIED', $resource, $action);
        $this->profileDecision($decision, $effectiveRoles, 'default:deny');

        return $decision;
    }

    /** @param list<string> $roles */
    private function profileDecision(
        AclDecision $decision,
        array $roles,
        string $decisiveRule
    ): void {
        if ($this->profiler?->getActiveTrace() === null) {
            return;
        }
        $context = [
            'roles' => $roles,
            'resource' => $decision->resource,
            'action' => $decision->action,
            'scope' => null,
            'decision' => $decision->allowed ? 'allowed' : 'denied',
            'decision_code' => $decision->code,
            'decisive_rule' => $decisiveRule,
        ];
        $status = $decision->allowed ? 'success' : 'error';
        $this->profiler->event(
            'acl',
            'acl.decision.evaluated',
            $context,
            $status,
            $this->parentSpanId
        );
        if (!$decision->allowed) {
            $this->profiler->event(
                'acl',
                'acl.decision.denied',
                $context,
                'error',
                $this->parentSpanId
            );
        }
    }
}
