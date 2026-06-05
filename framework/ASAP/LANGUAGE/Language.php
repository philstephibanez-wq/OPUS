<?php
declare(strict_types=1);
namespace ASAP\LANGUAGE;
/**
 * Legacy-aligned ASAP Language domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Language
{
public function __construct(public readonly string $code) { if (preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})?$/', strtolower($this->code)) !== 1) { throw new \InvalidArgumentException('ASAP_LANGUAGE_CODE_INVALID'); } }

}
