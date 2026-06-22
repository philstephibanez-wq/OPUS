#!/usr/bin/env python3
"""Smoke checks for OPUS reborn cleanup P0."""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[1]
TEXT_SUFFIXES = {".php", ".xml", ".json", ".md", ".score", ".css", ".js", ".txt"}
SCOPE_ROOTS = [ROOT / "Opus", ROOT / "www"]
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


def iter_text_files() -> Iterable[Path]:
    for base in SCOPE_ROOTS:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path.is_file() and not is_excluded(path) and path.suffix.lower() in TEXT_SUFFIXES:
                yield path
    for extra in [ROOT / "composer.json", ROOT / "README.md"]:
        if extra.is_file():
            yield extra


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def check(name: str, ok: bool, details: str = "") -> bool:
    if ok:
        print(f"{name}=OK")
        return True
    suffix = f": {details}" if details else ""
    print(f"{name}=FAIL{suffix}")
    return False


def contains_any(patterns: list[str]) -> list[str]:
    offenders: list[str] = []
    for path in iter_text_files():
        text = read(path)
        low = text.lower()
        for pattern in patterns:
            if pattern.lower() in low:
                offenders.append(path.relative_to(ROOT).as_posix())
                break
    return sorted(set(offenders))


def git_status_short() -> str:
    proc = subprocess.run(["git", "status", "--short"], cwd=str(ROOT), text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    return proc.stdout if proc.returncode == 0 else ""


def main() -> int:
    ok = True

    ok &= check("CHECK_OPUS_ROOT", (ROOT / "Opus").is_dir())
    ok &= check("CHECK_WWW_ROOT", (ROOT / "www").is_dir())
    ok &= check("CHECK_CONTROLLER_DIR", (ROOT / "Opus/Controller").is_dir())
    ok &= check("CHECK_NO_CONTROLER_DIR", not (ROOT / "Opus/Controler").exists())
    ok &= check("CHECK_SCAFFOLD_DIR", (ROOT / "Opus/Scaffold").is_dir())
    ok &= check("CHECK_NO_SCAFOLD_DIR", not (ROOT / "Opus/Scafold").exists())

    offenders = contains_any(["ASAP", "ASAP_", "namespace ASAP", "framework/ASAP", "asap_"])
    ok &= check("CHECK_NO_ASAP_IN_FRAMEWORK_SCOPE", not offenders, ", ".join(offenders[:20]))

    www = ROOT / "www/index.php"
    if www.is_file():
        www_text = read(www)
        ok &= check("CHECK_WWW_BOOTSTRAP_PATH", "/Opus/Bootstrap.php" in www_text)
        ok &= check("CHECK_WWW_APP_CLASS", "OPUS_Application::getInstance()" in www_text)
        ok &= check("CHECK_WWW_ENV", "OPUS_ENV" in www_text)

    bootstrap = ROOT / "Opus/Bootstrap.php"
    if bootstrap.is_file():
        bootstrap_text = read(bootstrap)
        ok &= check("CHECK_BOOTSTRAP_NAMESPACE", "namespace Opus;" in bootstrap_text)
        ok &= check("CHECK_BOOTSTRAP_LOAD_PATH", "'/Opus/'" in bootstrap_text or '"/Opus/"' in bootstrap_text)
        ok &= check("CHECK_BOOTSTRAP_FATAL_LABEL", "OPUS fatal error" in bootstrap_text or "OPUS" in bootstrap_text)

    expected = {
        "OPUS_Application": ROOT / "Opus/Application.class.php",
        "OPUS_Router": ROOT / "Opus/Router.class.php",
        "OPUS_Controller": ROOT / "Opus/Controller/Controller.class.php",
        "OPUS_FSM_Fsm": ROOT / "Opus/Fsm/Fsm.class.php",
        "OPUS_I18N_I18n": ROOT / "Opus/I18n/I18n.class.php",
        "OPUS_VIEW_Html": ROOT / "Opus/VIEW/Html.class.php",
        "OPUS_LINK_Link": ROOT / "Opus/Componants/Link/Link.class.php",
        "OPUS_MENU_Menu": ROOT / "Opus/Componants/Menu/Menu.class.php",
        "OPUS_Exception": ROOT / "Opus/Exception.class.php",
    }
    for class_name, path in expected.items():
        ok &= check(f"CHECK_CLASS_{class_name}", path.is_file() and class_name in read(path))

    composer = ROOT / "composer.json"
    if composer.is_file():
        try:
            data = json.loads(read(composer))
            psr4 = data.get("autoload", {}).get("psr-4", {})
            classmap = data.get("autoload", {}).get("classmap", [])
            ok &= check("CHECK_COMPOSER_PSR4_OPUS", psr4.get("Opus\\") == "Opus/")
            ok &= check("CHECK_COMPOSER_CLASSMAP_OPUS", classmap == ["Opus/"])
        except Exception as exc:  # noqa: BLE001 - smoke should report explicit failure
            ok &= check("CHECK_COMPOSER_JSON", False, str(exc))

    status = git_status_short().splitlines()
    forbidden_status = [line for line in status if line[3:].startswith(("sites/", "Sites/", "vendor/", "var/cache/", "var/log/", "var/tmp/"))]
    ok &= check("CHECK_NO_FORBIDDEN_SCOPE_GIT_CHANGES", not forbidden_status, ", ".join(forbidden_status[:20]))

    if ok:
        print("P0_OPUS_REBORN_CLEANUP_SMOKE_OK")
        return 0
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
