<?php
declare(strict_types=1);

final class OwasysRuntimeUserStore
{
    public function __construct(private readonly string $file)
    {
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        if (!is_file($this->file)) {
            return [
                'contract' => 'OWASYS_LOCAL_USER_STORE_V1',
                'committed' => false,
                'users' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($decoded) || ($decoded['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_INVALID');
        }

        $decoded['users'] = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];

        return $decoded;
    }

    /** @return array<string,mixed>|null */
    public function find(string $username): ?array
    {
        $store = $this->read();
        $user = $store['users'][$username] ?? null;

        return is_array($user) ? $user : null;
    }

    /** @param array<string,mixed> $user */
    public function saveUser(string $username, array $user): void
    {
        $store = $this->read();
        $store['users'][$username] = $user;
        $store['contract'] = 'OWASYS_LOCAL_USER_STORE_V1';
        $store['committed'] = false;
        $store['updated_at'] = gmdate('c');

        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_DIRECTORY_FAILED');
        }

        $encoded = json_encode(
            $store,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($this->file, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_WRITE_FAILED');
        }
    }
}
