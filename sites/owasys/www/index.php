<?php
declare(strict_types=1);
$siteRoot = dirname(__DIR__);
$config = json_decode((string) file_get_contents($siteRoot . '/config/routes.json'), true);
if (!is_array($config) || !isset($config['routes']) || !is_array($config['routes'])) { http_response_code(500); echo 'OWASYS_ROUTES_CONFIG_INVALID'; exit; }
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = '/' . trim($requestPath, '/');
if ($requestPath === '/') { $path = '/'; $mount = ''; }
elseif ($requestPath === '/owasys') { $path = '/'; $mount = '/owasys'; }
elseif (str_starts_with($requestPath, '/owasys/')) { $path = substr($requestPath, strlen('/owasys')); $path = $path === '' ? '/' : $path; $mount = '/owasys'; }
else { $path = $requestPath; $mount = ''; }
$route = null;
foreach ($config['routes'] as $candidate) { if (is_array($candidate) && ($candidate['path'] ?? null) === $path) { $route = $candidate; break; } }
if (!is_array($route)) { http_response_code(404); echo 'OWASYS_ROUTE_NOT_FOUND: ' . htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); exit; }
$controller = (string) ($route['controller'] ?? '');
if (!preg_match('/^[a-z0-9_-]+$/', $controller)) { http_response_code(500); echo 'OWASYS_CONTROLLER_INVALID'; exit; }
$viewFile = $siteRoot . '/application/' . $controller . '/views/index.php';
if (!is_file($viewFile)) { http_response_code(500); echo 'OWASYS_VIEW_MISSING: ' . htmlspecialchars($controller, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); exit; }
$page = require $viewFile;
if (!is_array($page)) { http_response_code(500); echo 'OWASYS_VIEW_MODEL_INVALID'; exit; }
$menu = [];
foreach ($config['routes'] as $candidate) { if (is_array($candidate) && ($candidate['show_in_menu'] ?? false) === true) $menu[] = $candidate; }
usort($menu, static fn(array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$asset = static fn(string $p): string => $mount . '/' . ltrim($p, '/');
$href = static fn(string $p): string => $mount . ($p === '/' ? '/' : $p);
$body = '<div class="ow-shell"><aside class="ow-sidebar"><div class="ow-brand"><strong>OWASYS</strong><span>OPUS Web Application System</span></div><nav class="ow-nav">';
foreach ($menu as $item) { $label = ucwords(str_replace('-', ' ', str_replace('menu.', '', (string) ($item['label'] ?? '')))); $body .= '<a href="' . $h($href((string) ($item['path'] ?? '#'))) . '">' . $h($label) . '</a>'; }
$body .= '</nav></aside><main class="ow-main"><header class="ow-topbar"><div><span class="ow-pill">OPUS application</span><h1>' . $h((string) ($page['title'] ?? 'OWASYS')) . '</h1><p class="ow-muted">' . $h((string) ($page['summary'] ?? '')) . '</p></div></header><section class="ow-grid">';
foreach ((array) ($page['sections'] ?? []) as $section) $body .= '<article class="ow-card"><h2>' . $h((string) $section) . '</h2><p class="ow-muted">Configuration through standard OPUS application folders, models, ODBC datasources and validation contracts.</p></article>';
$body .= '</section></main></div>';
echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h((string) ($page['title'] ?? 'OWASYS')) . ' — OWASYS</title><link rel="stylesheet" href="' . $h($asset('/asset/css/owasys.css')) . '"><link rel="stylesheet" href="' . $h($asset('/asset/themes/owasys/css/theme.css')) . '"></head><body>' . $body . '<script src="' . $h($asset('/asset/js/owasys.js')) . '"></script><script src="' . $h($asset('/asset/themes/owasys/js/theme.js')) . '"></script></body></html>';