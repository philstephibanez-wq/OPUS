<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
require $site . '/application/default/autoload.php';

use Owasys\Application\Configuration\SiteConfiguration;
use Owasys\Application\Http\RequestContext;
use Owasys\Application\I18n\Translator;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

try {
    $configuration = SiteConfiguration::load($site);
    if ($configuration->routeByPath('/') === []) {
        $fail('OWASYS_BOUNDARY_HOME_ROUTE_MISSING');
    }

    $direct = RequestContext::fromServer([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/owasys/structure?x=1',
    ]);
    if ($direct->path() !== '/structure' || $direct->mount() !== '/owasys' || $direct->link('/data') !== '/owasys/data') {
        $fail('OWASYS_BOUNDARY_REQUEST_CONTEXT_INVALID');
    }

    $translator = Translator::load(
        $site,
        $configuration->locales(),
        $configuration->defaultLocale(),
        $configuration->defaultLocale()
    );
    if ($translator->translate('__missing_boundary_key__') !== '[[__missing_boundary_key__]]') {
        $fail('OWASYS_BOUNDARY_I18N_FALLBACK_INVALID');
    }

    $index = (string) file_get_contents($site . '/www/index.php');
    if (substr_count($index, "<?php") !== 1) {
        $fail('OWASYS_BOUNDARY_PUBLIC_ENTRY_INVALID');
    }

    foreach ([
        'application/default/src/Configuration/SiteConfiguration.php',
        'application/default/src/Http/RequestContext.php',
        'application/default/src/I18n/Translator.php',
        'application/default/src/Session/SessionContext.php',
    ] as $relative) {
        if (!is_file($site . '/' . $relative)) {
            $fail('OWASYS_BOUNDARY_COMPONENT_MISSING:' . $relative);
        }
    }
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}

echo 'OWASYS_APPLICATION_BOUNDARIES_SMOKE_OK' . PHP_EOL;
