#!/usr/bin/env python3
"""
P117SITE25C — physically enforce OPUS framework FRONT / MIDDLE / BACK / COMMON roots.

This script is intentionally strict:
- it refuses a dirty Git tree before mutation;
- it refuses unknown framework/Opus root directories;
- it never moves unknown directories to COMMON;
- it preserves legacy namespaces through Composer classmap entries;
- it requires --write before mutating files.
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

ALLOWED_ROOT_DIRS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ALLOWED_ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}

BOUNDARY_MAP: Dict[str, Dict[str, str]] = {
    "FRONT": {
        "Asset": "FRONT/Asset",
        "Assets": "FRONT/Asset",
        "Breadcrumb": "FRONT/Breadcrumb",
        "Component": "FRONT/Component",
        "Css": "FRONT/Css",
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
        "Acl": "MIDDLE/Acl",
        "Auth": "MIDDLE/Auth",
        "Controller": "MIDDLE/Controller",
        "Cookie": "MIDDLE/Http/Cookie",
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
        "Url": "MIDDLE/Url",
    },
    "BACK": {
        "Action": "BACK/Action",
        "Admin": "BACK/Admin",
        "Application": "BACK/Application",
        "Command": "BACK/Command",
        "Console": "BACK/Console",
        "Database": "BACK/Database",
        "Ftp": "BACK/Ftp",
        "Job": "BACK/Job",
        "Mail": "BACK/Mail",
        "Model": "BACK/Model",
        "Module": "BACK/Module",
        "Package": "BACK/Package",
        "PublicSite": "BACK/PublicSite",
        "RefBook": "BACK/RefBook",
        "Runner": "BACK/Runner",
        "Runtime": "BACK/Runtime",
        "Scaffold": "BACK/Scaffold",
        "Site": "BACK/Site",
        "Smtp": "BACK/Smtp",
        "Worker": "BACK/Worker",
    },
    "COMMON": {
        "Autoload": "COMMON/Autoload",
        "Cache": "COMMON/Cache",
        "Config": "COMMON/Config",
        "Contract": "COMMON/Contract",
        "Core": "COMMON/Core",
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

LAYER_READMES = {
    "FRONT": """# OPUS FRONT\n\nRepresentation boundary only: views, layouts, sections, components, forms, menus, renderers, themes, frontend assets and API clients.\n\nForbidden: business logic, repositories, database access, runners, jobs, workers, system calls.\n""",
    "MIDDLE": """# OPUS MIDDLE\n\nRouting, transport, request/response contracts, API gateway, security pipeline, ACL, SSO, CSRF, audit, rate limiting and FSM gates.\n\nForbidden: UI rendering, business services, repositories, database gateways, runners, jobs, workers.\n""",
    "MIDDLE/FSM": """# OPUS MIDDLE FSM\n\nFSM processor boundary. Every FRONT intent, MIDDLE route/API dispatch and BACK action must pass through an explicit FSM transition.\n""",
    "BACK": """# OPUS BACK\n\nBusiness processing boundary: modules, actions, services, repositories, validators, policies, runners, jobs, workers and external adapters.\n\nForbidden: HTML rendering, frontend components, navigation presentation.\n""",
    "COMMON": """# OPUS COMMON\n\nStrict shared language only: contracts, DTOs, value objects, typed errors, results, enums, identifiers, assertions and pure technical primitives.\n\nForbidden: rendering, routing dispatch, security policy, business workflow, repositories, database gateways, runners, jobs, workers.\n""",
}

MERMAID_DOC = """# OPUS framework physical boundaries\n\n## Package diagram\n\n```mermaid\nclassDiagram\n    namespace FRONT {\n      class View\n      class Layout\n      class Section\n      class Component\n      class ApiClient\n    }\n    namespace MIDDLE {\n      class Router\n      class Request\n      class Response\n      class ApiGateway\n      class FsmGate\n      class SecurityPipeline\n    }\n    namespace BACK {\n      class Module\n      class Action\n      class Service\n      class Repository\n      class Runner\n      class Job\n    }\n    namespace COMMON {\n      class Contract\n      class Dto\n      class ValueObject\n      class TypedError\n      class Result\n    }\n    View --> ApiClient\n    ApiClient --> Router\n    Router --> FsmGate\n    FsmGate --> Action\n    Action --> Service\n    Service --> Repository\n```\n\n## End-to-end sequence\n\n```mermaid\nsequenceDiagram\n    actor User\n    participant Front as FRONT View/Component\n    participant Client as FRONT ApiClient\n    participant Route as MIDDLE Router\n    participant Security as MIDDLE SecurityPipeline\n    participant FSM as MIDDLE FSM Gate\n    participant Action as BACK Action\n    participant Service as BACK Service\n    participant Repo as BACK Repository\n    User->>Front: interaction\n    Front->>Client: typed intent\n    Client->>Route: request\n    Route->>Security: route match\n    Security->>FSM: transition request\n    FSM->>FSM: signal/state/action decision\n    alt allowed\n      FSM->>Action: dispatch backend action\n      Action->>Service: execute business rule\n      Service->>Repo: access data\n      Repo-->>Service: data\n      Service-->>Action: result\n      Action-->>Client: response DTO\n      Client-->>Front: view model\n    else denied\n      FSM-->>Client: typed error\n      Client-->>Front: explicit denial\n    end\n```\n\n## FSM transition graph\n\n```mermaid\nstateDiagram-v2\n    [*] --> IntentReceived\n    IntentReceived --> RouteResolved: ROUTE_MATCHED\n    RouteResolved --> SecurityChecked: SECURITY_CHECK\n    SecurityChecked --> FsmApproved: FSM_ALLOW\n    SecurityChecked --> FsmDenied: FSM_DENY\n    FsmApproved --> BackActionDispatched: DISPATCH_BACK_ACTION\n    BackActionDispatched --> ResponseBuilt: BUILD_RESPONSE\n    ResponseBuilt --> Rendered: RETURN_VIEWMODEL\n    FsmDenied --> ErrorResponseBuilt: BUILD_TYPED_ERROR\n    ErrorResponseBuilt --> Rendered\n```\n"""


