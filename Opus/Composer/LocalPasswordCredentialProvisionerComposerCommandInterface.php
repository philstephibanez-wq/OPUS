<?php
declare(strict_types=1);

namespace Opus\Composer;

interface LocalPasswordCredentialProvisionerComposerCommandInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function run(object $event): void;
}
