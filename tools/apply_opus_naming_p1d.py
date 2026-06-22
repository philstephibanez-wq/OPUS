#!/usr/bin/env python3
"""P1D OPUS naming standardization apply tool.

Purpose
-------
Normalize remaining legacy uppercase technical segments in the OPUS framework
without touching application sites or generated/vendor/runtime folders.

Scope
-----
Allowed:
- Opus/
- www/
- composer.json
- README.md

Forbidden:
- sites/
- vendor/
- var/cache/
- var/log/
- var/tmp/

Changes
-------
- Opus/VIEW  -> Opus/Html
- Opus/URL   -> Opus/Url
- Opus/SMTP  -> Opus/Smtp
- OPUS_VIEW_ -> OPUS_Html_
- OPUS_URL_  -> OPUS_Url_

SMTP class identifiers are not rewritten blindly because Opus/SMTP contains
third-party namespace content (SMTP4PHP) that must remain untouched.
"""

from __future__ import annotations

import argparse
import os
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Iterable, List, Sequence, Tuple

ROOT_MARKERS = ("Opus", "www", "composer.json")
FORBIDDEN_DIR_NAMES = {"sites", "vendor"}
FORBIDDEN_REL_PREFIXES = (
    "sites/",
    "vendor/",
    "var/cache/",
    "var/log/",
    "var/tmp/",
)
TEXT_SUFFIXES = {
    ".php",
    ".json",
    ".md",
    ".score",
    ".xml",
    ".yml",
    ".yaml",
    ".ini",
    ".txt",
}

DIR_RENAMES: Sequence[Tuple[str, str]] = (
    ("Opus/VIEW", "Opus/Html"),
    ("Opus/URL", "Opus/Url"),
    ("Opus/SMTP", "Opus/Smtp"),
)

TEXT_REPLACEMENTS: Sequence[Tuple[str, str]] = (
    ("OPUS_VIEW_", "OPUS_Html_"),
    ("OPUS_URL_", "OPUS_Url_"),
    ("Opus/VIEW", "Opus/Html"),
    ("Opus\\VIEW", "Opus\\Html"),
    ("Opus/URL", "Opus/Url"),
    ("Opus\\URL", "Opus\\Url"),
    ("Opus/SMTP", "Opus/Smtp"),
    ("Opus\\SMTP", "Opus\\Smtp"),
)


def repo_root() -> Path:
    return Path(__file__).resolve().parents[1]


def rel(path: Path, root: Path) -> str:
    return path.relative_to(root).as_posix()


def fail(message: str) -> None:
    print("P1D_OPUS_NAMING_APPLY_FAIL")
    print(message)
    raise SystemExit(1)


def run_git(root: Path, *args: str) -> str:
    proc = subprocess.run(
        ["git", *args],
        cwd=str(root),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if proc.returncode != 0:
        fail("git " + " ".join(args) + " failed:\n" + proc.stderr.strip())
    return proc.stdout


def assert_clean_git(root: Path) -> None:
    status = run_git(root, "status", "--short").strip()
    if status:
        fail("Working tree must be clean before applying P1D. Current status:\n" + status)


def is_forbidden(path: Path, root: Path) -> bool:
    relative = rel(path, root)
    parts = relative.split("/")
    if parts and parts[0] in FORBIDDEN_DIR_NAMES:
        return True
    return any(relative == p.rstrip("/") or relative.startswith(p) for p in FORBIDDEN_REL_PREFIXES)


def is_allowed_scope(path: Path, root: Path) -> bool:
    relative = rel(path, root)
    if is_forbidden(path, root):
        return False
    if relative.startswith("Opus/") or relative.startswith("www/"):
        return True
    return relative in {"composer.json", "README.md"}


def iter_text_files(root: Path) -> Iterable[Path]:
    candidates: List[Path] = []
    for base in (root / "Opus", root / "www"):
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path.is_file() and path.suffix.lower() in TEXT_SUFFIXES and not is_forbidden(path, root):
                candidates.append(path)
    for name in ("composer.json", "README.md"):
        path = root / name
        if path.exists() and path.is_file():
            candidates.append(path)
    return candidates


def preview_dir_renames(root: Path) -> List[str]:
    operations: List[str] = []
    for src_rel, dst_rel in DIR_RENAMES:
        src = root / src_rel
        dst = root / dst_rel
        if src.exists():
            operations.append(f"MOVE {src_rel} -> {dst_rel}")
        elif dst.exists():
            operations.append(f"KEEP {dst_rel}")
    return operations


def apply_dir_renames(root: Path) -> List[str]:
    operations: List[str] = []
    for src_rel, dst_rel in DIR_RENAMES:
        src = root / src_rel
        dst = root / dst_rel
        if not src.exists():
            if dst.exists():
                operations.append(f"KEEP {dst_rel}")
            continue
        if dst.exists():
            fail(f"Cannot rename {src_rel} to {dst_rel}: destination already exists")
        if is_forbidden(src, root) or is_forbidden(dst, root):
            fail(f"Forbidden path in directory rename: {src_rel} -> {dst_rel}")
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.move(str(src), str(dst))
        operations.append(f"MOVE {src_rel} -> {dst_rel}")
    return operations


def rewrite_file(path: Path) -> bool:
    raw = path.read_text(encoding="utf-8")
    updated = raw
    for old, new in TEXT_REPLACEMENTS:
        updated = updated.replace(old, new)
    if updated == raw:
        return False
    path.write_text(updated, encoding="utf-8", newline="")
    return True


def preview_text_edits(root: Path) -> List[str]:
    edits: List[str] = []
    for path in iter_text_files(root):
        raw = path.read_text(encoding="utf-8")
        if any(old in raw for old, _ in TEXT_REPLACEMENTS):
            edits.append(f"EDIT {rel(path, root)}")
    return edits


def apply_text_edits(root: Path) -> List[str]:
    edits: List[str] = []
    for path in iter_text_files(root):
        if not is_allowed_scope(path, root):
            fail("Refusing to edit forbidden/out-of-scope file: " + rel(path, root))
        if rewrite_file(path):
            edits.append(f"EDIT {rel(path, root)}")
    return edits


def main(argv: Sequence[str]) -> int:
    parser = argparse.ArgumentParser(description="Apply OPUS P1D naming standardization.")
    parser.add_argument("--write", action="store_true", help="Apply changes. Without this flag, only prints the plan.")
    args = parser.parse_args(argv)

    root = repo_root()
    for marker in ROOT_MARKERS:
        if not (root / marker).exists():
            fail("Not an OPUS repository root; missing " + marker)

    assert_clean_git(root)

    if not args.write:
        print("P1D_OPUS_NAMING_APPLY_PLAN")
        print("MODE=READ_ONLY")
        for op in preview_dir_renames(root):
            print(op)
        for op in preview_text_edits(root):
            print(op)
        print("P1D_OPUS_NAMING_APPLY_PLAN_OK")
        return 0

    print("P1D_OPUS_NAMING_APPLY_WRITE")
    operations = []
    operations.extend(apply_dir_renames(root))
    operations.extend(apply_text_edits(root))
    for op in operations:
        print(op)
    print("P1D_OPUS_NAMING_APPLY_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
