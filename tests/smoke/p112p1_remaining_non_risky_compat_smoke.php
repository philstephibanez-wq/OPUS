<?php

declare(strict_types=1);

require_once __DIR__ . '/../../framework/Opus/Exception/Exception.php';
require_once __DIR__ . '/../../framework/Opus/Contract/ContractException.php';
require_once __DIR__ . '/../../framework/Opus/Config/Configuration.php';
require_once __DIR__ . '/../../framework/Opus/Debug/Debug.php';
require_once __DIR__ . '/../../framework/Opus/Validation/Validator.php';
require_once __DIR__ . '/../../framework/Opus/Acl/Acl.php';
require_once __DIR__ . '/../../framework/Opus/Fsm/Fsm.php';
require_once __DIR__ . '/../../framework/Opus/Template/TemplateException.php';
require_once __DIR__ . '/../../framework/Opus/Template/Adapter.php';
require_once __DIR__ . '/../../framework/Opus/Template/Smarty.php';
require_once __DIR__ . '/../../framework/Opus/Template/X64.php';
require_once __DIR__ . '/../../framework/Opus/Template/TemplateRendererInterface.php';
require_once __DIR__ . '/../../framework/Opus/View/View.php';
require_once __DIR__ . '/../../framework/Opus/Link/Link.php';

use ASAP\Acl\Acl;
use ASAP\Config\Configuration;
use ASAP\Debug\Debug;
use ASAP\Fsm\Fsm;
use ASAP\Link\Link;
use ASAP\Template\Smarty;
use ASAP\Template\TemplateException;
use ASAP\Template\X64;
use ASAP\Validation\Validator;
use ASAP\View\View;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('ASSERT_FAILED: ' . $message);
    }
}

$config = new Configuration([
    'database' => ['driver' => 'sqlite'],
    'routes' => ['home' => '/'],
    'env' => 'dev',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/148.0.0.0 Safari/537.36',
]);

assertTrue($config->getDatabase()['driver'] === 'sqlite', 'Configuration getDatabase');
assertTrue($config->getRoutes()['home'] === '/', 'Configuration getRoutes');
assertTrue($config->getEnv() === 'dev', 'Configuration getEnv');
$config->setEnv('prod');
assertTrue($config->getEnv() === 'prod', 'Configuration setEnv');
assertTrue($config->get_browser() === 'chrome', 'Configuration get_browser');
assertTrue($config->get_os() === 'windows', 'Configuration get_os');

Debug::setDebug(true);
Debug::add('alpha');
Debug::addClasses(['One', 'Two']);
Debug::addDump(['ok' => true]);
assertTrue(count(Debug::get()) >= 3, 'Debug entries');
assertTrue(str_contains(Debug::dump(['x' => 1]), '[x]'), 'Debug dump');

$validator = new Validator();
assertTrue($validator instanceof Validator, 'Validator constructor');
assertTrue(Validator::isPasswd('abcde'), 'Validator isPasswd');
assertTrue(!Validator::isPasswd("abc\n"), 'Validator isPasswd rejects newline');

$smarty = new Smarty();
$smarty->assign('name', 'ASAP');
$smarty->assignAll(['version' => 'P112P1']);

try {
    $smarty->parse('missing.tpl');
    throw new RuntimeException('ASSERT_FAILED: Smarty parse should fail explicitly without runtime');
} catch (TemplateException $exception) {
    assertTrue(str_contains($exception->getMessage(), 'OPUS_TEMPLATE_SMARTY_RUNTIME_NOT_CONFIGURED'), 'Smarty explicit failure');
}

$x64 = new X64();
$x64->assign('name', 'ASAP');

try {
    $x64->loadTemplate('missing.tpl');
    throw new RuntimeException('ASSERT_FAILED: X64 loadTemplate should fail explicitly without runtime');
} catch (TemplateException $exception) {
    assertTrue(str_contains($exception->getMessage(), 'OPUS_TEMPLATE_X64_RUNTIME_NOT_CONFIGURED'), 'X64 explicit failure');
}

$acl = new Acl();
assertTrue(!$acl->canView(), 'Acl canView default false');
assertTrue($acl->canView(true), 'Acl canView explicit true');

$flow = Fsm::demoFlow();
assertTrue($flow['initial'] === 'START', 'Fsm demoFlow');

$view = new View('demo.twig', ['name' => 'ASAP']);
assertTrue($view->render(static fn (string $template, array $data): string => $template . ':' . $data['name']) === 'demo.twig:ASAP', 'View render callable');

$link = new Link('Home', '/home', 'main', 'default');
$link->changeClass('btn')->changeId('home-link');
assertTrue($link->getBlock() === 'main', 'Link getBlock');
assertTrue($link->getMode() === 'default', 'Link getMode');
assertTrue((string) $link === '<a href="/home" id="home-link" class="btn">Home</a>', 'Link __toString');

echo 'P112P1_REMAINING_NON_RISKY_COMPAT_SMOKE_OK' . PHP_EOL;
