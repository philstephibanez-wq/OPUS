<?php
declare(strict_types=1);

namespace Opus\Lstsar\Stage;

/**
 * Load stage contract.
 *
 * Loads only declared sources and exposes received size/type metadata before any
 * security or transformation decision is made.
 */
interface LoadStageInterface extends LstsarStageInterface
{
}
