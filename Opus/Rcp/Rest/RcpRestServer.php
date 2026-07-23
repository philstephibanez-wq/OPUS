<?php
declare(strict_types=1);

namespace Opus\Rcp\Rest;

use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\I18n\BrowserLocaleNegotiator;
use Opus\Rcp\Composer\ComposerCommandExecutor;
use Opus\Rcp\Composer\ComposerCommandExecutorInterface;
use Opus\Rcp\Composer\ComposerCommandRegistry;
use Opus\Rcp\Composer\ComposerCommandRegistryInterface;
use Opus\Rcp\Fsm\RcpExecutionStateMachine;
use Opus\Rcp\Security\RcpIdentityInterface;
use Opus\Rcp\Security\RcpRequestAuthenticator;
use Opus\Rcp\Security\RcpRequestAuthenticatorInterface;

/**
 * Generic secured REST boundary for typed Composer-backed OPUS operations.
 *
 * Clients supply only an operation identifier and typed parameters. Executable
 * paths, Composer script names, working directories and shell fragments are
 * resolved exclusively from trusted configuration.
 */
final class RcpRestServer implements RcpRestServerInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ComposerCommandRegistryInterface $registry,
        private readonly ComposerCommandExecutorInterface $executor,
        private readonly RcpRequestAuthenticatorInterface $authenticator,
        private readonly RcpExecutionStoreInterface $store
    ) {
    }

    public static function fromRoot(
        string $opusRoot,
        string $configRelative
    ): self {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('OPUS_RCP_ROOT_INVALID');
        }

        $loader = StructuredFileLoader::instance();
        $config = $loader->read(
            $root . '/' . self::safeRelative($configRelative)
        );
        if (($config['contract'] ?? null)
            !== 'OPUS_RCP_REST_SERVER_CONFIG_V2') {
            throw new \RuntimeException(
                'OPUS_RCP_REST_CONFIG_CONTRACT_INVALID'
            );
        }

        $catalogRelative = self::safeRelative(
            (string) ($config['operation_catalog'] ?? '')
        );
        $storeRelative = self::safeRelative(
            (string) ($config['execution_store'] ?? '')
        );

        return new self(
            $config,
            ComposerCommandRegistry::fromRoot($root, $catalogRelative),
            new ComposerCommandExecutor(
                $root,
                self::composerCommand($root, $config),
                (int) ($config['timeout_seconds'] ?? 120),
                (int) ($config['max_output_bytes'] ?? 2097152)
            ),
            new RcpRequestAuthenticator(
                is_array($config['authentication'] ?? null)
                    ? $config['authentication']
                    : []
            ),
            new RcpExecutionStore($root . '/' . $storeRelative)
        );
    }

    public function handle(Request $request): Response
    {
        $path = '/' . trim($request->path, '/');
        $base = '/' . trim(
            (string) ($this->config['base_path'] ?? '/api/v1'),
            '/'
        );
        $locale = $this->locale();

        if ($request->method === 'GET' && $path === $base . '/status') {
            return Response::json([
                'contract' => 'OPUS_RCP_REST_STATUS_V1',
                'status' => 'ok',
                'transport' => 'rest',
                'execution_boundary' => 'composer',
                'locale' => $locale,
            ]);
        }

        if ($request->method !== 'POST'
            || $path !== $base . '/executions') {
            return $this->errorCode(
                'OPUS_RCP_REST_ROUTE_NOT_FOUND',
                404,
                $locale
            );
        }

        return $this->execute($request, $locale);
    }

    private function execute(Request $request, string $locale): Response
    {
        $fsmConfig = is_array($this->config['fsm'] ?? null)
            ? $this->config['fsm']
            : [];
        $transitions = is_array($fsmConfig['transitions'] ?? null)
            ? $fsmConfig['transitions']
            : [];
        $fsm = new RcpExecutionStateMachine(
            (string) ($fsmConfig['initial_state'] ?? 'received'),
            $transitions
        );

        $executionId = '';
        $operation = '';

        try {
            $payload = $request->jsonBody();
            if (($payload['contract'] ?? null)
                !== 'OPUS_RCP_REST_EXECUTION_REQUEST_V1') {
                throw new \RuntimeException(
                    'OPUS_RCP_REQUEST_CONTRACT_INVALID'
                );
            }

            $executionId = trim((string) (
                $payload['execution_id'] ?? ''
            ));
            $operation = trim((string) ($payload['operation'] ?? ''));
            $parameters = $payload['parameters'] ?? null;

            if (preg_match('/^[a-f0-9]{32}$/', $executionId) !== 1) {
                throw new \RuntimeException(
                    'OPUS_RCP_EXECUTION_ID_INVALID'
                );
            }
            if (!is_array($parameters)) {
                throw new \RuntimeException('OPUS_RCP_PARAMETERS_INVALID');
            }

            $expiresAt = trim((string) (
                $payload['expires_at_utc'] ?? ''
            ));
            $expiry = strtotime($expiresAt);
            if ($expiry === false
                || $expiry < time()
                || $expiry > time() + 300) {
                throw new \RuntimeException('OPUS_RCP_REQUEST_EXPIRED');
            }
            if ($this->store->exists($executionId)) {
                throw new \RuntimeException('OPUS_RCP_REPLAY_REJECTED');
            }

            $identity = $this->authenticator->authenticate(
                $request,
                $payload,
                $_SERVER
            );
            $fsm->transition('authenticated');

            $entry = $this->registry->operation($operation);
            $this->assertAuthorized($identity, $entry);
            $fsm->transition('authorized');

            $entry['argv'] = $this->registry->arguments(
                $entry,
                $parameters
            );
            $commandRequest = [
                'contract' => 'OPUS_RCP_COMPOSER_COMMAND_REQUEST_V1',
                'execution_id' => $executionId,
                'operation' => $operation,
                'actor' => $identity->toArray(),
                'parameters' => $parameters,
                'requested_at_utc' => gmdate('c'),
            ];

            $fsm->transition('dispatching');
            $commandResult = $this->executor->execute(
                $entry,
                $commandRequest
            );
            $fsm->transition('succeeded');

            $record = [
                'contract' => 'OPUS_RCP_REST_EXECUTION_V1',
                'execution_id' => $executionId,
                'operation' => $operation,
                'status' => 'succeeded',
                'message_key' => 'rcp.'
                    . str_replace('.', '_', $operation)
                    . '.succeeded',
                'locale' => $locale,
                'actor' => [
                    'subject' => $identity->subject(),
                    'roles' => $identity->roles(),
                ],
                'fsm' => [
                    'state' => $fsm->state(),
                    'history' => $fsm->history(),
                ],
                'result' => $commandResult['data'] ?? null,
                'completed_at_utc' => gmdate('c'),
            ];
            $this->store->write($executionId, $record);
            unset($parameters, $commandRequest, $commandResult);

            return Response::json($record, 201);
        } catch (\Throwable $error) {
            try {
                if ($fsm->state() !== 'failed') {
                    $fsm->transition('failed');
                }
            } catch (\Throwable) {
            }

            $code = $this->safeErrorCode($error);
            $record = [
                'contract' => 'OPUS_RCP_REST_EXECUTION_V1',
                'execution_id' => $executionId,
                'operation' => $operation,
                'status' => 'failed',
                'error_code' => $code,
                'message_key' => 'rcp.execution.failed',
                'locale' => $locale,
                'fsm' => [
                    'state' => $fsm->state(),
                    'history' => $fsm->history(),
                ],
                'completed_at_utc' => gmdate('c'),
            ];

            if (preg_match('/^[a-f0-9]{32}$/', $executionId) === 1
                && !$this->store->exists($executionId)) {
                $this->store->write($executionId, $record);
            }

            $status = match (true) {
                str_contains($code, 'AUTH') => 401,
                str_contains($code, 'ACL') => 403,
                str_contains($code, 'UNKNOWN') => 404,
                str_contains($code, 'REPLAY') => 409,
                default => 400,
            };

            return Response::json($record, $status);
        }
    }

    /** @param array<string,mixed> $entry */
    private function assertAuthorized(
        RcpIdentityInterface $identity,
        array $entry
    ): void {
        $required = is_array($entry['roles'] ?? null)
            ? array_values(array_filter($entry['roles'], 'is_string'))
            : [];
        if ($required === []
            || array_intersect($required, $identity->roles()) === []) {
            throw new \RuntimeException('OPUS_RCP_ACL_DENIED');
        }
    }

    private function locale(): string
    {
        $supported = is_array($this->config['supported_locales'] ?? null)
            ? array_values(array_filter(
                $this->config['supported_locales'],
                'is_string'
            ))
            : [];
        $default = trim((string) (
            $this->config['default_locale'] ?? ''
        ));
        if ($supported === [] || $default === '') {
            throw new \RuntimeException(
                'OPUS_RCP_LOCALE_CONFIG_INVALID'
            );
        }

        return BrowserLocaleNegotiator::forLocales($supported, $default)
            ->negotiate(
                is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null)
                    ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
                    : null
            )->value;
    }

    private function errorCode(
        string $code,
        int $status,
        string $locale
    ): Response {
        return Response::json([
            'contract' => 'OPUS_RCP_REST_ERROR_V1',
            'status' => 'failed',
            'error_code' => $code,
            'message_key' => 'rcp.error.' . strtolower($code),
            'locale' => $locale,
        ], $status);
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OPUS_RCP_EXECUTION_FAILED';
    }

    /** @param array<string,mixed> $config @return list<string> */
    private static function composerCommand(
        string $root,
        array $config
    ): array {
        $selected = is_array($config['composer_command'] ?? null)
            ? array_values(array_filter(
                $config['composer_command'],
                'is_string'
            ))
            : [];
        if ($selected === []) {
            throw new \RuntimeException(
                'OPUS_RCP_COMPOSER_COMMAND_MISSING'
            );
        }

        $resolved = [];
        foreach ($selected as $index => $part) {
            $part = trim($part);
            if ($part === '@php') {
                $resolved[] = PHP_BINARY;
                continue;
            }
            if ($part === '') {
                throw new \RuntimeException(
                    'OPUS_RCP_COMPOSER_COMMAND_INVALID'
                );
            }
            if ($index > 0 && str_ends_with(strtolower($part), '.phar')) {
                $relative = self::safeRelative($part);
                $absolute = $root . '/' . $relative;
                if (!File::instance()->exists($absolute)) {
                    throw new \RuntimeException(
                        'OPUS_RCP_COMPOSER_PHAR_MISSING:' . $relative
                    );
                }
                $resolved[] = $absolute;
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._-]+$/', $part) !== 1) {
                throw new \RuntimeException(
                    'OPUS_RCP_COMPOSER_COMMAND_INVALID'
                );
            }
            $resolved[] = $part;
        }

        return $resolved;
    }

    private static function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \RuntimeException('OPUS_RCP_CONFIG_PATH_INVALID');
        }
        return $path;
    }
}
