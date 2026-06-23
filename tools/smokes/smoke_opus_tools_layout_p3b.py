#!/usr/bin/env python3
"""P3B OPUS tools layout smoke.

Read-only validation for the cleaned tools/ layout after the P4/P5 cleanup.

Contract:
- active tools stay inside their typed directories;
- no tool runner/script is allowed directly under tools/;
- obsolete P1/P2/P4/P5 scripts may exist only under tools/archive/;
- this smoke must reflect the current runtime layout, not the historical P1/P2 layout.
"""
from __future__ import annotations

import subprocess
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[2]

REQUIRED_ACTIVE_TRACKED = (
    "tools/audits/audit_opus_root_cleanup_p3.py",
    "tools/migrations/apply_opus_tools_layout_p3b.py",
    "tools/smokes/smoke_opus_naming_p1d.py",
    "tools/smokes/smoke_opus_tools_layout_p3b.py",
    "tools/smokes/smoke_p5b_current_runtime_layout.php",
)

REQUIRED_ARCHIVED_TRACKED = (
    "tools/archive/stale_smokes/smoke_opus_boot_render_p1.php",
    "tools/archive/stale_smokes/smoke_opus_view_scoretemplate_p1b.php",
    "tools/archive/stale_smokes/smoke_opus_singleton_accessor_p2.php",
    "tools/archive/p4_migrations/apply_p4x_move_legacy_application_boundary.py",
    "tools/archive/p4_audits/audit_p4v_entrypoints_runtime_split.py",
    "tools/archive/p5_migrations/apply_p5f_legacy_entrypoint_composer_boot.py",
    "tools/archive/p5_migrations/apply_p5g_legacy_autoloader_composer_guard.py",
    "tools/archive/p5_migrations/apply_p5i_migrate_bootstrap_to_runtime_namespace.py",
    "tools/archive/p5_migrations/apply_p5i_repair_runtime_bootstrap_checks.py",
)

FORBIDDEN_ROOT_TOOLS = (
    "tools/opus_reborn_cleanup_p0.py",
    "tools/smoke_opus_reborn_cleanup_p0.py",
    "tools/audit_opus_naming_p1c.py",
    "tools/apply_opus_naming_p1d.py",
    "tools/apply_opus_singleton_accessor_p2.py",
    "tools/audit_opus_root_cleanup_p3.py",
    "tools/smoke_opus_boot_render_p1.php",
    "tools/smoke_opus_view_scoretemplate_p1b.php",
    "tools/smoke_opus_naming_p1d.py",
    "tools/smoke_opus_singleton_accessor_p2.php",
)

FORBIDDEN_ACTIVE_TOOLS = (
    "tools/smokes/smoke_opus_boot_render_p1.php",
    "tools/smokes/smoke_opus_view_scoretemplate_p1b.php",
    "tools/smokes/smoke_opus_singleton_accessor_p2.php",
    "tools/migrations/apply_p5f_legacy_entrypoint_composer_boot.py",
    "tools/migrations/apply_p5g_legacy_autoloader_composer_guard.py",
    "tools/migrations/apply_p5i_migrate_bootstrap_to_runtime_namespace.py",
    "tools/migrations/apply_p5i_repair_runtime_bootstrap_checks.py",
)

CONTENT_MARKERS = (
    ("tools/smokes/smoke_opus_naming_p1d.py", "parents[2]"),
    ("tools/smokes/smoke_opus_tools_layout_p3b.py", "P3B_OPUS_TOOLS_LAYOUT_SMOKE"),
    ("tools/smokes/smoke_p5b_current_runtime_layout.php", "P5B_CURRENT_RUNTIME_LAYOUT_SMOKE_OK"),
)


