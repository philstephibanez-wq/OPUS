<?php
declare(strict_types=1);

use Opus\Application\Source\SiteSourceWorkspace;
use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\Definition\FsmDefinitionEditor;
use Opus\Fsm\Definition\FsmDefinitionValidator;
use Opus\Fsm\Definition\FsmHandlerSourceEditor;
use Opus\Fsm\FsmDiagramLayoutStore;
use Opus\Fsm\FsmSiteLoader;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/**
 * OWASYS backend adapter for validated EFSM semantic commands.
 *
 * Browser-authored definitions are never trusted. Persistent semantic changes
 * are rebuilt from the live canonical source and written through the OPUS
 * source workspace. State-label updates keep the technical state identity
 * unchanged and persist the exact active-locale message atomically with the
 * canonical label_key.
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
    private const MAX_LABEL_BYTES = 1024;

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
                'state.label.update',
                'state.delete',
                'signal.create',
                'transition.create',
                'transition.label.update',
                'transition.rename',
                'transition.delete',
            ],
            true
        );
        if ($persistentSemanticOperation && $history !== []) {
            throw new RuntimeException(
                'OWASYS_FSM_SEMANTIC_WRITE_HISTORY_FORBIDDEN'
            );
        }

        $labelMutation = null;
        $editorCommand = $semanticCommand;
        if ($operation === 'state.label.update') {
            $labelMutation = $this->stateLabelMutation(
                $live,
                $efsmId,
                $semanticCommand
            );
            $editorCommand = [
                'operation' => 'state.label.update',
                'state_id' => $labelMutation['state_id'],
                'label_key' => $labelMutation['label_key'],
            ];
        }
        elseif ($operation === 'transition.label.update') {
            $labelMutation = $this->transitionLabelMutation(
                $live,
                $efsmId,
                $semanticCommand
            );
            $editorCommand = [
                'operation' => 'transition.label.update',
                'transition_id' => $labelMutation['transition_id'],
                'label_key' => $labelMutation['label_key'],
            ];
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
                    'label_locale' => is_array($labelMutation)
                        ? $labelMutation['locale']
                        : '',
                    'label_key' => is_array($labelMutation)
                        ? $labelMutation['label_key']
                        : '',
                ]
            );

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
                $editorCommand
            );
            $definition = $result['definition'];
            $definitionJson = Json::instance()->encode(
                $definition,
                true
            );

            $sourceHash = $baseHash;
            $layoutRefactor = null;
            $labelCatalogPath = '';
            if ($persistentSemanticOperation) {
                $workspace = new SiteSourceWorkspace(
                    $this->opusRoot,
                    null,
                    $this->profiler
                );
                if ($operation === 'state.rename'
                    && trim((string) (
                        $result['refactor']['state_old'] ?? ''
                    )) !== ''
                    && trim((string) (
                        $result['refactor']['state_new'] ?? ''
                    )) !== '') {
                    $layoutRefactor = $this->prepareStateRenameLayoutRefactor(
                        $siteRoot,
                        $fsmRelativePath,
                        $live,
                        $definition,
                        $result['refactor'],
                        hash('sha256', $definitionJson)
                    );
                } elseif ($operation === 'transition.rename'
                    && trim((string) (
                        $result['refactor']['transition_old'] ?? ''
                    )) !== ''
                    && trim((string) (
                        $result['refactor']['transition_new'] ?? ''
                    )) !== '') {
                    $layoutRefactor =
                        $this->prepareTransitionRenameLayoutRefactor(
                            $siteRoot,
                            $fsmRelativePath,
                            $live,
                            $definition,
                            $result['refactor'],
                            hash('sha256', $definitionJson)
                        );
                }

                if (is_array($labelMutation)) {
                    $catalogWrite = $this->prepareStateLabelCatalogWrite(
                        $siteRoot,
                        $labelMutation['locale'],
                        $labelMutation['label_key'],
                        $labelMutation['label']
                    );
                    $labelCatalogPath = $catalogWrite['path'];
                    $batch = $workspace->writeBatch(
                        $siteId,
                        [
                            [
                                'path' => $fsmRelativePath,
                                'expected_sha256' => $baseHash,
                                'content' => $definitionJson,
                            ],
                            $catalogWrite,
                        ]
                    );
                    $sourceHash = $this->batchSourceHash(
                        $batch,
                        $fsmRelativePath
                    );
                } elseif ($layoutRefactor !== null) {
                    $batch = $workspace->writeBatch(
                        $siteId,
                        [
                            [
                                'path' => $fsmRelativePath,
                                'expected_sha256' => $baseHash,
                                'content' => $definitionJson,
                            ],
                            [
                                'path' => $layoutRefactor['path'],
                                'expected_sha256' =>
                                    $layoutRefactor['expected_sha256'],
                                'content' => $layoutRefactor['content'],
                            ],
                        ]
                    );
                    $sourceHash = $this->batchSourceHash(
                        $batch,
                        $fsmRelativePath
                    );
                } else {
                    $write = $workspace->write(
                        $siteId,
                        $fsmRelativePath,
                        $baseHash,
                        $definitionJson
                    );
                    $sourceHash = strtolower(trim((string) (
                        $write['sha256'] ?? ''
                    )));
                }
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
                    'label_catalog_path' => $labelCatalogPath,
                    'layout_refactor_prepared' =>
                        $layoutRefactor !== null,
                    'state_position_migrated' => (bool) (
                        $layoutRefactor['state_position_migrated']
                            ?? false
                    ),
                    'marker_count_migrated' => (int) (
                        $layoutRefactor['marker_count_migrated']
                            ?? 0
                    ),
                    'transition_geometry_migrated' => (bool) (
                        $layoutRefactor['transition_geometry_migrated']
                            ?? false
                    ),
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
                'label' => is_array($labelMutation)
                    ? [
                        'state_id' => $labelMutation['state_id'] ?? '',
                        'transition_id' => $labelMutation['transition_id'] ?? '',
                        'locale' => $labelMutation['locale'],
                        'label_key' => $labelMutation['label_key'],
                        'catalog_path' => $labelCatalogPath,
                    ]
                    : null,
                'layout_refactor' => [
                    'prepared' => $layoutRefactor !== null,
                    'state_position_migrated' => (bool) (
                        $layoutRefactor['state_position_migrated']
                            ?? false
                    ),
                    'marker_count_migrated' => (int) (
                        $layoutRefactor['marker_count_migrated']
                            ?? 0
                    ),
                    'transition_geometry_migrated' => (bool) (
                        $layoutRefactor['transition_geometry_migrated']
                            ?? false
                    ),
                ],
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
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $command
     * @return array{state_id:string,locale:string,label_key:string,label:string}
     */
    private function stateLabelMutation(
        array $definition,
        string $efsmId,
        array $command
    ): array {
        foreach (array_keys($command) as $field) {
            if (!in_array(
                $field,
                ['operation', 'state_id', 'locale', 'label'],
                true
            )) {
                throw new RuntimeException(
                    'OWASYS_FSM_STATE_LABEL_FIELD_FORBIDDEN:'
                    . (string) $field
                );
            }
        }
        $stateId = trim((string) ($command['state_id'] ?? ''));
        $locale = trim((string) ($command['locale'] ?? ''));
        $label = is_string($command['label'] ?? null)
            ? trim((string) $command['label'])
            : '';
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D', $stateId) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_STATE_ID_INVALID:' . $stateId
            );
        }
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z]{2})?$/D', $locale) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_LOCALE_INVALID:' . $locale
            );
        }
        if ($label === ''
            || strlen($label) > self::MAX_LABEL_BYTES
            || preg_match('//u', $label) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_MESSAGE_INVALID'
            );
        }

        $state = null;
        foreach ((array) ($definition['states'] ?? []) as $candidate) {
            if (is_array($candidate)
                && (string) ($candidate['id'] ?? '') === $stateId) {
                $state = $candidate;
                break;
            }
        }
        if (!is_array($state)) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_UNKNOWN:' . $stateId
            );
        }
        $labelKey = trim((string) ($state['label_key'] ?? ''));
        if ($labelKey === '') {
            $safeState = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', $stateId);
            $safeState = is_string($safeState) ? trim($safeState, '_') : '';
            if ($safeState === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_STATE_LABEL_KEY_INVALID'
                );
            }
            $labelKey = 'fsm.' . $efsmId . '.state.' . $safeState . '.label';
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,191}$/D', $labelKey) !== 1) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_KEY_INVALID:' . $labelKey
            );
        }

        return [
            'state_id' => $stateId,
            'locale' => $locale,
            'label_key' => $labelKey,
            'label' => $label,
        ];
    }


    /** @return array{transition_id:string,locale:string,label_key:string,label:string} */
    private function transitionLabelMutation(array $definition, string $efsmId, array $command): array
    {
        foreach (array_keys($command) as $field) {
            if (!in_array($field, ['operation', 'transition_id', 'locale', 'label'], true)) {
                throw new RuntimeException('OWASYS_FSM_TRANSITION_LABEL_FIELD_FORBIDDEN:' . (string) $field);
            }
        }
        $transitionId = trim((string) ($command['transition_id'] ?? ''));
        $locale = trim((string) ($command['locale'] ?? ''));
        $label = is_string($command['label'] ?? null) ? trim((string) $command['label']) : '';
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D', $transitionId) !== 1) {
            throw new RuntimeException('OWASYS_FSM_TRANSITION_LABEL_TRANSITION_ID_INVALID:' . $transitionId);
        }
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z]{2})?$/D', $locale) !== 1) {
            throw new RuntimeException('OWASYS_FSM_TRANSITION_LABEL_LOCALE_INVALID:' . $locale);
        }
        if ($label === '' || strlen($label) > self::MAX_LABEL_BYTES || preg_match('//u', $label) !== 1) {
            throw new RuntimeException('OWASYS_FSM_TRANSITION_LABEL_MESSAGE_INVALID');
        }
        $transition = null;
        foreach ((array) ($definition['transitions'] ?? []) as $candidate) {
            if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $transitionId) { $transition = $candidate; break; }
        }
        if (!is_array($transition)) {
            throw new RuntimeException('OWASYS_FSM_TRANSITION_UNKNOWN:' . $transitionId);
        }
        $labelKey = trim((string) ($transition['label_key'] ?? ''));
        if ($labelKey === '') {
            $labelKey = 'fsm.' . $efsmId . '.transition.' . $transitionId . '.label';
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,191}$/D', $labelKey) !== 1) {
            throw new RuntimeException('OWASYS_FSM_TRANSITION_LABEL_KEY_INVALID:' . $labelKey);
        }
        return ['transition_id'=>$transitionId,'locale'=>$locale,'label_key'=>$labelKey,'label'=>$label];
    }

    /**
     * @return array{path:string,expected_sha256:string,content:string}
     */
    private function prepareStateLabelCatalogWrite(
        string $siteRoot,
        string $locale,
        string $labelKey,
        string $label
    ): array {
        $relativePath = 'application/default/local/' . $locale . '.json';
        $path = rtrim($siteRoot, '/\\') . '/' . $relativePath;
        if (!File::instance()->exists($path)) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_CATALOG_MISSING:' . $locale
            );
        }
        $raw = File::instance()->read($path, 2097152);
        $catalog = StructuredFileLoader::instance()->read($path);
        if (!in_array(
                (string) ($catalog['contract'] ?? ''),
                ['OPUS_I18N_CATALOG_V1', 'OPUS_I18N_CATALOG_V2'],
                true
            )
            || (string) ($catalog['locale'] ?? '') !== $locale
            || !is_array($catalog['messages'] ?? null)) {
            throw new RuntimeException(
                'OWASYS_FSM_STATE_LABEL_CATALOG_INVALID:' . $locale
            );
        }
        $messages = $catalog['messages'];
        $messages[$labelKey] = $label;
        ksort($messages, SORT_STRING);
        $catalog['messages'] = $messages;

        return [
            'path' => $relativePath,
            'expected_sha256' => hash('sha256', $raw),
            'content' => Json::instance()->encode($catalog, true),
        ];
    }

    /**
     * @param array<string,mixed> $oldDefinition
     * @param array<string,mixed> $newDefinition
     * @param array<string,mixed> $refactor
     * @return array{
     *   path:string,
     *   expected_sha256:string,
     *   content:string,
     *   state_position_migrated:bool,
     *   marker_count_migrated:int
     * }|null
     */
    private function prepareStateRenameLayoutRefactor(
        string $siteRoot,
        string $fsmRelativePath,
        array $oldDefinition,
        array $newDefinition,
        array $refactor,
        string $newDefinitionSha256
    ): ?array {
        $layoutRelativePath = preg_replace(
            '/\.json$/D',
            '.layout.json',
            $fsmRelativePath
        );
        if (!is_string($layoutRelativePath)
            || $layoutRelativePath === $fsmRelativePath) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_PATH_INVALID'
            );
        }
        $layoutPath = rtrim($siteRoot, '/\\')
            . '/' . $layoutRelativePath;
        if (!File::instance()->exists($layoutPath)) {
            return null;
        }
        $layout = StructuredFileLoader::instance()->read($layoutPath);
        $direction = strtolower(trim((string) (
            $layout['layout_direction'] ?? ''
        )));
        if (!in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_DIRECTION_INVALID'
            );
        }

        return FsmDiagramLayoutStore::forSource(
            $siteRoot,
            $fsmRelativePath,
            $direction,
            false
        )->prepareStateIdentityRefactor(
            $oldDefinition,
            $newDefinition,
            trim((string) ($refactor['state_old'] ?? '')),
            trim((string) ($refactor['state_new'] ?? '')),
            $newDefinitionSha256
        );
    }

    /**
     * @param array<string,mixed> $oldDefinition
     * @param array<string,mixed> $newDefinition
     * @param array<string,mixed> $refactor
     * @return array{path:string,expected_sha256:string,content:string,transition_geometry_migrated:bool}|null
     */
    private function prepareTransitionRenameLayoutRefactor(
        string $siteRoot,
        string $fsmRelativePath,
        array $oldDefinition,
        array $newDefinition,
        array $refactor,
        string $newDefinitionSha256
    ): ?array {
        $layoutRelativePath = preg_replace(
            '/\\.json$/D',
            '.layout.json',
            $fsmRelativePath
        );
        if (!is_string($layoutRelativePath)
            || $layoutRelativePath === $fsmRelativePath) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_PATH_INVALID'
            );
        }
        $layoutPath = rtrim($siteRoot, '/\\\\')
            . '/' . $layoutRelativePath;
        if (!File::instance()->exists($layoutPath)) {
            return null;
        }
        $layout = StructuredFileLoader::instance()->read($layoutPath);
        $direction = strtolower(trim((string) (
            $layout['layout_direction'] ?? ''
        )));
        if (!in_array($direction, ['horizontal', 'vertical'], true)) {
            throw new RuntimeException(
                'OWASYS_FSM_LAYOUT_DIRECTION_INVALID'
            );
        }

        return FsmDiagramLayoutStore::forSource(
            $siteRoot,
            $fsmRelativePath,
            $direction,
            false
        )->prepareTransitionIdentityRefactor(
            $oldDefinition,
            $newDefinition,
            trim((string) ($refactor['transition_old'] ?? '')),
            trim((string) ($refactor['transition_new'] ?? '')),
            $newDefinitionSha256
        );
    }

    /** @param array<string,mixed> $batch */
    private function batchSourceHash(
        array $batch,
        string $fsmRelativePath
    ): string {
        foreach ((array) ($batch['files'] ?? []) as $file) {
            if (is_array($file)
                && (string) ($file['path'] ?? '') === $fsmRelativePath) {
                return strtolower(trim((string) ($file['sha256'] ?? '')));
            }
        }
        throw new RuntimeException(
            'OWASYS_FSM_SEMANTIC_BATCH_RESULT_INVALID'
        );
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

    /** @return list<string> */
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
     * @return array{subject:string,roles:list<string>,provider:string}
     */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
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
