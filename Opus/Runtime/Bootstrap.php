<?php
declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Http\Response;
use Opus\Http\Request;
use Opus\Runtime\Diagnostics\PhpErrorInterceptor;

/**
 * Runtime bootstrap entry point for the modern Composer-driven OPUS kernel.
 *
 * Loads the minimal framework surface required by the runtime kernel and converts fatal bootstrap failures into explicit HTTP responses.
 */
final class Bootstrap
 implements BootstrapInterface {
    public static function run(string $rootDir): void
    {
        self::loadDiagnostics($rootDir);
        PhpErrorInterceptor::register($rootDir);
        self::loadFramework($rootDir);

        try {
            $kernel = new Kernel($rootDir);
            $kernel->handle(Request::fromGlobals($rootDir))->send();
        } catch (\Throwable $e) {
            Response::html(self::renderFatal($e), 500)->send();
        }
    }

    private static function loadDiagnostics(string $rootDir): void
    {
        foreach ([
            'Framework/OpusFrameworkComponentInterface.php',
            'Framework/OpusExceptionAwareInterface.php',
            'Framework/OpusExceptionContractInterface.php',
            'Runtime/Diagnostics/PhpErrorExceptionInterface.php',
            'Runtime/Diagnostics/PhpErrorException.php',
            'Runtime/Diagnostics/ThrowableNormalizerInterface.php',
            'Runtime/Diagnostics/ThrowableNormalizer.php',
            'Runtime/Diagnostics/PhpErrorInterceptorInterface.php',
            'Runtime/Diagnostics/PhpErrorInterceptor.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
    }

    private static function loadFramework(string $rootDir): void
    {
        foreach ([
            'Framework/OpusProfilerAwareInterface.php',
            'Framework/OpusSelfDocumentingInterface.php',
            'Foundation/Support.php',
            'Http/RequestInterface.php',
            'Http/Request.php',
            'Http/ResponseInterface.php',
            'Http/Response.php',
            'Application/ApplicationDefinitionInterface.php',
            'Application/ApplicationDefinition.php',
            'Application/ApplicationRegistryInterface.php',
            'Application/ApplicationRegistry.php',
            'I18n/I18nInterface.php',
            'I18n/I18n.php',
            'View/ViewInterface.php',
            'View/View.php',
            'Security/AclInterface.php',
            'Security/Acl.php',
            'Profiler/TraceInterface.php',
            'Profiler/Trace.php',
            'Profiler/ProfilerInterface.php',
            'Profiler/Profiler.php',
            'Profiler/TraceFileRepositoryInterface.php',
            'Profiler/TraceFileRepository.php',
            'Fsm/Runtime/FsmRuntimeConfigLoaderInterface.php',
            'Fsm/Runtime/FsmRuntimeConfigLoader.php',
            'Profiler/WebProfilerViewInterface.php',
            'Profiler/WebProfilerView.php',
            'Profiler/WebProfilerControllerInterface.php',
            'Profiler/WebProfilerController.php',
            'Routing/RouterInterface.php',
            'Routing/Router.php',
            'Runtime/KernelInterface.php',
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