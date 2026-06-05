<?php
declare(strict_types=1);
namespace ASAP\JS;
/**
 * Legacy-aligned ASAP Javascript domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Javascript
{
public function __construct(public readonly string $href) { if (trim($this->href) === '') { throw new \InvalidArgumentException('ASAP_JS_HREF_EMPTY'); } }

}
