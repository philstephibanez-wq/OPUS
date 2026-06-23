#!/usr/bin/env python3
"""
P4X_MOVE_LEGACY_APPLICATION_BOUNDARY

Move the historical OPUS_Application file out of the active Opus/ root.
The class name intentionally stays OPUS_Application for this palier.

Contract:
- no namespace modernization here;
- no wrapper;
- no alias;
- no silent fallback;
- www/index.php must keep working through the legacy recursive autoloader;
- Bootstrap.php remains stable at Opus/Bootstrap.php.
"""
from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

PATCH_ID = "P4X_MOVE_LEGACY_APPLICATION_BOUNDARY"
ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / "Opus" / "Application.class.php"
DST = ROOT / "Opus" / "Legacy" / "Application" / "Application.class.php"

IGNORED_TOP_LEVEL = {
    ".git",
    "DOC",
    "tools",
    "vendor",
    "var",
    "tmp",
    "logs",
}

OLD_BOOT_FSM_FALLBACK = "dirname(__DIR__) . '/config/fsm.boot.php'"
NEW_BOOT_FSM_FALLBACK = "dirname(__DIR__, 3) . '/config/fsm.boot.php'"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def move_file() -> None:
    if SRC.exists() and DST.exists():
        fail(f"BOTH_SOURCE_AND_DESTINATION_EXIST source={rel(SRC)} destination={rel(DST)}")

    if SRC.exists():
        DST.parent.mkdir(parents=True, exist_ok=True)
        shutil.move(str(SRC), str(DST))
        print(f"MOVED={rel(SRC)} -> {rel(DST)}")
        return

    if DST.exists():
        print(f"ALREADY_MOVED={rel(DST)}")
        return

    fail(f"MISSING_SOURCE_AND_DESTINATION source={rel(SRC)} destination={rel(DST)}")


def patch_moved_file() -> None:
    content = read_text(DST)
    updated = content.replace(OLD_BOOT_FSM_FALLBACK, NEW_BOOT_FSM_FALLBACK)
    if updated != content:
        write_text(DST, updated)
        print(f"PATCHED={rel(DST)}::boot_fsm_fallback_root")
    elif NEW_BOOT_FSM_FALLBACK in content:
        print(f"ALREADY_PATCHED={rel(DST)}::boot_fsm_fallback_root")
    else:
        fail("BOOT_FSM_FALLBACK_PATTERN_NOT_FOUND")


def is_runtime_source(path: Path) -> bool:
    relative = path.relative_to(ROOT)
    if not relative.parts:
        return False
    if relative.parts[0] in IGNORED_TOP_LEVEL:
        return False
    if relative.name.startswith("RUN_"):
        return False
    if relative.suffix.lower() not in {".php", ".inc", ".phtml", ".cmd", ".bat"}:
        return False
    return True


def assert_no_active_root_application_path_refs() -> None:
    offenders: list[str] = []
    needle = "Opus/Application.class.php"
    backslash_needle = "Opus\\Application.class.php"

    for path in ROOT.rglob("*"):
        if not path.is_file() or not is_runtime_source(path):
            continue
        try:
            content = read_text(path)
        except UnicodeDecodeError:
            continue
        if needle in content or backslash_needle in content:
            offenders.append(rel(path))

    if offenders:
        print("ACTIVE_ROOT_APPLICATION_PATH_REFERENCES_FOUND")
        for offender in offenders:
            print(f"ROOT_APPLICATION_REFERENCE={offender}")
        fail("REFUSING_APPLICATION_MOVE_INCOMPLETE")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED path={rel(path)}")


def assert_legacy_autoload_can_load_application() -> None:
    php_code = (
        "define('ROOT', getcwd()); "
        "require 'Opus/Legacy/Autoload/autoloader.class.php'; "
        "echo class_exists('OPUS_Application') "
        "? 'P4X_LEGACY_APPLICATION_AUTOLOAD_OK' "
        ": 'P4X_LEGACY_APPLICATION_AUTOLOAD_FAIL';"
    )
    result = subprocess.run(
        ["php", "-r", php_code],
        cwd=ROOT,
        text=True,
        capture_output=True,
    )
    if result.stdout.strip():
        print(result.stdout.strip())
    if result.stderr.strip():
        print(result.stderr.strip())
    if result.returncode != 0:
        fail("LEGACY_APPLICATION_AUTOLOAD_CHECK_FAILED")
    if "P4X_LEGACY_APPLICATION_AUTOLOAD_OK" not in result.stdout:
        fail("LEGACY_APPLICATION_CLASS_NOT_AUTOLOADABLE")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    move_file()
    patch_moved_file()
    assert_no_active_root_application_path_refs()
    php_lint(DST)
    assert_legacy_autoload_can_load_application()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
