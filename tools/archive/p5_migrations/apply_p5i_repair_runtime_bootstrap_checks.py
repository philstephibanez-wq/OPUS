#!/usr/bin/env python3
"""
P5I_REPAIR_RUNTIME_BOOTSTRAP_CHECKS

Repair helper for the P5I local state where the Bootstrap file move was committed
before the companion smoke/audit updates were committed.

This script is intentionally narrow and idempotent:
- keeps Opus/Runtime/Bootstrap.php as the canonical Bootstrap file
- ensures the runtime namespace is Opus\Runtime
- keeps the legacy autoloader guard on Opus\Runtime\Bootstrap
- repairs P5B/P5H checks that still expect the old root Bootstrap path/namespace
"""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

PATCH_ID = "P5I_REPAIR_RUNTIME_BOOTSTRAP_CHECKS"
ROOT = Path(__file__).resolve().parents[2]

RUNTIME_BOOTSTRAP = ROOT / "Opus" / "Runtime" / "Bootstrap.php"
ROOT_BOOTSTRAP = ROOT / "Opus" / "Bootstrap.php"
LEGACY_AUTOLOADER = ROOT / "Opus" / "Legacy" / "Autoload" / "autoloader.class.php"
P5B_SMOKE = ROOT / "tools" / "smokes" / "smoke_p5b_current_runtime_layout.php"
P5E_AUDIT = ROOT / "tools" / "audits" / "audit_p5e_bootstrap_readiness.py"
P5G_AUDIT = ROOT / "tools" / "audits" / "audit_p5g_legacy_autoloader_bootstrap_bridge.py"
P5H_AUDIT = ROOT / "tools" / "audits" / "audit_p5h_bootstrap_move_design.py"


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read(path: Path) -> str:
    if not path.is_file():
        fail(f"missing file: {rel(path)}")
    return path.read_text(encoding="utf-8")


def write_if_changed(path: Path, old: str, new: str, label: str) -> None:
    if old == new:
        print(f"UNCHANGED={rel(path)}::{label}")
        return
    path.write_text(new, encoding="utf-8", newline="\n")
    print(f"PATCHED={rel(path)}::{label}")


def php_lint(path: Path) -> None:
    completed = subprocess.run(["php", "-l", str(path)], cwd=ROOT)
    if completed.returncode != 0:
        fail(f"PHP_LINT_FAILED path={rel(path)}")


def repair_runtime_bootstrap() -> None:
    if ROOT_BOOTSTRAP.exists():
        fail("Opus/Bootstrap.php still exists; expected moved file only")
    if not RUNTIME_BOOTSTRAP.is_file():
        fail("Opus/Runtime/Bootstrap.php missing")

    text = read(RUNTIME_BOOTSTRAP)
    new = text.replace("namespace Opus;", "namespace Opus\\Runtime;")
    new = new.replace("use Opus\\Runtime\\Kernel;\n", "")
    if "namespace Opus\\Runtime;" not in new:
        fail("runtime bootstrap namespace token missing")
    if "class Bootstrap" not in new:
        fail("runtime bootstrap class token missing")
    write_if_changed(RUNTIME_BOOTSTRAP, text, new, "runtime_bootstrap_namespace")


def repair_legacy_autoloader() -> None:
    text = read(LEGACY_AUTOLOADER)
    new = text.replace("Opus\\Bootstrap", "Opus\\Runtime\\Bootstrap")
    new = new.replace("Opus\\\\Bootstrap", "Opus\\\\Runtime\\\\Bootstrap")
    if "Opus\\Runtime\\Bootstrap" not in new and "Opus\\\\Runtime\\\\Bootstrap" not in new:
        fail("legacy autoloader runtime bootstrap guard token missing")
    if "Opus/Bootstrap.php" in new:
        fail("legacy autoloader still references old bootstrap path")
    write_if_changed(LEGACY_AUTOLOADER, text, new, "legacy_autoloader_runtime_bootstrap_guard")


def repair_p5b_smoke() -> None:
    text = read(P5B_SMOKE)
    new = text

    # The moved runtime bootstrap must not be checked as an absent former root file.
    new = re.sub(r"^\s*['\"]Opus/Runtime/Bootstrap\.php['\"],\r?\n", "", new, flags=re.MULTILINE)

    # Keep an explicit lint check for the new runtime bootstrap path.
    new = new.replace("CHECK_PHP_LINT_OPUS_BOOTSTRAP_PHP", "CHECK_PHP_LINT_OPUS_RUNTIME_BOOTSTRAP_PHP")
    new = re.sub(
        r"(CHECK_PHP_LINT_OPUS_RUNTIME_BOOTSTRAP_PHP[^\n]*['\"])(Opus/Bootstrap\.php)(['\"])",
        r"\1Opus/Runtime/Bootstrap.php\3",
        new,
    )
    new = re.sub(
        r"((?:assertPhpLint|phpLint|runPhpLint)\([^\n]*['\"])(Opus/Bootstrap\.php)(['\"])",
        r"\1Opus/Runtime/Bootstrap.php\3",
        new,
    )

    # Preserve negative checks against the old root path if they exist.
    if "Opus/Runtime/Bootstrap.php" not in new:
        fail("P5B smoke no longer references runtime bootstrap path")
    if "Opus/Bootstrap.php" in new and "NO_ROOT_BOOTSTRAP" not in new and "FORMER_ROOT" not in new:
        fail("P5B smoke still contains old bootstrap path outside a negative check context")

    write_if_changed(P5B_SMOKE, text, new, "p5b_runtime_bootstrap_checks")


def repair_p5h_audit() -> None:
    text = read(P5H_AUDIT)
    new = text
    new = new.replace("CHECK_TARGET_BOOTSTRAP_TOKEN", "CHECK_TARGET_BOOTSTRAP_TOKEN")
    new = new.replace("namespace Opus;", "namespace Opus\\\\Runtime;")
    new = new.replace("Opus\\Bootstrap", "Opus\\Runtime\\Bootstrap")
    new = new.replace("Opus\\\\Bootstrap", "Opus\\\\Runtime\\\\Bootstrap")
    if "namespace Opus\\\\Runtime;" not in new and "namespace Opus\\Runtime;" not in new:
        fail("P5H audit target namespace token missing")
    write_if_changed(P5H_AUDIT, text, new, "p5h_runtime_bootstrap_target_tokens")


def main() -> int:
    print(f"== {PATCH_ID} ==")
    repair_runtime_bootstrap()
    repair_legacy_autoloader()
    repair_p5b_smoke()
    repair_p5h_audit()

    php_lint(RUNTIME_BOOTSTRAP)
    php_lint(LEGACY_AUTOLOADER)
    php_lint(P5B_SMOKE)

    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise
