<?php
declare(strict_types=1);
namespace ASAP\XML;
use SimpleXMLElement;
final class Xml
{
    public function fromString(string $xml): SimpleXMLElement { $node = simplexml_load_string($xml); if (!$node instanceof SimpleXMLElement) { throw new \RuntimeException('ASAP_XML_STRING_INVALID'); } return $node; }
    public function fromFile(string $file): SimpleXMLElement { if (!is_file($file)) { throw new \RuntimeException('ASAP_XML_FILE_MISSING: ' . $file); } $node = simplexml_load_file($file); if (!$node instanceof SimpleXMLElement) { throw new \RuntimeException('ASAP_XML_FILE_INVALID: ' . $file); } return $node; }
}
