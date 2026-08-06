<?php
declare(strict_types=1);

use Opus\Application\Inspection\SiteSourceInspector;
use Opus\Application\Inspection\SiteSourceInspectorInterface;
use Opus\Application\Source\SiteSourceWorkspace;
use Opus\Application\Source\SiteSourceWorkspaceInterface;
use Opus\Log\Logger;
use Opus\Profiler\Profiler;
use Opus\Profiler\ProfilerInterface;
use Opus\Security\Acl\AclPolicy;

/** Application-owned adapter exposing generic OPUS source operations to REST_API. */
final class OwasysSourceCommandProvider implements OwasysSourceCommandProviderInterface
{
    private const COMMANDS = [
        'owasys:source:list' => true,
        'owasys:source:read' => true,
        'owasys:source:browse' => true,
        'owasys:source:preview' => true,
        'owasys:source:write' => true,
    ];

    private readonly AclPolicy $acl;
    private readonly SiteSourceInspectorInterface $inspector;
    private readonly SiteSourceWorkspaceInterface $workspace;
    private readonly ProfilerInterface $profiler;

    public function __construct(string $siteRoot, string $opusRoot)
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');
        $this->inspector = new SiteSourceInspector($opusRoot);
        $logger = new Logger(
            $siteRoot . '/var/logs',
            'owasys-back.log'
        );
        $this->profiler = new Profiler(
            $siteRoot . '/var/profiler/source'
        );
        $this->workspace = new SiteSourceWorkspace(
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
            throw new RuntimeException('OWASYS_SOURCE_COMMAND_UNKNOWN:' . $command);
        }
        $actor = $this->actor($request);
        $this->assertAllowed(
            $actor,
            'source',
            $this->action($command)
        );

        $traceId = $this->traceId($request);
        $ownsTrace = false;
        if ($this->profiler->getActiveTrace() === null) {
            $this->profiler->start($traceId);
            $ownsTrace = true;
        }

        try {
            $siteId = trim((string) ($arguments[0] ?? ''));
            return match ($command) {
                'owasys:source:list' => $this->inspector->list($siteId),
                'owasys:source:read' => $this->inspector->read(
                    $siteId,
                    (string) ($arguments[1] ?? '')
                ),
                'owasys:source:browse' => $this->browse(
                    $siteId,
                    (string) ($arguments[1] ?? '')
                ),
                'owasys:source:preview' => $this->preview($request),
                'owasys:source:write' => $this->write($request),
                default => throw new RuntimeException(
                    'OWASYS_SOURCE_COMMAND_UNKNOWN:' . $command
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

    /** @return array<string,mixed> */
    private function browse(string $siteId, string $path): array
    {
        return [
            'contract' => 'OWASYS_SOURCE_BROWSE_V1',
            'listing' => $this->inspector->list($siteId),
            'selected' => $this->inspector->read($siteId, $path),
        ];
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function preview(array $request): array
    {
        $parameters = $this->mutationParameters($request);
        return $this->workspace->preview(
            $parameters['site_id'],
            $parameters['path'],
            $parameters['expected_content_hash'],
            $parameters['new_content']
        );
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function write(array $request): array
    {
        $parameters = $this->mutationParameters($request);
        return $this->workspace->write(
            $parameters['site_id'],
            $parameters['path'],
            $parameters['expected_content_hash'],
            $parameters['new_content']
        );
    }

    /**
     * @param array<string,mixed> $request
     * @return array{
     *   site_id:string,
     *   path:string,
     *   expected_content_hash:string,
     *   new_content:string
     * }
     */
    private function mutationParameters(array $request): array
    {
        $parameters = is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
        $siteId = trim((string) ($parameters['site_id'] ?? ''));
        $path = (string) ($parameters['path'] ?? '');
        $expected = strtolower(trim((string) (
            $parameters['expected_content_hash'] ?? ''
        )));
        $newContent = $parameters['new_content'] ?? null;
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $siteId) !== 1
            || $path === ''
            || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1
            || !is_string($newContent)) {
            throw new RuntimeException(
                'OWASYS_SOURCE_MUTATION_PARAMETERS_INVALID'
            );
        }
        return [
            'site_id' => $siteId,
            'path' => $path,
            'expected_content_hash' => $expected,
            'new_content' => $newContent,
        ];
    }

    private function action(string $command): string
    {
        return match ($command) {
            'owasys:source:list',
            'owasys:source:read',
            'owasys:source:browse' => 'read',
            'owasys:source:preview' => 'preview',
            'owasys:source:write' => 'write',
            default => throw new RuntimeException(
                'OWASYS_SOURCE_COMMAND_UNKNOWN:' . $command
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
            throw new RuntimeException('OWASYS_SOURCE_COMMAND_ACL_DENIED');
        }
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException(
                'OWASYS_SOURCE_COMMAND_REQUEST_CONTRACT_INVALID'
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
            throw new RuntimeException('OWASYS_SOURCE_COMMAND_ACTOR_INVALID');
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
        if (preg_match('/^[a-f0-9]{16,64}$/', $traceId) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_TRACE_ID_INVALID');
        }
        return $traceId;
    }
}
