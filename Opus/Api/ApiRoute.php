<?php
declare(strict_types=1);

namespace Opus\Api;

/**
 * Immutable REST route contract loaded from OPUS API configuration.
 */
final class ApiRoute
{
    public string $id;
    public string $method;
    public string $path;
    public string $endpointClass;
    public string $aclPolicy;
    public ?string $fsmFlow;
    public ?string $fsmSignal;
    /** @var array<string,mixed> */
    public array $meta;

    /** @param array<string,mixed> $config */
    public function __construct(array $config)
    {
        foreach (['id', 'method', 'path', 'endpoint', 'acl_policy'] as $required) {
            if (!array_key_exists($required, $config) || (string) $config[$required] === '') {
                throw new \RuntimeException('OPUS_API_ROUTE_CONFIG_MISSING_KEY: ' . $required);
            }
        }

        $this->id = (string) $config['id'];
        $this->method = strtoupper((string) $config['method']);
        $this->path = trim((string) $config['path'], '/');
        $this->endpointClass = (string) $config['endpoint'];
        $this->aclPolicy = (string) $config['acl_policy'];
        $this->fsmFlow = isset($config['fsm_flow']) && (string) $config['fsm_flow'] !== '' ? (string) $config['fsm_flow'] : null;
        $this->fsmSignal = isset($config['fsm_signal']) && (string) $config['fsm_signal'] !== '' ? (string) $config['fsm_signal'] : null;
        $this->meta = $config;
    }
}
