<?php
declare(strict_types=1);

namespace Owasys\Application\Authentication;

use RuntimeException;

final class LocalUserStore
{
    private string $file;

    public function __construct(string $file)
    {
        $file = str_replace('\\', '/', $file);
        if ($file === '' || str_contains($file, "\0")) {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_PATH_INVALID');
        }
        $this->file = $file;
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        if (!is_file($this->file)) {
            return ['contract' => 'OWASYS_LOCAL_USER_STORE_V1', 'committed' => false, 'users' => []];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($decoded) || ($decoded['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
            throw new RuntimeException('OWASYS_LOCAL_USER_STORE_INVALID');
        }
        if (!is_array($decoded['users'] ?? null)) {
            $decoded['users'] = [];
        }
        return $decoded;
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        $users = $this->read()['users'] ?? [];
        $candidate = is_array($users) ? ($users[$id] ?? null) : null;
        return is_array($candidate) ? $candidate : null;
    }

    /** @param array<string,mixed> $store */
    public function write(array $store): void
    {
        $parent = dirname($this->file);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_DIRECTORY_FAILED');
        }

        $store['contract'] = 'OWASYS_LOCAL_USER_STORE_V1';
        $store['committed'] = false;
        $store['updated_at'] = gmdate('c');
        if (!is_array($store['users'] ?? null)) {
            $store['users'] = [];
        }

        $encoded = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $this->file . '.tmp';
        if (file_put_contents($tmp, $encoded . "\n", LOCK_EX) === false) {
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_WRITE_FAILED');
        }
        if (!rename($tmp, $this->file)) {
            @unlink($tmp);
            throw new RuntimeException('OWASYS_AUTH_USER_STORE_REPLACE_FAILED');
        }
    }
}
