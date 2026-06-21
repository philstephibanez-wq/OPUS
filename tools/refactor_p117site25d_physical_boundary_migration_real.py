#!/usr/bin/env python3
"""
P117SITE25D — real physical OPUS FRONT / MIDDLE / BACK / COMMON migration.

This runner moves every known legacy framework/Opus root directory into one
explicit boundary. It is intentionally strict:
- no silent fallback;
- no automatic COMMON catch-all;
- unknown root directory = hard failure before mutation;
- every move is logged;
- after mutation, only FRONT/MIDDLE/BACK/COMMON and approved root files may remain.
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

BOUNDARY_ROOTS = {"FRONT", "MIDDLE", "BACK", "COMMON"}
ROOT_FILES = {"README.md", "BOUNDARY_MAP.json", "ARCHITECTURE_BOUNDARIES.md"}

# Explicit architectural map. Never auto-place unknown directories in COMMON.
BOUNDARY_MAP: Dict[str, str] = {
    # FRONT: representation only.
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

    # MIDDLE: secure transport, route/API orchestration and mandatory FSM gate.
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

    # BACK: business processing, data access, runners and integrations.
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

    # COMMON: strict shared language and pure primitives only, never a catch-all.
    "Autoload": "COMMON/Autoload",
    "Cache": "COMMON/Cache",
    "Compatibility": "COMMON/Compatibility",
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
}

LAYER_READMES = {
    "FRONT": """# OPUS FRONT\n\nRepresentation boundary only.\n\nAllowed: views, layouts, sections, components, forms, menus, renderers, themes, assets and API clients.\n\nForbidden: business services, repositories, database access, runners, jobs, workers, system calls, security decisions and FSM execution.\n""",
    "MIDDLE": """# OPUS MIDDLE\n\nSecure transport and orchestration boundary.\n\nAllowed: routing, request/response contracts, API gateway, middleware pipeline, ACL, SSO, CSRF, session, audit, rate limiting and mandatory FSM gates.\n\nForbidden: UI rendering, business services, repositories, direct domain persistence, runners, jobs and workers.\n""",
    "MIDDLE/FSM": """# OPUS MIDDLE FSM\n\nMandatory processor boundary.\n\nEvery FRONT intent, MIDDLE route/API dispatch and BACK action must pass through an explicit FSM signal, state, action and transition.\n\nNo processing path bypasses the FSM.\n""",
    "BACK": """# OPUS BACK\n\nBusiness processing boundary.\n\nAllowed: modules, actions, services, repositories, validators, policies, runners, jobs, workers, database gateways and external adapters.\n\nForbidden: HTML rendering, frontend components, navigation presentation and direct UI concerns.\n""",
    "COMMON": """# OPUS COMMON\n\nStrict shared language only.\n\nAllowed: contracts, DTOs, value objects, typed errors, results, enums, identifiers, assertions, pure helpers and technical primitives shared by at least two boundaries.\n\nForbidden: rendering, route dispatch, security policy implementation, business workflows, repositories, database gateways, runners, jobs, workers and app-specific logic.\n""",
}

