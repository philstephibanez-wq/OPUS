<?php

#[AllowDynamicProperties]
class Transition {

    public $signal;
    public $state;
    public $nextState;
    public $action;
    public $nextSignal; // just for graph not computed

    function __construct($signal, $state, $nextState, $action, $nextSignal='') {
        $this->signal = $signal; //== this key_signal
        $this->state = $state;  //== this key_state
        $this->nextState = $nextState;
        $this->action = $action;
    }

}

#[AllowDynamicProperties]
class OPUS_FSM_GraphViz {
    public static function export(&$fsm) {
        throw new OPUS_Exception('GraphViz export has been removed from OPUS PHP 8 demo. Use OPUS_FSM_Diagram instead.');
    }

    public static function render(&$fsm): string {
        throw new OPUS_Exception('GraphViz renderer has been removed from OPUS PHP 8 demo. Use OPUS_FSM_Fsm::draw() or OPUS_FSM_Diagram directly.');
    }
}


interface iFSM {
    function create();
}

define('PROGRAM_COLOR', 'blue');

#[AllowDynamicProperties]
class OPUS_FSM_Fsm Implements iFSM {

    protected $_id;
    protected $_currentState;
    protected $_presetStack;
    protected $_presetMemory;
    protected $_memory;
    protected $_stack;
    protected $_stack_type;
    protected $_timeout;
    protected $_initialState;
    protected $_finalState;
    protected $_dir;

    public function __construct($id, $initialState, $finalState='', $presetMemory=array(), $presetStack=array(), $loadProgram=false) {     
 //       $this->_debug = OPUS_Debug::getInstance();
        $this->_id = $id;
        $this->_currentState = $initialState;
        $this->_presetStack = $presetStack;
        $this->_presetMemory = $presetMemory;
        $this->_memory = $presetMemory;
        $this->_stack = $presetStack;
        $this->_stack_type = 'fifo';
        $this->_timeout = 0;
        $this->_dir = ROOT."/tmp"; //"../../../tmp";

        $this->_initialState = $initialState;
        $this->_finalState = $finalState;

        $f = @fopen($this->_dir . "/" . $id . ".fsm", "r+");
        if ($f) {
            fclose($f);
        }

//		if($loadProgram) {
//			$this->loadProgram();
//		} else {
        $this->create();    // create transitions
//		}
		$this->loadState();
    }
    
    public function create(){throw new Exception("Method: create MUST BE IMPLEMENTED into the FSM");} 
    	
    final public function __destruct() {
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " FSM DESTRUCTOR (save state)", __FILE__, __LINE__, PROGRAM_COLOR);
        $this->saveState();
    }

    final public function setDir($dir) {
        $this->_dir = $dir;
    }

    final static function default_proc(&$fsm, $signal) {
        echo ("<br/>FSM::default_proc: ERREUR, STATE: " . $fsm->getCurrentState() . " Signal inconnu: $signal\n");
    }

    final public function getId() {
        return $this->_id;
    }

    final public function getTimeout() {
        return $this->_timeout;
    }

    final public function setTimeout($timeout) {
        $this->_timeout = $timeout;
    }

