<?php

declare(strict_types=1);

namespace Opus\Config;

use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: CONFIG
 *   role: Class XmlConfigReader belongs to the CONFIG Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONFIG domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - config-overview
 *   diagrams:
 *     - config-runtime
 * END_OPUS_REFBOOK
 */
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
            throw ConfigException::because('OPUS_CONFIG_XML_FILE_MISSING', $file);
        }

        $xml = simplexml_load_file($file);

        if (!$xml instanceof SimpleXMLElement) {
            throw ConfigException::because('OPUS_CONFIG_XML_INVALID', $file);
        }

        return $xml;
    }
}
