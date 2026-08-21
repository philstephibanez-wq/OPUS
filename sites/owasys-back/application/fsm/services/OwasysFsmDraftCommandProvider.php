<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\Definition\FsmDefinitionEditor;
use Opus\Fsm\Definition\FsmDefinitionValidator;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/**
 * OWASYS backend adapter for validated, non-persistent EFSM draft commands.
 *
 * V2 never trusts a browser-authored draft definition. It rebuilds the draft
 * deterministically from the live canonical fsm.json by replaying the bounded
 * semantic command history through the generic OPUS EFSM editor.
 */
final class OwasysFsmDraftCommandProvider implements OwasysFsmDraftCommandProviderInterface
{
    private const COMMAND = 'owasys:fsm:draft-edit';
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
        return $command === self::COMMAND;
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

        $fsmPath = $this->opusRoot
            . '/sites/' . $siteId
            . '/config/fsm.json';
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

        $history = $envelope['history'] ?? null;
        $semanticCommand = $envelope['command'] ?? null;
        $catalog = $envelope['handler_catalog'] ?? null;
        if (!is_array($history)
            || !array_is_list($history)
            || count($history) > self::MAX_HISTORY_COMMANDS
            || !is_array($semanticCommand)
            || array_is_list($semanticCommand)
            || !is_array($catalog)) {
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

        [$guardNames, $actionNames] =
            $this->handlerNames($catalog);
        $editor = new FsmDefinitionEditor(
            new FsmDefinitionValidator(),
            $guardNames,
            $actionNames
        );

        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }

        $operation = trim((string) (
            $semanticCommand['operation'] ?? ''
        ));
        try {
            $this->profiler->event(
                'fsm',
                'designer.draft_command.received',
                [
                    'site_id' => $siteId,
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
                false
            );

            $this->profiler->event(
                'fsm',
                'designer.draft_command.validated',
                [
                    'site_id' => $siteId,
                    'operation' => $operation,
                    'history_count' =>
                        count($history) + 1,
                    'definition_sha256' =>
                        hash('sha256', $definitionJson),
                    'diagnostic_count' =>
                        count($result['diagnostics']),
                ]
            );

            return [
                'contract' =>
                    'OWASYS_EFSM_DRAFT_COMMAND_RESULT_V2',
                'base_sha256' => $baseHash,
                'draft_sha256' =>
                    hash('sha256', $definitionJson),
                'history_count' =>
                    count($history) + 1,
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
                    'command' => self::COMMAND,
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
