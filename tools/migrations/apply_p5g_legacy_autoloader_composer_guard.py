#!/usr/bin/env python3
"""
P5G_LEGACY_AUTOLOADER_COMPOSER_GUARD

Migrate the legacy DirectoriesAutoloader bootstrap bridge from a direct
Opus/Bootstrap.php require to a Composer-aware class guard.

Contract:
- www/index.php already loads vendor/autoload.php before legacy autoloader;
- legacy autoloader must not require Opus/Bootstrap.php directly anymore;
- absence of Composer/bootstrap class must fail explicitly;
- no Bootstrap.php move in this palier;
- update current smoke/audit contracts with the new state.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P5G_LEGACY_AUTOLOADER_COMPOSER_GUARD"
ROOT = Path.cwd()
LEGACY_AUTOLOADER = ROOT / "Opus/Legacy/Autoload/autoloader.class.php"
P5B_SMOKE = ROOT / "tools/smokes/smoke_p5b_current_runtime_layout.php"
P5E_AUDIT = ROOT / "tools/audits/audit_p5e_bootstrap_readiness.py"
P5G_AUDIT = ROOT / "tools/audits/audit_p5g_legacy_autoloader_bootstrap_bridge.py"

OLD_DIRECT_BOOTSTRAP = """$opusBootstrap = defined('ROOT') ? ROOT . '/Opus/Bootstrap.php' : dirname(__DIR__, 2) . '/Bootstrap.php';
require_once $opusBootstrap;"""

NEW_COMPOSER_GUARD = """if (!class_exists(\\Opus\\Bootstrap::class)) {
    throw new RuntimeException('OPUS_BOOTSTRAP_CLASS_REQUIRED: Composer autoload must be loaded before legacy autoloader.');
}"""


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def require_file(path: Path) -> None:
    if not path.is_file():
        fail(f"REQUIRED_FILE_MISSING path={rel(path)}")


def patch_once(path: Path, old: str, new: str, label: str) -> None:
    content = read(path)
    if new in content:
        print(f"ALREADY_PATCHED={rel(path)}::{label}")
        return
    if old not in content:
        fail(f"PATTERN_NOT_FOUND path={rel(path)} label={label}")
    write(path, content.replace(old, new))
    print(f"PATCHED={rel(path)}::{label}")


def patch_legacy_autoloader() -> None:
    patch_once(
        LEGACY_AUTOLOADER,
        OLD_DIRECT_BOOTSTRAP,
        NEW_COMPOSER_GUARD,
        "composer_aware_bootstrap_guard",
    )


def patch_p5b_smoke() -> None:
    content = read(P5B_SMOKE)

    content = content.replace(
        " * - Legacy www/index.php explicitly loads Composer, legacy autoloader and legacy Application.\n",
        " * - Legacy www/index.php explicitly loads Composer, legacy autoloader and legacy Application.\n"
        " * - Legacy autoloader requires Composer-loaded Opus\\\\Bootstrap instead of requiring Opus/Bootstrap.php directly.\n",
    )

    if "        $this->checkLegacyAutoloaderComposerGuard();\n" not in content:
        needle = "        $this->checkLegacyEntrypoint();\n"
        if needle not in content:
            fail("P5B_RUN_LEGACY_ENTRYPOINT_CALL_NOT_FOUND")
        content = content.replace(needle, needle + "        $this->checkLegacyAutoloaderComposerGuard();\n")
        print(f"PATCHED={rel(P5B_SMOKE)}::run_legacy_autoloader_guard_check")
    else:
        print(f"ALREADY_PATCHED={rel(P5B_SMOKE)}::run_legacy_autoloader_guard_check")

    method = """    private function checkLegacyAutoloaderComposerGuard(): void
    {
        $file = $this->root . '/Opus/Legacy/Autoload/autoloader.class.php';
        $content = $this->read($file);
        if ($content === null) { return; }

        $this->contains($content, 'class_exists(\\Opus\\Bootstrap::class)', 'CHECK_LEGACY_AUTOLOADER_COMPOSER_BOOTSTRAP_GUARD');
        $this->contains($content, 'OPUS_BOOTSTRAP_CLASS_REQUIRED', 'CHECK_LEGACY_AUTOLOADER_BOOTSTRAP_REQUIRED_ERROR');
        $this->notContains($content, 'require_once $opusBootstrap;', 'CHECK_LEGACY_AUTOLOADER_NO_BOOTSTRAP_REQUIRE');
        $this->notContains($content, "ROOT . '/Opus/Bootstrap.php'", 'CHECK_LEGACY_AUTOLOADER_NO_ROOT_BOOTSTRAP_PATH');
    }

