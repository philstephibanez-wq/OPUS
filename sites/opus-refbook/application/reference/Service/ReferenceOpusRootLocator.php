<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Resolve the shared Opus framework root consumed by OPUS_REF_BOOK.
 *
 * Contract:
 *   Opus is mutualized. This service never copies or embeds the framework.
 */
final class ReferenceOpusRootLocator
{
    private const DEFAULT_OPUS_ROOT = 'H:\\Opus';

    public static function fromEnvironment(): string
    {
        $root = trim((string) (getenv('OPUS_ROOT') ?: self::DEFAULT_OPUS_ROOT));

        if ($root === '') {
            throw new RuntimeException('OPUS_REFBOOK_OPUS_ROOT_EMPTY');
        }

        $normalized = rtrim(str_replace('\\', '/', $root), '/');
        $frameworkRoot = $normalized . '/framework/Opus';

        if (!is_dir($frameworkRoot)) {
            throw new RuntimeException('OPUS_REFBOOK_SHARED_OPUS_FRAMEWORK_MISSING=' . $frameworkRoot);
        }

        $providerFile = $frameworkRoot . '/RefBook/Api/RefBookRestSnapshotProvider.php';
        if (!is_file($providerFile)) {
            throw new RuntimeException('OPUS_REFBOOK_SHARED_OPUS_REFBOOK_PROVIDER_MISSING=' . $providerFile);
        }

        return $normalized;
    }
}
