<?php
declare(strict_types=1);

namespace Opus\I18n;

/**
 * Contract interface for Opus\I18n\CatalogLoader.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface CatalogLoaderInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function loadDirectory(
        string $directory,
        Locale $locale,
        string $scope,
        bool $required
    ): ?Catalog;

    public function loadFile(
        string $file,
        Locale $expectedLocale,
        string $scope
    ): Catalog;
}
