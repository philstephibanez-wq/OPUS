<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_sign_out();
header('Location: /opus-lstsar-manager/login?logout=1', true, 302);
exit;