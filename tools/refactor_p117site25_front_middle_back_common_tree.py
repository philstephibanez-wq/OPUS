#!/usr/bin/env python3
"""
P117SITE25 — physically reorganize framework/Opus into FRONT / MIDDLE / BACK / COMMON.

This migration is intentionally strict:
- it refuses a dirty git tree before touching files;
- it refuses unknown root framework directories;
- it does not hide unknown things in COMMON;
- it preserves existing PHP namespaces by adding Composer classmap entries;
- it requires --write to mutate the tree.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path
from typing import Dict, Iterable, List

ROOT = Path(__file__).resolve().parents[1]
OPUS_ROOT = ROOT / "framework" / "Opus"
COMPOSER_JSON = ROOT / "composer.json"

BOUNDARY_MAP: Dict[str, Dict[str, str]] = {
    "FRONT": {
        "Form": "FRONT/Form",
        "Javascript": "FRONT/Javascript",
        "Link": "FRONT/Link",
        "Menu": "FRONT/Menu",
        "Renderer": "FRONT/Renderer",
        "Template": "FRONT/Template",
        "Theme": "FRONT/Theme",
        "View": "FRONT/View",
    },
    "MIDDLE": {
        "Fsm": "MIDDLE/FSM/Engine",
        "Http": "MIDDLE/Http",
        "Request": "MIDDLE/Request",
        "Response": "MIDDLE/Response",
        "Rest": "MIDDLE/Rest",
        "Router": "MIDDLE/Router",
        "Routing": "MIDDLE/Routing",
        "Security": "MIDDLE/Security",
        "Server": "MIDDLE/Server",
        "Session": "MIDDLE/Session",
        "Uri": "MIDDLE/Uri",
    },
    "BACK": {
        "Console": "BACK/Console",
        "Database": "BACK/Database",
        "Ftp": "BACK/Ftp",
        "Mail": "BACK/Mail",
        "Model": "BACK/Model",
        "Module": "BACK/Module",
        "Package": "BACK/Package",
        "PublicSite": "BACK/PublicSite",
        "RefBook": "BACK/RefBook",
        "Runtime": "BACK/Runtime",
        "Scaffold": "BACK/Scaffold",
        "Site": "BACK/Site",
        "Smtp": "BACK/Smtp",
    },
    "COMMON": {
        "Date": "COMMON/Date",
        "Debug": "COMMON/Debug",
        "Directory": "COMMON/Directory",
        "Documentation": "COMMON/Documentation",
        "Event": "COMMON/Event",
        "Exception": "COMMON/Error/Exception",
        "File": "COMMON/File",
        "Helper": "COMMON/Helper",
        "I18n": "COMMON/I18n",
        "Json": "COMMON/Serialization/Json",
        "Language": "COMMON/Language",
        "Log": "COMMON/Log",
        "Lstsa": "COMMON/Lstsa",
        "Support": "COMMON/Support",
        "Validation": "COMMON/Validation",
        "Xml": "COMMON/Serialization/Xml",
    },
}

ALLOWED_ROOT_DIRS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ALLOWED_ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}

LAYER_READMES = {
    "FRONT": """# OPUS FRONT\n\nRepresentation boundary. Views, layouts, sections, components, forms, menus, renderers, themes and API clients live here.\n\nForbidden: business logic, database access, runners, jobs, workers, system calls.\n""",
    "MIDDLE": """# OPUS MIDDLE\n\nRouting, transport, security, request/response contracts and FSM gates live here.\n\nThe FSM is the mandatory processor boundary before BACK execution.\n\nForbidden: rendering UI, business logic, direct business data access.\n""",
    "BACK": """# OPUS BACK\n\nBusiness processing, modules, actions, services, repositories, validators, policies, runners, jobs, workers and external adapters live here.\n\nForbidden: HTML rendering, frontend components, navigation presentation.\n""",
    "COMMON": """# OPUS COMMON\n\nStrict shared language only. Contracts, DTOs, value objects, typed errors, results, enums, identifiers, assertions and pure technical primitives live here.\n\nForbidden: rendering, routing dispatch, security policy, business workflow, repositories, database gateways, runners, jobs, workers.\n""",
    "MIDDLE/FSM": """# OPUS MIDDLE FSM\n\nFSM processor boundary. Every FRONT intent, MIDDLE route/API dispatch and BACK action must pass through an explicit FSM transition.\n""",
}


def run(cmd: List[str], cwd: Path = ROOT) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(cwd), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def git_status() -> str:
    proc = run(["git", "status", "--short"])
    if proc.returncode != 0:
        raise RuntimeError(proc.stdout.strip() or "git status failed")
    return proc.stdout.strip()


def flatten_mapping() -> Dict[str, str]:
    out: Dict[str, str] = {}
    for group in BOUNDARY_MAP.values():
        out.update(group)
    return out


def current_root_dirs() -> List[str]:
    if not OPUS_ROOT.exists():
        raise RuntimeError(f"OPUS root not found: {OPUS_ROOT}")
    return sorted(p.name for p in OPUS_ROOT.iterdir() if p.is_dir())


