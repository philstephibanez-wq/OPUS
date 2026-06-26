<?php
declare(strict_types=1);

namespace Opus\Framework;

/**
 * Marks a class as participating in the OPUS error-to-exception contract.
 *
 * Runtime warnings/notices/errors must be converted or surfaced through explicit OPUS exceptions when the class has runtime behavior.
 */
interface OpusExceptionAwareInterface
{
}
