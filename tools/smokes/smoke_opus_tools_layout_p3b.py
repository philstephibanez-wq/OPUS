#!/usr/bin/env python3
"""P3B OPUS tools layout smoke.

Read-only validation for the cleaned tools/ layout.
"""
from __future__ import annotations

import subprocess
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[2]

REQUIRED_TRACKED = (
    "tools/audits/audit_opus_root_cleanup_p3.py",
    "tools/migrations/apply_opus_tools_layout_p3b.py",
    "tools/smokes/smoke_opus_boot_render_p1.php",
    "tools/smokes/smoke_opus_view_scoretemplate_p1b.php",
    "tools/smokes/smoke_opus_naming_p1d.py",
    "tools/smokes/smoke_opus_singleton_accessor_p2.php",
    "tools/smokes/smoke_opus_tools_layout_p3b.py",
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

CONTENT_MARKERS = (
    ("tools/smokes/smoke_opus_boot_render_p1.php", "realpath(__DIR__ . '/../..')"),
    ("tools/smokes/smoke_opus_view_scoretemplate_p1b.php", "realpath(__DIR__ . '/../..')"),
    ("tools/smokes/smoke_opus_naming_p1d.py", "parents[2]"),
    ("tools/smokes/smoke_opus_singleton_accessor_p2.php", "dirname(__DIR__, 2)"),
)


def run_git(args: list[str]) -> str:
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
    return {line.strip().replace("\\", "/") for line in run_git(["ls-files"]).splitlines() if line.strip()}


def root_tool_scripts(files: Iterable[str]) -> list[str]:
    results: list[str] = []
    for rel in files:
        parts = Path(rel).parts
        if len(parts) == 2 and parts[0] == "tools" and Path(rel).suffix.lower() in {".py", ".php", ".cmd", ".bat"}:
            results.append(rel)
    return sorted(results)


def check(condition: bool, label: str, details: str = "") -> tuple[bool, str]:
    if condition:
        print(label + "=OK")
        return True, ""
    print(label + "=FAIL" + (" " + details if details else ""))
    return False, details


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

    for rel in REQUIRED_TRACKED:
        ok, detail = check(rel in tracked and (ROOT / rel).is_file(), "CHECK_REQUIRED_" + rel.replace("/", "_").replace(".", "_").upper(), rel)
        if not ok:
            failures.append(("CHECK_REQUIRED_FILE", detail))

    for rel in FORBIDDEN_ROOT_TOOLS:
        ok, detail = check(rel not in tracked, "CHECK_NOT_TRACKED_" + rel.replace("/", "_").replace(".", "_").upper(), rel)
        if not ok:
            failures.append(("CHECK_FORBIDDEN_TRACKED", detail))

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
