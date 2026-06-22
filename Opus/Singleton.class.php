<?php

#[AllowDynamicProperties]
abstract class OPUS_Singleton {
    protected static $_instance = NULL;     // php5.3
    protected $_controller = null;

    /**
     * Prevent direct object creation
     */
    private function  __construct() { 
        $this->_controller = OPUS_CONTROLLER_Controller::getInstance();					
    }

    /**
     * Prevent object cloning
     */
    private function  __clone() { }

    /**
     * Returns new or existing Singleton instance
     * @return Singleton
     */
    final public static function getInstance(){
     if(static::$_instance == null) {
         static::$_instance = new static;
      }
      return static::$_instance;
    }

   final public function __call($methodName, $args) {
        if (preg_match('~^(set|get)([A-Z])(.*)$~', $methodName, $matches)) {
            $property = strtolower($matches[2]) . $matches[3];
            if (!property_exists($this, $property)) {
                throw new Exception('Property ' . $property . ' not exists');
            }
            switch($matches[1]) {
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
       if($this->_controller) {
    	return $this->_controller->$property;
       } else {
    	return $this->$property;       	
       }
    }

    final protected function set($property, $value) {
      if($this->_controller) {
    	$this->_controller->$property = $value;
      } else {
    	$this->$property = $value;     	
      }
        return $this;
    }


    final protected function checkArguments(array $args, $min, $max, $methodName) {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

}

 

?>