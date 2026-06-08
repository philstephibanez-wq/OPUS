<?php

declare(strict_types=1);

/**
 * PUBLIC ROBOTIZED EVOLUTIVE RECIPE
 *
 * Role:
 *   Run the visible secure-by-design life recipe requested for P112Q3B2.
 *
 * Responsibility:
 *   Execute a real ASAP FSM + ACL decision matrix for three different users,
 *   produce JSON/Markdown/HTML reports, attempt to send the report by e-mail,
 *   and optionally verify a browser page through Panther when available.
 *
 * Reads:
 *   - framework/Asap/* classes through the local ASAP PSR-4 autoloader fallback
 *   - optional vendor/autoload.php or ASAP_P112Q3B2_PANTHER_AUTOLOAD
 *   - environment variables documented in DOC/P112Q3B2_*.md
 *
 * Writes:
 *   - var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.json
 *   - var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.md
 *   - var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.html
 *   - var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe_email.html
 *   - var/reports/p112q3b2/p112q3b2_secure_life_robotized_recipe.eml when requested
 *   - optional Panther screenshot when Panther is available
 *
 * Contract:
 *   No fake success. Browser, Panther and mail are reported separately. The core
 *   secure decision matrix must pass or the recipe fails. Mail is allowed to fail
 *   only when ASAP_P112Q3B2_MAIL_REQUIRED is not set to 1.
 */

use ASAP\Acl\AccessRule;
use ASAP\Acl\PrivilegeDefinition;
use ASAP\Acl\ResourceDefinition;
use ASAP\Acl\RoleDefinition;
use ASAP\Contract\ContractException;
use ASAP\Fsm\StateDefinition;
use ASAP\Fsm\StateMachineException;
use ASAP\Fsm\TransitionDefinition;
use ASAP\Http\Request;
use ASAP\Routing\Router;
use ASAP\Security\SecureDispatchGate;
use ASAP\Security\SiteSecurityPolicy;
use ASAP\Site\SiteDefinition;

$root = dirname(__DIR__, 2);
$reportDir = $root . '/var/reports/p112q3b2';
$jsonReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.json';
$markdownReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.md';
$htmlReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.html';
$emailHtmlReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe_email.html';
$emlReport = $reportDir . '/p112q3b2_secure_life_robotized_recipe.eml';
$pantherScreenshot = $reportDir . '/p112q3b2_secure_life_robotized_recipe_panther.png';

