#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""P7A0FG smoke: removed legacy debug-class deletion gate."""

from pathlib import Path
import subprocess
import sys


def scan_files() -> list[Path]:
    files: list[Path] = []
    for base in [Path("Opus"), Path("tools"), Path("DOC")]:
        if not base.exists():
            continue
        for pattern in ("*.php", "*.class.php", "*.py", "*.md"):
            files.extend(base.rglob(pattern))
    return sorted(set(files))


def main() -> int:
    print("P7A0FG_DELETE_LEGACY_DEBUG_CLASS_SMOKE")

    debug_class = Path("Opus") / "Debug" / ("Debug" + ".class.php")
    if debug_class.exists():
        print("CHECK_DEBUG_CLASS_DELETED=FAIL")
        return 1
    print("CHECK_DEBUG_CLASS_DELETED=OK")

    forbidden = [
        "OPUS" + "_Debug",
        "Debug" + ".class.php",
    ]

    remaining = []
    self_path = Path(__file__).resolve()

    for path in scan_files():
        if path.resolve() == self_path:
            continue

        text = path.read_text(encoding="utf-8", errors="replace")
        for token in forbidden:
            if token in text:
                remaining.append(f"{path}: {token}")

    if remaining:
        print("CHECK_NO_LEGACY_DEBUG_REFERENCES=FAIL")
        for item in remaining:
            print(item)
        return 1

    print("CHECK_NO_LEGACY_DEBUG_REFERENCES=OK")

    lint_targets = [
        "Opus/Diagnostics/Diagnostics.php",
        "tools/smokes/smoke_p7a0fg_diagnostics_replaces_debug.php",
    ]

    for target in lint_targets:
        if not Path(target).exists():
            print(f"CHECK_LINT_TARGET_EXISTS=FAIL {target}")
            return 1

        completed = subprocess.run(
            ["php", "-l", target],
            check=False,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        if completed.returncode != 0:
            print(completed.stdout)
            print(completed.stderr, file=sys.stderr)
            print(f"CHECK_LINT=FAIL {target}")
            return 1

    print("CHECK_LINT=OK")
    print("P7A0FG_DELETE_LEGACY_DEBUG_CLASS_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
