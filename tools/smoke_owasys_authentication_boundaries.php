<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
require $site . '/application/default/autoload.php';

use Owasys\Application\Authentication\LocalUserStore;
use Owasys\Application\Authentication\PasswordAuthenticator;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$tmpRoot = sys_get_temp_dir() . '/owasys-auth-boundary-' . bin2hex(random_bytes(6));
$storeFile = $tmpRoot . '/local-users.json';

try {
    $store = new LocalUserStore($storeFile);
    $document = $store->read();
    if (($document['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1' || ($document['users'] ?? null) !== []) {
        $fail('OWASYS_AUTH_BOUNDARY_EMPTY_STORE_INVALID');
    }

    $store->write([
        'users' => [
            'dev' => [
                'id' => 'dev',
                'label' => 'Developer',
                'profile' => 'dev',
                'password_hash' => password_hash('correct-password', PASSWORD_DEFAULT),
                'must_change_password' => true,
            ],
        ],
    ]);

    $authenticator = new PasswordAuthenticator($store);
    if ($authenticator->authenticate('dev', 'wrong-password') !== null) {
        $fail('OWASYS_AUTH_BOUNDARY_WRONG_PASSWORD_ACCEPTED');
    }

    $user = $authenticator->authenticate('dev', 'correct-password');
    if (!is_array($user) || ($user['profile'] ?? null) !== 'dev' || ($user['must_change_password'] ?? null) !== true) {
        $fail('OWASYS_AUTH_BOUNDARY_VALID_LOGIN_REJECTED');
    }

    $written = json_decode((string) file_get_contents($storeFile), true);
    if (!is_array($written) || ($written['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
        $fail('OWASYS_AUTH_BOUNDARY_ATOMIC_STORE_INVALID');
    }
} catch (Throwable $exception) {
    $fail($exception->getMessage());
} finally {
    if (is_file($storeFile)) {
        @unlink($storeFile);
    }
    if (is_dir($tmpRoot)) {
        @rmdir($tmpRoot);
    }
}

echo 'OWASYS_AUTHENTICATION_BOUNDARIES_SMOKE_OK' . PHP_EOL;