ARCHITECTURE_DOC = """# OPUS physical architecture boundaries\n\nP117SITE25D physically enforces the OPUS framework tree. Only the following root directories are valid under `framework/Opus`: `FRONT`, `MIDDLE`, `BACK`, `COMMON`.\n\n## Package diagram\n\n```mermaid\nclassDiagram\n    namespace FRONT {\n      class View\n      class Layout\n      class Section\n      class Component\n      class Form\n      class Menu\n      class ApiClient\n    }\n    namespace MIDDLE {\n      class Router\n      class Request\n      class Response\n      class ApiGateway\n      class SecurityPipeline\n      class FsmGate\n    }\n    namespace BACK {\n      class Module\n      class Action\n      class Service\n      class Repository\n      class Runner\n      class Job\n      class Worker\n    }\n    namespace COMMON {\n      class Contract\n      class Dto\n      class ValueObject\n      class TypedError\n      class Result\n      class Identifier\n    }\n    View --> ApiClient\n    ApiClient --> Router\n    Router --> SecurityPipeline\n    SecurityPipeline --> FsmGate\n    FsmGate --> Action\n    Action --> Service\n    Service --> Repository\n    FRONT ..> COMMON\n    MIDDLE ..> COMMON\n    BACK ..> COMMON\n    FRONT --x BACK : forbidden direct call\n    FRONT --x Repository : forbidden\n    BACK --x Component : forbidden\n```
\n## End-to-end secure and clean flow\n\n```mermaid\nsequenceDiagram\n    actor User\n    participant FView as FRONT View/Component\n    participant Client as FRONT ApiClient\n    participant Router as MIDDLE Router\n    participant Security as MIDDLE SecurityPipeline\n    participant FSM as MIDDLE FSM Gate\n    participant Action as BACK Action\n    participant Service as BACK Service\n    participant Repo as BACK Repository\n    participant Common as COMMON DTO/TypedError\n    User->>FView: UI interaction\n    FView->>Client: typed intent\n    Client->>Common: build Request DTO\n    Client->>Router: HTTP/API request\n    Router->>Security: route match\n    Security->>FSM: request FSM transition\n    FSM->>FSM: signal + current_state + action + next_state\n    alt transition allowed\n      FSM->>Action: dispatch approved backend action\n      Action->>Service: execute business rule\n      Service->>Repo: read/write data\n      Repo-->>Service: data\n      Service-->>Action: result\n      Action->>Common: build Response DTO\n      Common-->>Client: response\n      Client-->>FView: view model update\n    else transition denied\n      FSM->>Common: typed error\n      Common-->>Client: denied response\n      Client-->>FView: explicit error\n    end\n```\n\n## Mandatory FSM transitions\n\n```mermaid\nstateDiagram-v2\n    [*] --> IntentReceived\n    IntentReceived --> RouteResolved: ROUTE_MATCHED\n    RouteResolved --> SecurityChecked: SECURITY_CHECK\n    SecurityChecked --> FsmApproved: FSM_ALLOW\n    SecurityChecked --> FsmDenied: FSM_DENY\n    FsmApproved --> BackActionDispatched: DISPATCH_BACK_ACTION\n    BackActionDispatched --> ResponseBuilt: BUILD_RESPONSE\n    ResponseBuilt --> FrontUpdated: RETURN_VIEWMODEL\n    FsmDenied --> ErrorResponseBuilt: BUILD_TYPED_ERROR\n    ErrorResponseBuilt --> FrontUpdated\n    FrontUpdated --> [*]\n```\n\n## COMMON anti-catch-all rule\n\n`COMMON` is never a parking lot. A class may enter `COMMON` only when it is a stable shared language element with no rendering, no routing dispatch, no security policy implementation, no business workflow, no database access, no runner/job/worker and no app-specific logic.\n"""


