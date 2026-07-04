<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerAuth
{
    public const DEV_USERNAME = 'admin';
    public const DEV_PASSWORD = 'admin';

    public static function ensureSession(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public static function isSignedIn(): bool
    {
        self::ensureSession();

        return isset($_SESSION['opus_manager_user']) && is_string($_SESSION['opus_manager_user']) && $_SESSION['opus_manager_user'] !== '';
    }

    public static function user(): string
    {
        self::ensureSession();

        return self::isSignedIn() ? (string) $_SESSION['opus_manager_user'] : '';
    }

    public static function signIn(string $username, string $password): bool
    {
        self::ensureSession();

        if ($username === self::DEV_USERNAME && $password === self::DEV_PASSWORD) {
            $_SESSION['opus_manager_user'] = $username;
            return true;
        }

        return false;
    }

    public static function signOut(): void
    {
        self::ensureSession();
        unset($_SESSION['opus_manager_user']);
    }
}