"""
    if "private function checkLegacyAutoloaderComposerGuard" not in content:
        needle = "    private function checkBootstrapContract(): void\n"
        if needle not in content:
            fail("P5B_BOOTSTRAP_CONTRACT_METHOD_NOT_FOUND")
        content = content.replace(needle, method + needle)
        print(f"PATCHED={rel(P5B_SMOKE)}::legacy_autoloader_guard_method")
    else:
        print(f"ALREADY_PATCHED={rel(P5B_SMOKE)}::legacy_autoloader_guard_method")

    write(P5B_SMOKE, content)


def patch_p5e_audit() -> None:
    old = """    print(\"LEGACY_AUTOLOADER_STILL_BOOTSTRAPS_BRIDGE=YES\")
    print(\"DECISION=KEEP_OPUS_BOOTSTRAP_PHP_AT_ROOT_FOR_LEGACY_AUTOLOADER_BRIDGE\")
    print(\"NEXT_SAFE_STEP=P5G_DESIGN_LEGACY_AUTOLOADER_BOOTSTRAP_COMPATIBILITY\")"""
    new = """    print(\"LEGACY_AUTOLOADER_STILL_BOOTSTRAPS_BRIDGE=NO\")
    print(\"DECISION=BOOTSTRAP_DIRECT_RUNTIME_REFERENCES_REMOVED\")
    print(\"NEXT_SAFE_STEP=P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT\")"""
    patch_once(P5E_AUDIT, old, new, "decision_after_p5g")


def patch_p5g_audit() -> None:
    content = read(P5G_AUDIT)

    content = content.replace(
        "    \"class_exists('\\\\\\\\Opus\\\\\\\\Bootstrap'\",\n"
        "    \"class_exists('Opus\\\\\\\\Bootstrap'\",\n"
        "    \"OPUS_BOOTSTRAP_CLASS_REQUIRED\",\n",
        "    \"class_exists(\\\\Opus\\\\Bootstrap::class)\",\n"
        "    \"OPUS_BOOTSTRAP_CLASS_REQUIRED\",\n",
    )

    old_scan = """    if runtime_refs == [LEGACY_AUTOLOADER] or sorted(runtime_refs) == [LEGACY_AUTOLOADER]:
        ok(\"CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES\", LEGACY_AUTOLOADER)
        return 0
    return fail(\"CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES\", \",\".join(sorted(runtime_refs)))"""
    new_scan = """    if runtime_refs == [LEGACY_AUTOLOADER] or sorted(runtime_refs) == [LEGACY_AUTOLOADER]:
        ok(\"CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES\", LEGACY_AUTOLOADER)
        return 0
    if runtime_refs == []:
        ok(\"CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES\", \"none\")
        return 0
    return fail(\"CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES\", \",\".join(sorted(runtime_refs)))"""
    if new_scan not in content:
        if old_scan not in content:
            fail("P5G_RUNTIME_REF_SCAN_PATTERN_NOT_FOUND")
        content = content.replace(old_scan, new_scan)
        print(f"PATCHED={rel(P5G_AUDIT)}::runtime_refs_accept_none")
    else:
        print(f"ALREADY_PATCHED={rel(P5G_AUDIT)}::runtime_refs_accept_none")

    old_decision = """    elif composer_aware:
        print(\"DECISION=LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_ALREADY_COMPOSER_AWARE\")
        print(\"NEXT_SAFE_STEP=RERUN_P5E_AND_P5B\")"""
    new_decision = """    elif composer_aware and not direct_bridge:
        print(\"DECISION=P5G_LEGACY_AUTOLOADER_COMPOSER_GUARD_OK\")
        print(\"NEXT_SAFE_STEP=P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT\")
    elif composer_aware:
        print(\"DECISION=LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_ALREADY_COMPOSER_AWARE\")
        print(\"NEXT_SAFE_STEP=RERUN_P5E_AND_P5B\")"""
    if new_decision not in content:
        if old_decision not in content:
            fail("P5G_DECISION_PATTERN_NOT_FOUND")
        content = content.replace(old_decision, new_decision)
        print(f"PATCHED={rel(P5G_AUDIT)}::post_migration_decision")
    else:
        print(f"ALREADY_PATCHED={rel(P5G_AUDIT)}::post_migration_decision")

    write(P5G_AUDIT, content)


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True, check=False)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED path={rel(path)}")


def assert_contracts() -> None:
    autoloader = read(LEGACY_AUTOLOADER)
    if OLD_DIRECT_BOOTSTRAP in autoloader:
        fail("LEGACY_AUTOLOADER_STILL_HAS_DIRECT_BOOTSTRAP_BRIDGE")
    if NEW_COMPOSER_GUARD not in autoloader:
        fail("LEGACY_AUTOLOADER_COMPOSER_GUARD_MISSING")

    smoke = read(P5B_SMOKE)
    for token in [
        "CHECK_LEGACY_AUTOLOADER_COMPOSER_BOOTSTRAP_GUARD",
        "CHECK_LEGACY_AUTOLOADER_NO_BOOTSTRAP_REQUIRE",
    ]:
        if token not in smoke:
            fail(f"P5B_CONTRACT_TOKEN_MISSING token={token}")

    p5e = read(P5E_AUDIT)
    if "DECISION=BOOTSTRAP_DIRECT_RUNTIME_REFERENCES_REMOVED" not in p5e:
        fail("P5E_DECISION_NOT_UPDATED")

    p5g = read(P5G_AUDIT)
    if "DECISION=P5G_LEGACY_AUTOLOADER_COMPOSER_GUARD_OK" not in p5g:
        fail("P5G_DECISION_NOT_UPDATED")

    print("P5G_LEGACY_AUTOLOADER_COMPOSER_GUARD_CONTRACT_OK")


def main() -> int:
    print(f"== {PATCH_ID} ==")
    for path in [LEGACY_AUTOLOADER, P5B_SMOKE, P5E_AUDIT, P5G_AUDIT]:
        require_file(path)

    patch_legacy_autoloader()
    patch_p5b_smoke()
    patch_p5e_audit()
    patch_p5g_audit()

    for path in [LEGACY_AUTOLOADER, P5B_SMOKE]:
        php_lint(path)

    assert_contracts()
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
