<?php

declare(strict_types=1);

require_once __DIR__ . '/../../framework/ASAP/Exception.php';
require_once __DIR__ . '/../../framework/ASAP/Contract/ContractException.php';
require_once __DIR__ . '/../../framework/ASAP/Configuration.php';
require_once __DIR__ . '/../../framework/ASAP/ConfigLoader.php';
require_once __DIR__ . '/../../framework/ASAP/Support.php';
require_once __DIR__ . '/../../framework/ASAP/SimpleXMLElementExtended.php';
require_once __DIR__ . '/../../framework/ASAP/SimpleXMLElementExtended.class.php';
require_once __DIR__ . '/../../framework/ASAP/Singleton.php';
require_once __DIR__ . '/../../framework/ASAP/Singleton.class.php';
require_once __DIR__ . '/../../framework/ASAP/Validator.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/TranslationException.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/LocaleCode.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/PluralRuleInterface.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/Plural/EnglishPluralRule.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/Plural/FrenchPluralRule.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/Plural/RussianPluralRule.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/Plural/SpanishPluralRule.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/TranslationCatalog.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/JsonTranslationCatalogLoader.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/Translator.php';
require_once __DIR__ . '/../../framework/ASAP/I18N/I18n.php';
require_once __DIR__ . '/../../framework/ASAP/Http/Response.php';
require_once __DIR__ . '/../../framework/ASAP/Response.php';
require_once __DIR__ . '/../../framework/ASAP/URL/Url.php';
require_once __DIR__ . '/../../framework/ASAP/Package.php';
require_once __DIR__ . '/../../framework/ASAP/PackageRepository.php';
require_once __DIR__ . '/../../framework/ASAP/Kernel.php';
require_once __DIR__ . '/../../framework/ASAP/Bootstrap.php';

use ASAP\Bootstrap;
use ASAP\ConfigLoader;
use ASAP\I18N\I18n;
use ASAP\Kernel;
use ASAP\Package;
use ASAP\PackageRepository;
use ASAP\Response;
use ASAP\SimpleXMLElementExtended;
use ASAP\Singleton;
use ASAP\Support;
use ASAP\Url\Url;
use ASAP\Validator;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('ASSERT_FAILED: ' . $message);
    }
}

final class P112OSmokeSingleton extends Singleton
{
}

assertTrue(Support::e('<x>') === '&lt;x&gt;', 'Support::e');
assertTrue(Support::normalizePath('a/b/../c') === 'a/c', 'Support::normalizePath');
assertTrue(Support::startsWith('abcdef', 'abc'), 'Support::startsWith');
assertTrue(Support::trimSlashes('/abc/') === 'abc', 'Support::trimSlashes');

$xml = simplexml_load_string('<root id="7"><child /></root>', SimpleXMLElementExtended::class);
assertTrue($xml instanceof SimpleXMLElementExtended, 'SimpleXMLElementExtended instance');
assertTrue($xml->getAttribute('id') === '7', 'SimpleXMLElementExtended getAttribute');
assertTrue($xml->getAttributeCount() === 1, 'SimpleXMLElementExtended getAttributeCount');
assertTrue($xml->getChildrenCount() === 1, 'SimpleXMLElementExtended getChildrenCount');

assertTrue(P112OSmokeSingleton::getInstance() === P112OSmokeSingleton::getInstance(), 'Singleton getInstance');

assertTrue(Validator::isEmail('demo@example.test'), 'Validator isEmail');
assertTrue(Validator::isInt('-12'), 'Validator isInt');
assertTrue(Validator::isUnsignedInt('12'), 'Validator isUnsignedInt');
assertTrue(Validator::isEan13('4006381333931'), 'Validator isEan13');
assertTrue(Validator::isAbsoluteUrl('https://example.test/a'), 'Validator isAbsoluteUrl');
assertTrue(Validator::isCleanHtml('<p>ok</p>'), 'Validator isCleanHtml');
assertTrue(!Validator::isCleanHtml('<script>x</script>'), 'Validator blocks script');

$configFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p112o_asap_config.php';
file_put_contents($configFile, '<?php return ["name" => "asap", "enabled" => true];');
$config = (new ConfigLoader($configFile))->getConfig();
assertTrue($config->get('name') === 'asap', 'ConfigLoader getConfig');
@unlink($configFile);

$i18n = new I18n(__DIR__ . '/../../resources/i18n', 'fr');
assertTrue($i18n->t('asap.title') === 'ASAP Framework', 'I18N t');
assertTrue(isset($i18n->dictionary()['messages']['asap.title']), 'I18N dictionary');
assertTrue(in_array('fr', $i18n->getAvalaibleLanguages(), true), 'I18N getAvalaibleLanguages');
assertTrue(I18n::getInstance(__DIR__ . '/../../resources/i18n', 'fr')->translate('asap.title') === 'ASAP Framework', 'I18N getInstance');

$html = Response::html('<h1>OK</h1>');
assertTrue($html->body === '<h1>OK</h1>', 'Response html body');
$json = Response::json(['ok' => true]);
assertTrue($json->body === '{"ok":true}', 'Response json body');

$url = new Url('https://example.test/path?x=1#top');
assertTrue($url->getProtocol() === 'https', 'Url getProtocol');
assertTrue($url->getHost() === 'example.test', 'Url getHost');
assertTrue($url->getPath() === '/path', 'Url getPath');
assertTrue($url->getArguments()['x'] === '1', 'Url getArguments');
assertTrue($url->getAnchor() === 'top', 'Url getAnchor');
assertTrue((string) $url->setPath('/next')->setArguments(['q' => 'a b'])->setAnchor('anchor') === 'https://example.test/next?q=a%20b#anchor', 'Url __toString setters');

$package = new Package('core', __DIR__, ['fr', 'en'], ['home' => '/'], ['title' => 'Core']);
$repo = new PackageRepository([$package]);
$kernel = new Kernel(__DIR__, $repo);
assertTrue($repo->get('core') === $package, 'PackageRepository get');
assertTrue($repo->resolve('core')->hasLanguage('fr'), 'PackageRepository resolve');
assertTrue($kernel->getPackage('core') === $package, 'Kernel getPackage');
assertTrue($kernel->pageUrl('/home') === '/home', 'Kernel pageUrl');
assertTrue($kernel->apiUrl('status') === '/api/status', 'Kernel apiUrl');
assertTrue($kernel->assetUrl('css/app.css') === '/assets/css/app.css', 'Kernel assetUrl');
assertTrue($kernel->packageUrl('core', 'asset.js') === '/packages/core/asset.js', 'Kernel packageUrl');
assertTrue($kernel->handle(static fn (Kernel $k): string => $k->rootDir()) === str_replace('\\', '/', __DIR__), 'Kernel handle');

$bootstrap = new Bootstrap();
assertTrue($bootstrap->run(static fn (Bootstrap $bootstrap): string => 'BOOT_OK') === 'BOOT_OK', 'Bootstrap run callable');

echo 'P112O SAFE compat shims smoke OK' . PHP_EOL;
echo 'P112O MEDIUM foundation shims smoke OK' . PHP_EOL;
echo 'P112O ASAP compatibility smoke OK' . PHP_EOL;
