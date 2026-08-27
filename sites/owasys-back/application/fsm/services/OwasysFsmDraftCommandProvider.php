<?php
declare(strict_types=1);

use Opus\Application\Source\SiteSourceWorkspace;
use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\Definition\FsmDefinitionEditor;
use Opus\Fsm\Definition\FsmDefinitionValidator;
use Opus\Fsm\Definition\FsmHandlerSourceEditor;
use Opus\Fsm\FsmSiteLoader;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/**
 * OWASYS backend adapter for validated EFSM semantic commands.
 *
 * V2 never trusts a browser-authored definition. It rebuilds draft operations
 * deterministically from the live canonical fsm.json and persists atomic
 * state, signal and transition creation/refactor commands through the source
 * workspace after generic OPUS validation.
 */
final class OwasysFsmDraftCommandProvider implements OwasysFsmDraftCommandProviderInterface
{
    private const DRAFT_COMMAND = 'owasys:fsm:draft-edit';
    private const HANDLER_COMMAND = 'owasys:fsm:handler-write';
    private const HANDLER_SOURCE =
        'application/default/services/FsmDeveloperHandlers.php';
    private const ENVELOPE_CONTRACT =
        'OWASYS_EFSM_DRAFT_COMMAND_ENVELOPE_V2';
    private const MAX_HISTORY_COMMANDS = 128;

