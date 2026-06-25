<?php
//autogetter/setter pour les variables protected ou public
define('MODEL_COLOR', 'green');

#[AllowDynamicProperties]
/**
 * OPUS base model.
 *
 * Provides the base data model surface used by OPUS pages.
 */
class OPUS_MODEL_Model {

    protected $_app = null;
    protected $_controller = null;
    protected $_id = null;
    protected $_bdd = null;
    protected $_dbId = null;
    protected $_tables = array();

    public function __construct($dbCId = false, $tables = array()) {
        $this->_app = OPUS_Application::getInstance();
        $this->_controller = OPUS_Controller::getInstance();
        $this->init();
        $this->_dbId = $dbCId;
        if (!is_array($tables)) {
            $this->_tables = array(0 => $tables);
        } else {
            $this->_tables = $tables;
        }

        if ($this->_dbId)
            $this->dbConnect();
    }

    protected function init() { }

    protected function dbConnect() {
        $dbConf = $this->_app->config->getDatabase($this->_dbId);
        \Opus\Diagnostics\Diagnostics::dump(__CLASS__ . "::" . __FUNCTION__ . " INIT", $dbConf, __FILE__, __LINE__, TODO);

        $adapter = $dbConf['adapter'] ?? '';
        if ($adapter === 'mysql') {
            $adapter = 'mysqli';
        }

        foreach (array('server', 'username', 'schema') as $requiredKey) {
            if (!isset($dbConf[$requiredKey]) || $dbConf[$requiredKey] === '') {
                throw new OPUS_Exception('Database configuration "' . $this->_dbId . '" is incomplete: missing ' . $requiredKey . '. Set OPUS_DB_* environment variables or disable DB usage for this model.');
            }
        }

        $this->_bdd = OPUS_adodb5::newConnection($adapter);
        $result = $this->_bdd->Connect($dbConf['server'], $dbConf['username'], $dbConf['password'] ?? '', $dbConf['schema']);
        if($result==false || $result==null) throw new OPUS_Exception($this->_bdd->ErrorMsg());
    }

    final public function __call($methodName, $args) {
        if (preg_match('~^(new|set|get)([A-Z])(.*)$~', $methodName, $matches)) {
            $function = $matches[1];
            $property = strtolower($matches[2]) . $matches[3];
            if ($this->_controller) {
                if (!property_exists($this->_controller, $property) and $function != 'new') {
                    throw new OPUS_Exception('Property ' . $property . ' not exists');
                }
            } else {
                if (!property_exists($this, $property) and $function != 'new') {
                    throw new OPUS_Exception('Property ' . $property . ' not exists');
                }
            }
            switch ($function) {
                case 'new':
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property);
                case 'default':
                    throw new OPUS_Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    final protected function get($property) {
        if ($this->_controller) {
            return $this->_controller->$property;
        } else {
            return $this->$property;
        }
    }

    final protected function set($property, $value) {
        if ($this->_controller) {
            $this->_controller->$property = $value;
        } else {
            $this->$property = $value;
        }
        return $this;
    }

    final protected function checkArguments(array $args, $min, $max, $methodName) {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new OPUS_Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

}

?>