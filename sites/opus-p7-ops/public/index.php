<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

function ops_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$site = (string) ($_GET['site'] ?? 'site-alpha');
$title = 'OPUS P7 OPS SITE';
$data = [];
$error = '';
$status = 200;

try {
    $factory = new \OpusLstsarManager\View\LstsarManagerViewModelFactory();

    if ($path === '/' || $path === '/opus-lstsar-manager') {
        $title = 'OPUS LSTSAR Manager Dashboard';
        $data = (new \OpusLstsarManager\Controller\DashboardController($factory))->dashboard($site);
    } elseif ($path === '/opus-lstsar-manager/operations') {
        $title = 'OPUS LSTSAR Operations';
        $data = (new \OpusLstsarManager\Controller\OperationsController($factory))->operations($site);
    } else {
        $status = 404;
        $title = 'OPUS OPS ROUTE NOT FOUND';
        $error = 'Route inconnue : ' . $path;
    }
} catch (\Throwable $exception) {
    $status = 500;
    $title = 'OPUS OPS RENDER FAILED';
    $error = $exception::class . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
}

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ops_e($title) ?></title>
<style>
body{margin:0;background:#07111f;color:#f6f8ff;font-family:Segoe UI,Arial,sans-serif}
.shell{width:min(1180px,calc(100% - 48px));margin:0 auto;padding:36px 0}
.top{display:flex;justify-content:space-between;gap:16px;align-items:center;border-bottom:1px solid #29405f;padding-bottom:18px}
.brand{font-weight:900;color:#69e3ff;letter-spacing:.08em}
.nav a{color:#f6f8ff;border:1px solid #29405f;border-radius:999px;padding:9px 14px;text-decoration:none;margin-left:8px}
.panel{margin-top:24px;background:#0d1a2d;border:1px solid #29405f;border-radius:22px;padding:24px}
h1{font-size:clamp(2rem,5vw,4rem);margin:.25em 0}
.muted{color:#b6c5dc}
.badge{display:inline-block;color:#07111f;background:#69e3ff;border-radius:999px;padding:6px 10px;font-weight:900}
pre{background:#030813;border:1px solid #29405f;border-radius:14px;padding:18px;overflow:auto;color:#ffdf99}
code{color:#ffdf99}
</style>
</head>
<body>
<main class="shell">
<div class="top">
<div>
<div class="brand">OPUS P7 OPS SITE</div>
<div class="muted">sites/opus-p7-ops/public</div>
</div>
<nav class="nav">
<a href="/opus-lstsar-manager">Dashboard</a>
<a href="/opus-lstsar-manager/operations">Operations</a>
</nav>
</div>
<section class="panel">
<span class="badge"><?= ops_e($path) ?></span>
<h1><?= ops_e($title) ?></h1>
<p class="muted">Site : <code><?= ops_e($site) ?></code></p>
</section>
<?php if ($error !== ''): ?>
<section class="panel">
<h2>Erreur</h2>
<pre><?= ops_e($error) ?></pre>
</section>
<?php else: ?>
<section class="panel">
<h2>View model réel</h2>
<pre><?= ops_e($json) ?></pre>
</section>
<?php endif; ?>
</main>
</body>
</html>
