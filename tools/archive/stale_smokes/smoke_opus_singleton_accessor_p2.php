<?php
/**
 * P2 OPUS singleton/accessor smoke.
 *
 * Read-only smoke for the committed OPUS singleton/accessor contract.
 */

$root = dirname(__DIR__, 2);
$failures = array();

function p2_check($condition, $label, $detail = '') {
    global $failures;
    if ($condition) {
        echo $label . "=OK\n";
        return;
    }
    echo $label . "=FAIL" . ($detail !== '' ? ' ' . $detail : '') . "\n";
    $failures[] = array($label, $detail);
}

p2_check(is_dir($root . '/Opus'), 'CHECK_OPUS_ROOT');
p2_check(is_file($root . '/Opus/AccessorInterface.class.php'), 'CHECK_ACCESSOR_INTERFACE_FILE');
p2_check(is_file($root . '/Opus/Singleton.class.php'), 'CHECK_SINGLETON_FILE');

require_once $root . '/Opus/Exception.class.php';
require_once $root . '/Opus/AccessorInterface.class.php';
require_once $root . '/Opus/Singleton.class.php';

p2_check(interface_exists('OPUS_AccessorInterface'), 'CHECK_ACCESSOR_INTERFACE_EXISTS');
p2_check(class_exists('OPUS_Singleton'), 'CHECK_SINGLETON_CLASS_EXISTS');
p2_check(is_subclass_of('OPUS_Singleton', 'OPUS_AccessorInterface'), 'CHECK_SINGLETON_IMPLEMENTS_ACCESSOR');

class OPUS_P2_SmokeSingleton extends OPUS_Singleton {
    protected $_name = '';
    protected $_count = 0;
    protected $_initializedScope = '';

    protected function initSingleton(): void {
        $this->_initializedScope = $this->getScope();
    }
}

$defaultA = OPUS_P2_SmokeSingleton::getInstance();
$defaultB = OPUS_P2_SmokeSingleton::getInstance();
$siteA1 = OPUS_P2_SmokeSingleton::getInstanceForSite('alpha');
$siteA2 = OPUS_P2_SmokeSingleton::getInstanceForSite('alpha');
$siteB = OPUS_P2_SmokeSingleton::getInstanceForSite('beta');
$appA = OPUS_P2_SmokeSingleton::getInstanceForApplication('studio');

p2_check($defaultA === $defaultB, 'CHECK_DEFAULT_SINGLETON_SAME_INSTANCE');
p2_check($siteA1 === $siteA2, 'CHECK_SITE_SINGLETON_SAME_SCOPE');
p2_check($siteA1 !== $siteB, 'CHECK_SITE_SINGLETON_DIFFERENT_SCOPE');
p2_check($defaultA !== $siteA1, 'CHECK_DEFAULT_AND_SITE_DIFFERENT_SCOPE');
p2_check($appA !== $siteA1, 'CHECK_APPLICATION_AND_SITE_DIFFERENT_SCOPE');

p2_check($defaultA->getScope() === 'default', 'CHECK_DEFAULT_SCOPE');
p2_check($siteA1->getScope() === 'site:alpha', 'CHECK_SITE_SCOPE');
p2_check($appA->getScope() === 'application:studio', 'CHECK_APPLICATION_SCOPE');
p2_check($siteA1->getInitializedScope() === 'site:alpha', 'CHECK_INIT_AFTER_SCOPE_SET');

$siteA1->setName('Alpha');
$siteA1->setCount(7);
p2_check($siteA1->getName() === 'Alpha', 'CHECK_AUTO_GET_SET_STRING');
p2_check($siteA1->getCount() === 7, 'CHECK_AUTO_GET_SET_INTEGER');
p2_check($siteA1->hasName() === true, 'CHECK_AUTO_HAS_METHOD');
p2_check($siteA1->has('name') === true, 'CHECK_HAS_PUBLIC_NAME_TO_PROTECTED_SLOT');
p2_check($siteA1->has('_name') === true, 'CHECK_HAS_PROTECTED_SLOT');
p2_check($siteA1->has('missing') === false, 'CHECK_HAS_MISSING_FALSE');

$missingFailed = false;
try {
    $siteA1->getMissing();
} catch (Throwable $e) {
    $missingFailed = true;
}
p2_check($missingFailed, 'CHECK_MISSING_PROPERTY_FAILS_EXPLICITLY');

$ref = new ReflectionClass('OPUS_P2_SmokeSingleton');
p2_check($ref->hasProperty('_name'), 'CHECK_TEST_PROTECTED_PROPERTY_EXISTS');
p2_check(!$ref->hasProperty('name'), 'CHECK_NO_PUBLIC_SHADOW_PROPERTY');

$singletonSource = file_get_contents($root . '/Opus/Singleton.class.php');
p2_check(strpos($singletonSource, 'OPUS_CONTROLLER_Controller') === false, 'CHECK_NO_OLD_CONTROLLER_CLASS_REFERENCE');
p2_check(strpos($singletonSource, 'OPUS_AccessorInterface') !== false, 'CHECK_SINGLETON_REFERENCES_INTERFACE');
p2_check(strpos($singletonSource, 'getInstanceForSite') !== false, 'CHECK_SINGLETON_SITE_SCOPE_API');
p2_check(strpos($singletonSource, 'getInstanceForApplication') !== false, 'CHECK_SINGLETON_APPLICATION_SCOPE_API');

if ($failures) {
    echo "P2_OPUS_SINGLETON_ACCESSOR_SMOKE_FAIL\n";
    foreach ($failures as $failure) {
        echo ' - ' . $failure[0] . ': ' . $failure[1] . "\n";
    }
    exit(1);
}

echo "P2_OPUS_SINGLETON_ACCESSOR_SMOKE_OK\n";
exit(0);
