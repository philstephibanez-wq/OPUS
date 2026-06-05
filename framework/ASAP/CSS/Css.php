<?php
declare(strict_types=1);
namespace ASAP\CSS;
/**
 * Legacy-aligned ASAP Css domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Css
{
public function __construct(public readonly string $href) { if (trim($this->href) === '') { throw new \InvalidArgumentException('ASAP_CSS_HREF_EMPTY'); } }

}
