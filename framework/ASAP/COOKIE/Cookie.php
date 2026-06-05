<?php
declare(strict_types=1);
namespace ASAP\COOKIE;
/**
 * Legacy-aligned ASAP Cookie domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Cookie
{
public function __construct(public readonly string $name, public readonly string $value) { if (trim($this->name) === '') { throw new \InvalidArgumentException('ASAP_COOKIE_NAME_EMPTY'); } }

}
