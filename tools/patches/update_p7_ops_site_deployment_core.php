<?php
declare(strict_types=1);
$file = getcwd() . '/sites/opus-p7-ops/public/index.php';
if (!is_file($file)) { fwrite(STDERR, "OPS_INDEX_NOT_FOUND" . PHP_EOL); die(1); }
$s = file_get_contents($file);
if ($s === false) { fwrite(STDERR, "OPS_INDEX_READ_FAILED" . PHP_EOL); die(1); }
$d = chr(36);
$old = $d . "path = parse_url(" . $d . "_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';";
$new = $d . "rawPath = parse_url(" . $d . "_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';";
$new .= PHP_EOL . $d . "path = " . $d . "rawPath === '/' ? '/' : rtrim(" . $d . "rawPath, '/');";
if (strpos($s, "rawPath = parse_url") === false) {
    if (strpos($s, $old) === false) { fwrite(STDERR, "OPS_PATH_ASSIGNMENT_NOT_FOUND" . PHP_EOL); die(1); }
    $s = str_replace($old, $new, $s);
}
file_put_contents($file, $s);
echo "P7_OPS_SITE_DEPLOYMENT_CORE_UPDATED" . PHP_EOL;
