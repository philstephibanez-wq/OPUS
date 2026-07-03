<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_session_start_once();

$error = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/opus-lstsar-manager');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (p7ops_sign_in($username, $password)) {
        header('Location: ' . ($next !== '' ? $next : '/opus-lstsar-manager'), true, 302);
        exit;
    }

    $error = 'Sign in refused. Check credentials and environment configuration.';
}

$body = '<section class="ops-panel ops-auth-panel"><h2>Controlled sign-in</h2>';
$body .= '<p>Environment: <strong>' . p7ops_h(p7ops_environment()) . '</strong>. Dev default user is <code>admin</code>; production must provide a real password hash in <code>config/environment.php</code>.</p>';
if ($error !== '') {
    $body .= '<p class="ops-error">' . p7ops_h($error) . '</p>';
}
$body .= '<form method="post" class="ops-form" action="/opus-lstsar-manager/login">';
$body .= '<input type="hidden" name="next" value="' . p7ops_h($next) . '">';
$body .= '<label>Username <input name="username" autocomplete="username" required></label>';
$body .= '<label>Password <input name="password" type="password" autocomplete="current-password" required></label>';
$body .= '<button class="ops-action-button" type="submit">Sign in</button>';
$body .= '</form></section>';

p7ops_render_shell('OPUS OPS Sign in', $body);