<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$config = p7ops_config();
$enabled = ($config['sso']['enabled'] ?? false) ? 'enabled' : 'disabled';
$body = '<section class="ops-panel"><h2>SSO</h2><p>SSO is optional and currently <strong>' . p7ops_h($enabled) . '</strong>.</p><p>No silent fallback: production must explicitly configure the SSO provider or local auth password hash.</p></section>';
p7ops_render_shell('OPUS OPS SSO', $body);