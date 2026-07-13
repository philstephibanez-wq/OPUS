<?php
declare(strict_types=1);

/**
 * Bootstraps a runtime-only OWASYS local user.
 *
 * The generated user store is written under sites/owasys/var/auth/ and must stay
 * ignored by Git. No password or password hash belongs in committed files.
 */

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$site = json_decode((string) file_get_contents($siteFile), true);
if (!is_array($site)) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_SITE_CONFIG_INVALID\n");
    exit(1);
}

$auth = is_array($site['auth'] ?? null) ? $site['auth'] : [];
$storeRelative = trim(str_replace('\\', '/', (string) ($auth['user_store'] ?? 'var/auth/local-users.json')), '/');
if ($storeRelative === '' || str_contains($storeRelative, '..')) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_STORE_PATH_INVALID\n");
    exit(1);
}

$minimumPasswordLength = (int) ($auth['minimum_password_length'] ?? 12);
if ($minimumPasswordLength < 8) {
    $minimumPasswordLength = 8;
}
$mustChangePassword = ($auth['must_change_password_on_bootstrap'] ?? true) === true;

$storeFile = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storeRelative);
$username = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');
$profile = trim((string) ($argv[3] ?? 'admin'));

if ($username === '') {
    fwrite(STDOUT, "Username: ");
    $username = trim((string) fgets(STDIN));
}

if ($password === '') {
    fwrite(STDOUT, "Password: ");
    $password = rtrim((string) fgets(STDIN), "\r\n");
}

if (preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $username) !== 1) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_USERNAME_INVALID\n");
    exit(1);
}

if (strlen($password) < $minimumPasswordLength) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_PASSWORD_TOO_SHORT\n");
    exit(1);
}

if (!in_array($profile, ['admin', 'dev'], true)) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_PROFILE_INVALID\n");
    exit(1);
}

$parent = dirname($storeFile);
if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_STORE_DIRECTORY_FAILED\n");
    exit(1);
}

$store = [
    'contract' => 'OWASYS_LOCAL_USER_STORE_V1',
    'generated_by' => 'tools/owasys_auth_bootstrap_local_user.php',
    'committed' => false,
    'users' => [],
];

if (is_file($storeFile)) {
    $existing = json_decode((string) file_get_contents($storeFile), true);
    if (!is_array($existing) || ($existing['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
        fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_EXISTING_STORE_INVALID\n");
        exit(1);
    }
    $store = $existing;
    if (!isset($store['users']) || !is_array($store['users'])) {
        $store['users'] = [];
    }
}

$store['contract'] = 'OWASYS_LOCAL_USER_STORE_V1';
$store['generated_by'] = 'tools/owasys_auth_bootstrap_local_user.php';
$store['committed'] = false;
$store['updated_at'] = gmdate('c');
$store['users'][$username] = [
    'id' => $username,
    'label' => $username,
    'profile' => $profile,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'must_change_password' => $mustChangePassword,
    'password_created_at' => gmdate('c'),
    'password_changed_at' => null,
    'updated_at' => gmdate('c'),
];

$encoded = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($encoded) || file_put_contents($storeFile, $encoded . "\n") === false) {
    fwrite(STDERR, "OWASYS_AUTH_BOOTSTRAP_STORE_WRITE_FAILED\n");
    exit(1);
}

fwrite(STDOUT, "OWASYS_AUTH_BOOTSTRAP_USER_OK: {$username}\n");
fwrite(STDOUT, "OWASYS_AUTH_BOOTSTRAP_MUST_CHANGE_PASSWORD: " . ($mustChangePassword ? 'true' : 'false') . "\n");
fwrite(STDOUT, "OWASYS_AUTH_BOOTSTRAP_STORE: sites/owasys/{$storeRelative}\n");
