<?php
declare(strict_types=1);

use Opus\Application\Inspection\SiteSourceInspector;
use Opus\Application\Inspection\SiteSourceInspectorInterface;
use Opus\Security\Acl\AclPolicy;

/** Application-owned adapter exposing generic OPUS source inspection to REST_API. */
final class OwasysSourceCommandProvider implements OwasysSourceCommandProviderInterface
{
    private const COMMANDS = [
        'owasys:source:list' => true,
        'owasys:source:read' => true,
        'owasys:source:browse' => true,
    ];

    private readonly AclPolicy $acl;
    private readonly SiteSourceInspectorInterface $inspector;

    public function __construct(string $siteRoot, string $opusRoot)
    {
        $this->acl = new AclPolicy($siteRoot . '/config/acl.json');
        $this->inspector = new SiteSourceInspector($opusRoot);
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
        $this->assertAllowed($actor, 'source', 'read');
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
            default => throw new RuntimeException(
                'OWASYS_SOURCE_COMMAND_UNKNOWN:' . $command
            ),
        };
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
}
