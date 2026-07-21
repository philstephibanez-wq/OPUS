<?php
declare(strict_types=1);

namespace Opus\Assets;

final class FrameworkAssetResponder
{
    /** @var array<string,array{file:string,content_type:string}> */
    private const ASSETS = [
        'mermaid/opus-mermaid.js' => [
            'file' => 'mermaid/opus-mermaid.js',
            'content_type' => 'text/javascript; charset=UTF-8',
        ],
        'codemirror/opus-codemirror.js' => [
            'file' => 'codemirror/opus-codemirror.js',
            'content_type' => 'text/javascript; charset=UTF-8',
        ],
    ];

    public static function serveCurrentRequest(string $opusRoot): bool
    {
        $requestPath = parse_url(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            PHP_URL_PATH
        );
        if (!is_string($requestPath)) {
            return false;
        }

        $requestPath = rawurldecode($requestPath);
        if (str_contains($requestPath, "\0")) {
            return false;
        }

        $prefix = self::basePath() . '/asset/opus/';
        if (!str_starts_with($requestPath, $prefix)) {
            return false;
        }

        $assetKey = substr($requestPath, strlen($prefix));
        $asset = self::ASSETS[$assetKey] ?? null;
        if (!is_array($asset)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'OPUS_FRAMEWORK_ASSET_NOT_FOUND';
            return true;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'OPUS_FRAMEWORK_ASSET_METHOD_NOT_ALLOWED';
            return true;
        }

        $distRoot = realpath(
            rtrim($opusRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'Opus'
            . DIRECTORY_SEPARATOR . 'Assets'
            . DIRECTORY_SEPARATOR . 'dist'
        );
        if ($distRoot === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'OPUS_FRAMEWORK_ASSET_ROOT_MISSING';
            return true;
        }

        $candidate = realpath(
            $distRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $asset['file'])
        );
        $distPrefix = rtrim(str_replace('\\', '/', $distRoot), '/') . '/';
        $candidatePath = $candidate === false
            ? ''
            : str_replace('\\', '/', $candidate);

        if (
            $candidate === false
            || !str_starts_with($candidatePath, $distPrefix)
            || !is_file($candidate)
        ) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'OPUS_FRAMEWORK_ASSET_FILE_MISSING';
            return true;
        }

        $size = filesize($candidate);

        header('Content-Type: ' . $asset['content_type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        if (is_int($size)) {
            header('Content-Length: ' . $size);
        }

        if ($method === 'GET') {
            readfile($candidate);
        }

        return true;
    }

    private static function basePath(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '')
        );
        $directory = str_replace('\\', '/', dirname($script));

        return in_array($directory, ['/', '.', ''], true)
            ? ''
            : rtrim($directory, '/');
    }
}
