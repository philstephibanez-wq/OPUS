<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
require $site . '/application/default/autoload.php';

use Owasys\Application\Authentication\LocalUserStore;
use Owasys\Application\Authentication\PasswordAuthenticator;
use Owasys\Application\Authentication\PasswordChanger;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$tmp = sys_get_temp_dir() . '/owasys-password-change-' . bin2hex(random_bytes(8)) . '.json';

try {
    $store = new LocalUserStore($tmp);
    $store->write([
        'users' => [
            'dev' => [
                'id' => 'dev',
                'label' => 'Developer',
                'profile' => 'dev',
                'password_hash' => password_hash('CurrentPass123!', PASSWORD_DEFAULT),
                'must_change_password' => true,
            ],
        ],
    ]);

    $changer = new PasswordChanger($store);
    $changed = $changer->change('dev', 'CurrentPass123!', 'NewPassword456!', 'NewPassword456!');
    if (($changed['must_change_password'] ?? true) !== false) {
        $fail('OWASYS_PASSWORD_CHANGE_FLAG_NOT_CLEARED');
    }

    $authenticator = new PasswordAuthenticator($store);
    if ($authenticator->authenticate('dev', 'CurrentPass123!') !== null) {
        $fail('OWASYS_PASSWORD_CHANGE_OLD_PASSWORD_ACCEPTED');
    }
    $authenticated = $authenticator->authenticate('dev', 'NewPassword456!');
    if (!is_array($authenticated) || ($authenticated['id'] ?? null) !== 'dev') {
        $fail('OWASYS_PASSWORD_CHANGE_NEW_PASSWORD_REJECTED');
    }

    $persisted = $store->find('dev');
    if (!is_array($persisted) || ($persisted['must_change_password'] ?? true) !== false) {
        $fail('OWASYS_PASSWORD_CHANGE_NOT_PERSISTED');
    }
    if (!is_string($persisted['password_changed_at'] ?? null) || $persisted['password_changed_at'] === '') {
        $fail('OWASYS_PASSWORD_CHANGE_TIMESTAMP_MISSING');
    }

    foreach ([
        ['NewPassword456!', 'short', 'short', 'OWASYS_PASSWORD_CHANGE_TOO_SHORT'],
        ['NewPassword456!', 'AnotherPass789!', 'MismatchPass789!', 'OWASYS_PASSWORD_CHANGE_CONFIRMATION_MISMATCH'],
        ['NewPassword456!', 'NewPassword456!', 'NewPassword456!', 'OWASYS_PASSWORD_CHANGE_UNCHANGED'],
    ] as [$current, $new, $confirmation, $expected]) {
        try {
            $changer->change('dev', $current, $new, $confirmation);
            $fail('OWASYS_PASSWORD_CHANGE_EXPECTED_FAILURE_MISSING:' . $expected);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== $expected) {
                $fail('OWASYS_PASSWORD_CHANGE_WRONG_FAILURE:' . $exception->getMessage());
            }
        }
    }
} catch (Throwable $exception) {
    $fail($exception->getMessage());
} finally {
    @unlink($tmp);
    @unlink($tmp . '.tmp');
}

echo 'OWASYS_PASSWORD_CHANGE_BOUNDARY_SMOKE_OK' . PHP_EOL;
