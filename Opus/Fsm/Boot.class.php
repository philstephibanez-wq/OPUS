<?php

/**
 * OPUS boot finite state machine.
 *
 * Contract:
 * - The FSM is the runtime engine.
 * - Boot is a FSM program, not a hidden Kernel workflow.
 * - Transitions are declared as data and executed by the FSM.
 * - This class is a real FSM brick, not a wrapper.
 */
#[AllowDynamicProperties]
class OPUS_FSM_Boot extends OPUS_FSM_Fsm {

    public const STATE_BOOT_START = 'BOOT_START';
    public const STATE_BOOT_AUTOLOAD = 'BOOT_AUTOLOAD';
    public const STATE_BOOT_CONFIG = 'BOOT_CONFIG';
    public const STATE_BOOT_SITE_RESOLVE = 'BOOT_SITE_RESOLVE';
    public const STATE_BOOT_APPLICATION_SINGLETON = 'BOOT_APPLICATION_SINGLETON';
    public const STATE_BOOT_ROUTER_READY = 'BOOT_ROUTER_READY';
    public const STATE_BOOT_READY = 'BOOT_READY';

    public const SIGNAL_BOOT_AUTOLOAD = 'BOOT_AUTOLOAD';
    public const SIGNAL_BOOT_CONFIG = 'BOOT_CONFIG';
    public const SIGNAL_BOOT_SITE_RESOLVE = 'BOOT_SITE_RESOLVE';
    public const SIGNAL_BOOT_APPLICATION_SINGLETON = 'BOOT_APPLICATION_SINGLETON';
    public const SIGNAL_BOOT_ROUTER_READY = 'BOOT_ROUTER_READY';
    public const SIGNAL_BOOT_READY = 'BOOT_READY';

    /** @var array<int,array<string,string>> */
    protected $_program = array();

    /** @var array<int,string> */
    protected $_executedSteps = array();

    /**
     * @param string $id Stable FSM instance identifier.
     * @param array<int,array<string,string>> $program Optional boot transition program.
     * @param array<string,mixed> $presetMemory Optional initial memory.
     * @param array<int,mixed> $presetStack Optional initial stack.
     */
    public function __construct($id, $program = array(), $presetMemory = array(), $presetStack = array()) {
        $this->_program = is_array($program) && count($program) > 0 ? $program : self::defaultProgram();
        parent::__construct($id, self::STATE_BOOT_START, self::STATE_BOOT_READY, $presetMemory, $presetStack, false);
    }

    /** @return array<int,array<string,string>> */
    public static function defaultProgram() {
        return array(
            array(
                'signal' => self::SIGNAL_BOOT_AUTOLOAD,
                'state' => self::STATE_BOOT_START,
                'nextState' => self::STATE_BOOT_AUTOLOAD,
                'action' => 'onBootAutoload',
                'nextSignal' => self::SIGNAL_BOOT_CONFIG,
            ),
            array(
                'signal' => self::SIGNAL_BOOT_CONFIG,
                'state' => self::STATE_BOOT_AUTOLOAD,
                'nextState' => self::STATE_BOOT_CONFIG,
                'action' => 'onBootConfig',
                'nextSignal' => self::SIGNAL_BOOT_SITE_RESOLVE,
            ),
            array(
                'signal' => self::SIGNAL_BOOT_SITE_RESOLVE,
                'state' => self::STATE_BOOT_CONFIG,
                'nextState' => self::STATE_BOOT_SITE_RESOLVE,
                'action' => 'onBootSiteResolve',
                'nextSignal' => self::SIGNAL_BOOT_APPLICATION_SINGLETON,
            ),
            array(
                'signal' => self::SIGNAL_BOOT_APPLICATION_SINGLETON,
                'state' => self::STATE_BOOT_SITE_RESOLVE,
                'nextState' => self::STATE_BOOT_APPLICATION_SINGLETON,
                'action' => 'onBootApplicationSingleton',
                'nextSignal' => self::SIGNAL_BOOT_ROUTER_READY,
            ),
            array(
                'signal' => self::SIGNAL_BOOT_ROUTER_READY,
                'state' => self::STATE_BOOT_APPLICATION_SINGLETON,
                'nextState' => self::STATE_BOOT_ROUTER_READY,
                'action' => 'onBootRouterReady',
                'nextSignal' => self::SIGNAL_BOOT_READY,
            ),
            array(
                'signal' => self::SIGNAL_BOOT_READY,
                'state' => self::STATE_BOOT_ROUTER_READY,
                'nextState' => self::STATE_BOOT_READY,
                'action' => 'onBootReady',
                'nextSignal' => '',
            ),
        );
    }

    /**
     * Build the transition table from the configurable boot program.
     */
    public function create() {
        $this->setDefaultTransition('onInvalidBootTransition');
        foreach ($this->_program as $transition) {
            foreach (array('signal', 'state', 'nextState', 'action') as $required) {
                if (!isset($transition[$required]) || (string)$transition[$required] === '') {
                    throw new OPUS_Exception('Invalid boot FSM transition: missing ' . $required);
                }
            }
            $this->addTransition(
                (string)$transition['signal'],
                (string)$transition['state'],
                (string)$transition['nextState'],
                (string)$transition['action']
            );
        }
    }

    /**
     * Execute the boot program until BOOT_READY.
     */
    public function runBoot() {
        if ($this->getCurrentState() === self::STATE_BOOT_READY) {
            return true;
        }
        if ($this->getCurrentState() !== self::STATE_BOOT_START) {
            throw new OPUS_Exception('Boot FSM cannot start from state: ' . $this->getCurrentState());
        }
        $this->process(self::SIGNAL_BOOT_AUTOLOAD);
        return $this->isReady();
    }

    public function isReady() {
        return $this->getCurrentState() === self::STATE_BOOT_READY;
    }

    /** @return array<int,array<string,string>> */
    public function getProgram() {
        return $this->_program;
    }

    /** @return array<int,string> */
    public function getExecutedSteps() {
        return $this->_executedSteps;
    }

    protected function onBootAutoload($signal) {
        $this->markStep($signal);
        return self::SIGNAL_BOOT_CONFIG;
    }

    protected function onBootConfig($signal) {
        $this->markStep($signal);
        return self::SIGNAL_BOOT_SITE_RESOLVE;
    }

    protected function onBootSiteResolve($signal) {
        $this->markStep($signal);
        return self::SIGNAL_BOOT_APPLICATION_SINGLETON;
    }

    protected function onBootApplicationSingleton($signal) {
        $this->markStep($signal);
        return self::SIGNAL_BOOT_ROUTER_READY;
    }

    protected function onBootRouterReady($signal) {
        $this->markStep($signal);
        return self::SIGNAL_BOOT_READY;
    }

    protected function onBootReady($signal) {
        $this->markStep($signal);
        $this->poke('boot_ready', true);
        return null;
    }

    protected function onInvalidBootTransition($signal) {
        throw new OPUS_Exception('Invalid boot FSM transition from state ' . $this->getCurrentState() . ' with signal ' . $signal);
    }

    protected function markStep($signal) {
        $this->_executedSteps[] = (string)$signal;
        $this->poke('last_boot_signal', (string)$signal);
        $this->poke('boot_state', $this->getCurrentState());
    }
}

?>
