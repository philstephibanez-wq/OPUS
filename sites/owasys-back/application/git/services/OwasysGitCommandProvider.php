<?php
declare(strict_types=1);

use Opus\Application\Git\SiteGitWorkspace;
use Opus\Application\Git\SiteGitWorkspaceInterface;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/** Application-owned adapter exposing bounded OPUS Git operations to REST_API. */
final class OwasysGitCommandProvider
    implements OwasysGitCommandProviderInterface
{
    private const COMMANDS = [
        'owasys:git:status' => true,
        'owasys:git:diff' => true,
        'owasys:git:history' => true,
        'owasys:git:stage' => true,
        'owasys:git:unstage' => true,
        'owasys:git:commit' => true,
        'owasys:git:restore' => true,
    ];

    private readonly AclPolicy $acl;
    private readonly SiteGitWorkspaceInterface $workspace;
    private readonly ProfilerInterface $profiler;

    public function __construct(string $siteRoot, string $opusRoot)
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');
        $logger = new Logger(
            $siteRoot . '/var/logs',
            'owasys-back.log'
        );
        $this->profiler = new Profiler(
            $siteRoot . '/var/profiler/git'
        );
        $this->workspace = new SiteGitWorkspace(
            $opusRoot,
            $logger,
            $this->profiler
        );
    }

    public function supports(string $command): bool
    {
        return isset(self::COMMANDS[$command]);
    }

    public function execute(
        string $command,
        array $arguments,
        array $request
    ): array {
        if (!$this->supports($command)) {
            throw new RuntimeException(
                'OWASYS_GIT_COMMAND_UNKNOWN:' . $command
            );
        }

        $actor = $this->actor($request);
        $this->assertAllowed($actor, 'git', $this->action($command));
        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }

        try {
            $siteId = trim((string) ($arguments[0] ?? ''));
            $path = (string) ($arguments[1] ?? '');

            return match ($command) {
                'owasys:git:status' => $this->workspace->status($siteId),
                'owasys:git:diff' => $this->workspace->diff(
                    $siteId,
                    $path
                ),
                'owasys:git:history' => $this->workspace->history(
                    $siteId,
                    $this->historyLimit($request)
                ),
                'owasys:git:stage' => $this->workspace->stage(
                    $siteId,
                    $path
                ),
                'owasys:git:unstage' => $this->workspace->unstage(
                    $siteId,
                    $path
                ),
                'owasys:git:commit' => $this->workspace->commit(
                    $siteId,
                    $this->commitMessage($request)
                ),
                'owasys:git:restore' => $this->workspace->restore(
                    $siteId,
                    $path,
                    $this->restoreHash($request),
                    $this->restoreConfirmation($request)
                ),
                default => throw new RuntimeException(
                    'OWASYS_GIT_COMMAND_UNKNOWN:' . $command
                ),
            };
        } finally {
            if ($ownsTrace && $this->profiler->getActiveTrace() !== null) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => $command,
                    'trace_id' => $traceId,
                ]);
            }
        }
    }

    private function action(string $command): string
    {
        return match ($command) {
            'owasys:git:status',
            'owasys:git:diff',
            'owasys:git:history' => 'read',
            'owasys:git:stage' => 'stage',
            'owasys:git:unstage' => 'unstage',
            'owasys:git:commit' => 'commit',
            'owasys:git:restore' => 'restore',
            default => throw new RuntimeException(
                'OWASYS_GIT_COMMAND_UNKNOWN:' . $command
            ),
        };
    }

    /** @param array<string,mixed> $actor */
    private function assertAllowed(
        array $actor,
        string $resource,
        string $action
    ): void {
        $decision = $this->acl->decide(
            (array) ($actor['roles'] ?? []),
            $resource,
            $action
        );
        if (!$decision->allowed) {
            throw new RuntimeException('OWASYS_GIT_COMMAND_ACL_DENIED');
        }
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException(
                'OWASYS_GIT_COMMAND_REQUEST_CONTRACT_INVALID'
            );
        }
        $actor = is_array($request['actor'] ?? null)
            ? $request['actor']
            : [];
        $subject = trim((string) ($actor['subject'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new RuntimeException(
                'OWASYS_GIT_COMMAND_ACTOR_INVALID'
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
        $traceId = strtolower(trim((string) ($request['trace_id'] ?? '')));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new RuntimeException('OWASYS_GIT_TRACE_ID_INVALID');
        }
        return $traceId;
    }

    /** @param array<string,mixed> $request */
    private function historyLimit(array $request): int
    {
        $parameters = $this->parameters($request);
        $limit = $parameters['limit'] ?? 20;
        if (!is_int($limit) && !is_string($limit)) {
            throw new RuntimeException(
                'OWASYS_GIT_HISTORY_LIMIT_INVALID'
            );
        }
        $limit = filter_var(
            $limit,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 50]]
        );
        if (!is_int($limit)) {
            throw new RuntimeException(
                'OWASYS_GIT_HISTORY_LIMIT_INVALID'
            );
        }
        return $limit;
    }

    /** @param array<string,mixed> $request */
    private function commitMessage(array $request): string
    {
        $message = $this->parameters($request)['message'] ?? null;
        if (!is_string($message)) {
            throw new RuntimeException(
                'OWASYS_GIT_COMMIT_MESSAGE_INVALID'
            );
        }
        return $message;
    }

    /** @param array<string,mixed> $request */
    private function restoreHash(array $request): string
    {
        $hash = $this->parameters($request)[
            'expected_content_hash'
        ] ?? null;
        if (!is_string($hash)) {
            throw new RuntimeException(
                'OWASYS_GIT_RESTORE_HASH_INVALID'
            );
        }
        return $hash;
    }

    /** @param array<string,mixed> $request */
    private function restoreConfirmation(array $request): string
    {
        $confirmation = $this->parameters($request)[
            'confirmation'
        ] ?? null;
        if (!is_string($confirmation)) {
            throw new RuntimeException(
                'OWASYS_GIT_RESTORE_CONFIRMATION_INVALID'
            );
        }
        return $confirmation;
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    private function parameters(array $request): array
    {
        return is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
    }
}
