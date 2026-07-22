<?php
declare(strict_types=1);

namespace Opus\File;

final class Json implements JsonInterface
{
    public const CONTRACT = 'OPUS_JSON_PARSER_V1';
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function parse(string $contents, string $source = ''): array
    {
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException(
                'OPUS_JSON_PARSE_FAILED:' . $source . ':' . $error->getMessage(),
                0,
                $error
            );
        }
        if (!is_array($data)) {
            throw new \RuntimeException('OPUS_JSON_ROOT_ARRAY_REQUIRED:' . $source);
        }
        return $data;
    }

    public function encode(array $data, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($data, $flags) . "\n";
    }

    public function extensions(): array
    {
        return ['json'];
    }
}
