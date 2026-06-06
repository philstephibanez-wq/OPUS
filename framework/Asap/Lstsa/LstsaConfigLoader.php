<?php

declare(strict_types=1);

namespace ASAP\Lstsa;

use SimpleXMLElement;

/**
 * PUBLIC Lstsa CONFIG LOADER
 *
 * Role:
 *   Load one declarative Lstsa contract from XML.
 */
final class LstsaConfigLoader
{
    public function loadXmlFile(string $file): LstsaDefinition
    {
        if (!is_file($file)) {
            throw LstsaException::because('ASAP_Lstsa_CONFIG_FILE_MISSING', $file);
        }

        $xml = simplexml_load_file($file);

        if (!$xml instanceof SimpleXMLElement) {
            throw LstsaException::because('ASAP_Lstsa_CONFIG_XML_INVALID', $file);
        }

        return $this->fromXml($xml, $file);
    }

    public function fromXml(SimpleXMLElement $xml, string $source = '<memory>'): LstsaDefinition
    {
        if (strtolower($xml->getName()) !== 'lstsa') {
            throw LstsaException::because('ASAP_Lstsa_CONFIG_ROOT_INVALID', $xml->getName());
        }

        $id = $this->requiredAttr($xml, 'id', $source);
        $version = $this->requiredAttr($xml, 'version', $source);

        $load = $this->singleChild($xml, 'load', $source);
        $store = $this->singleChild($xml, 'store', $source);
        $archive = $this->singleChild($xml, 'archive', $source);

        $loadFields = [];
        foreach ($load->field as $fieldXml) {
            if (!$fieldXml instanceof SimpleXMLElement) {
                continue;
            }
            $field = LstsaFieldConstraint::fromXml($fieldXml, 'name');
            if (array_key_exists($field->name, $loadFields)) {
                throw LstsaException::because('ASAP_Lstsa_LOAD_FIELD_DUPLICATE', $field->name);
            }
            $loadFields[$field->name] = $field;
        }

        $mappings = [];
        $transform = $xml->transform[0] ?? null;
        if (!$transform instanceof SimpleXMLElement) {
            throw LstsaException::because('ASAP_Lstsa_TRANSFORM_NODE_MISSING', $source);
        }

        foreach ($transform->field as $fieldXml) {
            if (!$fieldXml instanceof SimpleXMLElement) {
                continue;
            }
            $mapping = LstsaFieldMapping::fromXml($fieldXml);
            if (array_key_exists($mapping->target, $mappings)) {
                throw LstsaException::because('ASAP_Lstsa_TARGET_FIELD_DUPLICATE', $mapping->target);
            }
            $mappings[$mapping->target] = $mapping;
        }

        $runtime = [];
        $runtimeXml = $xml->runtime[0] ?? null;
        if ($runtimeXml instanceof SimpleXMLElement) {
            foreach (['max_run_seconds', 'max_batch_seconds', 'max_rows_per_batch', 'max_memory_mb', 'heartbeat_every_seconds', 'stale_after_seconds'] as $attr) {
                $value = trim((string) ($runtimeXml[$attr] ?? ''));
                if ($value !== '') {
                    $runtime[$attr] = (int) $value;
                }
            }
        }

        $archiveConnection = trim((string) ($archive['connection'] ?? ''));
        $archiveTable = trim((string) ($archive['table'] ?? ''));

        return new LstsaDefinition(
            $id,
            $version,
            $this->requiredAttr($load, 'connection', $source . '#load'),
            $this->requiredAttr($load, 'table', $source . '#load'),
            $loadFields,
            $this->requiredAttr($store, 'connection', $source . '#store'),
            $this->requiredAttr($store, 'table', $source . '#store'),
            $this->requiredAttr($store, 'mode', $source . '#store'),
            $mappings,
            $this->requiredAttr($archive, 'mode', $source . '#archive'),
            $this->requiredAttr($archive, 'path', $source . '#archive'),
            $archiveConnection === '' ? null : $archiveConnection,
            $archiveTable === '' ? null : $archiveTable,
            $runtime
        );
    }

    private function singleChild(SimpleXMLElement $xml, string $name, string $source): SimpleXMLElement
    {
        $child = $xml->{$name}[0] ?? null;
        if (!$child instanceof SimpleXMLElement) {
            throw LstsaException::because('ASAP_Lstsa_CONFIG_NODE_MISSING', $source . '#' . $name);
        }

        return $child;
    }

    private function requiredAttr(SimpleXMLElement $xml, string $name, string $source): string
    {
        $value = trim((string) ($xml[$name] ?? ''));
        if ($value === '') {
            throw LstsaException::because('ASAP_Lstsa_CONFIG_ATTRIBUTE_MISSING', $source . '@' . $name);
        }

        return $value;
    }
}
