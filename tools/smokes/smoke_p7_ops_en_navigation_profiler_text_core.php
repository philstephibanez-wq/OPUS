<?php
declare(strict_types=1);

echo 'P7_OPS_EN_NAVIGATION_PROFILER_TEXT_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$languageFile = $publicDir . '/language.php';
$navigationFile = $publicDir . '/navigation.php';
$routerFile = $publicDir . '/router.php';
$cssFile = $publicDir . '/ops-ui.css';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';

foreach ([$languageFile, $navigationFile, $routerFile, $cssFile, $readmeFile] as $file) {
    if (!is_file($file)) {
        throw new RuntimeException('EN_NAV_PROFILER_FILE_MISSING: ' . $file);
    }
}

$combined = '';
foreach ([$languageFile, $routerFile, $cssFile, $readmeFile] as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        throw new RuntimeException('EN_NAV_PROFILER_READ_FAILED: ' . $file);
    }
    $combined .= $source . PHP_EOL;
}

foreach ([
    'P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE',
    'P7_OPS_PROFILER_AND_ACCESS_LOG_CORE',
    'P7_OPS_PROFESSIONAL_TEXT_LENGTH_CORE',
    'p7ops_access_log_once',
    'p7ops_profiler_start_once',
    'p7ops_profiler_finish_once',
    'profiler.log',
    'access.log',
    'Navigation unifiée',
    'Unified navigation',
    'Dashboard, Operations, Command Center, Navigation et Actions utilisent maintenant les mêmes routes OPS locales.',
    'Dashboard, Operations, Command Center, Navigation and Actions now use the same local OPS routes.',
] as $marker) {
    if (!str_contains($combined, $marker)) {
        throw new RuntimeException('EN_NAV_PROFILER_MARKER_MISSING: ' . $marker);
    }
}

if (!str_contains($combined, 'grid-template-columns:repeat(auto-fit,minmax(11rem,1fr))')) {
    throw new RuntimeException('TEXT_LENGTH_GRID_RULE_MISSING');
}

echo 'CHECK_P7_OPS_EN_NAV_PROFILER_MARKERS=OK' . PHP_EOL;

require_once $languageFile;

$_GET = ['site' => 'site-alpha', 'lang' => 'en'];
$sample = implode("\n", [
    'Navigation unifiée',
    'Dashboard, Operations, Command Center, Navigation et Actions utilisent maintenant les mêmes routes OPS locales.',
    'Table détaillée avec source/destination résumées. Les structures longues sont wrappées et confinées dans le panel.',
]);

$translated = p7ops_i18n_translate_html($sample);
foreach ([
    'Unified navigation',
    'Dashboard, Operations, Command Center, Navigation and Actions now use the same local OPS routes.',
    'Detailed table with summarized source/destination. Long structures are wrapped and confined in the panel.',
] as $marker) {
    if (!str_contains($translated, $marker)) {
        throw new RuntimeException('EN_NAV_TRANSLATION_DIRECT_MISSING: ' . $marker . ' IN ' . $translated);
    }
}

echo 'CHECK_P7_OPS_EN_NAV_TRANSLATION_DIRECT=OK' . PHP_EOL;

$render = static function (string $file, string $uri): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_EN_NAVIGATION_PROFILER_TEXT_CORE_SMOKE';
    $_GET = ['site' => 'site-alpha', 'lang' => 'en'];

    ob_start();
    (static function (string $__file): void {
        require $__file;
    })($file);
    $html = ob_get_clean();

    return is_string($html) ? p7ops_i18n_translate_html($html) : '';
};

$navigation = $render($navigationFile, '/opus-lstsar-manager/navigation?site=site-alpha&lang=en');
$visible = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $navigation);
$visible = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', is_string($visible) ? $visible : $navigation);
$visible = preg_replace('/<!--.*?-->/s', ' ', is_string($visible) ? $visible : $navigation);
$visible = html_entity_decode(strip_tags(is_string($visible) ? $visible : $navigation), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$visible = preg_replace('/\s+/u', ' ', is_string($visible) ? $visible : '');

foreach ([
    'Unified navigation',
    'Dashboard, Operations, Command Center, Navigation and Actions now use the same local OPS routes.',
    'Operations table',
] as $marker) {
    if (!str_contains($visible, $marker)) {
        throw new RuntimeException('EN_NAV_RENDER_EN_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    'Navigation unifiée',
    'utilisent maintenant les mêmes routes OPS locales',
    'mêmes routes OPS',
    'et Actions utilisent',
    'Table détaillée',
    'résumées',
    'wrappées',
    'confinées',
] as $forbidden) {
    if (str_contains($visible, $forbidden)) {
        throw new RuntimeException('EN_NAV_RENDER_FRENCH_LEAK: ' . $forbidden);
    }
}

echo 'CHECK_P7_OPS_EN_NAV_RENDER=OK' . PHP_EOL;

$logDir = $root . '/var/logs/opus_lstsar-manager';
$accessLog = $logDir . '/access.log';
$profilerLog = $logDir . '/profiler.log';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/english/navigation?site=site-alpha&lang=en&smoke=profiler';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_EN_NAVIGATION_PROFILER_TEXT_CORE_SMOKE';

$beforeAccess = is_file($accessLog) ? (int) filesize($accessLog) : 0;
$beforeProfiler = is_file($profilerLog) ? (int) filesize($profilerLog) : 0;

p7ops_access_log_once();
p7ops_profiler_start_once();
usleep(1000);
p7ops_profiler_finish_once('smoke');

if (!is_file($accessLog) || (int) filesize($accessLog) <= $beforeAccess) {
    throw new RuntimeException('ACCESS_LOG_NOT_APPENDED');
}

if (!is_file($profilerLog) || (int) filesize($profilerLog) <= $beforeProfiler) {
    throw new RuntimeException('PROFILER_LOG_NOT_APPENDED');
}

$accessTail = file_get_contents($accessLog, false, null, max(0, ((int) filesize($accessLog)) - 4096));
$profilerTail = file_get_contents($profilerLog, false, null, max(0, ((int) filesize($profilerLog)) - 4096));

foreach ([
    '"event":"http_request"',
    '"uri":"/english/navigation?site=site-alpha&lang=en&smoke=profiler"',
    '"path":"/english/navigation"',
] as $marker) {
    if (!is_string($accessTail) || !str_contains($accessTail, $marker)) {
        throw new RuntimeException('ACCESS_LOG_TAIL_MARKER_MISSING: ' . $marker);
    }
}

foreach ([
    '"event":"profile_request"',
    '"uri":"/english/navigation?site=site-alpha&lang=en&smoke=profiler"',
    '"duration_ms":',
    '"memory_peak_bytes":',
] as $marker) {
    if (!is_string($profilerTail) || !str_contains($profilerTail, $marker)) {
        throw new RuntimeException('PROFILER_LOG_TAIL_MARKER_MISSING: ' . $marker);
    }
}

echo 'CHECK_P7_OPS_ACCESS_AND_PROFILER_LOGS=OK' . PHP_EOL;
echo 'P7_OPS_EN_NAVIGATION_PROFILER_TEXT_CORE_SMOKE_OK' . PHP_EOL;
