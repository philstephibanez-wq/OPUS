<?php

declare(strict_types=1);

namespace Opus\Url;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: URL
 *   role: Class UrlException belongs to the URL Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the URL domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - url-overview
 *   diagrams:
 *     - url-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent URL generation contract failures.
 *
 * Since:
 *   P112D4B
 */
final class UrlException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
