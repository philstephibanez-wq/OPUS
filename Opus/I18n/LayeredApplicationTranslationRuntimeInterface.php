<?php
declare(strict_types=1);

namespace Opus\I18n;

interface LayeredApplicationTranslationRuntimeInterface extends
    TranslationRuntimeInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
}