def run(cmd: List[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def root_dirs() -> List[str]:
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_dir())


def root_files() -> List[str]:
    return sorted(path.name for path in OPUS_ROOT.iterdir() if path.is_file())


def git_status() -> str:
    proc = run(["git", "status", "--short"])
    if proc.returncode != 0:
        raise RuntimeError(proc.stdout.strip() or "git status failed")
    return proc.stdout.strip()


def unknown_root_dirs() -> List[str]:
    return [name for name in root_dirs() if name not in BOUNDARY_ROOTS and name not in BOUNDARY_MAP]


def unexpected_root_files() -> List[str]:
    return [name for name in root_files() if name not in ROOT_FILES]


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def merge_move(src: Path, dst: Path) -> None:
    if not src.exists():
        return
    dst.parent.mkdir(parents=True, exist_ok=True)
    if not dst.exists():
        shutil.move(str(src), str(dst))
        return
    if not dst.is_dir():
        raise RuntimeError(f"TARGET_EXISTS_NOT_DIRECTORY: {dst}")
    for child in sorted(src.iterdir(), key=lambda item: item.name.lower()):
        target = dst / child.name
        if target.exists():
            if child.is_dir() and target.is_dir():
                merge_move(child, target)
            else:
                raise RuntimeError(f"BOUNDARY_MOVE_COLLISION: {child} -> {target}")
        else:
            shutil.move(str(child), str(target))
    src.rmdir()


def ensure_safe_before_mutation() -> None:
    if not OPUS_ROOT.exists():
        raise RuntimeError(f"OPUS_ROOT_NOT_FOUND: {OPUS_ROOT}")
    unknown = unknown_root_dirs()
    if unknown:
        details = "\n".join(f"- framework/Opus/{name}" for name in unknown)
        raise RuntimeError("UNKNOWN_ROOT_DIRECTORIES_REFUSED\n" + details)
    bad_files = unexpected_root_files()
    if bad_files:
        details = "\n".join(f"- framework/Opus/{name}" for name in bad_files)
        raise RuntimeError("UNEXPECTED_ROOT_FILES_REFUSED\n" + details)


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


def write_boundary_docs() -> None:
    for relative, content in LAYER_READMES.items():
        write_text(OPUS_ROOT / relative / "README.md", content)
    payload = {
        "schema": "OPUS_FRAMEWORK_BOUNDARY_MAP_V3",
        "fsm_processor": "mandatory",
        "common_rule": "strict shared language only; never a catch-all",
        "legacy_root_directory_map": BOUNDARY_MAP,
        "allowed_root_directories": sorted(BOUNDARY_ROOTS),
    }
    write_text(OPUS_ROOT / "BOUNDARY_MAP.json", json.dumps(payload, indent=2, ensure_ascii=False) + "\n")
    write_text(OPUS_ROOT / "ARCHITECTURE_BOUNDARIES.md", ARCHITECTURE_DOC)


def composer_dump_autoload() -> None:
    proc = run(["cmd", "/d", "/c", "composer", "dump-autoload"])
    if proc.returncode != 0:
        raise RuntimeError("COMPOSER_DUMP_AUTOLOAD_FAILED\n" + proc.stdout.strip())
    print(proc.stdout.strip())


def print_plan() -> None:
    print("P117SITE25D_PHYSICAL_BOUNDARY_MIGRATION_PLAN")
    print("CURRENT_ROOT_DIRS=" + ", ".join(root_dirs()))
    for name in root_dirs():
        if name in BOUNDARY_ROOTS:
            continue
        target = BOUNDARY_MAP.get(name)
        if target:
            print(f"MOVE framework/Opus/{name} -> framework/Opus/{target}")
    unknown = unknown_root_dirs()
    if unknown:
        print("UNKNOWN_ROOT_DIRECTORIES_REFUSED")
        for name in unknown:
            print(f"- framework/Opus/{name}")


def apply() -> None:
    # The migration itself creates many Git moves. It may be run on a clean tree or
    # on the prior delivered P117 boundary skeleton. It still refuses unknown inputs.
    ensure_safe_before_mutation()
    for root in sorted(BOUNDARY_ROOTS):
        (OPUS_ROOT / root).mkdir(parents=True, exist_ok=True)
    for name, target in sorted(BOUNDARY_MAP.items(), key=lambda kv: kv[0].lower()):
        src = OPUS_ROOT / name
        if not src.exists():
            continue
        dst = OPUS_ROOT / target
        print(f"OPUS_BOUNDARY_MOVE: {src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")
        merge_move(src, dst)
    write_boundary_docs()
    patch_composer_classmap()
    composer_dump_autoload()
    remaining = [name for name in root_dirs() if name not in BOUNDARY_ROOTS]
    if remaining:
        raise RuntimeError("LEGACY_ROOT_DIRECTORIES_REMAIN\n" + "\n".join(remaining))
    print("FINAL_ROOT_DIRS=" + ", ".join(root_dirs()))
    print("P117SITE25D_PHYSICAL_BOUNDARY_MIGRATION_REAL_OK")


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
    except Exception as exc:
        print(str(exc))
        return 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
