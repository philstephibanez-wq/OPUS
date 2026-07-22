<?php
declare(strict_types=1);

namespace Opus\File;

/** Secure XML configuration parser using DOM with network access disabled. */
final class Xml implements XmlInterface
{
    public const CONTRACT = 'OPUS_XML_PARSER_V1';
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function extensions(): array
    {
        return ['xml'];
    }

    public function parse(string $contents, string $source = ''): array
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('OPUS_XML_DOM_EXTENSION_MISSING');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) === 1) {
            throw new \RuntimeException('OPUS_XML_DTD_FORBIDDEN:' . $source);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $ok = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
            if (!$ok || !$document->documentElement instanceof \DOMElement) {
                $messages = array_map(
                    static fn (\LibXMLError $error): string => trim($error->message),
                    libxml_get_errors()
                );
                throw new \RuntimeException('OPUS_XML_PARSE_FAILED:' . $source . ':' . implode('|', $messages));
            }
            $root = $document->documentElement;
            return $root->tagName === 'catalog'
                ? $this->catalog($root, $source)
                : [$root->tagName => $this->element($root)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<mixed> */
    private function catalog(\DOMElement $root, string $source): array
    {
        $data = [];
        foreach (['contract', 'locale', 'scope'] as $attribute) {
            if ($root->hasAttribute($attribute)) {
                $data[$attribute] = $root->getAttribute($attribute);
            }
        }
        $data['messages'] = [];
        foreach (['messages', 'plurals', 'grammatical'] as $sectionName) {
            $section = null;
            foreach ($root->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->tagName === $sectionName) {
                    $section = $child;
                    break;
                }
            }
            if (!$section instanceof \DOMElement) {
                continue;
            }
            $entries = [];
            foreach ($section->childNodes as $message) {
                if (!$message instanceof \DOMElement || $message->tagName !== 'message') continue;
                $key = trim($message->getAttribute('key'));
                if ($key === '') throw new \RuntimeException('OPUS_XML_CATALOG_KEY_MISSING:' . $source);
                $forms = [];
                foreach ($message->childNodes as $form) {
                    if ($form instanceof \DOMElement && $form->tagName === 'form') {
                        $name = trim($form->getAttribute('name'));
                        if ($name === '') throw new \RuntimeException('OPUS_XML_CATALOG_FORM_NAME_MISSING:' . $source . ':' . $key);
                        $forms[$name] = trim($form->textContent);
                    }
                }
                $entries[$key] = $forms === [] ? trim($message->textContent) : $forms;
            }
            if ($sectionName === 'messages') $data['messages'] = $entries;
            else $data[$sectionName] = $entries;
        }
        return $data;
    }

    private function element(\DOMElement $element): mixed
    {
        $children = array_values(array_filter(
            iterator_to_array($element->childNodes),
            static fn (\DOMNode $node): bool => $node instanceof \DOMElement
        ));
        if ($children === []) {
            return $this->scalar(trim($element->textContent), $element->getAttribute('type'));
        }
        $result = [];
        foreach ($children as $child) {
            $value = $this->element($child);
            if (array_key_exists($child->tagName, $result)) {
                if (!is_array($result[$child->tagName]) || !array_is_list($result[$child->tagName])) {
                    $result[$child->tagName] = [$result[$child->tagName]];
                }
                $result[$child->tagName][] = $value;
            } else {
                $result[$child->tagName] = $value;
            }
        }
        foreach ($element->attributes as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->name !== 'type') {
                $result['_attributes'][$attribute->name] = $attribute->value;
            }
        }
        return $result;
    }

    private function scalar(string $value, string $type): mixed
    {
        return match (strtolower(trim($type))) {
            'null' => null,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? throw new \RuntimeException('OPUS_XML_BOOLEAN_INVALID:' . $value),
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? (int) $value : throw new \RuntimeException('OPUS_XML_INTEGER_INVALID:' . $value),
            'float', 'number' => is_numeric($value)
                ? (float) $value : throw new \RuntimeException('OPUS_XML_FLOAT_INVALID:' . $value),
            'json' => Json::instance()->parse($value, 'xml:inline-json'),
            default => $value,
        };
    }
}
