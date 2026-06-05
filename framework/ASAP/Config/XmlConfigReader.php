<?php

declare(strict_types=1);

namespace ASAP\Config;

use SimpleXMLElement;

/**
 * PUBLIC READER
 *
 * Role:
 *   Load XML configuration documents.
 *
 * Responsibility:
 *   Centralize XML existence and parse validation.
 *
 * Contract:
 *   No fallback file. Invalid XML fails explicitly.
 *
 * Since:
 *   P112D4A
 */
final class XmlConfigReader
{
    public function read(string $file): SimpleXMLElement
    {
        if (!is_file($file)) {
            throw ConfigException::because('ASAP_CONFIG_XML_FILE_MISSING', $file);
        }

        $xml = simplexml_load_file($file);

        if (!$xml instanceof SimpleXMLElement) {
            throw ConfigException::because('ASAP_CONFIG_XML_INVALID', $file);
        }

        return $xml;
    }
}
