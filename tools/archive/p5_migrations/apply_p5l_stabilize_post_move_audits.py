#!/usr/bin/env python3
"""P5L_STABILIZE_POST_MOVE_AUDITS.

Stabilize P5E/P5G/P5H wording after the Bootstrap runtime move.
This migration only updates audit wording/decision labels. It does not move runtime files.
"""
from __future__ import annotations

from pathlib import Path

ROOT = Path.cwd()
PATCH_ID = "P5L_STABILIZE_POST_MOVE_AUDITS"

REPLACEMENTS: dict[str, list[tuple[str, str]]] = {
    "tools/audits/audit_p5e_bootstrap_readiness.py": [
        (
            "Read-only audit for the last OPUS direct PHP file under Opus/.",
            "Read-only audit for the stable OPUS bootstrap runtime boundary.",
        ),
        (
            "- prove that Opus/Bootstrap.php has been moved out of the Opus root;\n- classify the runtime Bootstrap namespace/location after migration;",
            "- prove that no PHP file remains directly under Opus/;\n- prove that the runtime Bootstrap lives at Opus/Runtime/Bootstrap.php;",
        ),
        ("COMPOSER_AUTOLOAD_CAN_LOAD_OPUS_BOOTSTRAP=YES", "COMPOSER_AUTOLOAD_CAN_LOAD_RUNTIME_BOOTSTRAP=YES"),
        ("BOOTSTRAP_READINESS_DECISION", "BOOTSTRAP_STABLE_STATE_DECISION"),
        ("DECISION=BOOTSTRAP_MOVED_TO_RUNTIME_NAMESPACE", "DECISION=BOOTSTRAP_RUNTIME_BOUNDARY_STABLE"),
        ("NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE", "NEXT_SAFE_STEP=P6A_SELECT_NEXT_RUNTIME_CLEANUP_TARGET"),
        ("SCOPE=bootstrap readiness, modern entrypoint, legacy entrypoint, composer autoload", "SCOPE=stable bootstrap runtime boundary, modern entrypoint, legacy entrypoint, composer autoload"),
    ],
    "tools/audits/audit_p5g_legacy_autoloader_bootstrap_bridge.py": [
        (
            "Read-only audit for the legacy DirectoriesAutoloader bootstrap dependency.",
            "Read-only audit for the stable legacy DirectoriesAutoloader composer-aware bootstrap guard.",
        ),
        (
            "- prove the modern www legacy entrypoint now loads Composer before legacy classes;\n- classify the remaining Opus/Bootstrap.php dependency inside Opus/Legacy/Autoload/autoloader.class.php;\n- decide whether the next safe migration can replace the direct file require with a composer-aware guard;",
            "- prove the modern www legacy entrypoint loads Composer before legacy classes;\n- prove the legacy autoloader no longer requires Opus/Bootstrap.php directly;\n- prove the composer-aware runtime Bootstrap guard remains active;",
        ),
        ("P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_DECISION", "P5G_LEGACY_AUTOLOADER_BOOTSTRAP_GUARD_DECISION"),
        ("NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE", "NEXT_SAFE_STEP=P6A_SELECT_NEXT_RUNTIME_CLEANUP_TARGET"),
        ("SCOPE=legacy autoloader bootstrap bridge, composer-aware readiness", "SCOPE=stable legacy autoloader composer-aware bootstrap guard"),
    ],
    "tools/audits/audit_p5h_bootstrap_move_design.py": [
        (
            "Read-only design audit for a future Bootstrap move.",
            "Read-only audit for the stable Bootstrap runtime location after the move.",
        ),
        (
            "- prove that Opus/Bootstrap.php has been moved to the runtime namespace;\n- verify that Composer can load the runtime Bootstrap class;\n- identify any remaining post-move cleanup work;",
            "- prove that Opus/Bootstrap.php remains absent;\n- prove that Opus/Runtime/Bootstrap.php owns the runtime Bootstrap class;\n- verify that Composer can load the runtime Bootstrap class;",
        ),
        ("P5H_BOOTSTRAP_MOVE_DESIGN_DECISION", "P5H_BOOTSTRAP_RUNTIME_LOCATION_DECISION"),
        ("CURRENT_BOOTSTRAP_CLASS=", "LEGACY_BOOTSTRAP_CLASS_ABSENT_OR_SUPERSEDED="),
        ("TARGET_BOOTSTRAP_PATH=", "RUNTIME_BOOTSTRAP_PATH="),
        ("TARGET_BOOTSTRAP_CLASS=", "RUNTIME_BOOTSTRAP_CLASS="),
        ("MOVE_REQUIRES_NAMESPACE_UPDATE=NO", "RUNTIME_BOOTSTRAP_NAMESPACE_STABLE=YES"),
        ("MOVE_REQUIRES_LEGACY_GUARD_UPDATE=NO", "LEGACY_GUARD_USES_RUNTIME_BOOTSTRAP=YES"),
        ("DECISION=P5I_BOOTSTRAP_MOVED_TO_RUNTIME_NAMESPACE", "DECISION=BOOTSTRAP_RUNTIME_LOCATION_STABLE"),
        ("NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE", "NEXT_SAFE_STEP=P6A_SELECT_NEXT_RUNTIME_CLEANUP_TARGET"),
        ("SCOPE=bootstrap move target design, namespace transition, runtime blockers", "SCOPE=stable bootstrap runtime location, namespace, composer, runtime blockers"),
    ],
}


def patch_file(rel: str, replacements: list[tuple[str, str]]) -> None:
    path = ROOT / rel
    content = path.read_text(encoding="utf-8")
    original = content
    for old, new in replacements:
        if old in content:
            content = content.replace(old, new)
            print(f"PATCHED={rel}::{old[:48].replace(chr(10), ' ')}")
        elif new in content:
            print(f"ALREADY_PATCHED={rel}::{new[:48].replace(chr(10), ' ')}")
        else:
            raise RuntimeError(f"TOKEN_NOT_FOUND={rel}::{old}")
    if content != original:
        path.write_text(content, encoding="utf-8", newline="\n")


def main() -> int:
    print(f"== {PATCH_ID} ==")
    for rel, replacements in REPLACEMENTS.items():
        patch_file(rel, replacements)
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
