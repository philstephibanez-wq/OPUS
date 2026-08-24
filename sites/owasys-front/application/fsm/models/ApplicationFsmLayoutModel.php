<?php
declare(strict_types=1);

use Opus\Api\Rest\RestClient;
use Opus\Api\Rest\RestClientInterface;
use Opus\Profiler\ProfilerInterface;

/** Read-only secured projection of one selected application's EFSM layout. */
final class OwasysApplicationFsmLayoutModel
{
    private readonly RestClientInterface $rest;

    public function __construct(
        string $siteRoot,
        ?RestClientInterface $rest = null,
        ?ProfilerInterface $profiler = null
    ) {
        $this->rest = $rest ?? RestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/')
                . '/config/rest-api.json',
            $profiler
        );
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function snapshot(
        string $siteId,
        string $efsmId,
        array $actor
    ): array {
        $siteId = strtolower(trim($siteId));
        $efsmId = strtolower(trim($efsmId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $efsmId) !== 1) {
            throw new RuntimeException(
                'OWASYS_APPLICATION_EFSM_LAYOUT_ID_INVALID'
            );
        }

        $result = $this->rest->request(
            'GET',
            '/api/v1/applications/' . rawurlencode($siteId)
                . '/fsm/layouts/' . rawurlencode($efsmId),
            [],
            $this->actor($actor)
        );

        if (($result['contract'] ?? null)
                !== 'OWASYS_EFSM_LAYOUT_SNAPSHOT_V1'
            || (string) ($result['application_id'] ?? '') !== $siteId
            || (string) ($result['efsm_id'] ?? '') !== $efsmId
            || !is_bool($result['layout_present'] ?? null)
            || !is_string($result['source_path'] ?? null)
            || !is_string($result['layout_path'] ?? null)
            || !is_string($result['source_sha256'] ?? null)
            || !is_string($result['layout_sha256'] ?? null)
            || !is_array($result['layout'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_APPLICATION_EFSM_LAYOUT_RESULT_INVALID'
            );
        }

        $sourcePath = trim(str_replace(
            '\\',
            '/',
            (string) $result['source_path']
        ), '/');
        $layoutPath = trim(str_replace(
            '\\',
            '/',
            (string) $result['layout_path']
        ), '/');
        $sourceHash = strtolower(trim((string) $result['source_sha256']));
        $layoutHash = strtolower(trim((string) $result['layout_sha256']));
        $layout = $result['layout'];

        if ($sourcePath === ''
            || $layoutPath === ''
            || str_contains($sourcePath, '..')
            || str_contains($layoutPath, '..')
            || preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1
            || ($layoutHash !== ''
                && preg_match('/^[a-f0-9]{64}$/D', $layoutHash) !== 1)
            || ($layout['contract'] ?? null)
                !== \Opus\Fsm\FsmDiagramLayoutStore::CONTRACT
            || (string) ($layout['fsm_path'] ?? '') !== $sourcePath
            || (string) ($layout['definition_sha256'] ?? '') !== $sourceHash
            || !in_array(
                (string) ($layout['layout_direction'] ?? ''),
                ['horizontal', 'vertical'],
                true
            )
            || !is_array($layout['canvas'] ?? null)
            || !is_array($layout['states'] ?? null)
            || !is_array($layout['transitions'] ?? null)
            || !is_array($layout['markers'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_APPLICATION_EFSM_LAYOUT_CONTRACT_INVALID'
            );
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{subject:string,roles:list<string>,provider:string}
     */
    private function actor(array $actor): array
    {
        $subject = trim((string) ($actor['subject'] ?? ''));
        $provider = trim((string) ($actor['provider'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        if ($subject === '' || $provider === '' || $roles === []) {
            throw new RuntimeException(
                'OWASYS_APPLICATION_EFSM_LAYOUT_ACTOR_INVALID'
            );
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }
}
