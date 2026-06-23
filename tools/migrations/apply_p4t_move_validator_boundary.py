#!/usr/bin/env python3
"""P4T_MOVE_VALIDATOR_BOUNDARY

Move the legacy global validator class out of the OPUS root without changing
its class name or runtime semantics.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P4T_MOVE_VALIDATOR_BOUNDARY"
ROOT = Path(__file__).resolve().parents[2]

SOURCE = ROOT / "Opus" / "Validator.class.php"
TARGET = ROOT / "Opus" / "Validation" / "Validator.class.php"

RUNTIME_SKIP_DIRS = {
    ".git",
    "vendor",
    "node_modules",
    "DOC",
    "tools",
}

OLD_PATH_TOKENS = (
    "Opus/Validator.class.php",
    "Opus\\Validator.class.php",
)
NEW_PATH_SLASH = "Opus/Validation/Validator.class.php"
NEW_PATH_BACKSLASH = "Opus\\Validation\\Validator.class.php"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, text: str) -> None:
    path.write_text(text, encoding="utf-8", newline="\n")


def iter_runtime_php_files() -> list[Path]:
    files: list[Path] = []
    for path in ROOT.rglob("*.php"):
        parts = set(path.relative_to(ROOT).parts)
        if parts & RUNTIME_SKIP_DIRS:
            continue
        files.append(path)
    return sorted(files)


def move_validator() -> None:
    if SOURCE.exists() and TARGET.exists():
        fail("SOURCE_AND_TARGET_BOTH_EXIST")
    if SOURCE.exists():
        TARGET.parent.mkdir(parents=True, exist_ok=True)
        SOURCE.rename(TARGET)
        print(f"MOVED={rel(SOURCE)} -> {rel(TARGET)}")
        return
    if TARGET.exists():
        print(f"ALREADY_MOVED={rel(TARGET)}")
        return
    fail("VALIDATOR_SOURCE_AND_TARGET_MISSING")


def patch_legacy_paths() -> None:
    for path in iter_runtime_php_files():
        if path == TARGET:
            continue
        text = read_text(path)
        patched = text.replace(OLD_PATH_TOKENS[0], NEW_PATH_SLASH)
        patched = patched.replace(OLD_PATH_TOKENS[1], NEW_PATH_BACKSLASH)
        if patched != text:
            write_text(path, patched)
            print(f"PATCHED={rel(path)}")


def assert_no_old_path_runtime_references() -> None:
    leftovers: list[str] = []
    for path in iter_runtime_php_files():
        if path == TARGET:
            continue
        text = read_text(path)
        if any(token in text for token in OLD_PATH_TOKENS):
            leftovers.append(rel(path))
    if leftovers:
        print("OLD_VALIDATOR_PATH_REFERENCES_FOUND")
        for item in leftovers:
            print(f"OLD_VALIDATOR_PATH_REFERENCE={item}")
        fail("REFUSING_VALIDATOR_MOVE_INCOMPLETE")


def assert_shape() -> None:
    if SOURCE.exists():
        fail("ROOT_VALIDATOR_STILL_EXISTS")
    if not TARGET.exists():
        fail("TARGET_VALIDATOR_MISSING")
    text = read_text(TARGET)
    if "class OPUS_Validator" not in text:
        fail("VALIDATOR_CLASS_DECLARATION_CHANGED")
    if "namespace " in text:
        fail("VALIDATOR_NAMESPACE_UNEXPECTED_FOR_THIS_PALIER")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED={rel(path)}")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    move_validator()
    patch_legacy_paths()
    assert_shape()
    assert_no_old_path_runtime_references()
    php_lint(TARGET)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
