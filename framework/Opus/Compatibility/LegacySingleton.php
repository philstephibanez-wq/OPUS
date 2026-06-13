<?php

declare(strict_types=1);

require_once __DIR__ . '/../Exception/Exception.php';
require_once __DIR__ . '/Singleton.php';

if (!class_exists('OPUS_Singleton', false)) {
    class OPUS_Singleton extends \ASAP\Compatibility\Singleton
    {
    }
}
