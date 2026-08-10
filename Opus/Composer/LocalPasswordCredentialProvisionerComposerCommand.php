<?php
declare(strict_types=1);

namespace Opus\Composer;

use Opus\File\Json;
use Opus\Security\Sso\LocalPasswordCredentialProvisioner;

/**
 * Composer callback for local deployment-time credential provisioning.
 *
 * The password is accepted exclusively from non-interactive STDIN and is never
 * copied into argv, output, logs, profiler data or a versioned configuration.
 */
final class LocalPasswordCredentialProvisionerComposerCommand implements
    LocalPasswordCredentialProvisionerComposerCommandInterface
{
    public static function run(object $event): void
    {
        if (!method_exists($event, 'getArguments')) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_EVENT_INVALID'
            );
        }
        $arguments = $event->getArguments();
        if (!is_array($arguments)
            || array_filter($arguments, 'is_string') !== $arguments) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ARGUMENTS_INVALID'
            );
        }
        $arguments = array_values($arguments);
        if (count($arguments) !== 2) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ARGUMENTS_REQUIRED'
            );
        }
        if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STDIN_PIPE_REQUIRED'
            );
        }

        $raw = stream_get_contents(STDIN);
        if (!is_string($raw)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STDIN_READ_FAILED'
            );
        }
        $password = preg_replace('/(?:\r\n|\n|\r)\z/', '', $raw, 1);
        if (!is_string($password) || $password === '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_PASSWORD_REQUIRED'
            );
        }

        try {
            $result = (new LocalPasswordCredentialProvisioner(
                dirname(__DIR__, 2)
            ))->provision(
                (string) $arguments[0],
                (string) $arguments[1],
                $password
            );
        } finally {
            $password = '';
            $raw = '';
        }

        fwrite(STDOUT, Json::instance()->encode($result, true));
    }
}
