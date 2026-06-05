<?php
declare(strict_types=1);
namespace ASAP\DATE;
/**
 * Legacy-aligned ASAP Date domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Date
{

public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now'); }
public function parse(string $value): \DateTimeImmutable { return new \DateTimeImmutable($value); }
}
