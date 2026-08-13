<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Log\Logger;
use Opus\Profiler\ProfilerInterface;

/**
 * Stateless preview/confirm/commit pipeline for target-security changes.
 *
 * The service never mutates OWASYS itself, never transports a secret and never
 * approximates an unsupported authorization model. Every commit is protected by
 * an optimistic state hash, a deterministic confirmation token, a fresh
 * front-bastion reauthentication assertion and an atomic rollback boundary.
 */
final class OwasysSecurityMutationService
    implements OwasysSecurityMutationServiceInterface
{
    private const PREVIEW_CONTRACT = 'OWASYS_SECURITY_MUTATION_PREVIEW_V1';
    private const COMMIT_CONTRACT = 'OWASYS_SECURITY_MUTATION_COMMIT_V1';
    private const MAX_REAUTH_AGE_SECONDS = 120;
    private const MUTATIONS = [
        'identity.reference' => true,
        'identity.update' => true,
        'identity.delete' => true,
        'role.create' => true,
        'permission.grant' => true,
        'assignment.grant' => true,
        'resource.allow' => true,
    ];

    private readonly File $file;
    private readonly Json $json;
    private readonly StructuredFileLoader $loader;
    private readonly Logger $logger;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot,
        private readonly ?ProfilerInterface $profiler = null
    ) {
        $this->file = File::instance();
        $this->json = Json::instance();
        $this->loader = StructuredFileLoader::instance();
        $this->logger = new Logger(
            $this->siteRoot . '/var/logs',
            'owasys-back.log'
        );
    }

    public function capabilities(
        string $siteId,
        array $site,
        array $acl,
        array $sso
    ): array {
        $mutable = $this->targetMutable($siteId, $site);
        $local = $this->localStoreDescriptor(
            $this->targetRoot($siteId),
            $sso,
            false
        );
        $identityLifecycle = $mutable
            && $this->identityReferenceRepresentable($sso);

        return [
            'target_mutable' => $mutable,
            'identity_reference' => $identityLifecycle,
            'identity_update' => $identityLifecycle,
            'identity_delete' => $identityLifecycle,
            'role_create' => $mutable
                && $this->supportedAclContract($acl),
            'permission_grant' => $mutable
                && $this->supportedAclContract($acl),
            'assignment_grant' => $mutable
                && is_array($local)
                && ($local['exists'] ?? false) === true,
            'resource_allow' => $mutable
                && $this->supportedAclContract($acl),
            'destructive_mutations' => $identityLifecycle,
        ];
    }

    public function preview(
        string $siteId,
        array $actor,
        array $request
    ): array {
        $preview = $this->buildPreview(
            $siteId,
            $actor,
            $request,
            'preview'
        );
        $traceId = $this->traceId($request);
        $this->logger->info(
            'security.mutation',
            'preview.succeeded',
            [
                'application_id' => $siteId,
                'actor' => (string) ($actor['subject'] ?? ''),
                'mutation' => (string) ($preview['mutation']['type'] ?? ''),
                'reason' => (string) ($preview['reason'] ?? ''),
                'before_state_hash' => (string) (
                    $preview['current_state_hash'] ?? ''
                ),
                'after_state_hash' => (string) (
                    $preview['proposed_state_hash'] ?? ''
                ),
                'files' => count((array) ($preview['files'] ?? [])),
                'result' => 'previewed',
            ],
            $traceId
        );
        $this->profile('preview.succeeded', [
            'application_id' => $siteId,
            'mutation' => (string) ($preview['mutation']['type'] ?? ''),
            'files' => count((array) ($preview['files'] ?? [])),
        ]);
        return $preview;
    }

    public function commit(
        string $siteId,
        array $actor,
        array $request
    ): array {
        $parameters = $this->parameters($request);
        $expected = strtolower(trim((string) (
            $parameters['expected_state_hash'] ?? ''
        )));
        $confirmation = strtolower(trim((string) (
            $parameters['confirmation_token'] ?? ''
        )));
        if (preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_EXPECTED_STATE_HASH_INVALID'
            );
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $confirmation) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_CONFIRMATION_INVALID'
            );
        }

        $preview = $this->buildPreview(
            $siteId,
            $actor,
            $request,
            'commit'
        );
        if (!hash_equals(
            (string) $preview['current_state_hash'],
            $expected
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_STATE_CONFLICT'
            );
        }
        if (!hash_equals(
            (string) $preview['confirmation_token'],
            $confirmation
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_CONFIRMATION_MISMATCH'
            );
        }

        $writes = is_array($preview['_writes'] ?? null)
            ? $preview['_writes']
            : [];
        if ($writes === []) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_WRITESET_EMPTY'
            );
        }
        $context = $this->loadTarget($siteId);
        if (!hash_equals(
            (string) $preview['current_state_hash'],
            $this->stateHash($context['raw'])
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_STATE_CONFLICT'
            );
        }

        $before = [];
        foreach (array_keys($writes) as $relative) {
            $path = $context['target_root'] . '/' . $relative;
            $before[$relative] = $this->file->exists($path)
                ? $this->file->read($path)
                : null;
        }

        try {
            foreach ($writes as $relative => $document) {
                if (!is_string($relative) || !is_array($document)) {
                    throw new RuntimeException(
                        'OWASYS_SECURITY_MUTATION_WRITESET_INVALID'
                    );
                }
                $this->file->writeAtomic(
                    $context['target_root'] . '/' . $relative,
                    $this->json->encode($document, true)
                );
            }
            $this->validateWrites($context, $writes);
            $after = $this->loadTarget($siteId);
            $afterHash = $this->stateHash($after['raw']);
            if (!hash_equals(
                (string) $preview['proposed_state_hash'],
                $afterHash
            )) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_MUTATION_POST_STATE_MISMATCH'
                );
            }
        } catch (Throwable $error) {
            $this->rollback($context['target_root'], $before);
            $this->logger->error(
                'security.mutation',
                'commit.rolled_back',
                [
                    'application_id' => $siteId,
                    'actor' => (string) ($actor['subject'] ?? ''),
                    'mutation' => (string) (
                        $preview['mutation']['type'] ?? ''
                    ),
                    'reason' => (string) ($preview['reason'] ?? ''),
                    'before_state_hash' => (string) (
                        $preview['current_state_hash'] ?? ''
                    ),
                    'proposed_state_hash' => (string) (
                        $preview['proposed_state_hash'] ?? ''
                    ),
                    'error_code' => $this->safeErrorCode($error),
                    'result' => 'rolled_back',
                ],
                $this->traceId($request)
            );
            $this->profile('commit.rolled_back', [
                'application_id' => $siteId,
                'mutation' => (string) (
                    $preview['mutation']['type'] ?? ''
                ),
                'error_code' => $this->safeErrorCode($error),
            ], 'error');
            throw $error;
        }

        $traceId = $this->traceId($request);
        $this->logger->info(
            'security.mutation',
            'commit.succeeded',
            [
                'application_id' => $siteId,
                'actor' => (string) ($actor['subject'] ?? ''),
                'mutation' => (string) ($preview['mutation']['type'] ?? ''),
                'reason' => (string) ($preview['reason'] ?? ''),
                'before_state_hash' => (string) (
                    $preview['current_state_hash'] ?? ''
                ),
                'after_state_hash' => (string) (
                    $preview['proposed_state_hash'] ?? ''
                ),
                'files' => array_values(array_keys($writes)),
                'result' => 'committed',
            ],
            $traceId
        );
        $this->profile('commit.succeeded', [
            'application_id' => $siteId,
            'mutation' => (string) ($preview['mutation']['type'] ?? ''),
            'files' => count($writes),
        ]);

        return [
            'contract' => self::COMMIT_CONTRACT,
            'application' => ['id' => $siteId],
            'mutation' => $preview['mutation'],
            'reason' => $preview['reason'],
            'actor' => $preview['actor'],
            'before_state_hash' => $preview['current_state_hash'],
            'after_state_hash' => $preview['proposed_state_hash'],
            'files_written' => array_values(array_keys($writes)),
            'diff' => $preview['diff'],
            'affected_subjects' => $preview['affected_subjects'],
            'access_delta' => $preview['access_delta'],
            'audit' => [
                'event' => 'security.mutation.committed',
                'trace_id' => $traceId,
                'secret_logged' => false,
                'rollback_required' => false,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function buildPreview(
        string $siteId,
        array $actor,
        array $request,
        string $phase
    ): array {
        $this->assertSiteId($siteId);
        $parameters = $this->parameters($request);
        (new OwasysFreshAuthProofService())->assertValid(
            (string) ($parameters['fresh_auth_proof'] ?? ''),
            $actor,
            $siteId,
            (string) ($parameters['mutation_json'] ?? ''),
            $phase
        );
        $reason = trim((string) ($parameters['reason'] ?? ''));
        if (strlen($reason) < 3
            || strlen($reason) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_REASON_INVALID'
            );
        }
        $mutationJson = (string) ($parameters['mutation_json'] ?? '');
        if ($mutationJson === '' || strlen($mutationJson) > 16384) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_PAYLOAD_INVALID'
            );
        }
        $mutation = $this->normalizeMutation(
            $this->json->parse(
                $mutationJson,
                'owasys:security-mutation'
            )
        );
        $context = $this->loadTarget($siteId);
        if (!$this->targetMutable($siteId, $context['site'])) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_TARGET_PROTECTED'
            );
        }
        $plan = $this->plan($context, $mutation);
        $currentHash = $this->stateHash($context['raw']);
        $proposedRaw = $context['raw'];
        foreach ($plan['writes'] as $relative => $document) {
            $proposedRaw[$relative] = $this->json->encode(
                $document,
                true
            );
        }
        $proposedHash = $this->stateHash($proposedRaw);
        $actorView = [
            'subject' => (string) ($actor['subject'] ?? ''),
            'provider' => (string) ($actor['provider'] ?? ''),
        ];
        $confirmation = hash(
            'sha256',
            $siteId
            . "\n"
            . $currentHash
            . "\n"
            . $proposedHash
            . "\n"
            . $this->json->encode($mutation, false)
            . "\n"
            . $this->json->encode($actorView, false)
            . "\n"
            . $reason
        );

        return [
            'contract' => self::PREVIEW_CONTRACT,
            'application' => ['id' => $siteId],
            'mutation' => $mutation,
            'reason' => $reason,
            'actor' => $actorView,
            'current_state_hash' => $currentHash,
            'proposed_state_hash' => $proposedHash,
            'confirmation_token' => $confirmation,
            'files' => array_values(array_keys($plan['writes'])),
            'diff' => $plan['diff'],
            'affected_subjects' => $plan['affected_subjects'],
            'access_delta' => [
                'gained' => $plan['gained'],
                'lost' => is_array($plan['lost'] ?? null)
                    ? $plan['lost']
                    : [],
            ],
            '_writes' => $plan['writes'],
        ];
    }

    /** @return array<string,mixed> */
    private function loadTarget(string $siteId): array
    {
        $targetRoot = $this->targetRoot($siteId);
        $paths = [
            'config/site.json',
            'config/acl.json',
            'config/sso.json',
        ];
        foreach ($paths as $relative) {
            if (!$this->file->exists($targetRoot . '/' . $relative)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_CONFIG_MISSING:' . $relative
                );
            }
        }
        $site = $this->loader->read($targetRoot . '/config/site.json');
        $acl = $this->loader->read($targetRoot . '/config/acl.json');
        $sso = $this->loader->read($targetRoot . '/config/sso.json');
        if (($site['contract'] ?? null)
            !== 'OPUS_SITE_STANDARD_CONTRACT_CORE') {
            throw new RuntimeException(
                'OWASYS_SECURITY_SITE_CONTRACT_INVALID'
            );
        }
        if (strtolower(trim((string) ($site['site_id'] ?? '')))
            !== $siteId) {
            throw new RuntimeException('OWASYS_SECURITY_SITE_ID_MISMATCH');
        }
        if (!$this->supportedAclContract($acl)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ACL_CONTRACT_INVALID:'
                . (string) ($acl['contract'] ?? '')
            );
        }
        if (!in_array(
            (string) ($sso['contract'] ?? ''),
            [
                'OPUS_SSO_CONFIGURATION_V1',
                'OPUS_GENERATED_APPLICATION_SSO_V1',
            ],
            true
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_SSO_CONTRACT_INVALID:'
                . (string) ($sso['contract'] ?? '')
            );
        }

        $raw = [];
        foreach ($paths as $relative) {
            $raw[$relative] = $this->file->read(
                $targetRoot . '/' . $relative
            );
        }
        $onboardingRelative = 'config/security.onboarding.json';
        $onboarding = null;
        if ($this->file->exists($targetRoot . '/' . $onboardingRelative)) {
            $onboarding = $this->loader->read(
                $targetRoot . '/' . $onboardingRelative
            );
            if (($onboarding['contract'] ?? null)
                !== 'OPUS_SECURITY_ONBOARDING_V1') {
                throw new RuntimeException(
                    'OWASYS_SECURITY_ONBOARDING_CONTRACT_INVALID'
                );
            }
            $raw[$onboardingRelative] = $this->file->read(
                $targetRoot . '/' . $onboardingRelative
            );
        } else {
            $raw[$onboardingRelative] = null;
        }

        $local = $this->localStoreDescriptor($targetRoot, $sso, false);
        if (is_array($local)) {
            $raw[(string) $local['relative']] =
                ($local['exists'] ?? false) === true
                    ? $this->file->read((string) $local['path'])
                    : null;
        }

        return [
            'site_id' => $siteId,
            'target_root' => $targetRoot,
            'site' => $site,
            'acl' => $acl,
            'sso' => $sso,
            'onboarding' => $onboarding,
            'local_store' => $local,
            'raw' => $raw,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $mutation
     * @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>}
     */
    private function plan(array $context, array $mutation): array
    {
        return match ($mutation['type']) {
            'identity.reference' => $this->planIdentityReference(
                $context,
                $mutation
            ),
            'identity.update' => $this->planIdentityUpdate(
                $context,
                $mutation
            ),
            'identity.delete' => $this->planIdentityDelete(
                $context,
                $mutation
            ),
            'role.create' => $this->planRoleCreate($context, $mutation),
            'permission.grant' => $this->planPermissionGrant(
                $context,
                $mutation
            ),
            'assignment.grant' => $this->planAssignmentGrant(
                $context,
                $mutation
            ),
            'resource.allow' => $this->planResourceAllow(
                $context,
                $mutation
            ),
            default => throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_UNSUPPORTED'
            ),
        };
    }

    /** @return array<string,mixed> */
    private function normalizeMutation(array $mutation): array
    {
        $type = strtolower(trim((string) ($mutation['type'] ?? '')));
        if (!isset(self::MUTATIONS[$type])) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_TYPE_INVALID'
            );
        }
        return match ($type) {
            'identity.reference' => $this->normalizedFields(
                $mutation,
                $type,
                ['provider', 'subject', 'identity_type']
            ),
            'identity.update' => $this->normalizedFields(
                $mutation,
                $type,
                ['provider', 'subject', 'identity_type']
            ),
            'identity.delete' => $this->normalizedFields(
                $mutation,
                $type,
                ['provider', 'subject']
            ),
            'role.create' => $this->normalizedFields(
                $mutation,
                $type,
                ['role']
            ),
            'permission.grant' => $this->normalizedFields(
                $mutation,
                $type,
                ['role', 'permission']
            ),
            'assignment.grant' => $this->normalizedFields(
                $mutation,
                $type,
                ['subject', 'role']
            ),
            'resource.allow' => $this->normalizedFields(
                $mutation,
                $type,
                ['resource', 'action', 'role']
            ),
        };
    }

    /** @param list<string> $fields @return array<string,string> */
    private function normalizedFields(
        array $mutation,
        string $type,
        array $fields
    ): array {
        $expected = array_fill_keys(['type', ...$fields], true);
        foreach (array_keys($mutation) as $key) {
            if (!is_string($key) || !isset($expected[$key])) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_MUTATION_FIELD_UNKNOWN'
                );
            }
        }
        $normalized = ['type' => $type];
        foreach ($fields as $field) {
            $value = trim((string) ($mutation[$field] ?? ''));
            if ($value === '') {
                throw new RuntimeException(
                    'OWASYS_SECURITY_MUTATION_FIELD_REQUIRED:' . $field
                );
            }
            $this->assertField($field, $value);
            $normalized[$field] = $value;
        }
        return $normalized;
    }

    private function assertField(string $field, string $value): void
    {
        $pattern = match ($field) {
            'provider', 'role' => '/^[a-z][a-z0-9._-]{0,63}$/D',
            'identity_type' => '/^(?:user|agent)$/D',
            'subject' => '/^[A-Za-z0-9._|:@+\\-]{1,160}$/D',
            'permission' => '/^(?:\\*|[a-z][a-z0-9._-]{0,63}):(?:\\*|[a-z][a-z0-9._-]{0,63})$/D',
            'resource', 'action' => '/^(?:\\*|[a-z][a-z0-9._-]{0,63})$/D',
            default => null,
        };
        if ($pattern === null || preg_match($pattern, $value) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_FIELD_INVALID:' . $field
            );
        }
    }

    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planIdentityReference(array $context, array $mutation): array
    {
        if (!$this->identityReferenceRepresentable($context['sso'])) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_REFERENCE_UNSUPPORTED'
            );
        }
        $provider = (string) $mutation['provider'];
        $identityType = strtolower(trim((string) (
            $mutation['identity_type'] ?? ''
        )));
        if (!in_array($identityType, ['user', 'agent'], true)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_TYPE_INVALID'
            );
        }
        $subject = (string) $mutation['subject'];
        $providers = is_array($context['sso']['providers'] ?? null)
            ? $context['sso']['providers']
            : [];
        $providerConfig = is_array($providers[$provider] ?? null)
            ? $providers[$provider]
            : null;
        if (!is_array($providerConfig)
            || ($providerConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_PROVIDER_INVALID'
            );
        }
        $onboarding = is_array($context['onboarding'] ?? null)
            ? $context['onboarding']
            : null;
        $defaultProvider = trim((string) (
            $context['sso']['default_provider'] ?? ''
        ));
        if ($onboarding === null) {
            if ($provider !== $defaultProvider) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_ONBOARDING_PROVIDER_MISMATCH'
                );
            }
            $onboarding = [
                'contract' => 'OPUS_SECURITY_ONBOARDING_V1',
                'provider' => $provider,
                'identities' => [],
                'secrets_versioned' => false,
            ];
            $local = $this->localStoreDescriptor(
                $context['target_root'],
                $context['sso'],
                false
            );
            if ($provider === 'local-password' && is_array($local)) {
                $onboarding['runtime_store'] = (string) $local['relative'];
            }
        }
        if ((string) ($onboarding['provider'] ?? '') !== $provider) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ONBOARDING_PROVIDER_MISMATCH'
            );
        }
        $identities = is_array($onboarding['identities'] ?? null)
            ? array_values(array_filter(
                $onboarding['identities'],
                'is_array'
            ))
            : [];
        foreach ($identities as $identity) {
            if ((string) ($identity['subject'] ?? '') === $subject) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_IDENTITY_ALREADY_REFERENCED'
                );
            }
        }
        $identities[] = [
            'subject' => $subject,
            'identity_type' => $identityType,
            'roles' => [],
            'status' => $provider === 'local-password'
                ? 'password-setup-required'
                : 'active',
        ];
        usort(
            $identities,
            static fn (array $a, array $b): int => strcmp(
                (string) ($a['subject'] ?? ''),
                (string) ($b['subject'] ?? '')
            )
        );
        $onboarding['identities'] = $identities;
        return [
            'writes' => ['config/security.onboarding.json' => $onboarding],
            'diff' => [[
                'path' => 'config/security.onboarding.json',
                'summary' => 'identity.reference:' . $provider . ':' . $subject,
            ]],
            'affected_subjects' => [$provider . ':' . $subject],
            'gained' => [],
        ];
    }

    /* BEGIN OPUS R45D2A24 IDENTITY LIFECYCLE */

    /** @return array<string,mixed> */
    private function planIdentityUpdate(
        array $context,
        array $mutation
    ): array {
        if (!$this->identityReferenceRepresentable($context['sso'])) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_UPDATE_UNSUPPORTED'
            );
        }

        $provider = (string) $mutation['provider'];
        $subject = (string) $mutation['subject'];
        $identityType = (string) $mutation['identity_type'];

        $this->assertIdentityProviderEnabled(
            $context['sso'],
            $provider
        );

        $onboarding = $this->editableOnboarding(
            $context,
            $provider
        );
        $identities = is_array($onboarding['identities'] ?? null)
            ? array_values(array_filter(
                $onboarding['identities'],
                'is_array'
            ))
            : [];

        $matched = null;
        foreach ($identities as $index => $identity) {
            if ((string) ($identity['subject'] ?? '') === $subject) {
                $matched = $index;
                break;
            }
        }

        $runtime = $this->runtimeIdentity(
            $context,
            $provider,
            $subject
        );

        if (!is_int($matched) && !is_array($runtime)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_NOT_FOUND'
            );
        }

        if (is_int($matched)) {
            $currentType = strtolower(trim((string) (
                $identities[$matched]['identity_type'] ?? ''
            )));
            if ($currentType === $identityType) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_IDENTITY_UPDATE_UNCHANGED'
                );
            }
            $identities[$matched]['identity_type'] = $identityType;
        } else {
            $runtimeEntry = is_array($runtime['entry'] ?? null)
                ? $runtime['entry']
                : [];
            $identities[] = [
                'subject' => $subject,
                'identity_type' => $identityType,
                'roles' => is_array($runtime['roles'] ?? null)
                    ? $runtime['roles']
                    : [],
                'status' => ($runtimeEntry['must_change_password'] ?? false)
                    === true
                        ? 'password-setup-required'
                        : 'active',
            ];
        }

        usort(
            $identities,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['subject'] ?? ''),
                (string) ($right['subject'] ?? '')
            )
        );

        $onboarding['identities'] = $identities;

        return [
            'writes' => [
                'config/security.onboarding.json' => $onboarding,
            ],
            'diff' => [[
                'path' => 'config/security.onboarding.json',
                'summary' => 'identity.update:'
                    . $provider
                    . ':'
                    . $subject
                    . ':'
                    . $identityType,
            ]],
            'affected_subjects' => [$provider . ':' . $subject],
            'gained' => [],
            'lost' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function planIdentityDelete(
        array $context,
        array $mutation
    ): array {
        if (!$this->identityReferenceRepresentable($context['sso'])) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_DELETE_UNSUPPORTED'
            );
        }

        $provider = (string) $mutation['provider'];
        $subject = (string) $mutation['subject'];

        $this->assertIdentityProviderEnabled(
            $context['sso'],
            $provider
        );

        $onboarding = is_array($context['onboarding'] ?? null)
            ? $context['onboarding']
            : null;
        $onboardingMatched = false;
        $onboardingRoles = [];

        if (is_array($onboarding)
            && (string) ($onboarding['provider'] ?? '') === $provider) {
            $remaining = [];
            foreach ((array) ($onboarding['identities'] ?? []) as $identity) {
                if (!is_array($identity)) {
                    continue;
                }

                if ((string) ($identity['subject'] ?? '') === $subject) {
                    $onboardingMatched = true;
                    $onboardingRoles = array_values(array_filter(
                        is_array($identity['roles'] ?? null)
                            ? $identity['roles']
                            : [],
                        'is_string'
                    ));
                    continue;
                }

                $remaining[] = $identity;
            }

            if ($onboardingMatched) {
                usort(
                    $remaining,
                    static fn (array $left, array $right): int => strcmp(
                        (string) ($left['subject'] ?? ''),
                        (string) ($right['subject'] ?? '')
                    )
                );
                $onboarding['identities'] = $remaining;
            }
        }

        $runtime = $this->runtimeIdentity(
            $context,
            $provider,
            $subject
        );

        if (!$onboardingMatched && !is_array($runtime)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_NOT_FOUND'
            );
        }

        $runtimeRoles = is_array($runtime['roles'] ?? null)
            ? $runtime['roles']
            : [];
        $targetRoles = array_values(array_unique(array_merge(
            $onboardingRoles,
            $runtimeRoles
        )));
        sort($targetRoles, SORT_STRING);

        $this->assertIdentityDeletionSafe(
            $context,
            $provider,
            $subject,
            $targetRoles
        );

        $writes = [];
        $diff = [];

        if ($onboardingMatched && is_array($onboarding)) {
            $writes['config/security.onboarding.json'] = $onboarding;
            $diff[] = [
                'path' => 'config/security.onboarding.json',
                'summary' => 'identity.delete:'
                    . $provider
                    . ':'
                    . $subject,
            ];
        }

        if (is_array($runtime)) {
            $local = is_array($context['local_store'] ?? null)
                ? $context['local_store']
                : null;
            if (!is_array($local)
                || ($local['exists'] ?? false) !== true) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_REQUIRED'
                );
            }

            $store = $this->loader->read((string) $local['path']);
            if (($store['contract'] ?? null)
                !== ($local['contract'] ?? null)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_INVALID'
                );
            }

            $users = is_array($store['users'] ?? null)
                ? $store['users']
                : [];
            unset($users[(string) $runtime['username']]);
            $store['users'] = $users;

            $relative = (string) $local['relative'];
            $writes[$relative] = $store;
            $diff[] = [
                'path' => $relative,
                'summary' => 'identity.delete-credential:'
                    . $subject,
            ];
        }

        $lost = [];
        foreach ($targetRoles as $role) {
            $lost[] = $subject . '->' . $role . '@application';
        }
        sort($lost, SORT_STRING);

        return [
            'writes' => $writes,
            'diff' => $diff,
            'affected_subjects' => [$provider . ':' . $subject],
            'gained' => [],
            'lost' => $lost,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function editableOnboarding(
        array $context,
        string $provider
    ): array {
        $onboarding = is_array($context['onboarding'] ?? null)
            ? $context['onboarding']
            : null;

        if (is_array($onboarding)) {
            if ((string) ($onboarding['provider'] ?? '') !== $provider) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_ONBOARDING_PROVIDER_MISMATCH'
                );
            }

            return $onboarding;
        }

        $defaultProvider = trim((string) (
            $context['sso']['default_provider'] ?? ''
        ));
        if ($provider !== $defaultProvider) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ONBOARDING_PROVIDER_MISMATCH'
            );
        }

        $onboarding = [
            'contract' => 'OPUS_SECURITY_ONBOARDING_V1',
            'provider' => $provider,
            'identities' => [],
            'secrets_versioned' => false,
        ];

        $local = is_array($context['local_store'] ?? null)
            ? $context['local_store']
            : null;
        if ($provider === 'local-password' && is_array($local)) {
            $onboarding['runtime_store'] =
                (string) $local['relative'];
        }

        return $onboarding;
    }

    private function assertIdentityProviderEnabled(
        array $sso,
        string $provider
    ): void {
        $providers = is_array($sso['providers'] ?? null)
            ? $sso['providers']
            : [];
        $providerConfig = is_array($providers[$provider] ?? null)
            ? $providers[$provider]
            : null;

        if (!is_array($providerConfig)
            || ($providerConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException(
                'OWASYS_SECURITY_IDENTITY_PROVIDER_INVALID'
            );
        }
    }

    /** @return array<string,mixed>|null */
    private function runtimeIdentity(
        array $context,
        string $provider,
        string $subject
    ): ?array {
        if ($provider !== 'local-password') {
            return null;
        }

        $local = is_array($context['local_store'] ?? null)
            ? $context['local_store']
            : null;
        if (!is_array($local)
            || ($local['exists'] ?? false) !== true) {
            return null;
        }

        $store = $this->loader->read((string) $local['path']);
        if (($store['contract'] ?? null)
            !== ($local['contract'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_INVALID'
            );
        }

        foreach ((array) ($store['users'] ?? []) as $username => $entry) {
            if (!is_string($username) || !is_array($entry)) {
                continue;
            }

            $entrySubject = trim((string) ($entry['id'] ?? $username));
            if ($username !== $subject && $entrySubject !== $subject) {
                continue;
            }

            $roles = is_array($entry['roles'] ?? null)
                ? array_values(array_filter(
                    $entry['roles'],
                    'is_string'
                ))
                : [];
            if ($roles === []) {
                $profile = trim((string) ($entry['profile'] ?? ''));
                if ($profile !== '') {
                    $roles = [$profile];
                }
            }
            $roles = array_values(array_unique($roles));
            sort($roles, SORT_STRING);

            return [
                'username' => $username,
                'entry' => $entry,
                'roles' => $roles,
            ];
        }

        return null;
    }

    /**
     * @param list<string> $targetRoles
     */
    private function assertIdentityDeletionSafe(
        array $context,
        string $provider,
        string $subject,
        array $targetRoles
    ): void {
        $administrative = $this->administrativeRoles(
            $context['acl']
        );
        if ($administrative === []
            || array_intersect($targetRoles, $administrative) === []) {
            return;
        }

        $identities = $this->effectiveIdentityRoles($context);
        unset($identities[$provider . ':' . $subject]);

        foreach ($identities as $roles) {
            if (array_intersect($roles, $administrative) !== []) {
                return;
            }
        }

        throw new RuntimeException(
            'OWASYS_SECURITY_LAST_ADMINISTRATOR_DELETE_FORBIDDEN'
        );
    }

    /** @return list<string> */
    private function administrativeRoles(array $acl): array
    {
        $contract = (string) ($acl['contract'] ?? '');
        $administrative = [];

        if ($contract === 'OPUS_ACL_POLICY_V1') {
            foreach ((array) ($acl['roles'] ?? []) as $role => $grants) {
                if (!is_string($role) || !is_array($grants)) {
                    continue;
                }

                foreach ($grants as $grant) {
                    if (!is_string($grant)) {
                        continue;
                    }
                    $grant = trim($grant);
                    if ($grant === '*:*'
                        || $grant === 'security:*'
                        || $grant === 'security:manage') {
                        $administrative[$role] = true;
                        break;
                    }
                }
            }
        } elseif ($contract === 'OPUS_GENERATED_APPLICATION_ACL_V1') {
            $intersection = null;
            $public = [
                'everyone' => true,
                'anonymous' => true,
                'guest' => true,
            ];

            foreach ((array) ($acl['policies'] ?? []) as $policy) {
                if (!is_array($policy)) {
                    continue;
                }

                $roles = [];
                foreach ((array) ($policy['roles'] ?? []) as $role) {
                    if (!is_string($role)) {
                        continue;
                    }
                    $role = trim($role);
                    if ($role === '' || isset($public[$role])) {
                        continue;
                    }
                    $roles[$role] = true;
                }

                if ($roles === []) {
                    continue;
                }

                $intersection = $intersection === null
                    ? $roles
                    : array_intersect_key($intersection, $roles);
            }

            if (is_array($intersection)) {
                $administrative = $intersection;
            }
        }

        $roles = array_keys($administrative);
        sort($roles, SORT_STRING);

        return $roles;
    }

    /** @return array<string,list<string>> */
    private function effectiveIdentityRoles(array $context): array
    {
        $result = [];

        $onboarding = is_array($context['onboarding'] ?? null)
            ? $context['onboarding']
            : null;
        if (is_array($onboarding)) {
            $provider = (string) ($onboarding['provider'] ?? '');
            foreach ((array) ($onboarding['identities'] ?? []) as $identity) {
                if (!is_array($identity)) {
                    continue;
                }
                $subject = trim((string) (
                    $identity['subject'] ?? ''
                ));
                if ($provider === '' || $subject === '') {
                    continue;
                }
                $key = $provider . ':' . $subject;
                $result[$key] ??= [];
                $result[$key] = array_values(array_unique(array_merge(
                    $result[$key],
                    array_values(array_filter(
                        is_array($identity['roles'] ?? null)
                            ? $identity['roles']
                            : [],
                        'is_string'
                    ))
                )));
            }
        }

        $local = is_array($context['local_store'] ?? null)
            ? $context['local_store']
            : null;
        if (is_array($local)
            && ($local['exists'] ?? false) === true) {
            $store = $this->loader->read((string) $local['path']);
            if (($store['contract'] ?? null)
                !== ($local['contract'] ?? null)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_INVALID'
                );
            }

            foreach ((array) ($store['users'] ?? []) as $username => $entry) {
                if (!is_string($username) || !is_array($entry)) {
                    continue;
                }
                $subject = trim((string) (
                    $entry['id'] ?? $username
                ));
                if ($subject === '') {
                    continue;
                }
                $roles = is_array($entry['roles'] ?? null)
                    ? array_values(array_filter(
                        $entry['roles'],
                        'is_string'
                    ))
                    : [];
                if ($roles === []) {
                    $profile = trim((string) (
                        $entry['profile'] ?? ''
                    ));
                    if ($profile !== '') {
                        $roles = [$profile];
                    }
                }
                $key = 'local-password:' . $subject;
                $result[$key] ??= [];
                $result[$key] = array_values(array_unique(array_merge(
                    $result[$key],
                    $roles
                )));
            }
        }

        foreach ($result as &$roles) {
            sort($roles, SORT_STRING);
        }
        unset($roles);
        ksort($result, SORT_STRING);

        return $result;
    }

    /* END OPUS R45D2A24 IDENTITY LIFECYCLE */
    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planRoleCreate(array $context, array $mutation): array
    {
        $role = (string) $mutation['role'];
        $acl = $context['acl'];
        $contract = (string) ($acl['contract'] ?? '');
        if ($this->roleExists($acl, $role)) {
            throw new RuntimeException('OWASYS_SECURITY_ROLE_ALREADY_EXISTS');
        }
        if ($contract === 'OPUS_ACL_POLICY_V1') {
            $roles = is_array($acl['roles'] ?? null) ? $acl['roles'] : [];
            $roles[$role] = [];
            ksort($roles, SORT_STRING);
            $acl['roles'] = $roles;
        } else {
            $roles = array_values(array_unique(array_filter(
                is_array($acl['roles'] ?? null) ? $acl['roles'] : [],
                'is_string'
            )));
            $roles[] = $role;
            $roles = array_values(array_unique($roles));
            sort($roles, SORT_STRING);
            $acl['roles'] = $roles;
        }
        return [
            'writes' => ['config/acl.json' => $acl],
            'diff' => [[
                'path' => 'config/acl.json',
                'summary' => 'role.create:' . $role,
            ]],
            'affected_subjects' => [],
            'gained' => [],
        ];
    }

    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planPermissionGrant(array $context, array $mutation): array
    {
        $role = (string) $mutation['role'];
        $permission = (string) $mutation['permission'];
        if (!$this->roleExists($context['acl'], $role)) {
            throw new RuntimeException('OWASYS_SECURITY_ROLE_NOT_FOUND');
        }
        [$resource, $action] = $this->permissionParts($permission);
        return $this->planAllow(
            $context,
            $role,
            $resource,
            $action,
            'permission.grant:' . $role . ':' . $permission
        );
    }

    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planAssignmentGrant(array $context, array $mutation): array
    {
        $subject = (string) $mutation['subject'];
        $role = (string) $mutation['role'];
        if (!$this->roleExists($context['acl'], $role)) {
            throw new RuntimeException('OWASYS_SECURITY_ROLE_NOT_FOUND');
        }
        $local = is_array($context['local_store'] ?? null)
            ? $context['local_store']
            : null;
        if (!is_array($local) || ($local['exists'] ?? false) !== true) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ASSIGNMENT_RUNTIME_STORE_REQUIRED'
            );
        }
        $store = $this->loader->read((string) $local['path']);
        if (($store['contract'] ?? null) !== ($local['contract'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_INVALID'
            );
        }
        $users = is_array($store['users'] ?? null) ? $store['users'] : [];
        $matched = null;
        foreach ($users as $username => $user) {
            if (!is_string($username) || !is_array($user)) {
                continue;
            }
            if ($username === $subject
                || (string) ($user['id'] ?? '') === $subject) {
                $matched = $username;
                break;
            }
        }
        if (!is_string($matched)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ASSIGNMENT_IDENTITY_NOT_FOUND'
            );
        }
        $user = $users[$matched];
        $roles = is_array($user['roles'] ?? null)
            ? array_values(array_filter($user['roles'], 'is_string'))
            : [];
        if (in_array($role, $roles, true)) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ASSIGNMENT_ALREADY_EXISTS'
            );
        }
        $roles[] = $role;
        $roles = array_values(array_unique($roles));
        sort($roles, SORT_STRING);
        $user['roles'] = $roles;
        $users[$matched] = $user;
        $store['users'] = $users;
        return [
            'writes' => [(string) $local['relative'] => $store],
            'diff' => [[
                'path' => (string) $local['relative'],
                'summary' => 'assignment.grant:' . $subject . ':' . $role,
            ]],
            'affected_subjects' => [$subject],
            'gained' => [$subject . '->' . $role . '@application'],
        ];
    }

    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planResourceAllow(array $context, array $mutation): array
    {
        $role = (string) $mutation['role'];
        if (!$this->roleExists($context['acl'], $role)) {
            throw new RuntimeException('OWASYS_SECURITY_ROLE_NOT_FOUND');
        }
        return $this->planAllow(
            $context,
            $role,
            (string) $mutation['resource'],
            (string) $mutation['action'],
            'resource.allow:'
                . $mutation['resource']
                . ':'
                . $mutation['action']
                . ':'
                . $role
        );
    }

    /** @return array{writes:array<string,array<string,mixed>>,diff:list<array<string,string>>,affected_subjects:list<string>,gained:list<string>} */
    private function planAllow(
        array $context,
        string $role,
        string $resource,
        string $action,
        string $summary
    ): array {
        $acl = $context['acl'];
        $permission = $resource . ':' . $action;
        $contract = (string) ($acl['contract'] ?? '');
        if ($contract === 'OPUS_ACL_POLICY_V1') {
            $roles = is_array($acl['roles'] ?? null) ? $acl['roles'] : [];
            $grants = is_array($roles[$role] ?? null)
                ? array_values(array_filter($roles[$role], 'is_string'))
                : [];
            if (in_array($permission, $grants, true)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_PERMISSION_ALREADY_GRANTED'
                );
            }
            $grants[] = $permission;
            $grants = array_values(array_unique($grants));
            sort($grants, SORT_STRING);
            $roles[$role] = $grants;
            $acl['roles'] = $roles;
        } else {
            $permissions = array_values(array_unique(array_filter(
                is_array($acl['permissions'] ?? null)
                    ? $acl['permissions']
                    : [],
                'is_string'
            )));
            if (!in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
                sort($permissions, SORT_STRING);
                $acl['permissions'] = $permissions;
            }
            $policies = is_array($acl['policies'] ?? null)
                ? $acl['policies']
                : [];
            $policyId = $this->policyId($policies, $resource, $action);
            $policy = is_array($policies[$policyId] ?? null)
                ? $policies[$policyId]
                : [];
            $allowed = is_array($policy['roles'] ?? null)
                ? array_values(array_filter($policy['roles'], 'is_string'))
                : [];
            if (in_array($role, $allowed, true)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_PERMISSION_ALREADY_GRANTED'
                );
            }
            $allowed[] = $role;
            $allowed = array_values(array_unique($allowed));
            sort($allowed, SORT_STRING);
            $policy['roles'] = $allowed;
            $policies[$policyId] = $policy;
            ksort($policies, SORT_STRING);
            $acl['policies'] = $policies;
        }
        return [
            'writes' => ['config/acl.json' => $acl],
            'diff' => [[
                'path' => 'config/acl.json',
                'summary' => $summary,
            ]],
            'affected_subjects' => [],
            'gained' => [$role . '->' . $permission],
        ];
    }

    /** @param array<string,array<string,mixed>> $writes */
    private function validateWrites(array $context, array $writes): void
    {
        foreach (array_keys($writes) as $relative) {
            $data = $this->loader->read(
                $context['target_root'] . '/' . $relative
            );
            if ($relative === 'config/acl.json') {
                if (($data['contract'] ?? null)
                    !== ($context['acl']['contract'] ?? null)) {
                    throw new RuntimeException(
                        'OWASYS_SECURITY_MUTATION_ACL_VALIDATION_FAILED'
                    );
                }
                continue;
            }
            if ($relative === 'config/security.onboarding.json') {
                if (($data['contract'] ?? null)
                    !== 'OPUS_SECURITY_ONBOARDING_V1') {
                    throw new RuntimeException(
                        'OWASYS_SECURITY_MUTATION_ONBOARDING_VALIDATION_FAILED'
                    );
                }
                continue;
            }
            $local = $context['local_store'] ?? null;
            if (is_array($local)
                && $relative === (string) ($local['relative'] ?? '')) {
                if (($data['contract'] ?? null)
                    !== ($local['contract'] ?? null)) {
                    throw new RuntimeException(
                        'OWASYS_SECURITY_MUTATION_LOCAL_STORE_VALIDATION_FAILED'
                    );
                }
                continue;
            }
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_WRITE_PATH_UNEXPECTED'
            );
        }
    }

    /** @param array<string,string|null> $before */
    private function rollback(string $targetRoot, array $before): void
    {
        $errors = [];
        foreach (array_reverse(array_keys($before)) as $relative) {
            try {
                $path = $targetRoot . '/' . $relative;
                if ($before[$relative] === null) {
                    $this->file->delete($path);
                } else {
                    $this->file->writeAtomic(
                        $path,
                        (string) $before[$relative]
                    );
                }
            } catch (Throwable $rollbackError) {
                $errors[] = $this->safeErrorCode($rollbackError);
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_ROLLBACK_FAILED:'
                . implode(',', $errors)
            );
        }
    }

    /** @param array<string,string|null> $raw */
    private function stateHash(array $raw): string
    {
        ksort($raw, SORT_STRING);
        $parts = [];
        foreach ($raw as $relative => $contents) {
            $parts[] = $relative
                . "\0"
                . ($contents === null
                    ? 'ABSENT'
                    : hash('sha256', $contents));
        }
        return hash('sha256', implode("\n", $parts));
    }

    /** @return array<string,mixed> */
    private function parameters(array $request): array
    {
        return is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
    }

    private function targetMutable(string $siteId, array $site): bool
    {
        if (in_array($siteId, ['owasys-front', 'owasys-back'], true)) {
            return false;
        }
        return (string) ($site['role'] ?? '')
                === 'generated-opus-application'
            && (string) ($site['generated_by'] ?? '') === 'composer';
    }

    private function identityReferenceRepresentable(array $sso): bool
    {
        return (string) ($sso['contract'] ?? '')
            === 'OPUS_GENERATED_APPLICATION_SSO_V1';
    }

    private function supportedAclContract(array $acl): bool
    {
        return in_array(
            (string) ($acl['contract'] ?? ''),
            ['OPUS_ACL_POLICY_V1', 'OPUS_GENERATED_APPLICATION_ACL_V1'],
            true
        );
    }

    private function roleExists(array $acl, string $role): bool
    {
        if ((string) ($acl['contract'] ?? '') === 'OPUS_ACL_POLICY_V1') {
            return is_array($acl['roles'] ?? null)
                && array_key_exists($role, $acl['roles']);
        }
        return in_array(
            $role,
            array_values(array_filter(
                is_array($acl['roles'] ?? null) ? $acl['roles'] : [],
                'is_string'
            )),
            true
        );
    }

    /** @return array{0:string,1:string} */
    private function permissionParts(string $permission): array
    {
        $parts = explode(':', $permission, 2);
        return [$parts[0], $parts[1] ?? 'open'];
    }

    /** @param array<string,mixed> $policies */
    private function policyId(
        array $policies,
        string $resource,
        string $action
    ): string {
        if (in_array($action, ['view', 'open'], true)
            && array_key_exists($resource, $policies)) {
            return $resource;
        }
        return $resource . ':' . $action;
    }

    /** @return array<string,mixed>|null */
    private function localStoreDescriptor(
        string $targetRoot,
        array $sso,
        bool $required
    ): ?array {
        $providers = is_array($sso['providers'] ?? null)
            ? $sso['providers']
            : [];
        $local = is_array($providers['local-password'] ?? null)
            ? $providers['local-password']
            : [];
        if (($local['enabled'] ?? false) !== true) {
            if ($required) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_DISABLED'
                );
            }
            return null;
        }
        $relative = trim((string) (
            $local['runtime_store']
            ?? $local['store']
            ?? ''
        ));
        if ($relative === '') {
            if ($required) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_PATH_MISSING'
                );
            }
            return null;
        }
        $relative = $this->safeRelative($relative);
        $contract = trim((string) (
            $local['store_contract']
            ?? ((string) ($sso['contract'] ?? '')
                === 'OPUS_GENERATED_APPLICATION_SSO_V1'
                ? 'OPUS_LOCAL_USER_STORE_V1'
                : '')
        ));
        if ($contract === '') {
            if ($required) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_MISSING'
                );
            }
            return null;
        }
        $path = $targetRoot . '/' . $relative;
        return [
            'relative' => $relative,
            'path' => $path,
            'contract' => $contract,
            'exists' => $this->file->exists($path),
        ];
    }

    private function targetRoot(string $siteId): string
    {
        $this->assertSiteId($siteId);
        return rtrim(str_replace('\\', '/', $this->opusRoot), '/')
            . '/sites/'
            . $siteId;
    }

    private function assertSiteId(string $siteId): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_SITE_ID_INVALID'
            );
        }
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_MUTATION_PATH_INVALID'
            );
        }
        return $path;
    }

    private function traceId(array $request): string
    {
        $trace = strtolower(trim((string) ($request['trace_id'] ?? '')));
        return preg_match('/^[a-f0-9]{16,64}$/D', $trace) === 1
            ? $trace
            : bin2hex(random_bytes(16));
    }

    /** @param array<string,mixed> $context */
    private function profile(
        string $event,
        array $context,
        string $status = 'success'
    ): void {
        if ($this->profiler?->getActiveTrace() === null) {
            return;
        }
        $this->profiler->event(
            'security.mutation',
            $event,
            $context,
            $status
        );
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        if (preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1) {
            return $message;
        }
        return 'OWASYS_SECURITY_MUTATION_FAILED';
    }
}
