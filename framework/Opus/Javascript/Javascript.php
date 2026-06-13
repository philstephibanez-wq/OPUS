<?php
declare(strict_types=1);
namespace Opus\Javascript;
/*
 * OPUS_REFBOOK:
 *   domain: JAVASCRIPT
 *   role: Class Javascript belongs to the JAVASCRIPT Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the JAVASCRIPT domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - javascript-overview
 *   diagrams:
 *     - javascript-runtime
 * END_OPUS_REFBOOK
 */
/**
 * Legacy-aligned Opus Javascript domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Javascript
{
public function __construct(public readonly string $href) { if (trim($this->href) === '') { throw new \InvalidArgumentException('OPUS_JS_HREF_EMPTY'); } }

}
