<?php
declare(strict_types=1);

namespace Opus\Runtime;


use Opus\Http\Response;
use Opus\Http\Request;
final class Bootstrap
{
    public static function run(string $rootDir): void
    {
        self::loadFramework($rootDir);

        try {
            $kernel = new Kernel($rootDir);
            $kernel->handle(Request::fromGlobals($rootDir))->send();
        } catch (\Throwable $e) {
            Response::html(self::renderFatal($e), 500)->send();
        }
    }

    private static function loadFramework(string $rootDir): void
    {
        foreach ([
            'Foundation/Support.php',
            'Http/Request.php',
            'Http/Response.php',
            'Application/ApplicationDefinition.php',
            'Application/ApplicationRegistry.php',
            'I18n/I18n.php',
            'View/View.php',
            'Security/Acl.php',
            'FSM/Fsm.php',
            'Routing/Router.php',
            'Runtime/Kernel.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
    }

    private static function renderFatal(\Throwable $e): string
    {
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $line = (int) $e->getLine();

        return <<<HTML
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OPUS fatal error</title>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:2rem;background:#151820;color:#f5f7ff;line-height:1.5}
pre{white-space:pre-wrap;background:#07090f;border:1px solid #353b4f;border-radius:12px;padding:1rem;color:#ffdf99}
strong{color:#ffb86b}
</style>
</head>
<body>
<h1>OPUS — erreur explicite</h1>
<p><strong>{$message}</strong></p>
<pre>{$file}:{$line}</pre>
</body>
</html>
HTML;
    }
}
