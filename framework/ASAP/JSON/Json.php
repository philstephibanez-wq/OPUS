<?php
declare(strict_types=1);
namespace ASAP\JSON;
final class Json
{
    public function encode(array $data): string { return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    public function decode(string $json): array { $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR); if (!is_array($data)) { throw new \RuntimeException('ASAP_JSON_ROOT_INVALID'); } return $data; }
}
