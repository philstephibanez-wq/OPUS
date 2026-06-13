<?php

declare(strict_types=1);

// Example: OPUS_REF_BOOK fetches the official Opus RefBook snapshot.
// Contract: the RefBook app consumes REST JSON; it does not scan H:\Opus.

$json = file_get_contents('http://127.0.0.1:8793/api/refbook/snapshot');
if (!is_string($json)) {
    throw new RuntimeException('OPUS_REFBOOK_API_READ_FAILED');
}

$snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
echo 'Schema: ' . $snapshot['schema_version'] . PHP_EOL;
echo 'Classes: ' . $snapshot['summary']['classes'] . PHP_EOL;
