<?php

/**
 * Transitional boot entry backed by the generic configurable FSM program.
 *
 * This class contains no boot transition program. It only keeps the current
 * OPUS_Application call site executable until the application constructor is
 * migrated to OPUS_FSM_Program directly.
 */
#[AllowDynamicProperties]
class OPUS_FSM_Boot extends OPUS_FSM_Program {
    private string $_programFile;

    public function __construct(string $id) {
        if (!class_exists('OPUS_FSM_Program')) {
            require_once __DIR__ . '/Program.class.php';
        }

        $this->_programFile = self::_resolveProgramFile();
        $program = require $this->_programFile;
        if (!is_array($program)) {
            self::_failBoot('OPUS boot FSM program must return an array: ' . $this->_programFile);
        }

        parent::__construct($id, $program);
    }

    public function runBoot(): string {
        return $this->run();
    }

    public function getProgramFile(): string {
        return $this->_programFile;
    }

    private static function _resolveProgramFile(): string {
        $candidates = array();

        if (defined('ROOT')) {
            $candidates[] = rtrim((string)ROOT, '/\\') . '/application/config/fsm.boot.php';
        }

        $candidates[] = dirname(__DIR__, 2) . '/config/fsm.boot.php';

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        self::_failBoot('OPUS boot FSM program not found. Expected one of: ' . implode(' | ', $candidates));
    }

    private static function _failBoot(string $message): void {
        if (class_exists('OPUS_Exception')) {
            throw new OPUS_Exception($message);
        }
        throw new RuntimeException($message);
    }
}
