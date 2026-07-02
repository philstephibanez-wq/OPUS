<?php
declare(strict_types=1);

echo 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE_SMOKE' . PHP_EOL;

$root = dirname(__DIR__, 2);
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';
$files = [$publicDir . '/language.php', $publicDir . '/router.php', $publicDir . '/ops-ui.css', $siteDir . '/README.md'];
foreach ($files as $file) { if (!is_file($file)) { throw new RuntimeException('UNIFIED_NAV_FILE_MISSING: ' . $file); } }
$combined = '';
foreach ($files as $file) { $source = file_get_contents($file); if (!is_string($source)) { throw new RuntimeException('UNIFIED_NAV_READ_FAILED: ' . $file); } $combined .= $source . PHP_EOL; }
foreach (['P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE','p7ops_unified_navigation_boot_once','p7ops_nav_groups','p7ops_nav_html','p7ops_nav_cleanup_legacy','Pilotage','Chaîne','Observabilité','ODBC Manager','Profiler ON','Sortir profiler','p7ops_unified_navigation_boot_once();','.opus-unified-nav','.oun-groups','position:sticky'] as $marker) {
    if (!str_contains($combined, $marker)) { throw new RuntimeException('UNIFIED_NAV_MARKER_MISSING: ' . $marker); }
}
echo 'CHECK_P7_OPS_UNIFIED_NAV_MARKERS=OK' . PHP_EOL;
require_once $publicDir . '/language.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/opus-lstsar-manager/operations?site=site-alpha&lang=fr&profiler=1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE_SMOKE';
$_GET = ['site' => 'site-alpha', 'lang' => 'fr', 'profiler' => '1'];
$html = p7ops_nav_html();
foreach (['OPUS OPS','LSTSAR Manager','Pilotage','Dashboard','Operations','Command Center','Chaîne','FSM','CL','Models','ODBC Manager','Observabilité','Profiler','Diagnostics','Health','site-alpha'] as $marker) {
    if (!str_contains($html, $marker)) { throw new RuntimeException('UNIFIED_NAV_HTML_MARKER_MISSING: ' . $marker); }
}
if (!str_contains($html, 'class="is-active"')) { throw new RuntimeException('UNIFIED_NAV_ACTIVE_ITEM_MISSING'); }
echo 'CHECK_P7_OPS_UNIFIED_NAV_HTML=OK' . PHP_EOL;
$legacy = '<!doctype html><html><body><section class="ops-panel"><div class="ops-topline"><span class="ops-badge">P7_OPS_CHAIN_AUTH_ENV_CORE</span><a>Logout</a></div><h1>Old</h1></section><main>Body</main></body></html>';
$clean = p7ops_nav_cleanup_legacy($legacy);
if (str_contains($clean, 'P7_OPS_CHAIN_AUTH_ENV_CORE')) { throw new RuntimeException('UNIFIED_NAV_LEGACY_HEADER_NOT_REMOVED'); }
echo 'CHECK_P7_OPS_UNIFIED_NAV_CLEANUP=OK' . PHP_EOL;
echo 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE_SMOKE_OK' . PHP_EOL;
