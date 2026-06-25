#!/usr/bin/env python3
"""
P6F0B5 site scaffold contract audit.

Read-only audit for a generated OPUS site.

Checks the generated create:site output against the current page contract:

    Route -> Controller/Action -> FSM -> ACL -> ViewModel -> Layout/Template

This script is intentionally conservative:
- it never modifies files;
- it reports explicit blocking failures;
- it reports starter-runtime review points without failing the audit.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any


def ok(message: str) -> None:
    print(f"CHECK_{message}=OK")


def fail(message: str) -> None:
    print(f"CHECK_{message}=FAIL")


def review(message: str) -> None:
    print(f"CHECK_{message}=REVIEW")


def load_json(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"JSON_INVALID: {path.as_posix()} :: {exc}") from exc

    if not isinstance(data, dict):
        raise RuntimeError(f"JSON_ROOT_NOT_OBJECT: {path.as_posix()}")

    return data


def find_repo_root(start: Path) -> Path:
    current = start.resolve()
    for candidate in [current, *current.parents]:
        if (candidate / "composer.json").is_file() and (candidate / "Opus").is_dir():
            return candidate
    raise RuntimeError("OPUS_REPO_ROOT_NOT_FOUND")


def require_path(path: Path, label: str, blocking: list[str]) -> None:
    if path.exists():
        ok(label)
    else:
        fail(label)
        blocking.append(label)


def collect_json_values_by_key(value: Any, key: str) -> list[Any]:
    found: list[Any] = []
    if isinstance(value, dict):
        for current_key, current_value in value.items():
            if current_key == key:
                found.append(current_value)
            found.extend(collect_json_values_by_key(current_value, key))
    elif isinstance(value, list):
        for item in value:
            found.extend(collect_json_values_by_key(item, key))
    return found


def normalized_routes(config: dict[str, Any]) -> list[dict[str, Any]]:
    routes = config.get("routes")
    if not isinstance(routes, list):
        raise RuntimeError("ROUTES_REGISTRY_ROUTES_NOT_LIST")

    output: list[dict[str, Any]] = []
    for index, route in enumerate(routes):
        if not isinstance(route, dict):
            raise RuntimeError(f"ROUTE_ENTRY_NOT_OBJECT:{index}")
        output.append(route)
    return output


def main(argv: list[str]) -> int:
    print("P6F0B5_SITE_SCAFFOLD_CONTRACT_AUDIT")
    print("MODE=READ_ONLY")

    if len(argv) < 2 or argv[1].strip() == "":
        print("USAGE=python tools/audits/audit_p6f0b5_site_scaffold_contract.py SITE_ID")
        print("DECISION=P6F0B5_SITE_ID_REQUIRED")
        return 1

    site_id = argv[1].strip()
    print(f"SITE_ID={site_id}")

    try:
        repo_root = find_repo_root(Path.cwd())
    except RuntimeError as exc:
        print(str(exc))
        print("DECISION=P6F0B5_REPO_ROOT_MISSING")
        return 1

    site_root = repo_root / "sites" / site_id
    blocking: list[str] = []
    reviews: list[str] = []

    require_path(site_root, "SITE_ROOT_EXISTS", blocking)
    if blocking:
        print("BLOCKING_TOTAL=1")
        print("REVIEW_TOTAL=0")
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_FAIL")
        return 1

    required_paths = {
        "OPUS_SITE_JSON_EXISTS": site_root / "opus-site.json",
        "APPLICATION_CONFIG_ROUTES_EXISTS": site_root / "application/config/routes.json",
        "APPLICATION_CONFIG_FSM_EXISTS": site_root / "application/config/fsm.json",
        "APPLICATION_CONFIG_PAGES_EXISTS": site_root / "application/config/pages.json",
        "APPLICATION_CONFIG_MENU_EXISTS": site_root / "application/config/menu.json",
        "APPLICATION_CONFIG_SITE_EXISTS": site_root / "application/config/site.json",
        "RESOURCES_I18N_FR_EXISTS": site_root / "resources/i18n/fr.json",
        "PUBLIC_INDEX_EXISTS": site_root / "public/index.php",
        "COMMON_LAYOUT_SCORE_EXISTS": site_root / "application/common/templates/layout.score",
    }

    for label, path in required_paths.items():
        require_path(path, label, blocking)

    if blocking:
        print(f"BLOCKING_TOTAL={len(blocking)}")
        print("REVIEW_TOTAL=0")
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_FAIL")
        return 1

    try:
        routes_config = load_json(site_root / "application/config/routes.json")
        fsm_config = load_json(site_root / "application/config/fsm.json")
        opus_site = load_json(site_root / "opus-site.json")
        routes = normalized_routes(routes_config)
    except RuntimeError as exc:
        print(str(exc))
        print("BLOCKING_TOTAL=1")
        print("REVIEW_TOTAL=0")
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_FAIL")
        return 1

    if opus_site.get("contract") == "OPUS_SITE_APPLICATION_V1":
        ok("OPUS_SITE_CONTRACT")
    else:
        fail("OPUS_SITE_CONTRACT")
        blocking.append("OPUS_SITE_CONTRACT")

    if routes_config.get("contract") == "OPUS_ROUTE_REGISTRY_V1":
        ok("ROUTE_REGISTRY_CONTRACT")
    else:
        fail("ROUTE_REGISTRY_CONTRACT")
        blocking.append("ROUTE_REGISTRY_CONTRACT")

    if routes:
        ok("ROUTE_REGISTRY_NOT_EMPTY")
    else:
        fail("ROUTE_REGISTRY_NOT_EMPTY")
        blocking.append("ROUTE_REGISTRY_NOT_EMPTY")

    fsm_routes = {
        str(value)
        for value in collect_json_values_by_key(fsm_config, "route")
        if isinstance(value, str)
    }

    required_route_fields = ["id", "path", "page", "controller", "action", "template", "acl", "fsm_state"]

    for route in routes:
        route_id = str(route.get("id", "UNKNOWN_ROUTE"))
        route_label = route_id.upper().replace(".", "_").replace("-", "_")
        missing_fields = [field for field in required_route_fields if not route.get(field)]

        if missing_fields:
            fail(f"ROUTE_{route_label}_FIELDS")
            print(f"ROUTE_{route_id}_MISSING_FIELDS={','.join(missing_fields)}")
            blocking.append(f"ROUTE_{route_id}_FIELDS")
            continue

        ok(f"ROUTE_{route_label}_FIELDS")

        page = str(route["page"])
        controller = str(route["controller"])
        template = str(route["template"]).replace("\\", "/")
        page_root = site_root / "application/pages" / page

        expected_paths = {
            f"ROUTE_{route_label}_PAGE_ROOT": page_root,
            f"ROUTE_{route_label}_PAGE_JSON": page_root / "page.json",
            f"ROUTE_{route_label}_CONTROLLER": page_root / "controllers" / f"{controller}.php",
            f"ROUTE_{route_label}_SERVICE": page_root / "services" / f"{page}PageService.php",
            f"ROUTE_{route_label}_VIEWMODEL": page_root / "view-models" / f"{page}PageViewModel.php",
            f"ROUTE_{route_label}_TEMPLATE": site_root / template,
            f"ROUTE_{route_label}_ACL_DIR": page_root / "acl",
            f"ROUTE_{route_label}_MODEL_DIR": page_root / "models",
        }

        for label, path in expected_paths.items():
            require_path(path, label, blocking)

        if route_id in fsm_routes:
            ok(f"ROUTE_{route_label}_FSM_LINK")
        else:
            fail(f"ROUTE_{route_label}_FSM_LINK")
            blocking.append(f"ROUTE_{route_id}_FSM_LINK")

    public_index = (site_root / "public/index.php").read_text(encoding="utf-8", errors="replace")

    if "ScoreTemplateRenderer" in public_index:
        ok("PUBLIC_INDEX_USES_SCORE_RENDERER")
    else:
        fail("PUBLIC_INDEX_USES_SCORE_RENDERER")
        blocking.append("PUBLIC_INDEX_USES_SCORE_RENDERER")

    if "routes.json" in public_index:
        ok("PUBLIC_INDEX_READS_DECLARED_ROUTES")
    else:
        fail("PUBLIC_INDEX_READS_DECLARED_ROUTES")
        blocking.append("PUBLIC_INDEX_READS_DECLARED_ROUTES")

    if "$renderer->render" in public_index:
        review("PUBLIC_INDEX_STARTER_RUNTIME_DIRECT_RENDERING")
        reviews.append("PUBLIC_INDEX_STARTER_RUNTIME_DIRECT_RENDERING")

    print(f"BLOCKING_TOTAL={len(blocking)}")
    print(f"REVIEW_TOTAL={len(reviews)}")

    if blocking:
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_FAIL")
        return 1

    if reviews:
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_OK_WITH_REVIEW")
    else:
        print("DECISION=P6F0B5_SITE_SCAFFOLD_CONTRACT_OK")

    print("P6F0B5_SITE_SCAFFOLD_CONTRACT_AUDIT_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
