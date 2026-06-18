<?php

declare(strict_types=1);

namespace LogAndPlay;

use RuntimeException;

final class OpusRuntime
{
    public static function boot(string $projectRoot): void
    {
        $runtimeFile = $projectRoot . DIRECTORY_SEPARATOR . 'opus-runtime.local.json';

        if (!is_file($runtimeFile)) {
            throw new RuntimeException('LOGANDPLAY_OPUS_RUNTIME_CONTRACT_MISSING');
        }

        $raw = file_get_contents($runtimeFile);
        if ($raw === false) {
            throw new RuntimeException('LOGANDPLAY_OPUS_RUNTIME_CONTRACT_UNREADABLE');
        }

        $config = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($config)) {
            throw new RuntimeException('LOGANDPLAY_OPUS_RUNTIME_CONTRACT_INVALID');
        }

        if (($config['fallback_allowed'] ?? null) !== false) {
            throw new RuntimeException('LOGANDPLAY_FALLBACK_MUST_BE_FALSE');
        }

        if (($config['framework_duplication_allowed'] ?? null) !== false) {
            throw new RuntimeException('LOGANDPLAY_FRAMEWORK_DUPLICATION_MUST_BE_FALSE');
        }

        if (is_dir($projectRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus')) {
            throw new RuntimeException('LOGANDPLAY_EMBEDDED_OPUS_FRAMEWORK_FORBIDDEN');
        }

        $opusRoot = $config['opus_root'] ?? null;
        if (!is_string($opusRoot) || $opusRoot === '') {
            throw new RuntimeException('LOGANDPLAY_OPUS_ROOT_MISSING');
        }

        $autoloadRoot = $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Autoload';

        $required = [
            $autoloadRoot . DIRECTORY_SEPARATOR . 'AutoloadCache.php',
            $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'RuntimeLogger.php',
            $autoloadRoot . DIRECTORY_SEPARATOR . 'ClassMapBuilder.php',
            $autoloadRoot . DIRECTORY_SEPARATOR . 'Autoloader.php',
        ];

        foreach ($required as $file) {
            if (!is_file($file)) {
                throw new RuntimeException('LOGANDPLAY_OPUS_BOOT_FILE_MISSING: ' . basename($file));
            }

            require_once $file;
        }

        \Opus\Autoload\Autoloader::boot($opusRoot);
    }
}