if (!is_dir($reportDir) && !mkdir($reportDir, 0777, true) && !is_dir($reportDir)) {
    fwrite(STDERR, 'P112Q3B2_REPORT_DIR_CREATE_FAILED: ' . $reportDir . PHP_EOL);
    exit(1);
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ASAP\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $root . '/framework/Asap/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

$explicitPantherAutoload = trim((string) getenv('ASAP_P112Q3B2_PANTHER_AUTOLOAD'));
$explicitVendorRoot = trim((string) getenv('ASAP_VENDOR_ROOT'));
$autoloadCandidates = [];

if ($explicitPantherAutoload !== '') {
    $autoloadCandidates[] = $explicitPantherAutoload;
}

if ($explicitVendorRoot !== '') {
    $autoloadCandidates[] = rtrim(str_replace('\\', '/', $explicitVendorRoot), '/') . '/vendor/autoload.php';
    $autoloadCandidates[] = rtrim(str_replace('\\', '/', $explicitVendorRoot), '/') . '/autoload.php';
}

$autoloadCandidates[] = $root . '/vendor/autoload.php';
$autoloadCandidates[] = $root . '/tools/vendor/autoload.php';
$autoloadCandidates[] = dirname($root) . '/ASAP_REF_BOOK/vendor/autoload.php';
$autoloadCandidates[] = dirname($root) . '/vendor/autoload.php';

$autoloadCandidates = array_values(array_unique(array_filter($autoloadCandidates, static fn (string $path): bool => trim($path) !== '')));
$vendorAutoload = $autoloadCandidates[0] ?? ($root . '/vendor/autoload.php');
$autoloadLoaded = false;
$autoloadTried = [];

foreach ($autoloadCandidates as $autoloadCandidate) {
    $autoloadTried[] = $autoloadCandidate;

    if (is_file($autoloadCandidate)) {
        require_once $autoloadCandidate;
        $vendorAutoload = $autoloadCandidate;
        $autoloadLoaded = true;
        break;
    }
}

/**
 * Abort the recipe with a stable error code.
 */
function p112q3b2_fail(string $code, string $detail = ''): never
{
    $message = $detail === '' ? $code : $code . ': ' . $detail;
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * Assert one condition or fail explicitly.
 */
function p112q3b2_assert(bool $condition, string $code, string $detail = ''): void
{
    if (!$condition) {
        p112q3b2_fail($code, $detail);
    }
}

/**
 * Create the temporary route XML used by the real Router.
 */
function p112q3b2_write_routes_xml(string $path): void
{
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<routes>
  <route name="public_fr" path="/fr/public" methods="GET" acl="page_public:read" fsmGuard="ROUTE_PUBLIC">
    <target controllerClass="DemoController" action="show" />
  </route>
  <route name="editor_es" path="/es/editor" methods="GET" acl="page_editor:edit" fsmGuard="ROUTE_EDITOR">
    <target controllerClass="DemoController" action="edit" />
  </route>
  <route name="admin_en" path="/en/admin" methods="GET" acl="page_admin:admin" fsmGuard="ROUTE_ADMIN">
    <target controllerClass="DemoController" action="admin" />
  </route>
  <route name="form_public_fr" path="/fr/contact" methods="POST" acl="public_form:submit" fsmGuard="FORM_GUEST_SUBMIT">
    <target controllerClass="DemoController" action="submitContact" />
  </route>
  <route name="form_editor_es" path="/es/editor/form" methods="POST" acl="editor_form:submit" fsmGuard="FORM_EDITOR_SUBMIT">
    <target controllerClass="DemoController" action="submitEditor" />
  </route>
  <route name="form_admin_en" path="/en/admin/settings" methods="POST" acl="admin_form:submit" fsmGuard="FORM_ADMIN_SUBMIT">
    <target controllerClass="DemoController" action="submitAdmin" />
  </route>
</routes>
XML;

    file_put_contents($path, $xml);
}

/**
 * Build one route-aware security policy for the requested role.
 */
function p112q3b2_policy_for_role(string $role): SiteSecurityPolicy
{
    return new SiteSecurityPolicy(
        'REQUEST_RECEIVED',
        'ROUTE_PUBLIC',
        [
            new StateDefinition('REQUEST_RECEIVED', 'Request received'),
            new StateDefinition('PUBLIC_ALLOWED', 'Public route allowed'),
            new StateDefinition('EDITOR_ALLOWED', 'Editor route allowed'),
            new StateDefinition('ADMIN_ALLOWED', 'Admin route allowed'),
            new StateDefinition('PUBLIC_FORM_SUBMITTED', 'Public form submitted'),
            new StateDefinition('EDITOR_FORM_SUBMITTED', 'Editor form submitted'),
            new StateDefinition('ADMIN_FORM_SUBMITTED', 'Admin form submitted'),
        ],
        [
            new TransitionDefinition('REQUEST_RECEIVED', 'ROUTE_PUBLIC', 'PUBLIC_ALLOWED'),
            new TransitionDefinition('REQUEST_RECEIVED', 'ROUTE_EDITOR', 'EDITOR_ALLOWED'),
            new TransitionDefinition('REQUEST_RECEIVED', 'ROUTE_ADMIN', 'ADMIN_ALLOWED'),
            new TransitionDefinition('REQUEST_RECEIVED', 'FORM_GUEST_SUBMIT', 'PUBLIC_FORM_SUBMITTED'),
            new TransitionDefinition('REQUEST_RECEIVED', 'FORM_EDITOR_SUBMIT', 'EDITOR_FORM_SUBMITTED'),
            new TransitionDefinition('REQUEST_RECEIVED', 'FORM_ADMIN_SUBMIT', 'ADMIN_FORM_SUBMITTED'),
        ],
        $role,
        'page_public',
        'read',
        [
            new RoleDefinition('guest'),
            new RoleDefinition('editor'),
            new RoleDefinition('admin'),
        ],
        [
            new ResourceDefinition('page_public'),
            new ResourceDefinition('page_editor'),
            new ResourceDefinition('page_admin'),
            new ResourceDefinition('public_form'),
            new ResourceDefinition('editor_form'),
            new ResourceDefinition('admin_form'),
        ],
        [
            new PrivilegeDefinition('read'),
            new PrivilegeDefinition('edit'),
            new PrivilegeDefinition('admin'),
            new PrivilegeDefinition('submit'),
        ],
        [
            new AccessRule('guest', 'page_public', 'read', true),
            new AccessRule('guest', 'page_editor', 'edit', false),
            new AccessRule('guest', 'page_admin', 'admin', false),
            new AccessRule('guest', 'public_form', 'submit', true),
            new AccessRule('guest', 'editor_form', 'submit', false),
            new AccessRule('guest', 'admin_form', 'submit', false),
            new AccessRule('editor', 'page_public', 'read', true),
            new AccessRule('editor', 'page_editor', 'edit', true),
            new AccessRule('editor', 'page_admin', 'admin', false),
            new AccessRule('editor', 'public_form', 'submit', true),
            new AccessRule('editor', 'editor_form', 'submit', true),
            new AccessRule('editor', 'admin_form', 'submit', false),
            new AccessRule('admin', 'page_public', 'read', true),
            new AccessRule('admin', 'page_editor', 'edit', true),
            new AccessRule('admin', 'page_admin', 'admin', true),
            new AccessRule('admin', 'public_form', 'submit', true),
            new AccessRule('admin', 'editor_form', 'submit', true),
            new AccessRule('admin', 'admin_form', 'submit', true),
        ]
    );
}

/**
 * Return the expected scenario list used by the visible page and recipe assertions.
 *
 * @return array<int,array<string,mixed>>
 */
function p112q3b2_scenarios(): array
{
    return [
        [
            'kind' => 'navigation',
            'method' => 'GET',
            'user' => 'guest',
            'label' => 'Invité',
            'language' => 'fr',
            'route_path' => '/fr/public',
            'route_name' => 'public_fr',
            'expect_allowed' => true,
            'expected_rights' => 'lecture publique uniquement',
        ],
        [
            'kind' => 'navigation',
            'method' => 'GET',
            'user' => 'editor',
            'label' => 'Éditeur',
            'language' => 'es',
            'route_path' => '/es/editor',
            'route_name' => 'editor_es',
            'expect_allowed' => true,
            'expected_rights' => 'édition autorisée, administration refusée',
        ],
        [
            'kind' => 'navigation',
            'method' => 'GET',
            'user' => 'admin',
            'label' => 'Administrateur',
            'language' => 'en',
            'route_path' => '/en/admin',
            'route_name' => 'admin_en',
            'expect_allowed' => true,
            'expected_rights' => 'administration complète',
        ],
        [
            'kind' => 'navigation',
            'method' => 'GET',
            'user' => 'guest',
            'label' => 'Invité',
            'language' => 'fr',
            'route_path' => '/en/admin',
            'route_name' => 'admin_en',
            'expect_allowed' => false,
            'expected_rights' => 'administration refusée',
        ],
        [
            'kind' => 'navigation',
            'method' => 'GET',
            'user' => 'editor',
            'label' => 'Éditeur',
            'language' => 'es',
            'route_path' => '/en/admin',
            'route_name' => 'admin_en',
            'expect_allowed' => false,
            'expected_rights' => 'administration refusée',
        ],
        [
            'kind' => 'form',
            'method' => 'POST',
            'user' => 'guest',
            'label' => 'Invité',
            'language' => 'fr',
            'route_path' => '/fr/contact',
            'route_name' => 'form_public_fr',
            'expect_allowed' => true,
            'expected_rights' => 'formulaire public autorisé',
            'form_title' => 'Contact public',
            'form_field' => 'message',
            'form_value' => 'Bonjour ASAP',
        ],
        [
            'kind' => 'form',
            'method' => 'POST',
            'user' => 'editor',
            'label' => 'Éditeur',
            'language' => 'es',
            'route_path' => '/es/editor/form',
            'route_name' => 'form_editor_es',
            'expect_allowed' => true,
            'expected_rights' => 'formulaire éditeur autorisé',
            'form_title' => 'Formulario editor',
            'form_field' => 'note',
            'form_value' => 'Edición segura',
        ],
        [
            'kind' => 'form',
            'method' => 'POST',
            'user' => 'admin',
            'label' => 'Administrateur',
            'language' => 'en',
            'route_path' => '/en/admin/settings',
            'route_name' => 'form_admin_en',
            'expect_allowed' => true,
            'expected_rights' => 'formulaire admin autorisé',
            'form_title' => 'Admin settings',
            'form_field' => 'setting',
            'form_value' => 'secure-by-design',
        ],
        [
            'kind' => 'form',
            'method' => 'POST',
            'user' => 'guest',
            'label' => 'Invité',
            'language' => 'fr',
            'route_path' => '/en/admin/settings',
            'route_name' => 'form_admin_en',
            'expect_allowed' => false,
            'expected_rights' => 'formulaire admin refusé',
            'form_title' => 'Admin settings denied',
            'form_field' => 'setting',
            'form_value' => 'denied',
        ],
        [
            'kind' => 'form',
            'method' => 'POST',
            'user' => 'editor',
            'label' => 'Éditeur',
            'language' => 'es',
            'route_path' => '/en/admin/settings',
            'route_name' => 'form_admin_en',
            'expect_allowed' => false,
            'expected_rights' => 'formulaire admin refusé',
            'form_title' => 'Admin settings denied',
            'form_field' => 'setting',
            'form_value' => 'denied',
        ],
        [
            'kind' => 'form',
            'method' => 'GET',
            'user' => 'guest',
            'label' => 'Invité',
            'language' => 'fr',
            'route_path' => '/fr/contact',
            'route_name' => 'form_public_fr',
            'expect_allowed' => false,
            'expected_rights' => 'GET interdit sur formulaire POST',
            'form_title' => 'Contact public method denied',
            'form_field' => 'message',
            'form_value' => 'GET denied',
        ],
    ];
}
/**
 * Execute the route-aware secure gate for all scenarios.
 *
 * @return array<int,array<string,mixed>>
 */
function p112q3b2_run_matrix(string $root): array
{
    $tmpRoot = sys_get_temp_dir() . '/asap_p112q3b2_' . bin2hex(random_bytes(4));

    if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
        p112q3b2_fail('P112Q3B2_TEMP_DIR_CREATE_FAILED', $tmpRoot);
    }

    $routesFile = $tmpRoot . '/routes.xml';
    $securityFile = $tmpRoot . '/security.xml';
    p112q3b2_write_routes_xml($routesFile);
    file_put_contents($securityFile, '<security />');

    $results = [];

    try {
        $site = new SiteDefinition('p112q3b2', '/asap-secure-life', $routesFile, $securityFile);
        $router = Router::fromXml($routesFile);
        $gate = new SecureDispatchGate();

        foreach (p112q3b2_scenarios() as $scenario) {
            $requestMethod = strtoupper((string) ($scenario['method'] ?? 'GET'));
            $request = new Request('/asap-secure-life' . $scenario['route_path'], $requestMethod);
            $policy = p112q3b2_policy_for_role((string) $scenario['user']);
            $allowed = false;
            $reason = 'UNSET';
            $decisionData = [];
            $matchedRoute = null;
            $routeAcl = null;
            $routeFsmGuard = null;

            try {
                $match = $router->match($request, $site);
                $matchedRoute = $match->name;
                $routeAcl = $match->acl;
                $routeFsmGuard = $match->fsmGuard;

                $decision = $gate->assertAllowed($request, $policy, $match);
                $allowed = true;
                $reason = 'SECURE_GATE_ALLOWED';
                $decisionData = [
                    'fsm_state' => $decision->fsmState,
                    'fsm_signal' => $decision->fsmSignal,
                    'acl_role' => $decision->role,
                    'acl_resource' => $decision->resource,
                    'acl_privilege' => $decision->privilege,
                    'metadata_source' => $decision->metadataSource,
                ];
            } catch (Throwable $throwable) {
                $allowed = false;
                $reason = $throwable->getMessage();
            }

            $passed = $allowed === (bool) $scenario['expect_allowed'];

            $results[] = array_merge($scenario, [
                'request_path' => $request->path,
                'request_method' => $requestMethod,
                'matched_route' => $matchedRoute,
                'route_acl' => $routeAcl,
                'route_fsm_guard' => $routeFsmGuard,
                'observed_allowed' => $allowed,
                'passed' => $passed,
                'reason' => $reason,
            ], $decisionData);
        }
    } finally {
        if (is_file($routesFile)) {
            unlink($routesFile);
        }

        if (is_file($securityFile)) {
            unlink($securityFile);
        }

        if (is_dir($tmpRoot)) {
            rmdir($tmpRoot);
        }
    }

    return $results;
}

/**
 * Build the human-readable HTML page that must open in the browser.
 *
 * @param array<int,array<string,mixed>> $results
 */
function p112q3b2_build_html_report(array $results, array $mailStatus, array $pantherStatus): string
{
    $generatedAt = htmlspecialchars(gmdate('c'), ENT_QUOTES, 'UTF-8');
    $rows = '';
    $cards = '';
    $formCards = '';

    foreach ($results as $result) {
        $ok = (bool) $result['passed'];
        $class = $ok ? 'ok' : 'fail';
        $status = $ok ? 'OK' : 'FAIL';
        $allowed = (bool) $result['observed_allowed'] ? 'ALLOWED' : 'DENIED';
        $expected = (bool) $result['expect_allowed'] ? 'ALLOWED' : 'DENIED';
        $method = strtoupper((string) ($result['request_method'] ?? $result['method'] ?? 'GET'));
        $kind = (string) ($result['kind'] ?? 'navigation');

        $rows .= '<tr class="' . $class . '">'
            . '<td>' . htmlspecialchars((string) $result['label'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars(strtoupper((string) $result['language']), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><code>' . htmlspecialchars((string) $result['request_path'], ENT_QUOTES, 'UTF-8') . '</code></td>'
            . '<td>' . htmlspecialchars($expected, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($allowed, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>' . PHP_EOL;

        if ($kind === 'navigation'
            && in_array((string) $result['user'], ['guest', 'editor', 'admin'], true)
            && (bool) $result['expect_allowed'] === true) {
            $cards .= '<article class="user-card">'
                . '<div class="avatar">' . htmlspecialchars(strtoupper(substr((string) $result['user'], 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div><h2>' . htmlspecialchars((string) $result['label'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars(strtoupper((string) $result['language']), ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<p>' . htmlspecialchars((string) $result['expected_rights'], ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><code>' . htmlspecialchars((string) $result['request_path'], ENT_QUOTES, 'UTF-8') . '</code></p></div>'
                . '</article>' . PHP_EOL;
        }

        if ($kind === 'form' && (bool) $result['expect_allowed'] === true) {
            $field = htmlspecialchars((string) ($result['form_field'] ?? 'message'), ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars((string) ($result['form_value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($result['form_title'] ?? 'Formulaire'), ENT_QUOTES, 'UTF-8');
            $action = htmlspecialchars((string) $result['request_path'], ENT_QUOTES, 'UTF-8');
            $lang = htmlspecialchars(strtoupper((string) $result['language']), ENT_QUOTES, 'UTF-8');
            $formCards .= '<article class="form-card">'
                . '<h2>' . $title . ' · ' . $lang . '</h2>'
                . '<form method="post" action="' . $action . '" onsubmit="event.preventDefault(); this.querySelector(\'.form-result\').textContent=\'Soumission simulée OK · test serveur déjà validé par la recette\';">'
                . '<label>' . $field . '<input name="' . $field . '" value="' . $value . '"></label>'
                . '<input type="hidden" name="asap_recipe" value="P112Q3B4">'
                . '<button type="submit">Tester le formulaire</button>'
                . '<p class="form-result">POST testé côté recette via Router + SecureDispatchGate + FSM + ACL.</p>'
                . '</form>'
                . '<p><code>POST ' . $action . '</code></p>'
                . '</article>' . PHP_EOL;
        }
    }

    $mailBadge = htmlspecialchars((string) ($mailStatus['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8');
    $pantherBadge = htmlspecialchars((string) ($pantherStatus['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>P112Q3B2 · ASAP Secure Life Recipe</title>
  <style>
    :root { color-scheme: dark; --bg:#07111f; --panel:#0e1b2d; --line:#26384f; --text:#eaf2ff; --muted:#9fb1ca; --ok:#4ade80; --fail:#fb7185; --accent:#60a5fa; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: Segoe UI, Arial, sans-serif; background:linear-gradient(135deg,#07111f,#101f34); color:var(--text); }
    header { padding:22px 28px; border-bottom:1px solid var(--line); background:rgba(14,27,45,.92); position:sticky; top:0; z-index:2; }
    .kicker { color:var(--accent); text-transform:uppercase; letter-spacing:.12em; font-size:12px; font-weight:700; }
    h1 { margin:.25rem 0 .35rem; font-size:30px; }
    .meta { color:var(--muted); }
    main { padding:24px 28px; max-width:1180px; margin:0 auto; }
    .badges { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
    .badge { border:1px solid var(--line); border-radius:999px; padding:8px 12px; background:#0b1626; font-weight:700; }
    .grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; margin:22px 0; }
    .user-card, .form-card { border:1px solid var(--line); border-radius:18px; padding:18px; background:var(--panel); display:flex; gap:14px; align-items:flex-start; box-shadow:0 12px 36px rgba(0,0,0,.25); }
    .form-card { display:block; }
    .avatar { width:42px; height:42px; border-radius:999px; display:grid; place-items:center; background:#1d4ed8; font-weight:800; }
    h2 { margin:0 0 8px; font-size:18px; }
    p { color:var(--muted); margin:7px 0; }
    form { display:grid; gap:10px; }
    label { color:#dbeafe; display:grid; gap:6px; font-weight:700; }
    input { border:1px solid var(--line); border-radius:10px; padding:10px 12px; background:#07111f; color:var(--text); }
    button { border:1px solid #2563eb; border-radius:999px; padding:10px 14px; background:#1d4ed8; color:white; font-weight:800; cursor:pointer; }
    table { width:100%; border-collapse:collapse; overflow:hidden; border-radius:16px; background:var(--panel); border:1px solid var(--line); }
    th, td { text-align:left; padding:12px 14px; border-bottom:1px solid var(--line); vertical-align:top; }
    th { color:#cfe0ff; background:#101f34; }
    tr.ok td:last-child { color:var(--ok); font-weight:800; }
    tr.fail td:last-child { color:var(--fail); font-weight:800; }
    code { color:#bfdbfe; }
    .panel { border:1px solid var(--line); border-radius:18px; padding:18px; background:rgba(14,27,45,.9); margin-top:22px; }
    @media (max-width: 900px) { .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<header>
  <div class="kicker">ASAP secure by design · robotized evolutive recipe</div>
  <h1>FSM + ACL + Navigation + Formulaires · 3 utilisateurs · FR / ES / EN</h1>
  <div class="meta">Généré le {$generatedAt}</div>
  <div class="badges">
    <span class="badge">Mail: {$mailBadge}</span>
    <span class="badge">Panther: {$pantherBadge}</span>
    <span class="badge">Default deny observable</span>
    <span class="badge">POST forms tested</span>
  </div>
</header>
<main>
  <section class="grid">{$cards}</section>
  <section class="panel">
    <h2>Formulaires testés</h2>
    <p>Les formulaires visibles ci-dessous sont aussi testés côté recette avec des requêtes POST réelles, une route explicite, une transition FSM et une règle ACL.</p>
    <div class="grid">{$formCards}</div>
  </section>
  <section class="panel">
    <h2>Matrice runtime réelle</h2>
    <p>Chaque ligne passe par le Router ASAP, le SecureDispatchGate, la FSM et l'ACL. Les formulaires POST vérifient aussi l'absence de fallback GET implicite.</p>
    <table>
      <thead><tr><th>User</th><th>Lang</th><th>Méthode</th><th>Type</th><th>Route</th><th>Attendu</th><th>Observé</th><th>Résultat</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </section>
</main>
</body>
</html>
HTML;
}
/**
 * Build the Markdown report sent by e-mail and saved on disk.
 *
 * @param array<int,array<string,mixed>> $results
 */
function p112q3b2_build_markdown(array $results, array $mailStatus, array $pantherStatus): string
{
    $md = '# P112Q3B2 — ASAP Secure Life Robotized Recipe' . PHP_EOL . PHP_EOL;
    $md .= '- Generated at: `' . gmdate('c') . '`' . PHP_EOL;
    $md .= '- Mail status: `' . (string) ($mailStatus['status'] ?? 'UNKNOWN') . '`' . PHP_EOL;
    $md .= '- Panther status: `' . (string) ($pantherStatus['status'] ?? 'UNKNOWN') . '`' . PHP_EOL . PHP_EOL;
    $md .= '| User | Lang | Method | Type | Route | Expected | Observed | Result |' . PHP_EOL;
    $md .= '|---|---:|---:|---|---|---|---|---|' . PHP_EOL;

    foreach ($results as $result) {
        $md .= '| ' . (string) $result['user']
            . ' | ' . strtoupper((string) $result['language'])
            . ' | ' . strtoupper((string) ($result['request_method'] ?? $result['method'] ?? 'GET'))
            . ' | ' . (string) ($result['kind'] ?? 'navigation')
            . ' | `' . (string) $result['request_path'] . '`'
            . ' | ' . ((bool) $result['expect_allowed'] ? 'ALLOWED' : 'DENIED')
            . ' | ' . ((bool) $result['observed_allowed'] ? 'ALLOWED' : 'DENIED')
            . ' | ' . ((bool) $result['passed'] ? 'OK' : 'FAIL')
            . ' |' . PHP_EOL;
    }

    return $md;
}

/**
 * Build the dedicated email-safe HTML report.
 *
 * This renderer intentionally uses old, conservative table markup and inline
 * attributes instead of the richer browser report layout. It is separate from
 * the visible browser page so the recipe can stay pleasant in the browser while
 * the e-mail remains robust in Mailpit, Outlook-like clients and webmail.
 *
 * @param array<int,array<string,mixed>> $results
 */
function p112q3b2_build_email_safe_html_report(array $results, array $mailStatus, array $pantherStatus): string
{
    $generatedAt = htmlspecialchars(gmdate('c'), ENT_QUOTES, 'UTF-8');
    $mailBadge = htmlspecialchars((string) ($mailStatus['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8');
    $pantherBadge = htmlspecialchars((string) ($pantherStatus['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8');
    $rows = '';

    foreach ($results as $result) {
        $ok = (bool) $result['passed'];
        $status = $ok ? 'OK' : 'FAIL';
        $statusColor = $ok ? '#15803d' : '#be123c';
        $allowed = (bool) $result['observed_allowed'] ? 'ALLOWED' : 'DENIED';
        $expected = (bool) $result['expect_allowed'] ? 'ALLOWED' : 'DENIED';
        $method = strtoupper((string) ($result['request_method'] ?? $result['method'] ?? 'GET'));
        $kind = (string) ($result['kind'] ?? 'navigation');

        $rows .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars((string) $result['label'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars(strtoupper((string) $result['language']), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;"><code>' . htmlspecialchars((string) $result['request_path'], ENT_QUOTES, 'UTF-8') . '</code></td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars($expected, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;">' . htmlspecialchars($allowed, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #d9e2f3;color:' . $statusColor . ';font-weight:bold;">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }

    return '<!doctype html>'
        . '<html><head><meta charset="utf-8"><title>ASAP secure life recipe report</title></head>'
        . '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:18px;">'
        . '<table width="760" cellpadding="0" cellspacing="0" border="0" style="max-width:760px;width:100%;border:1px solid #d9e2f3;">'
        . '<tr><td bgcolor="#0f2342" style="padding:20px;color:#ffffff;">'
        . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#93c5fd;font-weight:bold;">ASAP secure by design · robotized evolutive recipe</div>'
        . '<h1 style="margin:8px 0 6px;font-size:26px;line-height:32px;color:#ffffff;">FSM + ACL + Navigation + Formulaires</h1>'
        . '<div style="font-size:14px;color:#cbd5e1;">3 utilisateurs · FR / ES / EN · généré le ' . $generatedAt . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:16px;">'
        . '<p><strong>Mail:</strong> ' . $mailBadge . ' &nbsp; <strong>Panther:</strong> ' . $pantherBadge . ' &nbsp; <strong>Default deny:</strong> observable &nbsp; <strong>POST forms:</strong> tested</p>'
        . '<p>Ce mail utilise un template séparé, compatible e-mail, distinct du rapport navigateur complet.</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #d9e2f3;">'
        . '<tr bgcolor="#edf3ff">'
        . '<th style="padding:8px;">User</th><th style="padding:8px;">Lang</th><th style="padding:8px;">Method</th><th style="padding:8px;">Type</th><th style="padding:8px;">Route</th><th style="padding:8px;">Expected</th><th style="padding:8px;">Observed</th><th style="padding:8px;">Result</th>'
        . '</tr>' . $rows . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}

/**
 * Return the report-visible successful mail status before the message body is built.
 *
 * The recipe cannot put an actual post-send value inside the very same mail body
 * after SMTP has already accepted it. Instead, it builds the mail body with the
 * exact success status expected for the selected transport, then verifies that
 * the transport accepted the message. If the transport refuses the message, the
 * JSON/MD/HTML reports saved on disk are regenerated with the failure status.
 */
function p112q3b2_success_mail_status(string $mode, string $to, string $from): array
{
    $host = trim((string) getenv('ASAP_P112Q3B2_SMTP_HOST'));
    $port = (int) (getenv('ASAP_P112Q3B2_SMTP_PORT') ?: 1025);

    if ($host === '') {
        $host = '127.0.0.1';
    }

    if ($mode === 'smtp' && in_array(strtolower($host), ['127.0.0.1', 'localhost'], true) && $port === 1025) {
        return [
            'status' => 'DELIVERED_TO_MAILPIT',
            'reason' => 'SMTP_ACCEPTED_BY_LOCAL_MAILPIT',
            'required' => getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1',
            'mode' => $mode,
            'host' => $host,
            'port' => $port,
            'to' => $to,
            'from' => $from,
        ];
    }

    if ($mode === 'smtp') {
        return [
            'status' => 'SENT',
            'reason' => 'SMTP_ACCEPTED',
            'required' => getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1',
            'mode' => $mode,
            'host' => $host,
            'port' => $port,
            'to' => $to,
            'from' => $from,
        ];
    }

    if ($mode === 'phpmail') {
        return [
            'status' => 'SENT',
            'reason' => 'PHP_MAIL_ACCEPTED',
            'required' => getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1',
            'mode' => $mode,
            'to' => $to,
            'from' => $from,
        ];
    }

    return [
        'status' => 'EML_WRITTEN',
        'reason' => 'MAIL_EML_WRITTEN_NOT_SENT',
        'required' => getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1',
        'mode' => $mode,
        'to' => $to,
        'from' => $from,
    ];
}

/**
 * Build and send the final report through the explicitly selected local mail mode.
 *
 * @param array<int,array<string,mixed>> $results
 */
function p112q3b2_send_mail(string $subject, array $results, array $pantherStatus): array
{
    global $emlReport;

    $to = trim((string) getenv('ASAP_P112Q3B2_REPORT_EMAIL_TO'));
    $from = trim((string) getenv('ASAP_P112Q3B2_REPORT_EMAIL_FROM'));
    $mode = strtolower(trim((string) getenv('ASAP_P112Q3B2_MAIL_MODE')));
    $required = getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1';

    if ($from === '') {
        $from = 'asap-recipes@localhost';
    }

    if ($mode === '') {
        $mode = 'phpmail';
    }

    if ($to === '') {
        return [
            'status' => $required ? 'FAILED' : 'SKIPPED',
            'reason' => 'MAIL_RECIPIENT_MISSING',
            'required' => $required,
            'mode' => $mode,
        ];
    }

    if (!in_array($mode, ['phpmail', 'smtp', 'eml'], true)) {
        return [
            'status' => 'FAILED',
            'reason' => 'MAIL_MODE_UNKNOWN',
            'required' => $required,
            'mode' => $mode,
        ];
    }

    $visibleSuccessStatus = p112q3b2_success_mail_status($mode, $to, $from);
    $htmlBody = p112q3b2_build_email_safe_html_report($results, $visibleSuccessStatus, $pantherStatus);
    $textBody = p112q3b2_build_markdown($results, $visibleSuccessStatus, $pantherStatus);

    $headers = [
        'From: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
    ];

    if ($mode === 'eml') {
        $eml = 'To: ' . $to . "\r\n"
            . 'From: ' . $from . "\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . implode("\r\n", array_slice($headers, 1)) . "\r\n\r\n"
            . $htmlBody . "\r\n\r\n<!-- MARKDOWN FALLBACK -->\r\n"
            . htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8');
        file_put_contents($emlReport, $eml);

        return $visibleSuccessStatus + ['eml_path' => $emlReport];
    }

    if ($mode === 'smtp') {
        return p112q3b2_send_smtp($to, $from, $subject, $htmlBody, $required, $visibleSuccessStatus);
    }

    $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));

    if ($sent) {
        return $visibleSuccessStatus;
    }

    return [
        'status' => $required ? 'FAILED' : 'SKIPPED',
        'reason' => 'PHP_MAIL_RETURNED_FALSE',
        'required' => $required,
        'mode' => $mode,
        'to' => $to,
        'from' => $from,
    ];
}

/**
 * Send a simple local SMTP message, suitable for Mailpit/MailHog-style dev SMTP.
 * No authentication and no TLS are attempted here by design.
 */
function p112q3b2_send_smtp(string $to, string $from, string $subject, string $htmlBody, bool $required, array $visibleSuccessStatus): array
{
    $host = trim((string) getenv('ASAP_P112Q3B2_SMTP_HOST'));
    $port = (int) (getenv('ASAP_P112Q3B2_SMTP_PORT') ?: 1025);

    if ($host === '') {
        $host = '127.0.0.1';
    }

    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 5);

    if (!is_resource($socket)) {
        return [
            'status' => $required ? 'FAILED' : 'SKIPPED',
            'reason' => 'SMTP_CONNECT_FAILED',
            'required' => $required,
            'mode' => 'smtp',
            'host' => $host,
            'port' => $port,
            'error' => $errstr,
        ];
    }

    $read = static function () use ($socket): string {
        $line = fgets($socket);
        return is_string($line) ? $line : '';
    };

    $write = static function (string $command) use ($socket): void {
        fwrite($socket, $command . "\r\n");
    };

    $read();
    $write('HELO localhost');
    $read();
    $write('MAIL FROM:<' . $from . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();
    $write('From: ' . $from . "\r\n" . 'To: ' . $to . "\r\n" . 'Subject: ' . $subject . "\r\n" . 'MIME-Version: 1.0' . "\r\n" . 'Content-Type: text/html; charset=UTF-8' . "\r\n\r\n" . $htmlBody . "\r\n.");
    $dataResponse = $read();
    $write('QUIT');
    fclose($socket);

    if (str_starts_with($dataResponse, '250')) {
        return $visibleSuccessStatus + ['smtp_data_response' => trim($dataResponse)];
    }

    return [
        'status' => $required ? 'FAILED' : 'SKIPPED',
        'reason' => 'SMTP_DATA_NOT_ACCEPTED',
        'required' => $required,
        'mode' => 'smtp',
        'host' => $host,
        'port' => $port,
        'smtp_data_response' => trim($dataResponse),
        'to' => $to,
        'from' => $from,
    ];
}

/**
 * Optionally verify a URL with Panther when the class is available.
 */
function p112q3b2_panther_check(string $defaultUrl): array
{
    global $autoloadLoaded, $vendorAutoload, $autoloadTried, $pantherScreenshot;

    $required = getenv('ASAP_P112Q3B2_PANTHER_REQUIRED') === '1';
    $url = trim((string) getenv('ASAP_P112Q3B2_PANTHER_URL'));
    $expectedText = trim((string) getenv('ASAP_P112Q3B2_PANTHER_EXPECT_TEXT'));

    if ($url === '') {
        $url = $defaultUrl;
    }

    if (!$autoloadLoaded) {
        return [
            'status' => $required ? 'FAILED' : 'SKIPPED',
            'reason' => 'PANTHER_AUTOLOAD_NOT_FOUND',
            'required' => $required,
            'autoload_path' => $vendorAutoload,
            'autoload_tried' => $autoloadTried,
        ];
    }

    if (!class_exists('Symfony\\Component\\Panther\\Client')) {
        return [
            'status' => $required ? 'FAILED' : 'SKIPPED',
            'reason' => 'PANTHER_CLIENT_NOT_AVAILABLE',
            'required' => $required,
            'autoload_path' => $vendorAutoload,
            'autoload_tried' => $autoloadTried,
        ];
    }

    try {
        /** @var class-string $clientClass */
        $clientClass = 'Symfony\\Component\\Panther\\Client';
        $client = $clientClass::createChromeClient();
        $crawler = $client->request('GET', $url);
        $pageText = '';

        if (method_exists($crawler, 'filter')) {
            $body = $crawler->filter('body');

            if ($body->count() > 0) {
                $pageText = trim($body->text());
            }
        }

        if ($pageText === '') {
            return ['status' => 'FAILED', 'reason' => 'PANTHER_PAGE_BODY_EMPTY', 'url' => $url];
        }

        if ($expectedText !== '' && !str_contains($pageText, $expectedText)) {
            return ['status' => 'FAILED', 'reason' => 'PANTHER_EXPECTED_TEXT_NOT_FOUND', 'url' => $url, 'expected_text' => $expectedText];
        }

        if (method_exists($client, 'takeScreenshot')) {
            $client->takeScreenshot($pantherScreenshot);
        }

        return [
            'status' => 'OK',
            'reason' => 'PANTHER_PAGE_RENDERED',
            'url' => $url,
            'screenshot' => is_file($pantherScreenshot) ? $pantherScreenshot : null,
        ];
    } catch (Throwable $throwable) {
        return ['status' => 'FAILED', 'reason' => 'PANTHER_RUNTIME_ERROR', 'url' => $url, 'error' => $throwable->getMessage()];
    }
}

$results = p112q3b2_run_matrix($root);
$matrixOk = $results !== [] && count(array_filter($results, static fn (array $row): bool => (bool) $row['passed'])) === count($results);

p112q3b2_assert($matrixOk, 'P112Q3B2_MATRIX_FAILED');

$initialMailStatus = ['status' => 'NOT_SENT_YET', 'reason' => 'MAIL_STATUS_COMPUTED_DURING_SEND'];
file_put_contents($htmlReport, p112q3b2_build_html_report($results, $initialMailStatus, ['status' => 'NOT_RUN_YET', 'reason' => 'PANTHER_AFTER_HTML_BUILD']));

$pantherStatus = p112q3b2_panther_check('file:///' . str_replace('\\', '/', $htmlReport));
$mailStatus = p112q3b2_send_mail('ASAP P112Q3B2 secure life recipe report', $results, $pantherStatus);
$html = p112q3b2_build_html_report($results, $mailStatus, $pantherStatus);
$emailHtml = p112q3b2_build_email_safe_html_report($results, $mailStatus, $pantherStatus);
$markdown = p112q3b2_build_markdown($results, $mailStatus, $pantherStatus);

file_put_contents($htmlReport, $html);
file_put_contents($emailHtmlReport, $emailHtml);
file_put_contents($markdownReport, $markdown);

$payload = [
    'id' => 'P112Q3B2_ASAP_SECURE_LIFE_ROBOTIZED_RECIPE',
    'status' => 'OK',
    'generated_at' => gmdate('c'),
    'matrix_ok' => $matrixOk,
    'scenarios' => $results,
    'mail' => $mailStatus,
    'panther' => $pantherStatus,
    'reports' => [
        'json' => $jsonReport,
        'markdown' => $markdownReport,
        'html' => $htmlReport,
        'email_html' => $emailHtmlReport,
        'eml' => is_file($emlReport) ? $emlReport : null,
        'panther_screenshot' => is_file($pantherScreenshot) ? $pantherScreenshot : null,
    ],
];

file_put_contents($jsonReport, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$mailRequired = getenv('ASAP_P112Q3B2_MAIL_REQUIRED') === '1';
$pantherRequired = getenv('ASAP_P112Q3B2_PANTHER_REQUIRED') === '1';
$mailOk = in_array((string) $mailStatus['status'], ['SENT', 'DELIVERED_TO_MAILPIT', 'EML_WRITTEN'], true);
$pantherOk = (string) ($pantherStatus['status'] ?? '') === 'OK';

if ($mailRequired && !$mailOk) {
    echo 'P112Q3B2_SECURE_LIFE_RECIPE_FAILED: MAIL_NOT_DELIVERED' . PHP_EOL;
    echo 'Report HTML: ' . $htmlReport . PHP_EOL;
    echo 'Report JSON: ' . $jsonReport . PHP_EOL;
    exit(1);
}

if ($pantherRequired && !$pantherOk) {
    echo 'P112Q3B2_SECURE_LIFE_RECIPE_FAILED: PANTHER_NOT_OK' . PHP_EOL;
    echo 'Report HTML: ' . $htmlReport . PHP_EOL;
    echo 'Report JSON: ' . $jsonReport . PHP_EOL;
    exit(1);
}

echo 'P112Q3B2_SECURE_LIFE_RECIPE_OK' . PHP_EOL;
echo 'Report HTML: ' . $htmlReport . PHP_EOL;
echo 'Report MD: ' . $markdownReport . PHP_EOL;
echo 'Email HTML: ' . $emailHtmlReport . PHP_EOL;
echo 'Report JSON: ' . $jsonReport . PHP_EOL;
echo 'Mail: ' . (string) $mailStatus['status'] . ' / ' . (string) $mailStatus['reason'] . PHP_EOL;
echo 'Panther: ' . (string) ($pantherStatus['status'] ?? 'UNKNOWN') . ' / ' . (string) ($pantherStatus['reason'] ?? 'UNKNOWN') . PHP_EOL;
