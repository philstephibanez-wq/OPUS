<?php
declare(strict_types=1);

/** Single authority for OWASYS contextual EFSM ownership and runtime session keys. */
final class OwasysContextEfsmRegistry
{
    /** @var array<string,string> */
    private const HOST_EFSMS = [
        'registry' => 'registry',
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

        /*
         * Application is not an OWASYS-host EFSM. Its designer projection is
         * the selected application's canonical navigation/application FSM.
         * No substitution to owasys-front is permitted.
         */
        if ($module === 'application') {
            return 'navigation';
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
        $applicationId = $host
            ? 'owasys-front'
            : strtolower(trim((string) ($pageData['current_app']['id'] ?? '')));

        if (!$host
            && preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $applicationId) !== 1) {
            throw new RuntimeException(
                'OWASYS_CONTEXT_EFSM_APPLICATION_REQUIRED:' . $efsmId
            );
        }

        return [
            'efsm_id' => $efsmId,
            'host' => $host,
            'application_id' => $applicationId,
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
