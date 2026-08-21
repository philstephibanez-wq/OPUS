<?php
declare(strict_types=1);

use Opus\File\File;
use Opus\File\Json;
use Opus\File\StructuredFileLoader;
use Opus\Fsm\Definition\FsmDefinitionEditor;
use Opus\Fsm\Definition\FsmDefinitionEditorInterface;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/** OWASYS backend adapter for validated, non-persistent EFSM draft commands. */
final class OwasysFsmDraftCommandProvider implements OwasysFsmDraftCommandProviderInterface
{
    private const COMMAND = 'owasys:fsm:draft-edit';

    private readonly AclPolicy $acl;
    private readonly ProfilerInterface $profiler;
    private readonly FsmDefinitionEditorInterface $editor;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');
        $this->profiler = new Profiler($siteRoot . '/var/profiler/fsm');
        $this->editor = new FsmDefinitionEditor();
    }

    public function supports(string $command): bool
    {
        return $command === self::COMMAND;
    }

    public function execute(string $command, array $arguments, array $request): array
    {
        if (!$this->supports($command)) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_COMMAND_UNKNOWN:' . $command);
        }
        $actor = $this->actor($request);
        $decision = $this->acl->decide((array) $actor['roles'], 'fsm', 'update');
        if (!$decision->allowed) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_ACL_DENIED');
        }

        $parameters = is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
        $siteId = trim((string) ($parameters['site_id'] ?? ($arguments[0] ?? '')));
        $baseHash = strtolower(trim((string) ($parameters['base_sha256'] ?? '')));
        $draftJson = $parameters['draft_json'] ?? null;
        $commandJson = $parameters['command_json'] ?? null;
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $baseHash) !== 1
            || !is_string($draftJson)
            || !is_string($commandJson)
            || strlen($draftJson) > 2097152
            || strlen($commandJson) > 65536) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_PARAMETERS_INVALID');
        }

        $fsmPath = $this->opusRoot . '/sites/' . $siteId . '/config/fsm.json';
        $raw = File::instance()->read($fsmPath, 2097152);
        $liveHash = hash('sha256', $raw);
        if (!hash_equals($liveHash, $baseHash)) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_BASE_HASH_CONFLICT');
        }
        $live = StructuredFileLoader::instance()->read($fsmPath);
        $draft = Json::instance()->parse($draftJson, 'fsm-draft');
        $semanticCommand = Json::instance()->parse($commandJson, 'fsm-command');
        if (($draft['contract'] ?? null) !== ($live['contract'] ?? null)) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_CONTRACT_MISMATCH');
        }

        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }
        $operation = trim((string) ($semanticCommand['operation'] ?? ''));
        try {
            $this->profiler->event('fsm', 'designer.draft_command.received', [
                'site_id' => $siteId,
                'operation' => $operation,
                'state_id' => (string) ($semanticCommand['state_id'] ?? ''),
            ]);
            $result = $this->editor->apply($draft, $semanticCommand);
            $definition = $result['definition'];
            $definitionJson = Json::instance()->encode($definition, false);
            $this->profiler->event('fsm', 'designer.draft_command.validated', [
                'site_id' => $siteId,
                'operation' => $operation,
                'definition_sha256' => hash('sha256', $definitionJson),
                'diagnostic_count' => count($result['diagnostics']),
            ]);
            return [
                'contract' => 'OWASYS_EFSM_DRAFT_COMMAND_RESULT_V1',
                'base_sha256' => $baseHash,
                'draft_sha256' => hash('sha256', $definitionJson),
                'operation' => $result['operation'],
                'refactor' => $result['refactor'],
                'diagnostics' => $result['diagnostics'],
                'definition' => $definition,
            ];
        } finally {
            if ($ownsTrace && $this->profiler->getActiveTrace() !== null) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => self::COMMAND,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }

    /** @param array<string,mixed> $request @return array{subject:string,roles:list<string>,provider:string} */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null) !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException('OWASYS_FSM_DRAFT_REQUEST_CONTRACT_INVALID');
        }
        $actor = is_array($request['actor'] ?? null) ? $request['actor'] : [];
        $subject = trim((string) ($actor['subject'] ?? ''));
        $provider = trim((string) ($actor['provider'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter($actor['roles'], 'is_string')))
            : [];
        if ($subject === '' || $provider === '' || $roles === []) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_ACTOR_INVALID');
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
        $traceId = strtolower(trim((string) ($request['trace_id'] ?? '')));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new RuntimeException('OWASYS_FSM_DRAFT_TRACE_ID_INVALID');
        }
        return $traceId;
    }
}