#!/usr/bin/env python3
"""P3B OPUS tools layout migration.

This migration cleans the root of tools/ without touching OPUS runtime code.
It moves still-useful smoke/audit scripts into explicit subfolders and removes
migration scripts that have become obsolete after their milestones were committed.
"""
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path
from typing import Iterable, Sequence

ROOT = Path(__file__).resolve().parents[2]

OBSOLETE_TRACKED_FILES = (
    "tools/opus_reborn_cleanup_p0.py",
    "tools/smoke_opus_reborn_cleanup_p0.py",
    "tools/audit_opus_naming_p1c.py",
    "tools/apply_opus_naming_p1d.py",
    "tools/apply_opus_singleton_accessor_p2.py",
)

MOVE_TRACKED_FILES = (
    ("tools/smoke_opus_boot_render_p1.php", "tools/smokes/smoke_opus_boot_render_p1.php"),
    ("tools/smoke_opus_view_scoretemplate_p1b.php", "tools/smokes/smoke_opus_view_scoretemplate_p1b.php"),
    ("tools/smoke_opus_naming_p1d.py", "tools/smokes/smoke_opus_naming_p1d.py"),
    ("tools/smoke_opus_singleton_accessor_p2.php", "tools/smokes/smoke_opus_singleton_accessor_p2.php"),
    ("tools/audit_opus_root_cleanup_p3.py", "tools/audits/audit_opus_root_cleanup_p3.py"),
)

TEXT_REWRITES = {
    "tools/smokes/smoke_opus_boot_render_p1.php": (
        ("realpath(__DIR__ . '/..')", "realpath(__DIR__ . '/../..')"),
    ),
    "tools/smokes/smoke_opus_view_scoretemplate_p1b.php": (
        ("realpath(__DIR__ . '/..')", "realpath(__DIR__ . '/../..')"),
    ),
    "tools/smokes/smoke_opus_naming_p1d.py": (
        ("Path(__file__).resolve().parents[1]", "Path(__file__).resolve().parents[2]"),
    ),
    "tools/smokes/smoke_opus_singleton_accessor_p2.php": (
        ("Read-only smoke after tools/apply_opus_singleton_accessor_p2.py --write.", "Read-only smoke for the committed OPUS singleton/accessor contract."),
        ("$root = dirname(__DIR__);", "$root = dirname(__DIR__, 2);"),
    ),
    "tools/audits/audit_opus_root_cleanup_p3.py": (
        ("SCOPE=repo root, Opus/ direct files, tools/ root scripts", "SCOPE=repo root, Opus/ direct files, tools/ organized scripts"),
        ("ROOT_TOOL_SCRIPTS", "TOOLS_LAYOUT"),
        ("FINDINGS_ROOT_TOOL_SCRIPTS", "FINDINGS_TOOLS_ROOT_SCRIPTS"),
    ),
}


def run_git(args: Sequence[str]) -> str:
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


def git_status_short() -> str:
    return run_git(["status", "--short"]).strip()


def assert_repo_root() -> None:
    for marker in ("Opus", "www", "composer.json"):
        if not (ROOT / marker).exists():
            raise RuntimeError(f"Not an OPUS repository root: missing {marker}")


def assert_clean_tree(write: bool) -> None:
    if not write:
        return
    status = git_status_short()
    if status:
        raise RuntimeError("Refusing to write because git status is not clean:\n" + status)


def tracked_files() -> set[str]:
    return {line.strip().replace("\\", "/") for line in run_git(["ls-files"]).splitlines() if line.strip()}


def rewrite_file(rel_path: str) -> bool:
    replacements = TEXT_REWRITES.get(rel_path, ())
    if not replacements:
        return False
    path = ROOT / rel_path
    text = path.read_text(encoding="utf-8")
    updated = text
    for old, new in replacements:
        updated = updated.replace(old, new)
    if updated == text:
        return False
    path.write_text(updated, encoding="utf-8", newline="\n")
    return True


def planned_operations(existing: set[str]) -> list[str]:
    operations: list[str] = []
    for old, new in MOVE_TRACKED_FILES:
        if old in existing:
            operations.append(f"MOVE {old} -> {new}")
        elif new in existing:
            operations.append(f"KEEP {new}")
    for rel_path in OBSOLETE_TRACKED_FILES:
        if rel_path in existing:
            operations.append(f"DELETE_OBSOLETE {rel_path}")
    return operations


def apply_operations(existing: set[str]) -> list[str]:
    operations: list[str] = []
    for old, new in MOVE_TRACKED_FILES:
        if old not in existing:
            if new in existing:
                operations.append(f"KEEP {new}")
            continue
        (ROOT / new).parent.mkdir(parents=True, exist_ok=True)
        run_git(["mv", old, new])
        if rewrite_file(new):
            operations.append(f"MOVE_AND_REWRITE {old} -> {new}")
        else:
            operations.append(f"MOVE {old} -> {new}")

    current = tracked_files()
    for rel_path in OBSOLETE_TRACKED_FILES:
        if rel_path in current:
            run_git(["rm", rel_path])
            operations.append(f"DELETE_OBSOLETE {rel_path}")
    return operations


def main(argv: Sequence[str]) -> int:
    parser = argparse.ArgumentParser(description="Organize OPUS root tool scripts into audits/smokes/migrations.")
    parser.add_argument("--write", action="store_true", help="Apply changes. Without this flag, only prints the plan.")
    args = parser.parse_args(argv)

    try:
        assert_repo_root()
        assert_clean_tree(args.write)
        existing = tracked_files()
        if not args.write:
            print("P3B_OPUS_TOOLS_LAYOUT_PLAN")
            print("MODE=READ_ONLY")
            operations = planned_operations(existing)
        else:
            print("P3B_OPUS_TOOLS_LAYOUT_APPLIED")
            operations = apply_operations(existing)
        if operations:
            for operation in operations:
                print(operation)
        else:
            print("NO_CHANGE")
        if args.write:
            print("P3B_OPUS_TOOLS_LAYOUT_APPLY_OK")
        else:
            print("P3B_OPUS_TOOLS_LAYOUT_PLAN_OK")
        return 0
    except RuntimeError as exc:
        print("P3B_OPUS_TOOLS_LAYOUT_FAIL")
        print(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
