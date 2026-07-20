<?php
declare(strict_types=1);

return [
    'state' => 'security',
    'title' => 'Security',
    'badge' => 'Profiles',
    'summary' => 'Configuration of admin/dev profiles, RBAC, ACL, SSO, session policy and authentication audit strategy.',
    'sections' => ['Profiles', 'Permissions', 'Sessions', 'Audit'],
    'cards' => [
        [
            'title' => 'Profiles',
            'body' => 'OWASYS starts with explicit admin and dev profiles for generation and configuration work.',
            'items' => ['admin: registry, blueprint, generation, validation and export rights', 'dev: draft, structure, data, workflow, security draft and validation rights'],
        ],
        [
            'title' => 'Write action audit',
            'body' => 'Registry and generation mutations must be auditable and tied to an explicit session strategy.',
            'items' => ['session_strategy: explicit', 'audit_strategy: mandatory_for_write_actions'],
        ],
    ],
    'contracts' => ['OWASYS_SECURITY_POLICY_V1', 'OPUS_PROFILE_REGISTRY_V1'],
    'actions' => ['Assign profile', 'Configure permissions', 'Review audit strategy', 'Prepare SSO strategy as separate configuration'],
];