#!/usr/bin/env python3
"""
P5F_LEGACY_ENTRYPOINT_COMPOSER_BOOT

Patch the legacy www/index.php entrypoint so it loads Composer explicitly before
legacy boot code, instead of directly requiring Opus/Bootstrap.php.

Contract:
- no Bootstrap move;
- no compatibility wrapper;
- no silent fallback;
- Composer autoload is mandatory and fails explicitly when missing;
- legacy autoloader and legacy application remain explicit;
- P5B/P5E validation tools are updated to the new contract.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P5F_LEGACY_ENTRYPOINT_COMPOSER_BOOT"
ROOT = Path(__file__).resolve().parents[2]
WWW_INDEX = ROOT / "www" / "index.php"
P5B_SMOKE = ROOT / "tools" / "smokes" / "smoke_p5b_current_runtime_layout.php"
P5E_AUDIT = ROOT / "tools" / "audits" / "audit_p5e_bootstrap_readiness.py"

OLD_WWW_BOOT_BLOCK = """require_once ROOT . '/Opus/Bootstrap.php';
require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';"""

NEW_WWW_BOOT_BLOCK = """$composerAutoload = ROOT . '/vendor/autoload.php';
if (!is_file($composerAutoload)) {
    throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . $composerAutoload);
}
require_once $composerAutoload;
require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';"""

OLD_P5B_DOC = "- Legacy www/index.php explicitly loads Bootstrap, legacy autoloader and legacy Application."
NEW_P5B_DOC = "- Legacy www/index.php explicitly loads Composer, legacy autoloader and legacy Application."

OLD_P5B_CHECK = """        $this->contains($content, \"require_once ROOT . '/Opus/Bootstrap.php';\", 'CHECK_WWW_REQUIRES_BOOTSTRAP');
        $this->contains($content, \"require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';\", 'CHECK_WWW_REQUIRES_LEGACY_AUTOLOADER');"""

NEW_P5B_CHECK = """        $this->contains($content, '$composerAutoload = ROOT . \'/vendor/autoload.php\';', 'CHECK_WWW_DECLARES_COMPOSER_AUTOLOAD');
        $this->contains($content, 'throw new RuntimeException(\'OPUS_COMPOSER_AUTOLOAD_REQUIRED: \' . $composerAutoload);', 'CHECK_WWW_COMPOSER_AUTOLOAD_REQUIRED_ERROR');
        $this->contains($content, 'require_once $composerAutoload;', 'CHECK_WWW_REQUIRES_COMPOSER_AUTOLOAD');
        $this->notContains($content, \"require_once ROOT . '/Opus/Bootstrap.php';\", 'CHECK_WWW_DOES_NOT_REQUIRE_BOOTSTRAP_DIRECTLY');
        $this->contains($content, \"require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';\", 'CHECK_WWW_REQUIRES_LEGACY_AUTOLOADER');"""

OLD_P5E_EXPECTED = """EXPECTED_WWW_REQUIRES = [
    \"require_once ROOT . '/Opus/Bootstrap.php';\",
    \"require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';\",
    \"require_once ROOT . '/Opus/Legacy/Application/Application.class.php';\",
]"""

NEW_P5E_EXPECTED = """EXPECTED_WWW_REQUIRES = [
    \"$composerAutoload = ROOT . '/vendor/autoload.php';\",
    \"throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . $composerAutoload);\",
    \"require_once $composerAutoload;\",
    \"require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';\",
    \"require_once ROOT . '/Opus/Legacy/Application/Application.class.php';\",
]"""

OLD_P5E_REF_CHECK = """    if runtime_refs != [\"www/index.php\"]:
        return fail(\"CHECK_BOOTSTRAP_RUNTIME_REFERENCES\", \",\".join(runtime_refs))
    ok(\"CHECK_BOOTSTRAP_RUNTIME_REFERENCES\", \"www/index.php\")"""

