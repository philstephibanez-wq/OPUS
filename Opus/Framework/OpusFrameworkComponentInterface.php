<?php
declare(strict_types=1);

namespace Opus\Framework;

/**
 * Base OPUS framework component contract.
 *
 * This is intentionally a marker-level PHP contract at P7A1C so existing legacy classes can be wired without unsafe mechanical method injection.
 * The RefBook audit remains responsible for enforcing full human documentation.
 */
interface OpusFrameworkComponentInterface
{
}
