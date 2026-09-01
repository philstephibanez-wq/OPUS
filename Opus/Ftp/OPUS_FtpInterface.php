<?php
declare(strict_types=1);

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/**
 * Contract for the legacy-compatible OPUS FTP component.
 *
 * The implementation deliberately keeps the historical public FTP API while
 * participating in the mandatory OPUS framework component contracts.
 */
interface OPUS_FtpInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
}
