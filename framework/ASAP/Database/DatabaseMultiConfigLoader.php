<?php

declare(strict_types=1);

namespace ASAP\Database;

use SimpleXMLElement;

/**
 * PUBLIC MULTI DATABASE CONFIG LOADER
 *
 * Role:
 *   Load several named database connections from one site XML configuration.
 *
 * Supported XML:
 *
 * <databases default="main">
 *   <connection name="main" provider="sqlite">
 *     <path>var/data/app.sqlite</path>
 *   </connection>
 *   <connection name="audit" provider="sqlite">
 *     <path>var/data/audit.sqlite</path>
 *   </connection>
 * </databases>
 *
 * Compatibility:
 *   A legacy single <database provider="..."> document is accepted and exposed
 *   as one named connection. The name attribute is used when present, otherwise
 *   "default" is used explicitly.
 */
final class DatabaseMultiConfigLoader
{
    public function loadXmlFile(string $file): DatabaseConnectionsConfig
    {
        if (!is_file($file)) {
            throw DatabaseException::because('ASAP_DATABASE_CONFIG_FILE_MISSING', $file);
        }

        $xml = simplexml_load_file($file);

        if (!$xml instanceof SimpleXMLElement) {
            throw DatabaseException::because('ASAP_DATABASE_CONFIG_XML_INVALID', $file);
        }

        return $this->fromXml($xml, $file);
    }

    public function fromXml(SimpleXMLElement $xml, string $source = '<memory>'): DatabaseConnectionsConfig
    {
        $singleLoader = new DatabaseConfigLoader();
        $rootName = strtolower($xml->getName());

        if ($rootName === 'database') {
            $name = trim((string) ($xml['name'] ?? ''));
            if ($name === '') {
                $name = 'default';
            }

            DatabaseConnectionsConfig::assertValidName($name);

            return new DatabaseConnectionsConfig([
                $name => $singleLoader->fromXml($xml, $source),
            ], $name);
        }

        if ($rootName !== 'databases') {
            throw DatabaseException::because('ASAP_DATABASE_MULTI_CONFIG_ROOT_INVALID', $rootName);
        }

        $default = trim((string) ($xml['default'] ?? ''));
        $default = $default === '' ? null : $default;
        $connections = [];

        foreach ($xml->connection as $connectionXml) {
            if (!$connectionXml instanceof SimpleXMLElement) {
                continue;
            }

            $name = trim((string) ($connectionXml['name'] ?? ''));
            if ($name === '') {
                throw DatabaseException::because('ASAP_DATABASE_CONNECTION_NAME_MISSING', $source);
            }

            DatabaseConnectionsConfig::assertValidName($name);

            if (array_key_exists($name, $connections)) {
                throw DatabaseException::because('ASAP_DATABASE_CONNECTION_DUPLICATE', $name);
            }

            $connections[$name] = $singleLoader->fromXml($connectionXml, $source . '#' . $name);
        }

        if ($connections === []) {
            throw DatabaseException::because('ASAP_DATABASE_CONNECTIONS_EMPTY', $source);
        }

        return new DatabaseConnectionsConfig($connections, $default);
    }
}