def run_git(args: list[str]) -> str:
    """Run git in the repository root and return stdout, failing loudly."""
    proc = subprocess.run(
        ["git", *args],
        cwd=str(ROOT),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError("git " + " ".join(args) + " failed:\n" + (proc.stdout + proc.stderr).strip())
    return proc.stdout


def tracked_files() -> set[str]:
    """Return tracked files using normalized forward-slash paths."""
    return {line.strip().replace("\\", "/") for line in run_git(["ls-files"]).splitlines() if line.strip()}


def root_tool_scripts(files: Iterable[str]) -> list[str]:
    """Find script-like files directly below tools/, which are no longer allowed."""
    results: list[str] = []
    for rel in files:
        parts = Path(rel).parts
        if len(parts) == 2 and parts[0] == "tools" and Path(rel).suffix.lower() in {".py", ".php", ".cmd", ".bat"}:
            results.append(rel)
    return sorted(results)


def check(condition: bool, label: str, details: str = "") -> tuple[bool, str]:
    """Print an OPUS-style check result and return its status."""
    if condition:
        print(label + "=OK")
        return True, ""
    print(label + "=FAIL" + (" " + details if details else ""))
    return False, details


def assert_tracked_file(rel: str, tracked: set[str], failures: list[tuple[str, str]], label_prefix: str) -> None:
    """Validate that a tracked file exists at the expected repository path."""
    ok, detail = check(
        rel in tracked and (ROOT / rel).is_file(),
        label_prefix + rel.replace("/", "_").replace(".", "_").upper(),
        rel,
    )
    if not ok:
        failures.append((label_prefix.rstrip("_"), detail))


def main() -> int:
    failures: list[tuple[str, str]] = []
    try:
        tracked = tracked_files()
    except RuntimeError as exc:
        print("CHECK_GIT_LS_FILES=FAIL " + str(exc))
        print("P3B_OPUS_TOOLS_LAYOUT_SMOKE_FAIL")
        return 1

    print("P3B_OPUS_TOOLS_LAYOUT_SMOKE")
    ok, detail = check((ROOT / "Opus").is_dir(), "CHECK_OPUS_ROOT")
    if not ok:
        failures.append(("CHECK_OPUS_ROOT", detail))

    roots = root_tool_scripts(tracked)
    ok, detail = check(not roots, "CHECK_NO_ROOT_TOOL_SCRIPTS", ", ".join(roots))
    if not ok:
        failures.append(("CHECK_NO_ROOT_TOOL_SCRIPTS", detail))

    for rel in REQUIRED_ACTIVE_TRACKED:
        assert_tracked_file(rel, tracked, failures, "CHECK_REQUIRED_ACTIVE_")

    for rel in REQUIRED_ARCHIVED_TRACKED:
        assert_tracked_file(rel, tracked, failures, "CHECK_REQUIRED_ARCHIVED_")

    for rel in FORBIDDEN_ROOT_TOOLS:
        ok, detail = check(rel not in tracked, "CHECK_NOT_TRACKED_ROOT_" + rel.replace("/", "_").replace(".", "_").upper(), rel)
        if not ok:
            failures.append(("CHECK_FORBIDDEN_ROOT_TRACKED", detail))

    for rel in FORBIDDEN_ACTIVE_TOOLS:
        ok, detail = check(rel not in tracked, "CHECK_NOT_TRACKED_ACTIVE_" + rel.replace("/", "_").replace(".", "_").upper(), rel)
        if not ok:
            failures.append(("CHECK_FORBIDDEN_ACTIVE_TRACKED", detail))

    for rel, marker in CONTENT_MARKERS:
        text = (ROOT / rel).read_text(encoding="utf-8", errors="replace") if (ROOT / rel).is_file() else ""
        ok, detail = check(marker in text, "CHECK_MARKER_" + rel.replace("/", "_").replace(".", "_").upper(), marker)
        if not ok:
            failures.append(("CHECK_CONTENT_MARKER", f"{rel}: {marker}"))

    if failures:
        print("P3B_OPUS_TOOLS_LAYOUT_SMOKE_FAIL")
        for label, detail in failures:
            print(f" - {label}: {detail}")
        return 1

    print("P3B_OPUS_TOOLS_LAYOUT_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
