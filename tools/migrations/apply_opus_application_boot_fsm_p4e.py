#!/usr/bin/env python3
"""
P4E - Wire OPUS_Application to the real OPUS_FSM_Boot brick.

This is a targeted migration:
- no wrapper creation
- no Kernel shortcut
- OPUS_Application owns the boot FSM
- BOOT must reach BOOT_READY before dispatch
"""
from __future__ import annotations

import argparse
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "Opus" / "Application.class.php"
BOOT = ROOT / "Opus" / "Fsm" / "Boot.class.php"

BOOT_FIELD = "    private $_bootFsm = null;\n"
BOOT_CALL = "        $this->_initBootFsm();\n"
DISPATCH_GUARD = """        if (!($this->_bootFsm instanceof OPUS_FSM_Boot) || !$this->_bootFsm->isReady()) {\n            throw new OPUS_Exception('OPUS boot FSM did not reach BOOT_READY. Runtime dispatch is forbidden.');\n        }\n        $this->dispatch();\n"""
OLD_DISPATCH_CALL = "        $this->dispatch();\n"
BOOT_METHOD = r'''
    /**
     * Initialize and execute the mandatory boot FSM.
     *
     * Contract:
     * - no FSM, no engine
     * - boot is a FSM program
     * - Application owns the runtime boot FSM
     */
    private function _initBootFsm(): void {
        $siteKey = PHP_SAPI === 'cli' ? 'cli' : $this->_detectRequestHost();
        $siteKey = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string)$siteKey);
        if ($siteKey === '') {
            $siteKey = 'default';
        }

        $this->_bootFsm = new OPUS_FSM_Boot('opus_boot_' . $siteKey);
        $this->_bootFsm->runBoot();
    }

    public function getBootFsm() {
        return $this->_bootFsm;
    }
'''


def git_status_porcelain() -> str:
    result = subprocess.run(
        ["git", "status", "--porcelain"],
        cwd=ROOT,
        check=True,
        text=True,
        capture_output=True,
    )
    return result.stdout.strip()


def ensure_clean() -> None:
    status = git_status_porcelain()
    if status:
        print("P4E_REFUSE_DIRTY_TREE")
        print(status)
        raise SystemExit(1)


def patch_application(content: str) -> tuple[str, list[str]]:
    actions: list[str] = []

    if BOOT_FIELD not in content:
        needle = "    private $_sites = array();\n"
        if needle not in content:
            raise RuntimeError("Application sites field anchor not found")
        content = content.replace(needle, needle + BOOT_FIELD, 1)
        actions.append("ADD_BOOT_FSM_FIELD")

    if BOOT_CALL not in content:
        needle = "        OPUS_Application::$_instance = $this;\n"
        if needle not in content:
            raise RuntimeError("Application singleton anchor not found")
        content = content.replace(needle, needle + BOOT_CALL, 1)
        actions.append("ADD_BOOT_FSM_INIT_CALL")

    if "private function _initBootFsm(): void" not in content:
        needle = "    private function _routeDebugLog(string $event, $payload = null): void {\n"
        if needle not in content:
            raise RuntimeError("Application method insertion anchor not found")
        content = content.replace(needle, BOOT_METHOD + "\n" + needle, 1)
        actions.append("ADD_BOOT_FSM_METHODS")

    if "OPUS boot FSM did not reach BOOT_READY" not in content:
        occurrence_count = content.count(OLD_DISPATCH_CALL)
        if occurrence_count < 1:
            raise RuntimeError("Application dispatch call anchor not found")
        # Replace only the constructor dispatch call: currently the first direct dispatch call.
        content = content.replace(OLD_DISPATCH_CALL, DISPATCH_GUARD, 1)
        actions.append("GUARD_DISPATCH_WITH_BOOT_READY")

    return content, actions


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true")
    args = parser.parse_args()

    print("P4E_APPLICATION_BOOT_FSM_PATCH")
    print("MODE=" + ("WRITE" if args.write else "READ_ONLY"))

    if not APP.is_file():
        print("ERROR Application.class.php missing")
        return 1
    if not BOOT.is_file():
        print("ERROR Boot.class.php missing")
        return 1

    ensure_clean()

    original = APP.read_text(encoding="utf-8")
    patched, actions = patch_application(original)

    if not actions:
        print("NO_CHANGE Application already wired to OPUS_FSM_Boot")
    else:
        for action in actions:
            print(action)

    if args.write and patched != original:
        APP.write_text(patched, encoding="utf-8")
        print("P4E_APPLICATION_BOOT_FSM_APPLIED")

    print("P4E_APPLICATION_BOOT_FSM_PATCH_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
