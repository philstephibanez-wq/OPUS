<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED CONFIG LOADER
 *
 * Role:
 *   Preserve the original ASAP ConfigLoader concept.
 *
 * Responsibility:
 *   Load JSON or PHP array configuration files into Configuration.
 *
 * Contract:
 *   No implicit config file and no silent fallback.
 *
 * Since:
 *   P112D4C
 */
final class ConfigLoader
{
    public function load(string $file): Configuration
    {
        if (!is_file($file)) {
            throw Exception::because('ASAP_CONFIG_FILE_MISSING', $file);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            $data = require $file;

            if (!is_array($data)) {
                throw Exception::because('ASAP_CONFIG_PHP_RETURN_INVALID', $file);
            }

            return new Configuration($data);
        }

        if ($extension === 'json') {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw Exception::because('ASAP_CONFIG_JSON_ROOT_INVALID', $file);
            }

            return new Configuration($data);
        }

        throw Exception::because('ASAP_CONFIG_FORMAT_UNSUPPORTED', $file);
    }
}
