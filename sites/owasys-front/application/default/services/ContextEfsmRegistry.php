<?php
declare(strict_types=1);

/** Single authority for OWASYS contextual EFSM ownership and runtime session keys. */
final class OwasysContextEfsmRegistry
{
    /** @var array<string,string> */
    private const HOST_EFSMS = [
        'registry' => 'registry',
        'application' => 'application',
        'data' => 'data',
        'source' => 'source',
        'git' => 'git',
        'build' => 'build',
    ];

    public function isHostEfsm(string $efsmId): bool
    {
        return isset(self::HOST_EFSMS[strtolower(trim($efsmId))]);
    }

    /** @param array<string,mixed> $pageData */
    public function efsmIdForPage(array $pageData): string
    {
        $module = strtolower(trim((string) ($pageData['fsm']['module'] ?? '')));
        $explicit = strtolower(trim((string) ($pageData['fsm']['context_efsm'] ?? '')));

        if ($module === 'source' && in_array($explicit, ['source', 'git'], true)) {
            return $explicit;
        }
        if (isset(self::HOST_EFSMS[$module])) {
            return self::HOST_EFSMS[$module];
        }
        return match ($module) {
            'structure' => 'navigation',
            'security' => 'security',
            default => '',
        };
    }

    /** @param array<string,mixed> $pageData @return array<string,mixed> */
    public function forPage(array $pageData): array
    {
        $efsmId = $this->efsmIdForPage($pageData);
        if ($efsmId === '') {
            return [];
        }
        $host = $this->isHostEfsm($efsmId);
        return [
            'efsm_id' => $efsmId,
            'host' => $host,
            'application_id' => $host
                ? 'owasys-front'
                : strtolower(trim((string) ($pageData['current_app']['id'] ?? ''))),
            'session_key' => $host ? $this->sessionKey($efsmId) : '',
            'navigation_state' => $host ? $this->navigationState($efsmId) : '',
        ];
    }

    public function sessionKey(string $efsmId): string
    {
        $efsmId = strtolower(trim($efsmId));
        if (!$this->isHostEfsm($efsmId)) {
            throw new RuntimeException('OWASYS_CONTEXT_EFSM_HOST_UNKNOWN:' . $efsmId);
        }
        return 'opus.fsm.owasys-front.' . $efsmId;
    }

    public function navigationState(string $efsmId): string
    {
        $efsmId = strtolower(trim($efsmId));
        if (!$this->isHostEfsm($efsmId)) {
            throw new RuntimeException('OWASYS_CONTEXT_EFSM_HOST_UNKNOWN:' . $efsmId);
        }
        return $efsmId === 'git' ? 'source' : $efsmId;
    }

    /** @return list<string> */
    public function hostEfsmIds(): array
    {
        return array_keys(self::HOST_EFSMS);
    }
}