    private readonly AclPolicy $acl;
    private readonly ProfilerInterface $profiler;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
        $this->acl = new AclPolicy(
            $siteRoot . '/config/acl.json'
        );
        $this->profiler = new Profiler(
            $siteRoot . '/var/profiler/fsm'
        );
    }

    public function supports(string $command): bool
    {
        return in_array(
            $command,
            [self::DRAFT_COMMAND, self::HANDLER_COMMAND],
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
                'OWASYS_FSM_DRAFT_COMMAND_UNKNOWN:'
                . $command
            );
        }

        $actor = $this->actor($request);
        $decision = $this->acl->decide(
            (array) $actor['roles'],
            'fsm',
            'update'
        );
        if (!$decision->allowed) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_ACL_DENIED'
            );
        }

        if ($command === self::HANDLER_COMMAND) {
            return $this->writeHandler(
                $arguments,
                $request,
                $actor
            );
        }

        $parameters = is_array(
            $request['parameters'] ?? null
        ) ? $request['parameters'] : [];

        $siteId = trim((string) (
            $parameters['site_id']
                ?? ($arguments[0] ?? '')
        ));
        $baseHash = strtolower(trim((string) (
            $parameters['base_sha256'] ?? ''
        )));
        $draftJson = $parameters['draft_json'] ?? null;
        $envelopeJson = $parameters['command_json'] ?? null;

        if (preg_match(
            '/^[a-z][a-z0-9-]{0,63}$/D',
            $siteId
        ) !== 1
            || preg_match(
                '/^[a-f0-9]{64}$/D',
                $baseHash
            ) !== 1
            || !is_string($draftJson)
            || trim($draftJson) !== '{}'
            || !is_string($envelopeJson)
            || strlen($envelopeJson) > 65536) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_PARAMETERS_INVALID'
            );
        }
        $this->assertSystemApplicationMutationAllowed($actor, $siteId);

        $envelope = Json::instance()->parse(
            $envelopeJson,
            'fsm-command-envelope'
        );
        if (($envelope['contract'] ?? null)
            !== self::ENVELOPE_CONTRACT) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_ENVELOPE_CONTRACT_INVALID'
            );
        }
        $efsmId = strtolower(trim((string) (
            $envelope['efsm_id'] ?? ''
        )));
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $efsmId) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_EFSM_ID_INVALID'
            );
        }

        $siteRoot = $this->opusRoot . '/sites/' . $siteId;
        $resolvedFsm = FsmSiteLoader::resolveEfsm($siteRoot, $efsmId);
        $fsmPath = (string) ($resolvedFsm['fsm_path'] ?? '');
        $fsmRelativePath = trim((string) (
            $resolvedFsm['fsm_relative_path'] ?? ''
        ));
        if ($fsmPath === '' || $fsmRelativePath === '') {
            throw new RuntimeException(
                'OWASYS_FSM_CANONICAL_SOURCE_UNRESOLVED'
            );
        }
        $raw = File::instance()->read(
            $fsmPath,
            2097152
        );
        $liveHash = hash('sha256', $raw);
        if (!hash_equals($liveHash, $baseHash)) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_BASE_HASH_CONFLICT'
            );
        }
        $live = StructuredFileLoader::instance()->read(
            $fsmPath
        );

        $history = $envelope['history'] ?? null;
        $semanticCommand = $envelope['command'] ?? null;
        $catalog = $envelope['handler_catalog'] ?? null;
        if (!is_array($history)
            || !array_is_list($history)
            || count($history) > self::MAX_HISTORY_COMMANDS
            || !is_array($semanticCommand)
            || array_is_list($semanticCommand)
            || (!is_array($catalog) && $catalog !== null)) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_ENVELOPE_INVALID'
            );
        }
        foreach ($history as $historicCommand) {
            if (!is_array($historicCommand)
                || array_is_list($historicCommand)) {
                throw new RuntimeException(
                    'OWASYS_FSM_DRAFT_HISTORY_INVALID'
                );
            }
        }

        $operation = trim((string) (
            $semanticCommand['operation'] ?? ''
        ));
        $persistentSemanticOperation = in_array(
            $operation,
            [
                'state.create',
                'state.rename',
                'state.delete',
                'signal.create',
                'transition.create',
                'transition.delete',
            ],
            true
        );
        if ($persistentSemanticOperation && $history !== []) {
            throw new RuntimeException(
                'OWASYS_FSM_SEMANTIC_WRITE_HISTORY_FORBIDDEN'
            );
        }

        if ($operation === 'transition.handlers.update') {
            if (!is_array($catalog)
                || (isset($catalog['application_id'])
                    && (string) $catalog['application_id'] !== $siteId)) {
                throw new RuntimeException(
                    'OWASYS_FSM_DRAFT_HANDLER_CATALOG_INVALID'
                );
            }
            [$guardNames, $actionNames] =
                $this->handlerNames($catalog);
            $editor = new FsmDefinitionEditor(
                new FsmDefinitionValidator(),
                $guardNames,
                $actionNames
            );
        } else {
            $editor = new FsmDefinitionEditor(
                new FsmDefinitionValidator()
            );
        }

        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }

        try {
            $this->profiler->event(
                'fsm',
                'designer.draft_command.received',
                [
                    'site_id' => $siteId,
                    'efsm_id' => $efsmId,
                    'operation' => $operation,
                    'history_count' => count($history),
                    'state_id' => (string) (
                        $semanticCommand['state_id']
                            ?? ''
                    ),
                    'transition_id' => (string) (
                        $semanticCommand['transition_id']
                            ?? ''
                    ),
                ]
            );

            /*
             * Stateless authoritative draft: canonical definition + replayed
             * semantic command history. The browser cannot smuggle arbitrary
             * definition fields because raw draft_json is never consumed.
             */
            $definition = $live;
            foreach ($history as $historicCommand) {
                $replayed = $editor->apply(
                    $definition,
                    $historicCommand
                );
                $definition = $replayed['definition'];
            }

            $result = $editor->apply(
                $definition,
                $semanticCommand
            );
            $definition = $result['definition'];
            $definitionJson = Json::instance()->encode(
                $definition,
                true
            );

            $sourceHash = $baseHash;
            if ($persistentSemanticOperation) {
                $write = (new SiteSourceWorkspace(
                    $this->opusRoot,
                    null,
                    $this->profiler
                ))->write(
                    $siteId,
                    $fsmRelativePath,
                    $baseHash,
                    $definitionJson
                );
                $sourceHash = strtolower(trim((string) (
                    $write['sha256'] ?? ''
                )));
                if (preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_FSM_SEMANTIC_WRITE_RESULT_INVALID'
                    );
                }
            }

            $this->profiler->event(
                'fsm',
                'designer.draft_command.validated',
                [
                    'site_id' => $siteId,
                    'efsm_id' => $efsmId,
                    'operation' => $operation,
                    'history_count' => $persistentSemanticOperation
                        ? 0
                        : count($history) + 1,
                    'definition_sha256' =>
                        hash('sha256', $definitionJson),
                    'persisted' => $persistentSemanticOperation,
                    'source_path' => $fsmRelativePath,
                    'source_sha256' => $sourceHash,
                    'diagnostic_count' =>
                        count($result['diagnostics']),
                ]
            );

            return [
                'contract' =>
                    'OWASYS_EFSM_DRAFT_COMMAND_RESULT_V2',
                'base_sha256' => $sourceHash,
                'efsm_id' => $efsmId,
                'draft_sha256' =>
                    hash('sha256', $definitionJson),
                'history_count' => $persistentSemanticOperation
                    ? 0
                    : count($history) + 1,
                'persisted' => $persistentSemanticOperation,
                'source_path' => $fsmRelativePath,
                'source_sha256' => $sourceHash,
                'operation' => $result['operation'],
                'refactor' => $result['refactor'],
                'diagnostics' => $result['diagnostics'],
                'definition' => $definition,
            ];
        } finally {
            if ($ownsTrace
                && $this->profiler->getActiveTrace()
                    !== null) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => self::DRAFT_COMMAND,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }

    /**
     * @param list<string> $arguments
     * @param array<string,mixed> $request
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    private function writeHandler(
        array $arguments,
        array $request,
        array $actor
    ): array {
        $parameters = is_array(
            $request['parameters'] ?? null
        ) ? $request['parameters'] : [];

        $siteId = trim((string) (
            $parameters['site_id'] ?? ($arguments[0] ?? '')
        ));
        $kind = strtolower(trim((string) (
            $parameters['kind'] ?? ''
        )));
        $handlerId = trim((string) (
            $parameters['handler_id'] ?? ''
        ));
        $mode = strtolower(trim((string) (
            $parameters['mode'] ?? ''
        )));
        $expectedSourceHash = strtolower(trim((string) (
            $parameters['expected_source_sha256'] ?? ''
        )));
        $handlerCode = $parameters['handler_code'] ?? null;

        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || !in_array($kind, ['guard', 'action'], true)
            || preg_match(
                '/^[a-z][a-z0-9_:-]{0,127}$/D',
                $handlerId
            ) !== 1
            || !in_array($mode, ['create', 'update'], true)
            || preg_match(
                '/^[a-f0-9]{64}$/D',
                $expectedSourceHash
            ) !== 1
            || !is_string($handlerCode)
            || $handlerCode === ''
            || strlen($handlerCode) > 16384
            || ($kind === 'guard'
                && str_starts_with($handlerId, 'acl:'))) {
            throw new RuntimeException(
                'OWASYS_FSM_HANDLER_WRITE_PARAMETERS_INVALID'
            );
        }
        $this->assertSystemApplicationMutationAllowed($actor, $siteId);

        $sourcePath = $this->opusRoot . '/sites/' . $siteId
            . '/' . self::HANDLER_SOURCE;
        $current = File::instance()->read($sourcePath, 1048576);
        $currentHash = hash('sha256', $current);
        if (!hash_equals($expectedSourceHash, $currentHash)) {
            throw new RuntimeException(
                'OWASYS_FSM_HANDLER_SOURCE_HASH_CONFLICT'
            );
        }

        $editor = new FsmHandlerSourceEditor();
        $edited = $editor->upsert(
            $current,
            $kind,
            $handlerId,
            $handlerCode,
            $mode
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
                'designer.handler_source.write.started',
                [
                    'site_id' => $siteId,
                    'kind' => $kind,
                    'handler_id' => $handlerId,
                    'mode' => $mode,
                    'role_count' => count((array) ($actor['roles'] ?? [])),
                ]
            );

            $write = (new SiteSourceWorkspace(
                $this->opusRoot,
                null,
                $this->profiler
            ))->write(
                $siteId,
                self::HANDLER_SOURCE,
                $expectedSourceHash,
                (string) $edited['source']
            );

            $sourceHash = strtolower((string) (
                $write['sha256'] ?? ''
            ));
            if (preg_match('/^[a-f0-9]{64}$/D', $sourceHash) !== 1) {
                throw new RuntimeException(
                    'OWASYS_FSM_HANDLER_WRITE_RESULT_INVALID'
                );
            }

            $this->profiler->event(
                'fsm',
                'designer.handler_source.write.succeeded',
                [
                    'site_id' => $siteId,
                    'kind' => $kind,
                    'handler_id' => $handlerId,
                    'mode' => $mode,
                    'source_sha256' => $sourceHash,
                ]
            );

            return [
                'contract' => 'OWASYS_EFSM_HANDLER_WRITE_RESULT_V1',
                'site_id' => $siteId,
                'kind' => $kind,
                'handler_id' => $handlerId,
                'mode' => $mode,
                'created' => ($edited['created'] ?? false) === true,
                'handler_sha256' => (string) (
                    $edited['handler_sha256'] ?? ''
                ),
                'source_path' => self::HANDLER_SOURCE,
                'source_sha256' => $sourceHash,
            ];
        } finally {
            if ($ownsTrace
                && $this->profiler->getActiveTrace() !== null) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => self::HANDLER_COMMAND,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }
    /**
     * @param array<string,mixed> $catalog
     * @return array{0:list<string>,1:list<string>}
     */
    private function handlerNames(array $catalog): array
    {
        if (($catalog['contract'] ?? null)
            !== 'OWASYS_EFSM_HANDLER_CATALOG_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_HANDLER_CATALOG_INVALID'
            );
        }

        return [
            $this->catalogNames(
                $catalog['guards'] ?? null,
                'GUARD'
            ),
            $this->catalogNames(
                $catalog['actions'] ?? null,
                'ACTION'
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function catalogNames(
        mixed $entries,
        string $kind
    ): array {
        if (!is_array($entries)
            || !array_is_list($entries)) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_' . $kind
                . '_CATALOG_INVALID'
            );
        }

        $names = [];
        $seen = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)
                || array_is_list($entry)) {
                throw new RuntimeException(
                    'OWASYS_FSM_DRAFT_' . $kind
                    . '_CATALOG_ENTRY_INVALID'
                );
            }
            $id = trim((string) ($entry['id'] ?? ''));
            if (preg_match(
                '/^[a-z][a-z0-9_:-]{0,127}$/D',
                $id
            ) !== 1) {
                throw new RuntimeException(
                    'OWASYS_FSM_DRAFT_' . $kind
                    . '_CATALOG_ID_INVALID:' . $id
                );
            }
            if (isset($seen[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_DRAFT_' . $kind
                    . '_CATALOG_ID_DUPLICATE:' . $id
                );
            }
            $seen[$id] = true;
            $names[] = $id;
        }
        return $names;
    }

    /** @param array<string,mixed> $actor */
    private function assertSystemApplicationMutationAllowed(
        array $actor,
        string $siteId
    ): void {
        if (!in_array($siteId, ['owasys-front', 'owasys-back'], true)) {
            return;
        }
        $decision = $this->acl->decide(
            (array) ($actor['roles'] ?? []),
            'owasys',
            'modify'
        );
        if (!$decision->allowed) {
            throw new RuntimeException(
                'OWASYS_SYSTEM_APPLICATION_MODIFY_ACL_DENIED:' . $siteId
            );
        }
    }

    /**
     * @param array<string,mixed> $request
     * @return array{
     *   subject:string,
     *   roles:list<string>,
     *   provider:string
     * }
     */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !==
            'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_REQUEST_CONTRACT_INVALID'
            );
        }
        $actor = is_array($request['actor'] ?? null)
            ? $request['actor']
            : [];
        $subject = trim((string) (
            $actor['subject'] ?? ''
        ));
        $provider = trim((string) (
            $actor['provider'] ?? ''
        ));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        if ($subject === ''
            || $provider === ''
            || $roles === []) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_ACTOR_INVALID'
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
        if (preg_match(
            '/^[a-f0-9]{16,64}$/D',
            $traceId
        ) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_DRAFT_TRACE_ID_INVALID'
            );
        }
        return $traceId;
    }
}
