<?php
declare(strict_types=1);

namespace Opus\Runtime;

/**
 * @internal
 *
 * P117A13 smoke test for the Apache/UwAmp public front controller contract.
 *
 * This smoke does not configure Apache. It verifies the OPUS repository side:
 * public/index.php, public/.htaccess, routing rewrite contract and neutral public failure text.
 */
final class UwAmpPublicFrontControllerSmoke
{
    /**
     * @return array<string, bool|string|int>
     */
    public static function run(?string $opusRoot = null): array
    {
        $root = $opusRoot ?? dirname(__DIR__, 3);
        $publicDir = $root . DIRECTORY_SEPARATOR . 'public';
        $publicIndex = $publicDir . DIRECTORY_SEPARATOR . 'index.php';
        $htaccess = $publicDir . DIRECTORY_SEPARATOR . '.htaccess';
        $rootIndex = $root . DIRECTORY_SEPARATOR . 'index.php';

        $indexContent = is_file($publicIndex) ? (string) file_get_contents($publicIndex) : '';
        $htaccessContent = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';

        $checks = [
            'root_index_exists' => is_file($rootIndex),
            'public_dir_exists' => is_dir($publicDir),
            'public_index_exists' => is_file($publicIndex),
            'public_htaccess_exists' => is_file($htaccess),
            'public_index_delegates_to_root_index' => str_contains($indexContent, "'index.php'") && str_contains($indexContent, 'dirname(__DIR__)'),
            'public_failure_is_neutral' => str_contains($indexContent, 'Site temporairement bloquÃ©') && str_contains($indexContent, 'Contactez le support'),
            'public_index_logs_internal_failure' => str_contains($indexContent, 'OPUS_PUBLIC_FRONT_CONTROLLER_FAILURE'),
            'htaccess_disables_indexes' => str_contains($htaccessContent, 'Options -Indexes'),
            'htaccess_uses_rewrite_engine' => str_contains($htaccessContent, 'RewriteEngine On'),
            'htaccess_routes_to_index' => str_contains($htaccessContent, 'RewriteRule ^ index.php [QSA,L]'),
            'htaccess_blocks_dotfiles' => str_contains($htaccessContent, '<FilesMatch "^\.">'),
        ];

        $ok = true;
        foreach ($checks as $value) {
            if ($value !== true) {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'gate' => 'P117A13_UWAMP_APACHE_PUBLIC_FRONT_CONTROLLER_SMOKE',
            'root_index_exists' => $checks['root_index_exists'],
            'public_dir_exists' => $checks['public_dir_exists'],
            'public_index_exists' => $checks['public_index_exists'],
            'public_htaccess_exists' => $checks['public_htaccess_exists'],
            'public_index_delegates_to_root_index' => $checks['public_index_delegates_to_root_index'],
            'public_failure_is_neutral' => $checks['public_failure_is_neutral'],
            'public_index_logs_internal_failure' => $checks['public_index_logs_internal_failure'],
            'htaccess_disables_indexes' => $checks['htaccess_disables_indexes'],
            'htaccess_uses_rewrite_engine' => $checks['htaccess_uses_rewrite_engine'],
            'htaccess_routes_to_index' => $checks['htaccess_routes_to_index'],
            'htaccess_blocks_dotfiles' => $checks['htaccess_blocks_dotfiles'],
            'public_document_root' => $publicDir,
            'target_url' => 'http://opus.localhost/admin/blocked-states',
        ];
    }
}