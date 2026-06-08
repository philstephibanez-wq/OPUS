<?php

declare(strict_types=1);

/**
 * P112Q3D RefBook tag contract test.
 *
 * Public contract test.
 * Role:
 *   Make the Reference Book tagging rule executable from the test layer.
 *
 * Contract:
 *   Every public ASAP class/interface/trait/enum and every public method must
 *   have its own `ASAP_REFBOOK` block. This test is intentionally strict and
 *   fails until missing method-level tags are completed.
 */
$root = dirname(__DIR__, 2);
$contractScript = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'quality' . DIRECTORY_SEPARATOR . 'p112q3d_refbook_tag_contract.php';

if (!is_file($contractScript)) {
    fwrite(STDERR, 'P112Q3D_REFBOOK_TAG_CONTRACT_TEST_FAILED: CONTRACT_SCRIPT_MISSING' . PHP_EOL);
    exit(1);
}

require_once $contractScript;

try {
    exit((new P112Q3DRefBookTagContract($root))->run(true));
} catch (Throwable $exception) {
    fwrite(STDERR, 'P112Q3D_REFBOOK_TAG_CONTRACT_TEST_FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
