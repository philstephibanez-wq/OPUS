#!/usr/bin/env python3
"""
P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT

Read-only audit for the stable Bootstrap runtime location after the move.

Purpose:
- prove that Opus/Bootstrap.php remains absent;
- prove that Opus/Runtime/Bootstrap.php owns the runtime Bootstrap class;
- verify that Composer can load the runtime Bootstrap class;
- never move files, rewrite sources, regenerate Composer output, or clean caches.
"""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

PATCH_ID = "P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT"
ROOT = Path.cwd()

CURRENT_BOOTSTRAP = "Opus/Bootstrap.php"
TARGET_BOOTSTRAP = "Opus/Runtime/Bootstrap.php"
CURRENT_CLASS = "Opus\\Runtime\\Bootstrap"
TARGET_CLASS = "Opus\\Runtime\\Bootstrap"
WWW_INDEX = "www/index.php"
MODERN_INDEX = "index.php"
COMPOSER_JSON = "composer.json"

RUNTIME_FILES = {
    MODERN_INDEX,
    WWW_INDEX,
}

REQUIRED_FILES = [
    TARGET_BOOTSTRAP,
    "Opus/Runtime/Kernel.php",
    WWW_INDEX,
    MODERN_INDEX,
    COMPOSER_JSON,
    "vendor/autoload.php",
]

CURRENT_BOOTSTRAP_TOKENS = [
    "namespace Opus\\Runtime;",
    "final class Bootstrap",
    "public static function run(string $rootDir): void",
    "private static function loadFramework(string $rootDir): void",
    "Request::fromGlobals($rootDir)",
    "Response::html(self::renderFatal($e), 500)->send();",
]

TARGET_BOOTSTRAP_DESIGN_TOKENS = [
    "namespace Opus\\Runtime;",
    "final class Bootstrap",
    "public static function run(string $rootDir): void",
    "private static function loadFramework(string $rootDir): void",
]


def fail(code: str, detail: str = "") -> int:
    print(f"{code}=FAIL" + (f" {detail}" if detail else ""))
    return 1


def ok(code: str, detail: str = "") -> None:
    print(f"{code}=OK" + (f" {detail}" if detail else ""))


def run_git(args: list[str]) -> list[str]:
    result = subprocess.run(["git", *args], cwd=ROOT, text=True, capture_output=True, check=False)
    if result.returncode != 0:
        raise RuntimeError(f"git {' '.join(args)} failed: {result.stderr.strip()}")
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8", errors="ignore")


def php_lint(rel: str) -> bool:
    result = subprocess.run(["php", "-l", rel], cwd=ROOT, text=True, capture_output=True, check=False)
    if result.returncode == 0:
        ok(f"CHECK_PHP_LINT_{rel.upper().replace('/', '_').replace('.', '_')}")
        return True
    if result.stdout.strip():
        print(result.stdout.strip())
    if result.stderr.strip():
        print(result.stderr.strip())
    return False


def check_required_files() -> int:
    missing = [rel for rel in REQUIRED_FILES if not (ROOT / rel).is_file()]
    if missing:
        return fail("CHECK_REQUIRED_FILES", ",".join(missing))
    ok("CHECK_REQUIRED_FILES")
    return 0


def check_current_bootstrap_contract() -> int:
    content = read(TARGET_BOOTSTRAP)
    for token in CURRENT_BOOTSTRAP_TOKENS:
        if token not in content:
            return fail("CHECK_TARGET_BOOTSTRAP_TOKEN", token)
    ok("CHECK_TARGET_BOOTSTRAP_CONTRACT")

    if not php_lint(TARGET_BOOTSTRAP):
        return fail("CHECK_TARGET_BOOTSTRAP_PHP_LINT", TARGET_BOOTSTRAP)
    return 0


def check_target_collision() -> int:
    if (ROOT / CURRENT_BOOTSTRAP).exists():
        return fail("CHECK_CURRENT_BOOTSTRAP_ABSENT", CURRENT_BOOTSTRAP)
    ok("CHECK_CURRENT_BOOTSTRAP_ABSENT", CURRENT_BOOTSTRAP)
    if not (ROOT / TARGET_BOOTSTRAP).is_file():
        return fail("CHECK_TARGET_BOOTSTRAP_PRESENT", TARGET_BOOTSTRAP)
    ok("CHECK_TARGET_BOOTSTRAP_PRESENT", TARGET_BOOTSTRAP)
    return 0


