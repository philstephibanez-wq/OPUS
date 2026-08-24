<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmDiagramLayoutStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/** Secured backend authority for contextual EFSM presentation layout. */
final class OwasysFsmLayoutCommandProvider implements
    OwasysFsmLayoutCommandProviderInterface
{
    private const READ_COMMAND = 'owasys:fsm:layout-read';
    private const WRITE_COMMAND = 'owasys:fsm:layout-write';
    private const DEFAULT_LAYOUT_DIRECTION = 'horizontal';
    private const MAX_GEOMETRY_BYTES = 262144;

    private readonly AclPolicy $acl;
    private readonly ProfilerInterface $profiler;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');
        $this->profiler = new Profiler($siteRoot . '/var/profiler/fsm');
    }

    public function supports(string $command): bool
    {
        return in_array(
            $command,
            [self::READ_COMMAND, self::WRITE_COMMAND],
            true
        );
    }

    public function execute(
        string $command,
        array $arguments,
        array $request
    ): array {
        if (!$this->supports($command)) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_COMMAND_UNKNOWN:' . $command
            );
        }

        $actor = $this->actor($request);
        $operation = $command === self::WRITE_COMMAND ? 'update' : 'read';
        $decision = $this->acl->decide(
            (array) $actor['roles'],
            'fsm',
            $operation
        );
        if (!$decision->allowed) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_ACL_DENIED:' . $operation
            );
        }

        $parameters = is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
        $siteId = strtolower(trim((string) (
            $parameters['site_id'] ?? ($arguments[0] ?? '')
        )));
        $efsmId = strtolower(trim((string) (
            $parameters['efsm_id'] ?? ($arguments[1] ?? '')
        )));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $efsmId) !== 1) {
            throw new RuntimeException('OWASYS_FSM_LAYOUT_TARGET_INVALID');
        }

        $applicationRoot = rtrim(
            str_replace('\\', '/', $this->opusRoot),
            '/'
        ) . '/sites/' . $siteId;
        $resolved = FsmSiteLoader::resolveEfsm($applicationRoot, $efsmId);
        $fsmPath = (string) ($resolved['fsm_path'] ?? '');
        $fsmRelative = trim(str_replace(
            '\\',
            '/',
            (string) ($resolved['fsm_relative_path'] ?? '')
        ), '/');
        if ($fsmPath === '' || $fsmRelative === '') {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_CANONICAL_SOURCE_UNRESOLVED'
            );
        }

        $file = File::instance();
        $raw = $file->read($fsmPath, 2097152);
        $sourceHash = hash('sha256', $raw);
        $definition = StructuredFileLoader::instance()->read($fsmPath);
        $layoutPath = $this->layoutPath($fsmRelative);
        $layoutAbsolute = $applicationRoot . '/' . $layoutPath;
        $layoutPresent = $file->exists($layoutAbsolute);
        $layoutDirection = $this->layoutDirection(
            $layoutPresent ? $layoutAbsolute : '',
            $layoutPath
        );

        $diagram = \OPUS_FSM_Diagram::fromDefinition($definition);
        $diagram->setLayoutDirection($layoutDirection);
        $automatic = $diagram->layoutSnapshot();
        $store = FsmDiagramLayoutStore::forSource(
            $applicationRoot,
            $fsmRelative,
            $layoutDirection,
            $command === self::WRITE_COMMAND
        );

        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }

        try {
            $this->profiler->event(
                'fsm',
                'layout.' . ($command === self::WRITE_COMMAND
                    ? 'write.received'
                    : 'read.received'),
                [
                    'site_id' => $siteId,
                    'efsm_id' => $efsmId,
                    'source_path' => $fsmRelative,
                    'layout_path' => $layoutPath,
                    'layout_present' => $layoutPresent,
                ]
            );

            if ($command === self::READ_COMMAND) {
                $layout = $store->resolve($definition, $automatic);
                $result = $this->snapshot(
                    $siteId,
                    $efsmId,
                    $fsmRelative,
                    $sourceHash,
                    $layoutPath,
                    $layoutPresent,
                    $layout,
                    $layoutAbsolute
                );
                $this->profiler->event(
                    'fsm',
                    'layout.read.succeeded',
                    [
                        'site_id' => $siteId,
                        'efsm_id' => $efsmId,
                        'layout_present' => $layoutPresent,
                        'layout_direction' => $layoutDirection,
                    ]
                );
                return $result;
            }

            $expectedHash = strtolower(trim((string) (
                $parameters['expected_definition_sha256'] ?? ''
            )));
            if (preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
                || !hash_equals($sourceHash, $expectedHash)) {
                throw new RuntimeException(
                    'OWASYS_FSM_LAYOUT_DEFINITION_HASH_CONFLICT'
                );
            }

            $action = trim((string) ($parameters['layout_action'] ?? ''));
            if (!in_array(
                $action,
                ['save-state', 'save-signal', 'save-marker'],
                true
            )) {
                throw new RuntimeException(
                    'OWASYS_FSM_LAYOUT_ACTION_INVALID:' . $action
                );
            }

            $geometryJson = $parameters['geometry_json'] ?? null;
            if (!is_string($geometryJson)
                || $geometryJson === ''
                || strlen($geometryJson) > self::MAX_GEOMETRY_BYTES) {
                throw new RuntimeException(
                    'OWASYS_FSM_LAYOUT_GEOMETRY_INVALID'
                );
            }
            $geometry = Json::instance()->parse(
                $geometryJson,
                'fsm-layout-geometry'
            );
            if (!is_array($geometry) || array_is_list($geometry)) {
                throw new RuntimeException(
                    'OWASYS_FSM_LAYOUT_GEOMETRY_INVALID'
                );
            }

            $mutation = [
                'action' => $action,
                'state_id' => trim((string) (
                    $parameters['state_id'] ?? ''
                )),
                'marker_id' => trim((string) (
                    $parameters['marker_id'] ?? ''
                )),
                'x' => $parameters['x'] ?? null,
                'y' => $parameters['y'] ?? null,
                'geometry' => $geometry,
            ];
            $layout = $store->mutate(
                $definition,
                $automatic,
                $mutation
            );

            if (!$file->exists($layoutAbsolute)) {
                throw new RuntimeException(
                    'OWASYS_FSM_LAYOUT_WRITE_RESULT_MISSING'
                );
            }
            $layoutRaw = $file->read($layoutAbsolute, 1048576);
            $layoutHash = hash('sha256', $layoutRaw);

            $this->profiler->event(
                'fsm',
                'layout.write.succeeded',
                [
                    'site_id' => $siteId,
                    'efsm_id' => $efsmId,
                    'layout_action' => $action,
                    'layout_path' => $layoutPath,
                    'layout_sha256' => $layoutHash,
                ]
            );

            return [
                'contract' => 'OWASYS_EFSM_LAYOUT_WRITE_RESULT_V1',
                'application_id' => $siteId,
                'efsm_id' => $efsmId,
                'source_path' => $fsmRelative,
                'source_sha256' => $sourceHash,
                'layout_path' => $layoutPath,
                'layout_sha256' => $layoutHash,
                'layout_present' => true,
                'layout' => $layout,
            ];
        } finally {
            if ($ownsTrace
                && $this->profiler->getActiveTrace() !== null) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => $command,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private function snapshot(
        string $siteId,
        string $efsmId,
        string $sourcePath,
        string $sourceHash,
        string $layoutPath,
        bool $layoutPresent,
        array $layout,
        string $layoutAbsolute
    ): array {
        $layoutHash = '';
        if ($layoutPresent) {
            $layoutHash = hash(
                'sha256',
                File::instance()->read($layoutAbsolute, 1048576)
            );
        }
        return [
            'contract' => 'OWASYS_EFSM_LAYOUT_SNAPSHOT_V1',
            'application_id' => $siteId,
            'efsm_id' => $efsmId,
            'source_path' => $sourcePath,
            'source_sha256' => $sourceHash,
            'layout_path' => $layoutPath,
            'layout_sha256' => $layoutHash,
            'layout_present' => $layoutPresent,
            'layout' => $layout,
        ];
    }

    private function layoutPath(string $fsmRelative): string
    {
        $layout = preg_replace('/\.json$/D', '.layout.json', $fsmRelative);
        if (!is_string($layout) || $layout === $fsmRelative) {
            throw new RuntimeException('OWASYS_FSM_LAYOUT_PATH_INVALID');
        }
        return $layout;
    }

    private function layoutDirection(
        string $layoutAbsolute,
        string $layoutPath
    ): string {
        if ($layoutAbsolute === '') {
            return self::DEFAULT_LAYOUT_DIRECTION;
        }
        $layout = StructuredFileLoader::instance()->read($layoutAbsolute);
        $direction = strtolower(trim((string) (
            $layout['layout_direction'] ?? ''
        )));
        if (!in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_DIRECTION_INVALID:' . $layoutPath
            );
        }
        return $direction;
    }

    /**
     * @param array<string,mixed> $request
     * @return array{subject:string,roles:list<string>,provider:string}
     */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_REQUEST_CONTRACT_INVALID'
            );
        }
        $actor = is_array($request['actor'] ?? null)
            ? $request['actor']
            : [];
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
                'OWASYS_FSM_LAYOUT_ACTOR_INVALID'
            );
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }

    /** @param array<string,mixed> $request */
    private function traceId(array $request): string
    {
        $traceId = strtolower(trim((string) (
            $request['trace_id'] ?? ''
        )));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_TRACE_ID_INVALID'
            );
        }
        return $traceId;
    }
}
