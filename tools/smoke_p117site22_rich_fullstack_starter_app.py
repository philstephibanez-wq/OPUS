from __future__ import annotations

import json
import shutil
import subprocess
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP_ID = "demo-rich-fullstack"
APP_ROOT = ROOT / "sites" / APP_ID
PORT = "8792"
BASE_URL = f"http://127.0.0.1:{PORT}"


def run(cmd: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=ROOT, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)


def run_composer(args: list[str]) -> subprocess.CompletedProcess[str]:
    return run(["cmd", "/d", "/c", "composer", *args])


def cleanup() -> None:
    if APP_ROOT.exists():
        shutil.rmtree(APP_ROOT)


def assert_file(path: str) -> None:
    file_path = APP_ROOT / path
    if not file_path.is_file():
        raise AssertionError(f"MISSING_FILE: {path}")


def assert_dir(path: str) -> None:
    dir_path = APP_ROOT / path
    if not dir_path.is_dir():
        raise AssertionError(f"MISSING_DIR: {path}")


def read_url(url: str) -> str:
    with urllib.request.urlopen(url, timeout=10) as response:
        return response.read().decode("utf-8")


def wait_for_server(process: subprocess.Popen[str]) -> None:
    last_error: Exception | None = None
    for _ in range(40):
        if process.poll() is not None:
            raise RuntimeError("PHP_SERVER_EXITED_EARLY")
        try:
            read_url(BASE_URL + "/")
            return
        except Exception as exc:  # noqa: BLE001 - smoke reports exact failure later.
            last_error = exc
            time.sleep(0.25)
    raise RuntimeError(f"PHP_SERVER_NOT_READY: {last_error}")


def main() -> int:
    cleanup()
    print("CHECK_CLEANUP_BEFORE=OK")

    result = run_composer(["opus:create-application", "--", APP_ID, "--write"])
    if result.returncode != 0:
        print(result.stdout)
        return result.returncode
    if f"OPUS_CREATE_APPLICATION_WRITTEN: {APP_ID}" not in result.stdout:
        raise AssertionError("CREATE_APPLICATION_OUTPUT_MISSING")
    print("CHECK_CREATE_APPLICATION=OK")

    for path in [
        "frontend/views/home/home.view.json",
        "frontend/views/architecture/architecture.view.json",
        "frontend/views/catalog-index/catalog-index.view.json",
        "frontend/views/catalog-detail/catalog-detail.view.json",
        "frontend/views/components/components.view.json",
        "frontend/views/security/security.view.json",
        "frontend/views/backoffice/backoffice.view.json",
        "frontend/views/documentation/documentation.view.json",
        "frontend/layouts/public/public.layout.score",
        "frontend/layouts/backoffice/backoffice.layout.score",
        "frontend/sections/catalog-grid/catalog-grid.section.score",
        "middle/routes/routes.json",
        "middle/api/catalog.list.contract.json",
        "middle/security/security.pipeline.json",
        "backend/modules/catalog/module.opus.json",
        "backend/modules/catalog/catalog.items.json",
        "backend/api-endpoints/catalog-list.endpoint.json",
        "resources/i18n/fr.json",
        "resources/i18n/en.json",
        "resources/i18n/es.json",
        "public/index.php",
        "public/architecture/index.php",
        "public/catalog/index.php",
        "public/catalog/module-catalog/index.php",
        "public/api/catalog/index.php",
    ]:
        assert_file(path)
    print("CHECK_RICH_FULLSTACK_FILES=OK")

    for path in ["frontend", "middle", "backend", "public"]:
        assert_dir(path)
    print("CHECK_FRONT_MIDDLE_BACK_ROOTS=OK")

    routes = json.loads((APP_ROOT / "middle/routes/routes.json").read_text(encoding="utf-8"))
    expected_routes = ["/", "/architecture", "/catalog", "/catalog/module-catalog", "/components", "/security", "/backoffice", "/documentation"]
    for route in expected_routes:
        if route not in routes["routes"]:
            raise AssertionError(f"ROUTE_MISSING: {route}")
    print("CHECK_MULTIPLE_VIEWS_ROUTES=OK")

    app_contract = json.loads((APP_ROOT / "application.opus.json").read_text(encoding="utf-8"))
    if app_contract.get("front_contract") != "OPUS_FRONT_VIEWS_LAYOUTS_SECTIONS_COMPONENTS_V1":
        raise AssertionError("FRONT_CONTRACT_MISSING")
    if app_contract.get("middle_contract") != "OPUS_MIDDLE_ROUTING_TRANSPORT_SECURITY_V1":
        raise AssertionError("MIDDLE_CONTRACT_MISSING")
    if app_contract.get("back_contract") != "OPUS_BACK_BUSINESS_DATA_PROCESSING_V1":
        raise AssertionError("BACK_CONTRACT_MISSING")
    print("CHECK_FRONT_MIDDLE_BACK_CONTRACT=OK")

    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{PORT}", "-t", str(APP_ROOT / "public")],
        cwd=ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    try:
        wait_for_server(server)
        print("CHECK_INTERNAL_SERVER_STARTED=OK")

        checks = [
            ("/", "OPUS Fullstack Starter"),
            ("/architecture?lang=en", "Front / Middle / Back"),
            ("/catalog?lang=fr", "Module Catalogue actif"),
            ("/catalog/module-catalog?lang=es", "Módulo Catálogo"),
            ("/components?lang=fr", "Les composants standards appartiennent à OPUS"),
            ("/security?lang=fr", "API, FSM, ACL, SSO"),
            ("/backoffice?lang=fr", "Backoffice n’est pas backend"),
            ("/documentation?lang=fr", "Contrats générés"),
        ]
        for path, needle in checks:
            html = read_url(BASE_URL + path)
            if needle not in html:
                raise AssertionError(f"HTML_NEEDLE_MISSING: {path} :: {needle}")
        print("CHECK_INTERNAL_SERVER_HTML_ROUTES=OK")

        api_payload = read_url(BASE_URL + "/api/catalog?lang=en")
        api = json.loads(api_payload)
        if api.get("contract") != "OPUS_API_RESPONSE_V1":
            raise AssertionError("API_CONTRACT_MISSING")
        if len(api.get("items", [])) < 3:
            raise AssertionError("API_ITEMS_MISSING")
        print("CHECK_INTERNAL_SERVER_API_ROUTE=OK")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
        cleanup()
        print("CHECK_CLEANUP=OK")

    if APP_ROOT.exists():
        raise AssertionError("APP_ROOT_LEFT_AFTER_CLEANUP")

    print("P117SITE22_RICH_FULLSTACK_STARTER_APP_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