def check_composer_contract() -> int:
    data = json.loads(read(COMPOSER_JSON))
    autoload = data.get("autoload", {})
    psr4 = autoload.get("psr-4", {})
    classmap = autoload.get("classmap", [])

    if psr4.get("Opus\\") != "Opus/":
        return fail("CHECK_COMPOSER_PSR4_OPUS", str(psr4.get("Opus\\")))
    ok("CHECK_COMPOSER_PSR4_CAN_LOAD_TARGET_CLASS", f"{TARGET_CLASS} => {TARGET_BOOTSTRAP}")

    if "Opus/" not in classmap:
        return fail("CHECK_COMPOSER_CLASSMAP_OPUS", str(classmap))
    ok("CHECK_COMPOSER_CLASSMAP_OPUS", "Opus/")
    return 0


def check_runtime_references_removed() -> int:
    runtime_refs: list[str] = []
    for rel in RUNTIME_FILES:
        content = read(rel)
        if "Opus/Bootstrap.php" in content:
            runtime_refs.append(rel)
    if runtime_refs:
        return fail("CHECK_RUNTIME_DIRECT_BOOTSTRAP_PATH_REFS", ",".join(sorted(runtime_refs)))
    ok("CHECK_RUNTIME_DIRECT_BOOTSTRAP_PATH_REFS", "none")
    return 0


def check_legacy_runtime_absent() -> int:
    existing = [rel for rel in ["Opus/Legacy", "Opus/Legacy/Autoload/autoloader.class.php", "Opus/Legacy/Application/Application.class.php"] if (ROOT / rel).exists()]
    if existing:
        return fail("CHECK_LEGACY_RUNTIME_PATHS_ABSENT", ",".join(existing))
    ok("CHECK_LEGACY_RUNTIME_PATHS_ABSENT")
    return 0


def scan_class_and_path_references() -> int:
    files = run_git(["ls-files"])
    path_refs: list[str] = []
    current_class_refs: list[str] = []
    target_class_refs: list[str] = []

    for rel in files:
        if not rel.endswith((".php", ".py", ".md", ".json", ".cmd")):
            continue
        if rel.startswith(("vendor/", "var/", "tmp/")):
            continue
        try:
            content = read(rel)
        except OSError:
            continue
        if "Opus/Bootstrap.php" in content:
            path_refs.append(rel)
        if "Opus\\Runtime\\Bootstrap" in content or "Opus\\\\Bootstrap" in content:
            current_class_refs.append(rel)
        if "Opus\\Runtime\\Bootstrap" in content or "Opus\\\\Runtime\\\\Bootstrap" in content:
            target_class_refs.append(rel)

    print("BOOTSTRAP_MOVE_REFERENCE_SCAN")
    for rel in sorted(path_refs):
        print(f"PATH_REF={rel}")
    for rel in sorted(current_class_refs):
        print(f"CURRENT_CLASS_REF={rel}")
    for rel in sorted(target_class_refs):
        print(f"TARGET_CLASS_REF={rel}")

    blocking_path_refs = sorted(set(path_refs).intersection(RUNTIME_FILES))
    if blocking_path_refs:
        return fail("CHECK_BLOCKING_PATH_REFS", ",".join(blocking_path_refs))
    ok("CHECK_BLOCKING_PATH_REFS", "none")
    return 0


def print_design_decision() -> None:
    print()
    print("P5H_BOOTSTRAP_RUNTIME_LOCATION_DECISION")
    print(f"CURRENT_BOOTSTRAP_PATH={CURRENT_BOOTSTRAP}")
    print(f"LEGACY_BOOTSTRAP_CLASS_ABSENT_OR_SUPERSEDED={CURRENT_CLASS}")
    print(f"RUNTIME_BOOTSTRAP_PATH={TARGET_BOOTSTRAP}")
    print(f"RUNTIME_BOOTSTRAP_CLASS={TARGET_CLASS}")
    print("TARGET_NAMESPACE=Opus\\Runtime")
    print("RUNTIME_BOOTSTRAP_NAMESPACE_STABLE=YES")
    print("LEGACY_RUNTIME_BOUNDARY_PRESENT=NO")
    print("MOVE_REQUIRES_COMPOSER_DUMP_AUTOLOAD=YES_LOCAL_REFRESH_REQUIRED")
    print("ROOT_BOOTSTRAP_RUNTIME_BLOCKERS=NO")
    print("DECISION=BOOTSTRAP_RUNTIME_LOCATION_STABLE")
    print("NEXT_SAFE_STEP=P6B_ARCHIVE_P6A_MIGRATION_OR_REMOVE_STALE_LEGACY_AUDITS")


def main() -> int:
    print(PATCH_ID)
    print("MODE=READ_ONLY")
    print("SCOPE=stable bootstrap runtime location, namespace, composer, runtime blockers")

    checks = [
        check_required_files(),
        check_current_bootstrap_contract(),
        check_target_collision(),
        check_composer_contract(),
        check_runtime_references_removed(),
        check_legacy_runtime_absent(),
        scan_class_and_path_references(),
    ]
    if any(code != 0 for code in checks):
        print(f"{PATCH_ID}_FAIL")
        return 1

    print_design_decision()
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
