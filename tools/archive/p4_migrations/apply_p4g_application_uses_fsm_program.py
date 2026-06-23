from pathlib import Path

PATCH_ID = "P4G_APPLICATION_USES_FSM_PROGRAM"


def fail(message: str) -> None:
    print(message)
    raise SystemExit(1)


def main() -> None:
    root = Path(__file__).resolve().parents[2]
    app_path = root / "Opus" / "Application.class.php"
    config_path = root / "config" / "fsm.boot.php"

    if not app_path.is_file():
        fail(f"APPLICATION_FILE_NOT_FOUND={app_path}")
    if not config_path.is_file():
        fail(f"FSM_BOOT_PROGRAM_NOT_FOUND={config_path}")

    original = app_path.read_text(encoding="utf-8")
    updated = original

    legacy_boot_call = """        $this->_bootFsm = new OPUS_FSM_Boot('opus_boot_' . $siteKey);\n        $this->_bootFsm->runBoot();"""
    program_boot_call = """        $programFile = $this->_resolveBootFsmProgramFile();\n        $this->_bootFsm = OPUS_FSM_Program::fromFile('opus_boot_' . $siteKey, $programFile);\n        $this->_bootFsm->run();"""

    if legacy_boot_call in updated:
        updated = updated.replace(legacy_boot_call, program_boot_call, 1)
    elif program_boot_call not in updated:
        fail("APPLICATION_BOOT_CALL_TARGET_NOT_FOUND")

    helper = """\n    /**\n     * Resolve the configured boot FSM program file.\n     *\n     * Contract:\n     * - the Application never owns boot transition instructions;\n     * - transitions are loaded from configuration;\n     * - missing boot program is a hard error, never a silent fallback.\n     */\n    private function _resolveBootFsmProgramFile(): string {\n        $candidates = array();\n\n        if (defined('ROOT')) {\n            $candidates[] = rtrim((string)ROOT, '/\\\\') . '/application/config/fsm.boot.php';\n        }\n\n        $candidates[] = dirname(__DIR__) . '/config/fsm.boot.php';\n\n        foreach ($candidates as $candidate) {\n            if (is_file($candidate)) {\n                return $candidate;\n            }\n        }\n\n        $message = 'OPUS boot FSM program not found. Expected one of: ' . implode(' | ', $candidates);\n        if (class_exists('OPUS_Exception')) {\n            throw new OPUS_Exception($message);\n        }\n        throw new RuntimeException($message);\n    }\n"""

    if "private function _resolveBootFsmProgramFile()" not in updated:
        insert_after = """        $this->_bootFsm->run();\n    }\n\n    public function getBootFsm() {"""
        replacement = """        $this->_bootFsm->run();\n    }\n""" + helper + """\n    public function getBootFsm() {"""
        if insert_after not in updated:
            fail("APPLICATION_BOOT_HELPER_INSERT_TARGET_NOT_FOUND")
        updated = updated.replace(insert_after, replacement, 1)

    legacy_ready_guard = """if (!($this->_bootFsm instanceof OPUS_FSM_Boot) || !$this->_bootFsm->isReady()) {"""
    program_ready_guard = """if (!($this->_bootFsm instanceof OPUS_FSM_Program) || !$this->_bootFsm->isReady()) {"""
    if legacy_ready_guard in updated:
        updated = updated.replace(legacy_ready_guard, program_ready_guard, 1)
    elif program_ready_guard not in updated:
        fail("APPLICATION_READY_GUARD_TARGET_NOT_FOUND")

    if "OPUS_FSM_Boot" in updated:
        fail("APPLICATION_STILL_REFERENCES_OPUS_FSM_BOOT")

    if updated == original:
        print(f"NO_CHANGE_ALREADY_APPLIED={PATCH_ID}")
        return

    app_path.write_text(updated, encoding="utf-8", newline="\n")

    print(f"APPLICATION_PATCHED={app_path}")
    print(f"FSM_PROGRAM_CONFIG={config_path}")
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
