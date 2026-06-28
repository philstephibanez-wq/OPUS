#!/usr/bin/env python3
"""
P4S_LEGACY_ROOT_USAGE_AUDIT

Non-destructive audit for remaining legacy root classes before moving them out
of Opus/. This script intentionally changes nothing.
"""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

PATCH_ID = "P4S_LEGACY_ROOT_USAGE_AUDIT"
ROOT = Path(__file__).resolve().parents[2]

EXCLUDED_DIRS = {
    ".git",
    "vendor",
    "node_modules",
    "tmp",
    "var",
    "cache",
    ".idea",
    ".vscode",
}

NON_RUNTIME_DIRS = {
    "tools",
    "DOC",
    "docs",
    "doc",
}

TARGET_FILES = [
    Path("Opus/Application.class.php"),
    Path("Opus/Validator.class.php"),
    Path("Opus/autoloader.class.php"),
    Path("Opus/autoloader_new2.class.php"),
]

TOKENS = [
    "OPUS_Application",
    "OPUS_Validator",
    "DirectoriesAutoloader",
    "DirectoriesAutoloaderException",
    "Autoloader",
    "_import(",
    "Application.class.php",
    "Validator.class.php",
    "autoloader.class.php",
    "autoloader_new2.class.php",
]

@dataclass(frozen=True)
class Hit:
    path: Path
    line_no: int
    token: str
    line: str


def rel(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def is_binary(path: Path) -> bool:
    try:
        chunk = path.read_bytes()[:2048]
    except OSError:
        return True
    return b"\0" in chunk


def iter_source_files() -> Iterable[Path]:
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        parts = set(path.relative_to(ROOT).parts)
        if parts & EXCLUDED_DIRS:
            continue
        if path.suffix.lower() not in {".php", ".py", ".cmd", ".md", ".json", ".xml", ".txt", ".score"}:
            continue
        if is_binary(path):
            continue
        yield path


def is_runtime(path: Path) -> bool:
    rel_parts = path.relative_to(ROOT).parts
    if not rel_parts:
        return False
    if rel_parts[0] in NON_RUNTIME_DIRS:
        return False
    if path.name.upper().startswith("RUN_P"):
        return False
    return True


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def scan_hits() -> list[Hit]:
    hits: list[Hit] = []
    for path in iter_source_files():
        text = read_text(path)
        lines = text.splitlines()
        for i, line in enumerate(lines, start=1):
            for token in TOKENS:
                if token in line:
                    hits.append(Hit(path, i, token, line.strip()))
    return hits


def print_target_summary() -> None:
    print("\n== LEGACY_ROOT_TARGETS ==")
    for target in TARGET_FILES:
        path = ROOT / target
        if path.exists():
            text = read_text(path)
            line_count = len(text.splitlines())
            print(f"{target.as_posix()} | EXISTS | lines={line_count}")
        else:
            print(f"{target.as_posix()} | MISSING")


def print_hits(title: str, hits: list[Hit], runtime: bool) -> None:
    filtered = [h for h in hits if is_runtime(h.path) is runtime]
    print(f"\n== {title} ==")
    if not filtered:
        print("NONE")
        return
    for h in filtered:
        print(f"{rel(h.path)}:{h.line_no} | {h.token} | {h.line}")


def print_readiness(hits: list[Hit]) -> None:
    runtime_hits = [h for h in hits if is_runtime(h.path)]
    by_token: dict[str, int] = {}
    for h in runtime_hits:
        by_token[h.token] = by_token.get(h.token, 0) + 1

    print("\n== MOVE_READINESS ==")
    for token in TOKENS:
        print(f"{token} | runtime_hits={by_token.get(token, 0)}")

    print("\n== RECOMMENDED_NEXT_BOUNDARY ==")
    validator_refs = [h for h in runtime_hits if h.token in {"OPUS_Validator", "Validator.class.php"} and rel(h.path) != "Opus/Validator.class.php"]
    app_refs = [h for h in runtime_hits if h.token in {"OPUS_Application", "Application.class.php"} and rel(h.path) != "Opus/Application.class.php"]
    old_loader_refs = [h for h in runtime_hits if h.token in {"autoloader_new2.class.php", "_import("}]

    if not validator_refs:
        print("NEXT_SAFE_MOVE=Opus/Validator.class.php -> Opus/Validation/Validator.class.php")
        print("NOTE=Keep class name OPUS_Validator for this palier; namespace modernization is separate.")
    else:
        print("NEXT_SAFE_MOVE=BLOCKED_BY_VALIDATOR_RUNTIME_REFERENCES")

    if app_refs:
        print("APPLICATION_LEGACY_MOVE=BLOCKED_BY_RUNTIME_REFERENCES_OR_BOOT_CONTRACT")
    else:
        print("APPLICATION_LEGACY_MOVE=REVIEW_REQUIRED_BECAUSE_CLASS_IS_778_LINES_AND_BOOT_FSM_OWNER")

    if old_loader_refs:
        print("AUTOLOADER_NEW2=REVIEW_OR_ARCHIVE_REQUIRED")
    else:
        print("AUTOLOADER_NEW2=NO_RUNTIME_IMPORT_USAGE_FOUND_BY_TOKEN_SCAN")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    print_target_summary()
    hits = scan_hits()
    print_hits("RUNTIME_LEGACY_REFERENCES", hits, runtime=True)
    print_hits("NON_RUNTIME_LEGACY_REFERENCES", hits, runtime=False)
    print_readiness(hits)
    print(f"\n{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
