#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path

PATCH_ID = "P4M_MOVE_ACL_FSM_CLASSES"
ROOT = Path(__file__).resolve().parents[2]

MOVES = [
    ("Opus/Acl.php", "Opus/Security/Acl.php", "namespace Opus;", "namespace Opus\\Security;"),
    ("Opus/Fsm.php", "Opus/FSM/Fsm.php", "namespace Opus;", "namespace Opus\\FSM;"),
]

IMPORT_REPLACEMENTS = [
    ("use Opus\\Acl;", "use Opus\\Security\\Acl;"),
    ("use Opus\\Fsm;", "use Opus\\FSM\\Fsm;"),
    ("\\Opus\\Acl", "\\Opus\\Security\\Acl"),
    ("\\Opus\\Fsm", "\\Opus\\FSM\\Fsm"),
]

LEGACY_SYMBOLS = ["Opus\\Acl", "Opus\\Fsm"]
NON_RUNTIME_ROOTS = {"DOC", "tools", "vendor", ".git"}
TEXT_SUFFIXES = {".php", ".json", ".md", ".cmd", ".py", ".score", ".txt"}


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def text_files() -> list[Path]:
    files: list[Path] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        parts = path.relative_to(ROOT).parts
        if any(part in {".git", "vendor", "node_modules"} for part in parts):
            continue
        if path.suffix.lower() in TEXT_SUFFIXES:
            files.append(path)
    return sorted(files)


def move_class(source_name: str, target_name: str, old_namespace: str, new_namespace: str) -> None:
    source = ROOT / source_name
    target = ROOT / target_name
    if source.is_file() and target.is_file():
        fail(f"SOURCE_AND_TARGET_BOTH_EXIST={source_name}->{target_name}")
    if not source.is_file() and not target.is_file():
        fail(f"SOURCE_AND_TARGET_MISSING={source_name}->{target_name}")
    if source.is_file():
        content = source.read_text(encoding="utf-8")
        if old_namespace not in content:
            fail(f"OLD_NAMESPACE_NOT_FOUND={source_name}")
        content = content.replace(old_namespace, new_namespace, 1)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8", newline="")
        source.unlink()
        print(f"MOVED={source_name} -> {target_name}")
    else:
        content = target.read_text(encoding="utf-8")
        if new_namespace not in content:
            fail(f"TARGET_NAMESPACE_INVALID={target_name}")
        print(f"ALREADY_MOVED={target_name}")


def patch_imports() -> None:
    for path in text_files():
        if rel(path).startswith("tools/migrations/"):
            continue
        content = path.read_text(encoding="utf-8", errors="ignore")
        updated = content
        for old, new in IMPORT_REPLACEMENTS:
            updated = updated.replace(old, new)
        if updated != content:
            path.write_text(updated, encoding="utf-8", newline="")
            print(f"PATCHED={rel(path)}")


def assert_no_runtime_legacy_symbols() -> None:
    runtime_hits: list[str] = []
    non_runtime_hits: list[str] = []
    for path in text_files():
        content = path.read_text(encoding="utf-8", errors="ignore")
        for symbol in LEGACY_SYMBOLS:
            if symbol not in content:
                continue
            hit = f"{rel(path)} contains {symbol}"
            first = path.relative_to(ROOT).parts[0]
            if first in NON_RUNTIME_ROOTS or path.name.startswith("RUN_P4M_"):
                non_runtime_hits.append(hit)
            else:
                runtime_hits.append(hit)
    if non_runtime_hits:
        print("NON_RUNTIME_LEGACY_SYMBOL_REFERENCES_FOUND")
        for hit in non_runtime_hits:
            print(hit)
    if runtime_hits:
        print("RUNTIME_LEGACY_SYMBOL_REFERENCES_FOUND")
        for hit in runtime_hits:
            print(hit)
        fail("RUNTIME_LEGACY_SYMBOL_REFERENCE_EXISTS")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    for move in MOVES:
        move_class(*move)
    patch_imports()
    assert_no_runtime_legacy_symbols()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
