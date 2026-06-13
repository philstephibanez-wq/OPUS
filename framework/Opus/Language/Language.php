<?php
declare(strict_types=1);
namespace Opus\Language;
/*
 * OPUS_REFBOOK:
 *   domain: LANGUAGE
 *   role: Class Language belongs to the LANGUAGE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LANGUAGE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - language-overview
 *   diagrams:
 *     - language-runtime
 * END_OPUS_REFBOOK
 */
/**
 * Legacy-aligned Opus Language domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Language
{
public function __construct(public readonly string $code) { if (preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', strtolower($this->code)) !== 1) { throw new \InvalidArgumentException('OPUS_LANGUAGE_CODE_INVALID'); } }

}
