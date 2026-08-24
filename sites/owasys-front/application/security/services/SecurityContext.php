<?php
declare(strict_types=1);

/** Request-local SecurityContext. It never stores credentials or secrets. */
final class OwasysSecurityContext implements OwasysSecurityContextWriterInterface
{
    /** @var array<string,mixed> */
    private array $data = [
        'contract' => 'OWASYS_SECURITY_CONTEXT_V1',
        'authenticated' => false,
        'subject' => '',
        'roles' => [],
        'provider' => '',
        'application_id' => '',
        'runtime_state' => 'anonymous',
    ];

    public function snapshot(): array { return $this->data; }
    public function authenticated(): bool { return $this->data['authenticated'] === true; }
    public function roles(): array { return $this->data['roles']; }
    public function provider(): string { return (string) $this->data['provider']; }
    public function applicationId(): string { return (string) $this->data['application_id']; }
    public function runtimeState(): string { return (string) $this->data['runtime_state']; }

    public function synchronize(array $identity, string $applicationId, string $runtimeState): void
    {
        $subject = trim((string) ($identity['subject'] ?? ''));
        $roles = is_array($identity['roles'] ?? null) ? array_values($identity['roles']) : [];
        $provider = trim((string) ($identity['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new RuntimeException('OWASYS_SECURITY_CONTEXT_IDENTITY_INVALID');
        }
        foreach ($roles as $role) {
            if (!is_string($role) || preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $role) !== 1) {
                throw new RuntimeException('OWASYS_SECURITY_CONTEXT_ROLES_INVALID');
            }
        }
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
            throw new RuntimeException('OWASYS_SECURITY_CONTEXT_APPLICATION_INVALID');
        }
        $this->data = [
            'contract' => 'OWASYS_SECURITY_CONTEXT_V1',
            'authenticated' => true,
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
            'application_id' => $applicationId,
            'runtime_state' => $this->state($runtimeState),
        ];
    }

    public function setRuntimeState(string $runtimeState): void
    {
        $this->data['runtime_state'] = $this->state($runtimeState);
    }

    private function state(string $state): string
    {
        $state = strtolower(trim($state));
        if (!in_array($state, ['anonymous', 'authenticating', 'authenticated', 'reauthenticating'], true)) {
            throw new RuntimeException('OWASYS_SECURITY_CONTEXT_RUNTIME_STATE_INVALID:' . $state);
        }
        return $state;
    }
}
