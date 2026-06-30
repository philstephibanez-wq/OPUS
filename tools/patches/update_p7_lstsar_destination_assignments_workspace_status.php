<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$statusPath = $root . '/DOC/WORKSPACE_STATUS.md';

if (!is_file($statusPath)) {
    fwrite(STDERR, "WORKSPACE_STATUS_NOT_FOUND\n");
    exit(1);
}

$status = (string) file_get_contents($statusPath);
$marker = 'P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE';

if (!str_contains($status, $marker)) {
    $status .= "\n\n## P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE\n\n";
    $status .= "Status: applied locally, smoke pending.\n\n";
    $status .= "Latest functional commit: `pending commit after P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE smoke`\n\n";
    $status .= "Scope:\n\n";
    $status .= "- destination-field assignments in `03_Transform.php`;\n";
    $status .= "- constants, metadata, security, source, transformed, hash, concat and hook assignments;\n";
    $status .= "- explicit pure transform hook registry;\n";
    $status .= "- LSTSAR script necessity audit.\n\n";
    $status .= "Validation:\n\n";
    $status .= "- `P7_LSTSAR_DESTINATION_ASSIGNMENTS_CORE_SMOKE_OK`\n";
}

file_put_contents($statusPath, $status);
echo "WORKSPACE_STATUS_UPDATED\n";
