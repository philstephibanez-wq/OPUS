<?php

declare(strict_types=1);

/*
 * OPUS Framework 8.1.0 "Lysenko"
 *
 * Single product runtime entrypoint.
 *
 * Contract:
 *   - This is the only OPUS root runtime entrypoint.
 *   - There is no root autoload.php script.
 *   - The autoloader is a framework class.
 *   - Runtime cache lives in OPUS/var/cache.
 *   - Runtime logs live in OPUS/var/logs.
 */

$opusRoot = __DIR__;

require_once $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'ClassMapBuilder.php';
require_once $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'AutoloadCache.php';
require_once $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Log' . DIRECTORY_SEPARATOR . 'RuntimeLogger.php';
require_once $opusRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'Autoloader.php';

return \Opus\Autoload\Autoloader::boot($opusRoot);
