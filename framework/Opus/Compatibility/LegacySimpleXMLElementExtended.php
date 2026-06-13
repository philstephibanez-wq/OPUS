<?php

declare(strict_types=1);

require_once __DIR__ . '/SimpleXMLElementExtended.php';

if (!class_exists('OPUS_SimpleXMLElementExtended', false)) {
    class OPUS_SimpleXMLElementExtended extends \ASAP\Compatibility\SimpleXMLElementExtended
    {
    }
}
