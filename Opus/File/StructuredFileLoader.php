<?php
declare(strict_types=1);

namespace Opus\File;

/** Selects the strict structured-data parser from the file extension. */
final class StructuredFileLoader implements StructuredFileLoaderInterface
{
    public const CONTRACT = 'OPUS_STRUCTURED_FILE_LOADER_V1';
    private static ?self $instance = null;

    /** @var array<string,StructuredDataParserInterface> */
    private array $parsers = [];

    private function __construct(private readonly FileInterface $file)
    {
        foreach ([Json::instance(), Yaml::instance(), Xml::instance()] as $parser) {
            foreach ($parser->extensions() as $extension) {
                if (isset($this->parsers[$extension])) {
                    throw new \LogicException('OPUS_STRUCTURED_PARSER_DUPLICATE:' . $extension);
                }
                $this->parsers[$extension] = $parser;
            }
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(File::instance());
    }

    public function read(string $path, ?int $maxBytes = null): array
    {
        $extension = $this->file->extension($path);
        $parser = $this->parsers[$extension] ?? null;
        if (!$parser instanceof StructuredDataParserInterface) {
            throw new \RuntimeException('OPUS_STRUCTURED_FILE_FORMAT_UNSUPPORTED:' . $extension);
        }
        return $parser->parse($this->file->read($path, $maxBytes), $path);
    }

    public function writeJson(string $path, array $data, bool $pretty = true): void
    {
        $this->file->writeAtomic($path, Json::instance()->encode($data, $pretty));
    }

    public function supportedExtensions(): array
    {
        $extensions = array_keys($this->parsers);
        sort($extensions, SORT_STRING);
        return $extensions;
    }
}
