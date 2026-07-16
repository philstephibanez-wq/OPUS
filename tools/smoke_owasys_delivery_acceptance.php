<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$contractPath = $root . '/sites/owasys/config/delivery-acceptance.json';
if (!is_file($contractPath)) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_CONTRACT_MISSING\n");
    exit(1);
}

$decoded = json_decode((string) file_get_contents($contractPath), true);
if (!is_array($decoded) || ($decoded['contract'] ?? null) !== 'OWASYS_DELIVERY_ACCEPTANCE_V1') {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_CONTRACT_INVALID\n");
    exit(1);
}

if (($decoded['automated_acceptance']['completed'] ?? null) !== true) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_AUTOMATED_NOT_COMPLETE\n");
    exit(1);
}

$requiredMarkers = $decoded['automated_acceptance']['required_markers'] ?? null;
if (!is_array($requiredMarkers) || count($requiredMarkers) < 8) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_MARKERS_INVALID\n");
    exit(1);
}

$required = [
    'OPUS_SMOKE_ALL_OK',
    'OWASYS_STRUCTURE_DRAFT_APPLY_UI_HTTP_SMOKE_OK',
    'OWASYS_RUNTIME_FSM_HTTP_SMOKE_OK',
    'OWASYS_SOURCE_HTTP_SMOKE_OK',
    'OWASYS_SOURCE_EDITOR_UI_SMOKE_OK',
    'OWASYS_SOURCE_GIT_WRITE_UI_SMOKE_OK',
    'OWASYS_REPOSITORY_OPERATOR_SMOKE_OK',
    'OPUS_VALIDATE_SITE_OK: owasys',
];
foreach ($required as $marker) {
    if (!in_array($marker, $requiredMarkers, true)) {
        fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_MARKER_MISSING: {$marker}\n");
        exit(1);
    }
}

$visual = $decoded['visual_acceptance'] ?? null;
if (!is_array($visual) || ($visual['completed'] ?? null) !== false || ($visual['application'] ?? null) !== 'demo-app') {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_VISUAL_STATE_INVALID\n");
    exit(1);
}
if (!is_array($visual['steps'] ?? null) || count($visual['steps']) < 10) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_VISUAL_STEPS_INVALID\n");
    exit(1);
}

$security = $decoded['security_boundaries'] ?? null;
if (!is_array($security)
    || ($security['git_scope'] ?? null) !== 'selected-application-subtree'
    || ($security['free_form_git_commands'] ?? null) !== false
    || ($security['pull'] ?? null) !== false
    || ($security['push'] ?? null) !== false
    || ($security['reset'] ?? null) !== false
    || ($security['branch_mutation'] ?? null) !== false
) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_SECURITY_INVALID\n");
    exit(1);
}

$roots = $security['editor_roots'] ?? null;
if ($roots !== ['config/', 'application/', 'www/asset/']) {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_EDITOR_ROOTS_INVALID\n");
    exit(1);
}

if (($decoded['status'] ?? null) !== 'technical-acceptance-complete-visual-acceptance-pending') {
    fwrite(STDERR, "OWASYS_DELIVERY_ACCEPTANCE_STATUS_INVALID\n");
    exit(1);
}

echo "OWASYS_DELIVERY_ACCEPTANCE_SMOKE_OK\n";
