<?php

/**
 * OPUS singleton and automatic accessor base.
 *
 * Contract:
 * - one singleton instance per concrete class and per scope;
 * - default scope keeps the historical getInstance() behavior;
 * - site/application scopes are explicit;
 * - protected properties stay protected;
 * - callers use getXxx()/setXxx()/hasXxx() or get()/set()/has();
 * - missing properties fail explicitly.
 */
#[AllowDynamicProperties]
/**
 * OPUS singleton base helper.
 *
 * Provides shared singleton access behavior used by OPUS classes during dependency-injection migration.
 */
abstract class OPUS_Singleton implements OPUS_AccessorInterface {
    /** @var array<string,array<string,object>> */
    protected static $_instances = array();

    /**
     * OPUS default pointer, kept for old code that inspects $_instance in
     * subclasses or expects getInstance() to behave like the historical singleton
     * singleton.
     */
    protected static $_instance = null;

    protected $_scope = 'default';

    /**
     * Reserved for classes that expose controller through accessors.
     * It is no longer auto-filled here; controller coupling must be explicit.
     */
    protected $_controller = null;

    /** Prevent direct object creation. */
    protected function __construct() {}

    /** Prevent object cloning. */
    private function __clone() {}

    /**
     * Hook for subclasses that need initialization after the scope has been set.
     */
    protected function initSingleton(): void {}

    /**
     * Returns the singleton for the concrete class and scope.
     *
     * @param string $scope default, site:<id>, application:<id>, or custom scope.
     * @return static
     */
    final public static function getInstance($scope = 'default') {
        $class = static::class;
        $scope = self::normalizeScope($scope);

        if (!isset(self::$_instances[$class])) {
            self::$_instances[$class] = array();
        }

        if (!isset(self::$_instances[$class][$scope])) {
            $instance = new static();
            $instance->_scope = $scope;
            $instance->initSingleton();
            self::$_instances[$class][$scope] = $instance;
        }

        if ($scope === 'default') {
            static::$_instance = self::$_instances[$class][$scope];
        }

        return self::$_instances[$class][$scope];
    }

    /** @return static */
    final public static function getInstanceForSite($siteId) {
        return static::getInstance('site:' . self::normalizeScope($siteId));
    }

    /** @return static */
    final public static function getInstanceForApplication($applicationId) {
        return static::getInstance('application:' . self::normalizeScope($applicationId));
    }

    final public function getScope(): string {
        return (string)$this->_scope;
    }

    final public function __call($methodName, $args) {
        if (!preg_match('~^(set|get|has)([A-Z])(.*)$~', $methodName, $matches)) {
            throw new OPUS_Exception('Method ' . $methodName . ' not exists');
        }

        $property = strtolower($matches[2]) . $matches[3];
        switch ($matches[1]) {
            case 'set':
                $this->checkArguments($args, 1, 1, $methodName);
                return $this->set($property, $args[0]);
            case 'get':
                $this->checkArguments($args, 0, 0, $methodName);
                return $this->get($property);
            case 'has':
                $this->checkArguments($args, 0, 0, $methodName);
                return $this->has($property);
        }

        throw new OPUS_Exception('Method ' . $methodName . ' not exists');
    }

    final public function get($property) {
        $property = $this->resolveAccessorProperty($property);
        return $this->$property;
    }

    final public function set($property, $value) {
        $property = $this->resolveAccessorProperty($property);
        $this->$property = $value;
        return $this;
    }

    final public function has($property): bool {
        return $this->resolveAccessorPropertyOrNull($property) !== null;
    }

    final protected function checkArguments(array $args, $min, $max, $methodName): void {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new OPUS_Exception('Method ' . $methodName . ' needs minimally ' . $min . ' and maximally ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

    final protected function resolveAccessorProperty($property): string {
        $resolved = $this->resolveAccessorPropertyOrNull($property);
        if ($resolved === null) {
            throw new OPUS_Exception('Property ' . $property . ' not exists');
        }
        return $resolved;
    }

    final protected function resolveAccessorPropertyOrNull($property): ?string {
        $property = (string)$property;
        if ($property === '') {
            return null;
        }

        if (property_exists($this, $property)) {
            return $property;
        }

        if ($property[0] !== '_') {
            $protected = '_' . $property;
            if (property_exists($this, $protected)) {
                return $protected;
            }
        }

        return null;
    }

    private static function normalizeScope($scope): string {
        $scope = trim((string)$scope);
        return $scope !== '' ? $scope : 'default';
    }
}

?>