//timestamp is in seconds

    // MEMORY
    final protected function _setMemory($memory) {
        $this->_memory = $memory;
    }

    final public function getMemory() {
        return $this->_memory;
    }

    final public function peek($name) {
//		OPUS_Debug::addDump(__CLASS__."::".__FUNCTION__." name. $name", $this->_memory[$name], __FILE__, __LINE__, PROGRAM_COLOR);		
        return $this->_memory[$name];
    }

    final public function poke($name, $value) {
        $this->_memory[$name] = $value;
//		OPUS_Debug::addDump(__CLASS__."::".__FUNCTION__." name. $name", $value, __FILE__, __LINE__, PROGRAM_COLOR);		
    }

    // STACK
    final public function clearStack() {
        $this->_stack = array();
    }

    final public function setStackType($type) {
        $this->_stack_type = $type;
    }

    final public function getStackType() {
        return $this->_stack_type;
    }

    final protected function _setStack($stack) {
        $this->_stack = $stack;
    }

    final public function getStack() {
        return $this->_stack;
    }

    final public function pop() {
        switch ($this->_stack_type) {
            case 'lifo':
                if (count($this->_stack) > 0) {
                    $lastIndex = count($this->_stack) - 1;
                    $value = $this->_stack[$lastIndex];
                    unset($this->_stack[$lastIndex]);
                    return $value;
                }
                break;
            case 'fifo':
            default:
                if (count($this->_stack) > 0) {
                    $value = array_shift($this->_stack);
                    return $value;
                }
        }
    }

    final public function push($value) {
        $this->_stack[] = $value;
        OPUS_Debug::addDump('VALUE:', $value, __CLASS__ . "::" . __FUNCTION__, __FILE__, __LINE__, PROGRAM_COLOR);
    }

    final protected function _setCurrentState($state) {
        $this->_currentState = $state;
    }

    final public function getCurrentState() {
        return $this->_currentState;
    }

    final public function reset() {
//		FSM_computer::clearState($this->_id, $this);
        $this->_currentState = $this->_initialState;
        $this->_memory = $this->_presetMemory;
        $this->_stack = $this->_presetStack;
    }

    final public function draw() {
        print OPUS_FSM_Diagram::renderRuntime(
            get_class($this),
            (string)$this->_initialState,
            (string)$this->_finalState,
            (string)$this->getCurrentState(),
            $this->getMemory(),
            $this->getTransitions()
        );
    }

    final protected function _execute($method, $signal) {
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . "::$method, signal: $signal", __FILE__, __LINE__, 'cyan');
        return $this->{$method}($signal);
    }

    final protected function _getTransition($signal) {
        $state = $this->getCurrentState();
        OPUS_Debug::add(__FUNCTION__ . " state: $state", __FILE__, __LINE__, 'cyan');
        if (isset($this->_transitions["$signal,$state"])) {
            return $this->_transitions["$signal,$state"];
        } elseif (isset($this->_transitions["__any__,$state"])) {
            return $this->_transitions["__any__,$state"];
        } else {
            return $this->_transitions['__default__'];
        }
    }

    final protected function process($signal) {
        $transition = $this->_getTransition($signal);
        $msg = __FUNCTION__ . " TRANSITION: [$signal," . $this->getCurrentState() . "] action: " . $transition->action;
        OPUS_Debug::add($msg, __FILE__, __LINE__, 'cyan');

        // Update the current state to this transition's exit state. 
//		$fsm->setCurrentSignal($signal);
        if ($transition->signal != '__default__') {
            $this->_setCurrentState($transition->nextState);
        }

        // If an action for this transition has been specified, execute it. 
        if ($transition->action != '')
            $receivedSignal = $this->_execute($transition->action, $signal);

        // If a new signal was returned process new signal, here the state can't change
        if (!is_null($receivedSignal)) {
//			OPUS_Debug::add(  __CLASS__."::".__FUNCTION__." CHAIN-------------> $receivedSignal", __FILE__, __LINE__, PROGRAM_COLOR);
            $this->process($receivedSignal);
        }
//		OPUS_Debug::add(  __CLASS__."::".__FUNCTION__." end", __FILE__, __LINE__, PROGRAM_COLOR);
    }

    final protected function attachEvent($event, $handler) {
        $this->_events[$event] = $handler;
    }

    final protected function fireEvent($event, $arguments) {
        $handler = $this->_events[$event];
        if (is_callable($this->$handler)) {
            return call_user_func_array($this->$handler, $arguments);
        }
    }

    final protected function setDefaultTransition($action) {
        $this->_transitions['__default__'] = new Transition('__default__', '', '', $action);
    }

    final protected function addTransition($signal, $state, $nextState, $action) {
        $this->_transitions["$signal,$state"] = new Transition($signal, $state, $nextState, $action);
    }

    // plusieurs signaux traiter par la m�me action (ressemble � any, mais avec des signaux pr�d�finis)
    final protected function addTransitions($signals, $state, $nextState, $action = null) {
        foreach ($signals as $signal) {
            $this->addTransition($signal, $state, $nextState, $action);
        }
    }

    final protected function getTransitions() {
        return $this->_transitions;
    }

    final public function saveState() {
//		$this->_lockExec('save');	
        $file = $this->_dir . "/" . $this->_id . ".fsm";
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " $file", __FILE__, __LINE__, PROGRAM_COLOR);

        $timeLimit = time() + $this->getTimeout();
        $dump = array(
            'lastUpdate' => time(),
            'timeout' => $this->getTimeout(),
            'timeLimit' => $timeLimit,
            'state' => $this->getCurrentState(),
            'memory' => $this->getMemory(),
            'stack' => $this->getStack()
        );
        OPUS_Debug::addDump(__CLASS__ . "::" . __FUNCTION__ . " data:", $dump, __FILE__, __LINE__, PROGRAM_COLOR);
        $s = serialize($dump);
        file_put_contents($file, $s);
    }

    final public function loadState() {
        $file = $this->_dir . "/" . $this->_id . ".fsm";
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . " $file", __FILE__, __LINE__, PROGRAM_COLOR);
        $t = time();
        if (is_file($file)) {
//			while( !$s ) {
            $s = file_get_contents($file);
//			   usleep(100);
//			   if(time() - $t > 1 ) throw new Exception("ERROR reading $file");;
//			}

            if (is_string($s)) {
                $dump = unserialize($s);

                $lastUpdate = $dump['lastUpdate'];
                $timeout = $dump['timeout'];
                $timeLimit = $dump['timeLimit'];

                if (time() > $timeLimit) {
                    $this->create(); // reset
                    $this->poke('timeLimit', $timeLimit);
                } else {
                    if ($dump['state'] === '') {
                        $this->create(); // reset
                    }
                    $this->_setCurrentState($dump['state']);
                    $this->_setMemory($dump['memory']);
                    $this->_setStack($dump['stack']);
                    $this->setTimeout($dump['timeout']);
                    $this->poke('timeLimit', $timeLimit);
                    return true;
                }
            } else
                return false;
        }
        /*
          else {
          //			OPUS_Debug::add( __CLASS__."::".__FUNCTION__." (no file) STATE = ".$this->getCurrentState(), __FILE__, __LINE__, PROGRAM_COLOR);
          $this->create(); // reset
          $this->poke('timeLimit', time() + $this->getTimeout(), false);
          }
         */
        return false;
    }

    final public function clearState() {
        $file = $this->_dir . "/" . $this->_id . ".fsm";
        @unlink($file);
        $this->reset();
    }

    final protected function x_loadState() {
        return $this->_lockExec('save');
    }

    final protected function _lockExec($cmd, $timeout=5) {
        // Tache � effectuer ou non ? 
        $lockFile = $this->_dir . "/" . $this->_id . ".sem";
        OPUS_Debug::add(__CLASS__ . "::" . __FUNCTION__ . "LOCKFILE: $lockFile", __FILE__, __LINE__, PROGRAM_COLOR);

        if (!file_exists($lockFile)) {
            // Fichier n'existe pas, pose du verrou mode bloquant. 
            if (!($fp_verrou = fopen($lockFile, "a"))) {
                // Verrou apparemment pos�. 
                // Il faut attendre que le fichier $verrou 
                // n'existe plus. 
                while (file_exists($lockFile)) {
                    usleep(2);
                }
            } else {
                // Pose du verrou. 
                if (!flock($fp_verrou, LOCK_EX)) {
                    // Verrou apparemment d�pos�, 
                    // Il faut attendre que le fichier verrou 
                    // n'existe plus. 
                    while (file_exists($lockFile)) {
                        usleep(2);
                    }
                } else {
                    // Le verrou est pos� correctement, 
                    // ex�cution de la t�che. 
                    // 

                    switch ($cmd) {
                        case 'save':
                            $this->_saveState();
                            break;
                        case 'load':
                            $result = $this->_loadState();
                    }

                    // Fin de t�che, d�verrouillage 
                    // du fichier verrou, 
                    // puis effacement du fichier verrou 
                    flock($fp_verrou, LOCK_UN);
                    fclose($fp_verrou);
                    // Effacement du fichier verrou. 
                    unlink($lockFile);

                    return $result;
                }
            }
        } else {
//Board::set($this, 'bugvador', 'ALERT: ', " timeout ", time(), 15 * 60);
            $expire = filectime($lockFile) + $timeout;
            if ($expire >= microtime(true)) {
                @unlink($lockFile);
                $this->_lockExec($cmd, $timeout);
            }
        }
    }

}

?>