NEW_P5E_REF_CHECK = """    if runtime_refs:
        return fail(\"CHECK_BOOTSTRAP_RUNTIME_REFERENCES\", \",\".join(runtime_refs))
    ok(\"CHECK_BOOTSTRAP_RUNTIME_REFERENCES\", \"none\")"""

OLD_P5E_DECISION = """    print(\"LEGACY_WWW_ENTRYPOINT_DIRECTLY_REQUIRES_BOOTSTRAP=YES\")
    print(\"DECISION=KEEP_OPUS_BOOTSTRAP_PHP_AT_ROOT_FOR_NOW\")
    print(\"NEXT_SAFE_STEP=P5F_DESIGN_BOOTSTRAP_COMPATIBILITY_OR_LEGACY_ENTRYPOINT_AUTLOAD_SWITCH\")"""

NEW_P5E_DECISION = """    print(\"LEGACY_WWW_ENTRYPOINT_DIRECTLY_REQUIRES_BOOTSTRAP=NO\")
    print(\"LEGACY_AUTOLOADER_STILL_BOOTSTRAPS_BRIDGE=YES\")
    print(\"DECISION=KEEP_OPUS_BOOTSTRAP_PHP_AT_ROOT_FOR_LEGACY_AUTOLOADER_BRIDGE\")
    print(\"NEXT_SAFE_STEP=P5G_DESIGN_LEGACY_AUTOLOADER_BOOTSTRAP_COMPATIBILITY\")"""


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read(path: Path) -> str:
    if not path.is_file():
        fail(f"REQUIRED_FILE_MISSING path={rel(path)}")
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def replace_once(path: Path, old: str, new: str, marker: str) -> None:
    content = read(path)
    if new in content:
        print(f"ALREADY_PATCHED={rel(path)}::{marker}")
        return
    if old not in content:
        fail(f"PATCH_PATTERN_NOT_FOUND path={rel(path)} marker={marker}")
    write(path, content.replace(old, new, 1))
    print(f"PATCHED={rel(path)}::{marker}")


def assert_www_contract() -> None:
    content = read(WWW_INDEX)
    required = [
        "$composerAutoload = ROOT . '/vendor/autoload.php';",
        "throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . $composerAutoload);",
        "require_once $composerAutoload;",
        "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';",
        "require_once ROOT . '/Opus/Legacy/Application/Application.class.php';",
        "OPUS_Application::getInstance()",
    ]
    for token in required:
        if token not in content:
            fail(f"WWW_CONTRACT_TOKEN_MISSING token={token}")
    if "require_once ROOT . '/Opus/Bootstrap.php';" in content:
        fail("WWW_STILL_REQUIRES_BOOTSTRAP_DIRECTLY")
    print("P5F_WWW_COMPOSER_BOOT_CONTRACT_OK")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED path={rel(path)}")


def main() -> int:
    print(f"== {PATCH_ID} ==")
    replace_once(WWW_INDEX, OLD_WWW_BOOT_BLOCK, NEW_WWW_BOOT_BLOCK, "composer_before_legacy_boot")
    replace_once(P5B_SMOKE, OLD_P5B_DOC, NEW_P5B_DOC, "legacy_entrypoint_doc")
    replace_once(P5B_SMOKE, OLD_P5B_CHECK, NEW_P5B_CHECK, "legacy_entrypoint_composer_checks")
    replace_once(P5E_AUDIT, OLD_P5E_EXPECTED, NEW_P5E_EXPECTED, "expected_www_requires")
    replace_once(P5E_AUDIT, OLD_P5E_REF_CHECK, NEW_P5E_REF_CHECK, "runtime_bootstrap_refs_none")
    replace_once(P5E_AUDIT, OLD_P5E_DECISION, NEW_P5E_DECISION, "decision_after_p5f")
    assert_www_contract()
    php_lint(WWW_INDEX)
    php_lint(P5B_SMOKE)
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
