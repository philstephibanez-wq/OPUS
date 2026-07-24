<?php
declare(strict_types=1);

namespace Opus\Composer;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/**
 * Contract for the generic Composer-to-OPUS console callback.
 */
interface ComposerScriptsInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /**
     * Dispatches the current Composer user-script event to OPUS.
     *
     * @param object $event Composer script event exposing getName/getArguments.
     *
     * @throws \RuntimeException when the event or command contract is invalid.
     */
    public static function run(object $event): void;
}
