<?php
declare(strict_types=1);

$path = getcwd() . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'WORKSPACE_STATUS.md';
if (!is_file($path)) {
    fwrite(STDERR, "WORKSPACE_STATUS_MISSING\n");
    exit(1);
}
$txt = (string) file_get_contents($path);
$txt = preg_replace('/Latest validated milestone: `[^`]+`/', 'Latest validated milestone: `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`', $txt, 1);
$txt = preg_replace('/Latest functional commit: `[^`]+`/', 'Latest functional commit: `pending commit after P7_OPUS_APP_PACKAGE_CONTRACT_CORE smoke`', $txt, 1);
$txt = preg_replace('/Previous validated milestone: `[^`]+`/', 'Previous validated milestone: `P7_ODBC_EXPLORER_CONTRACT_CORE`', $txt, 1);
$txt = preg_replace('/Previous cleanup commit: `[^`]+`/', 'Previous cleanup commit: `e12b1dd`', $txt, 1);
if (strpos($txt, '`P7_OPUS_APP_PACKAGE_CONTRACT_CORE`') === false) {
    $needle = "- `P7_ODBC_EXPLORER_CONTRACT_CORE`: OK in source. OPUS ODBC Explorer contract is validated as the Adminer/phpMyAdmin-like OPUS database administration surface for ODBC + Model + LSTSAR, with destructive operations guarded for later milestones.\n";
    $insert = $needle . "- `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`: OK in source. Official OPUS applications must be Composer-installable packages with an OPUS application manifest, including RefBook, demo applications and ODBC Manager / ODBC Explorer.\n";
    $txt = str_replace($needle, $insert, $txt);
}
if (strpos($txt, 'Official OPUS applications are Composer-installable packages') === false) {
    $needle = "- OPUS ODBC Explorer must be a standalone OPUS site/application, not only a utility class.\n";
    $insert = $needle . "- Official OPUS applications are Composer-installable packages; RefBook, demo applications, ODBC Manager / ODBC Explorer and future OPUS sites must not rely on manual folder copy as the official installation contract.\n";
    $txt = str_replace($needle, $insert, $txt);
}
if (strpos($txt, '`P7_OPUS_APP_PACKAGE_CONTRACT_CORE`') !== false && strpos($txt, 'P7_ODBC_EXPLORER_READONLY_CORE') !== false) {
    $txt = str_replace('1. `P7_ODBC_EXPLORER_READONLY_CORE`', '1. `P7_OPUS_APP_PACKAGE_CONTRACT_CORE`', $txt);
    $txt = str_replace('2. `P7_ODBC_EXPLORER_SITE_APP_CORE`', '2. `P7_ODBC_EXPLORER_READONLY_CORE`', $txt);
    $txt = str_replace('3. `P7_ODBC_EXPLORER_CRUD_CORE`', '3. `P7_ODBC_EXPLORER_SITE_APP_CORE`', $txt);
}
file_put_contents($path, $txt);
echo "WORKSPACE_STATUS_UPDATED\n";
