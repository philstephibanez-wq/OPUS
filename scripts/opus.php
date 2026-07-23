#!/usr/bin/env php
<?php
declare(strict_types=1);

use Opus\Console\OpusConsoleApplication;

$opusRoot = dirname(__DIR__);
$autoload = $opusRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "OPUS_COMPOSER_AUTOLOAD_MISSING\n");
    exit(1);
}

require $autoload;

exit(OpusConsoleApplication::fromRoot($opusRoot)->run($argv));
