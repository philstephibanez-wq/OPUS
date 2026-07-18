<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

echo "OWASYS_LEGACY_APPLICATION_REMOVED\n";
