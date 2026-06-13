<?php

declare(strict_types=1);

namespace Opus\Database;

use SimpleXMLElement;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class DatabaseConfigLoader belongs to the DATABASE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DATABASE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - database-overview
 *   diagrams:
 *     - database-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC DATABASE CONFIG LOADER
 *
 * Role:
 *   Load one database connection configuration from site-declared XML.
 *
 * Contract:
 *   Site chooses provider explicitly.
 *   Missing database config fails when database is requested.
 *   Missing <options> is valid and produces an empty options array.
 */
final class DatabaseConfigLoader
{
    public function loadXmlFile(string $file): DatabaseConnectionConfig
    {
        if (!is_file($file)) {
            throw DatabaseException::because('OPUS_DATABASE_CONFIG_FILE_MISSING', $file);
        }

        $xml = simplexml_load_file($file);

        if (!$xml instanceof SimpleXMLElement) {
            throw DatabaseException::because('OPUS_DATABASE_CONFIG_XML_INVALID', $file);
        }

        return $this->fromXml($xml, $file);
    }

    public function fromXml(SimpleXMLElement $xml, string $source = '<memory>'): DatabaseConnectionConfig
    {
        $provider = trim((string) ($xml['provider'] ?? ''));

        if ($provider === '') {
            throw DatabaseException::because('OPUS_DATABASE_PROVIDER_MISSING', $source);
        }

        $dsn = trim((string) ($xml->dsn ?? ''));
        $user = trim((string) ($xml->user ?? ''));
        $password = (string) ($xml->password ?? '');

        $parameters = [];

        foreach ($xml->children() as $name => $child) {
            if (in_array($name, ['dsn', 'user', 'password', 'options'], true)) {
                continue;
            }

            $parameters[$name] = trim((string) $child);
        }

        $options = [];
        $optionNodes = $xml->xpath('options/option');

        if ($optionNodes === false) {
            throw DatabaseException::because('OPUS_DATABASE_OPTIONS_XPATH_FAILED', $source);
        }

        foreach ($optionNodes as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));

            if ($name === '') {
                throw DatabaseException::because('OPUS_DATABASE_OPTION_NAME_EMPTY', $source);
            }

            $options[$name] = $value;
        }

        return new DatabaseConnectionConfig(
            $provider,
            $dsn === '' ? null : $dsn,
            $user === '' ? null : $user,
            $password === '' ? null : $password,
            $parameters,
            $options
        );
    }
}
