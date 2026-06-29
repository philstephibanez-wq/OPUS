<?php
declare(strict_types=1);

use Opus\Application\Package\ApplicationPackageManifest;
use Opus\Application\Package\PackagesDirectoryApplicationRepository;
use Opus\Application\Package\PackagesDirectoryContract;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

echo "P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE_SMOKE\n";

$root = dirname(__DIR__, 2);
$contract = new PackagesDirectoryContract();
$contract->assertRootComposerHasPackagesPathRepository($root);
echo "CHECK_PACKAGES_PATH_REPOSITORY=OK\n";

$manifests = $contract->validateRequiredPackages($root, [
    'logandplay/opus-ref-book',
    'logandplay/opus-demo',
    'logandplay/opus-odbc-manager',
]);

if (count($manifests) < 3) {
    throw new RuntimeException('CHECK_PACKAGES_DISCOVERY_COUNT=FAIL');
}
echo "CHECK_PACKAGES_DISCOVERY_COUNT=OK\n";

$byName = [];
foreach ($manifests as $manifest) {
    if (!$manifest instanceof ApplicationPackageManifest) {
        throw new RuntimeException('CHECK_PACKAGES_MANIFEST_OBJECT=FAIL');
    }
    $byName[$manifest->packageName()] = $manifest;
}
echo "CHECK_PACKAGES_MANIFEST_OBJECT=OK\n";

if (($byName['logandplay/opus-ref-book'] ?? null)?->applicationSlug() !== 'opus-ref-book') {
    throw new RuntimeException('CHECK_REFBOOK_PACKAGE=FAIL');
}
echo "CHECK_REFBOOK_PACKAGE=OK\n";

if (($byName['logandplay/opus-demo'] ?? null)?->applicationSlug() !== 'opus-demo') {
    throw new RuntimeException('CHECK_DEMO_PACKAGE=FAIL');
}
echo "CHECK_DEMO_PACKAGE=OK\n";

$manager = $byName['logandplay/opus-odbc-manager'] ?? null;
if (!$manager instanceof ApplicationPackageManifest || $manager->applicationSlug() !== 'opus-odbc-manager' || !$manager->isProtected()) {
    throw new RuntimeException('CHECK_ODBC_MANAGER_PACKAGE=FAIL');
}
echo "CHECK_ODBC_MANAGER_PACKAGE=OK\n";

foreach ($manifests as $manifest) {
    $integrations = $manifest->integrations();
    foreach (['scoretemplate', 'i18n', 'sso_acl', 'diagnostics', 'profiler'] as $key) {
        if (!array_key_exists($key, $integrations)) {
            throw new RuntimeException('CHECK_PACKAGE_INTEGRATION_' . strtoupper($key) . '=FAIL');
        }
    }
}
echo "CHECK_PACKAGE_INTEGRATIONS=OK\n";

$repository = new PackagesDirectoryApplicationRepository($root);
$discovered = $repository->discover();
if (count($discovered) < 3) {
    throw new RuntimeException('CHECK_PACKAGES_DIRECTORY_REPOSITORY=FAIL');
}
echo "CHECK_PACKAGES_DIRECTORY_REPOSITORY=OK\n";

echo "P7_OPUS_PACKAGES_DIRECTORY_CONTRACT_CORE_SMOKE_OK\n";