def run(cmd: List[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


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
        raise RuntimeError(f"OPUS_ROOT_NOT_FOUND: {OPUS_ROOT}")
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_dir())


def unknown_root_dirs() -> List[str]:
    mapped = set(flatten_mapping())
    return [name for name in current_root_dirs() if name not in ALLOWED_ROOT_DIRS and name not in mapped]


def unexpected_root_files() -> List[str]:
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_file() and path.name not in ALLOWED_ROOT_FILES)


def ensure_clean_tree() -> None:
    status = git_status()
    if status:
        raise RuntimeError("GIT_TREE_NOT_CLEAN_BEFORE_P117SITE25C\n" + status)


def merge_move(src: Path, dst: Path) -> None:
    if not src.exists():
        return
    dst.parent.mkdir(parents=True, exist_ok=True)
    if not dst.exists():
        shutil.move(str(src), str(dst))
        return
    if not dst.is_dir():
        raise RuntimeError(f"TARGET_EXISTS_NOT_DIRECTORY: {dst}")
    for child in sorted(src.iterdir(), key=lambda p: p.name.lower()):
        target = dst / child.name
        if target.exists():
            if child.is_dir() and target.is_dir():
                merge_move(child, target)
            else:
                raise RuntimeError(f"BOUNDARY_MOVE_COLLISION: {child} -> {target}")
        else:
            shutil.move(str(child), str(target))
    src.rmdir()


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def write_docs() -> None:
    for relative, content in LAYER_READMES.items():
        write_text(OPUS_ROOT / relative / "README.md", content)
    payload = {
        "schema": "OPUS_FRAMEWORK_BOUNDARY_MAP_V2",
        "fsm_processor": "mandatory",
        "common_rule": "strict shared language only; never a catch-all",
        "layers": BOUNDARY_MAP,
    }
    write_text(OPUS_ROOT / "BOUNDARY_MAP.json", json.dumps(payload, indent=2, ensure_ascii=False) + "\n")
    write_text(OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md", MERMAID_DOC)


def patch_composer_classmap() -> None:
    data = json.loads(COMPOSER_JSON.read_text(encoding="utf-8"))
    autoload = data.setdefault("autoload", {})
    classmap = autoload.setdefault("classmap", [])
    if not isinstance(classmap, list):
        raise RuntimeError("COMPOSER_CLASSMAP_NOT_LIST")
    for entry in ["framework/Opus/FRONT/", "framework/Opus/MIDDLE/", "framework/Opus/BACK/", "framework/Opus/COMMON/"]:
        if entry not in classmap:
            classmap.append(entry)
    COMPOSER_JSON.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n", encoding="utf-8", newline="\n")


def composer_dump_autoload() -> None:
    proc = run(["cmd", "/d", "/c", "composer", "dump-autoload"])
    if proc.returncode != 0:
        raise RuntimeError("COMPOSER_DUMP_AUTOLOAD_FAILED\n" + proc.stdout.strip())
    print(proc.stdout.strip())


def print_plan() -> None:
    print("P117SITE25C_PHYSICAL_BOUNDARY_REFACTOR_PLAN")
    flat = flatten_mapping()
    for name in current_root_dirs():
        if name in ALLOWED_ROOT_DIRS:
            continue
        if name in flat:
            print(f"MOVE framework/Opus/{name} -> framework/Opus/{flat[name]}")
    unknown = unknown_root_dirs()
    if unknown:
        print("UNKNOWN_ROOT_DIRECTORIES_REFUSED")
        for name in unknown:
            print(f"- framework/Opus/{name}")
    bad_files = unexpected_root_files()
    if bad_files:
        print("UNEXPECTED_ROOT_FILES_REFUSED")
        for name in bad_files:
            print(f"- framework/Opus/{name}")


def apply() -> None:
    ensure_clean_tree()
    unknown = unknown_root_dirs()
    if unknown:
        raise RuntimeError("UNKNOWN_ROOT_DIRECTORIES_REFUSED\n" + "\n".join(unknown))
    bad_files = unexpected_root_files()
    if bad_files:
        raise RuntimeError("UNEXPECTED_ROOT_FILES_REFUSED\n" + "\n".join(bad_files))

    for layer in sorted(ALLOWED_ROOT_DIRS):
        (OPUS_ROOT / layer).mkdir(parents=True, exist_ok=True)

    for name, target in sorted(flatten_mapping().items()):
        src = OPUS_ROOT / name
        if src.exists():
            dst = OPUS_ROOT / target
            print(f"OPUS_BOUNDARY_MOVE: {src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")
            merge_move(src, dst)

    write_docs()
    patch_composer_classmap()
    composer_dump_autoload()

    remaining = [name for name in current_root_dirs() if name not in ALLOWED_ROOT_DIRS]
    if remaining:
        raise RuntimeError("LEGACY_ROOT_DIRECTORIES_REMAIN\n" + "\n".join(remaining))

    print("P117SITE25C_FRONT_MIDDLE_BACK_COMMON_PHYSICAL_TREE_OK")


def main(argv: Iterable[str]) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--write", action="store_true")
    args = parser.parse_args(list(argv))
    try:
        print_plan()
        if not args.write:
            print("DRY_RUN_ONLY: add --write to apply")
            return 0
        apply()
        return 0
    except Exception as exc:  # noqa: BLE001
        print(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
