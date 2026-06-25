<?php

define('CTRL_COLOR', 'lime');

/**
 * OPUS controller interface.
 *
 * Defines the controller contract used by OPUS controller implementations.
 */
interface OPUS_CONTROLLER_iController {
    function default_action();
}

#[AllowDynamicProperties]
/**
 * OPUS base controller.
 *
 * Provides the base controller surface used by OPUS runtime dispatch and application pages.
 */
class  OPUS_Controller implements OPUS_CONTROLLER_iController {
        public $response;
        private static $_instance = null;     // php5.3
        protected $_app = null;
	protected $_i18n = null;
	protected $_params;
	protected $_action = 'show';
	protected $_data = array();
        protected $_acl;


    public function default_action(){throw new Exception("Method: default_action MUST BE IMPLEMENTED into the index controler");}

    public function __construct($params=array(), $acl=false) {
      if(self::$_instance != null){
          throw new Exception("Only one instance of controller is allowed");
      }
        $this->_app = OPUS_Application::getInstance();
        $this->_acl = OPUS_Acl::getInstance();

        $this->_params = $params;
//echo "<br>CONTROLLER <font color='blue'><pre>PARAMS " . print_r($params, true) . "</pre></font>";

	$this->_i18n = OPUS_I18N_I18n::getInstance(null, $this);
	$id = 'c_'; //.$this->_app->getID();

        if(count($params)>0) foreach($params as $key => $value) {
            $call = "new".ucfirst($key);
//echo "<br>CONTROLLER <font color='blue'>$call($value)</font>";
            $key = $this->__call($call, array($value));
        }
        return self::$_instance = $this;
    }

   public static function getInstance (){
      return self::$_instance;
   }


   final public function __call($methodName, $args) {
        if (preg_match('~^(new|set|get)([A-Z])(.*)$~', $methodName, $matches)) {
            $function = $matches[1];
            $property = strtolower($matches[2]) . $matches[3];
            if (!property_exists($this, $property) and $function != 'new') {
                throw new Exception('Property ' . $property . ' not exists');
            }
            switch($function) {
	case 'new':
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property);
                case 'default':
                    throw new Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    final protected function get($property) {
        return $this->$property;
    }

    final protected function set($property, $value) {
        $this->$property = $value;
        return $this;
    }

    final protected function checkArguments(array $args, $min, $max, $methodName) {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

    public function getParams() { return $this->_params; }
    public function getParam($key) { return $this->_params[$key]; }

    public function getTemplateEngine($name) {
		$name = "OPUS_TEMPLATE_".ucfirst($name);
		$reflection = new ReflectionClass($name);
		return $reflection->newInstanceArgs(array($this));
	}


    public function run() {

        \Opus\Diagnostics\Diagnostics::dump(__CLASS__."::".__FUNCTION__." parameters", $this->_params,  __FILE__, __LINE__, CTRL_COLOR);
        if(!isset($this->_params['action'])) $this->_params['action'] = 'default';
        $this->_action = $this->_params['action']."_action";
        $action = $this->_action;

	$this->response = false;

	$this->init();
	$this->before_action();

        if (!method_exists($this, $action)) {
            throw new OPUS_Exception('Controller action not found: ' . static::class . '::' . $action);
        }
        $this->{$action}();

        //acj
	$this->after_action();

        return $this->response;


    }


    protected function init() {}
    protected function before_action() {}
    protected function after_action() {}


}  // class




?>