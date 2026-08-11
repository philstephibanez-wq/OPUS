<?php
declare(strict_types=1);

namespace Opus\Composer;

use Opus\File\Json;
use Opus\Security\Sso\LocalPasswordCredentialResetter;

/**
 * Composer callback for deployment/operator local-password reset.
 *
 * Password input is accepted exclusively from non-interactive STDIN and is
 * never copied into argv, output, logs, profiler data or versioned config.
 */
final class LocalPasswordCredentialResetterComposerCommand implements
    LocalPasswordCredentialResetterComposerCommandInterface
{
    public static function run(object $event): void
    {
        if (!method_exists($event, 'getArguments')) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_EVENT_INVALID'
            );
        }

        $arguments = $event->getArguments();
        if (!is_array($arguments)
            || array_filter($arguments, 'is_string') !== $arguments) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_ARGUMENTS_INVALID'
            );
        }

        $arguments = array_values($arguments);
        if (count($arguments) < 2 || count($arguments) > 3) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_ARGUMENTS_REQUIRED'
            );
        }

        $mustChangePassword = false;
        if (isset($arguments[2])) {
            if ($arguments[2] !== '--must-change') {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_RESET_OPTION_INVALID'
                );
            }
            $mustChangePassword = true;
        }

        if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STDIN_PIPE_REQUIRED'
            );
        }

        $raw = stream_get_contents(STDIN);
        if (!is_string($raw)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STDIN_READ_FAILED'
            );
        }

        $password = preg_replace(
            '/(?:\r\n|\n|\r)\z/',
            '',
            $raw,
            1
        );
        if (!is_string($password) || $password === '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_PASSWORD_REQUIRED'
            );
        }

        try {
            $result = (new LocalPasswordCredentialResetter(
                dirname(__DIR__, 2)
            ))->reset(
                (string) $arguments[0],
                (string) $arguments[1],
                $password,
                $mustChangePassword
            );
        } finally {
            $password = '';
            $raw = '';
        }

        fwrite(STDOUT, Json::instance()->encode($result, true));
    }
}