def unknown_root_dirs() -> List[str]:
    mapped = set(flatten_mapping())
    unknown: List[str] = []
    for name in current_root_dirs():
        if name in ALLOWED_ROOT_DIRS:
            continue
        if name in mapped:
            continue
        unknown.append(name)
    return unknown


def ensure_clean_tree() -> None:
    status = git_status()
    if status:
        raise RuntimeError("GIT_TREE_NOT_CLEAN_BEFORE_BOUNDARY_REFACTOR\n" + status)


def merge_move(src: Path, dst: Path) -> None:
    if not src.exists():
        return
    if src.resolve() == dst.resolve():
        return
    dst.parent.mkdir(parents=True, exist_ok=True)
    if not dst.exists():
        shutil.move(str(src), str(dst))
        return
    if not dst.is_dir():
        raise RuntimeError(f"TARGET_EXISTS_NOT_DIRECTORY: {dst}")
    for child in list(src.iterdir()):
        target = dst / child.name
        if target.exists():
            if child.is_dir() and target.is_dir():
                merge_move(child, target)
            else:
                raise RuntimeError(f"BOUNDARY_MOVE_COLLISION: {child} -> {target}")
        else:
            shutil.move(str(child), str(target))
    src.rmdir()


def write_layer_readmes() -> None:
    for relative, content in LAYER_READMES.items():
        target = OPUS_ROOT / relative / "README.md"
        target.parent.mkdir(parents=True, exist_ok=True)
        if not target.exists():
            target.write_text(content, encoding="utf-8", newline="\n")


def write_boundary_map() -> None:
    payload = {
        "schema": "OPUS_FRAMEWORK_BOUNDARY_MAP_V1",
        "layers": BOUNDARY_MAP,
        "rules": {
            "front": "representation only",
            "middle": "routing transport security contracts fsm gates",
            "back": "business processing data runners jobs integrations",
            "common": "strict shared language only, never a catch-all",
            "fsm": "mandatory processor for every operation path",
        },
    }
    (OPUS_ROOT / "BOUNDARY_MAP.json").write_text(
        json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n"
    )


def patch_composer_classmap() -> bool:
    if not COMPOSER_JSON.exists():
        raise RuntimeError(f"composer.json not found: {COMPOSER_JSON}")
    data = json.loads(COMPOSER_JSON.read_text(encoding="utf-8"))
    autoload = data.setdefault("autoload", {})
    classmap = autoload.setdefault("classmap", [])
    if not isinstance(classmap, list):
        raise RuntimeError("composer.json autoload.classmap must be a list")
    required = [
        "framework/Opus/FRONT/",
        "framework/Opus/MIDDLE/",
        "framework/Opus/BACK/",
        "framework/Opus/COMMON/",
    ]
    changed = False
    for entry in required:
        if entry not in classmap:
            classmap.append(entry)
            changed = True
    if changed:
        COMPOSER_JSON.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")
    return changed


def composer_dump_autoload() -> None:
    proc = run(["cmd", "/d", "/c", "composer", "dump-autoload"])
    if proc.returncode != 0:
        raise RuntimeError("COMPOSER_DUMP_AUTOLOAD_FAILED\n" + proc.stdout.strip())
    print(proc.stdout.strip())


def print_plan() -> None:
    print("OPUS_BOUNDARY_REFACTOR_PLAN")
    for src_name, dst_rel in sorted(flatten_mapping().items()):
        src = OPUS_ROOT / src_name
        if src.exists():
            print(f"MOVE {src.relative_to(ROOT)} -> {(OPUS_ROOT / dst_rel).relative_to(ROOT)}")
    unknown = unknown_root_dirs()
    if unknown:
        print("UNKNOWN_ROOT_DIRECTORIES_REFUSED:")
        for name in unknown:
            print(f"- framework/Opus/{name}")


def apply_migration() -> None:
    ensure_clean_tree()
    unknown = unknown_root_dirs()
    if unknown:
        raise RuntimeError("UNKNOWN_ROOT_DIRECTORIES_REFUSED\n" + "\n".join(unknown))

    for layer in ALLOWED_ROOT_DIRS:
        (OPUS_ROOT / layer).mkdir(parents=True, exist_ok=True)

    for src_name, dst_rel in sorted(flatten_mapping().items()):
        src = OPUS_ROOT / src_name
        dst = OPUS_ROOT / dst_rel
        if src.exists():
            print(f"OPUS_BOUNDARY_MOVE: {src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")
            merge_move(src, dst)

    write_layer_readmes()
    write_boundary_map()
    patch_composer_classmap()
    composer_dump_autoload()

    print("P117SITE25_FRONT_MIDDLE_BACK_COMMON_TREE_REFACTOR_OK")


def main(argv: Iterable[str]) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true", help="Apply the physical reorganization")
    args = parser.parse_args(list(argv))

    try:
        print_plan()
        if not args.write:
            print("DRY_RUN_ONLY: add --write to apply")
            return 0
        apply_migration()
        return 0
    except Exception as exc:  # noqa: BLE001 - explicit CLI error path
        print(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
