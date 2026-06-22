#!/usr/bin/env python3
"""
OPUS Reborn cleanup P0.

Scope: identity and naming cleanup only.
- No business logic rewrite.
- No FRONT/MIDDLE/BACK migration.
- No site content migration.
- No vendor/cache mutation.

The script is intentionally conservative and refuses --write unless the git tree is clean.
"""
from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[1]

INCLUDED_TOP_LEVEL = {"Opus", "www"}
EXTRA_TEXT_FILES = {"composer.json", "README.md"}
TEXT_SUFFIXES = {".php", ".xml", ".json", ".md", ".score", ".css", ".js", ".txt"}
EXCLUDED_PARTS = {".git", "vendor"}
EXCLUDED_PREFIXES = {
    ("sites",),
    ("Sites",),
    ("var", "cache"),
    ("var", "log"),
    ("var", "logs"),
    ("var", "tmp"),
    ("var", "temp"),
}

RENAME_DIRS = [
    (Path("Opus/Controler"), Path("Opus/Controller")),
    (Path("Opus/Scafold"), Path("Opus/Scaffold")),
]

# Order matters: path/namespace exceptions first, broad legacy identity last.
REPLACEMENTS = [
    ("ROOT . '/framework/ASAP/Bootstrap.php'", "ROOT . '/Opus/Bootstrap.php'"),
    ('ROOT . "/framework/ASAP/Bootstrap.php"', 'ROOT . "/Opus/Bootstrap.php"'),
    ("ROOT . '/framework/autoloader.class.php'", "ROOT . '/Opus/autoloader.class.php'"),
    ('ROOT . "/framework/autoloader.class.php"', 'ROOT . "/Opus/autoloader.class.php"'),
    ("__DIR__ . '/ASAP/bootstrap.php'", "__DIR__ . '/Bootstrap.php'"),
    ('__DIR__ . "/ASAP/bootstrap.php"', '__DIR__ . "/Bootstrap.php"'),
    ("/framework/ASAP/", "/Opus/"),
    ("/framework/ASAP", "/Opus"),
    ("/framework/Opus/", "/Opus/"),
    ("/framework/Opus", "/Opus"),
    ("$base . '/framework/ASAP/'", "$base . '/Opus/'"),
    ('$base . "/framework/ASAP/"', '$base . "/Opus/"'),
    ("$rootDir . '/framework/ASAP/'", "$rootDir . '/Opus/'"),
    ('$rootDir . "/framework/ASAP/"', '$rootDir . "/Opus/"'),
    ("namespace ASAP;", "namespace Opus;"),
    ("ASAP_ENV", "OPUS_ENV"),
    ("ASAP_", "OPUS_"),
    ("asap_", "opus_"),
    ("ASAP", "OPUS"),
    ("Asap", "Opus"),
    ("asap", "opus"),
]


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def git_status_short() -> str:
    proc = run(["git", "status", "--short"])
    if proc.returncode != 0:
        raise RuntimeError(proc.stdout.strip())
    return proc.stdout


def ensure_git_clean(write: bool) -> None:
    if not write:
        return
    status = git_status_short().strip()
    if status:
        raise RuntimeError("Refusing to write because git status is not clean:\n" + status)


def rel_parts(path: Path) -> tuple[str, ...]:
    try:
        return path.relative_to(ROOT).parts
    except ValueError:
        return path.parts


def is_excluded(path: Path) -> bool:
    parts = rel_parts(path)
    if any(part in EXCLUDED_PARTS for part in parts):
        return True
    for prefix in EXCLUDED_PREFIXES:
        if parts[: len(prefix)] == prefix:
            return True
    return False


def is_in_scope(path: Path) -> bool:
    parts = rel_parts(path)
    if not parts or is_excluded(path):
        return False
    if parts[0] in INCLUDED_TOP_LEVEL:
        return True
    if len(parts) == 1 and parts[0] in EXTRA_TEXT_FILES:
        return True
    return False


def iter_text_files() -> Iterable[Path]:
    for base_name in sorted(INCLUDED_TOP_LEVEL):
        base = ROOT / base_name
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path.is_file() and not is_excluded(path) and path.suffix.lower() in TEXT_SUFFIXES:
                yield path
    for name in sorted(EXTRA_TEXT_FILES):
        path = ROOT / name
        if path.is_file() and path.suffix.lower() in TEXT_SUFFIXES:
            yield path


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, text: str) -> None:
    path.write_text(text, encoding="utf-8", newline="")


def rename_directories(write: bool) -> list[str]:
    changes: list[str] = []
    for src_rel, dst_rel in RENAME_DIRS:
        src = ROOT / src_rel
        dst = ROOT / dst_rel
        if not src.exists():
            continue
        if dst.exists():
            raise RuntimeError(f"Cannot rename {src_rel} to {dst_rel}: destination already exists")
        changes.append(f"MOVE {src_rel.as_posix()} -> {dst_rel.as_posix()}")
        if write:
            dst.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(src), str(dst))
    return changes


def replace_in_files(write: bool) -> list[str]:
    changes: list[str] = []
    for path in iter_text_files():
        original = read_text(path)
        updated = original
        for old, new in REPLACEMENTS:
            updated = updated.replace(old, new)
        if updated != original:
            rel = path.relative_to(ROOT).as_posix()
            changes.append(f"EDIT {rel}")
            if write:
                write_text(path, updated)
    return changes


def normalize_composer(write: bool) -> list[str]:
    path = ROOT / "composer.json"
    if not path.is_file():
        return []
    try:
        data = json.loads(read_text(path))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"composer.json is invalid JSON: {exc}") from exc

    original = json.dumps(data, sort_keys=True, ensure_ascii=False)
    data["name"] = data.get("name") or "logandplay/opus"
    data["description"] = "OPUS framework core - As Simple As Possible"
    autoload = data.setdefault("autoload", {})
    psr4 = autoload.setdefault("psr-4", {})
    # Remove stale path/identity entries and keep the modern source root.
    for key in list(psr4.keys()):
        if key.lower().startswith("asap"):
            del psr4[key]
    psr4["Opus\\"] = "Opus/"
    autoload["classmap"] = ["Opus/"]

    updated = json.dumps(data, indent=4, ensure_ascii=False) + "\n"
    if json.dumps(data, sort_keys=True, ensure_ascii=False) == original:
        return []
    if write:
        write_text(path, updated)
    return ["EDIT composer.json autoload"]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="OPUS reborn naming cleanup P0")
    parser.add_argument("--write", action="store_true", help="Apply changes. Without this flag, only prints the plan.")
    args = parser.parse_args(argv)

    try:
        ensure_git_clean(args.write)
        changes: list[str] = []
        changes.extend(rename_directories(args.write))
        changes.extend(replace_in_files(args.write))
        changes.extend(normalize_composer(args.write))
    except RuntimeError as exc:
        print(f"P0_OPUS_REBORN_CLEANUP_ERROR: {exc}")
        return 1

    print("P0_OPUS_REBORN_CLEANUP_PLAN" if not args.write else "P0_OPUS_REBORN_CLEANUP_APPLIED")
    if changes:
        for change in changes:
            print(change)
    else:
        print("NO_CHANGE")
    if args.write:
        print("P0_OPUS_REBORN_CLEANUP_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
