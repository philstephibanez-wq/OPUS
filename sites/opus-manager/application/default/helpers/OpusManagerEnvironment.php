<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerEnvironment
{
    public static function current(?string $candidate = null): string
    {
        $value = strtolower(trim((string) ($candidate ?? ($_ENV['OPUS_ENV'] ?? getenv('OPUS_ENV') ?: 'dev'))));

        return in_array($value, ['dev', 'staging', 'prod'], true) ? $value : 'dev';
    }

    public static function isProd(?string $candidate = null): bool
    {
        return self::current($candidate) === 'prod';
    }

    public static function filterProfilerInput(?string $candidate = null): void
    {
        if (!self::isProd($candidate)) {
            return;
        }

        unset($_GET['profiler'], $_GET['_profiler'], $_GET['profile']);
        unset($_POST['profiler'], $_POST['_profiler'], $_POST['profile']);
        unset($_REQUEST['profiler'], $_REQUEST['_profiler'], $_REQUEST['profile']);
    }
}