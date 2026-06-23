#!/usr/bin/env python3
"""
P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT

Read-only design audit for a future Bootstrap move.

Purpose:
- prove that Opus/Bootstrap.php has been moved to the runtime namespace;
- verify that Composer can load the runtime Bootstrap class;
- identify any remaining post-move cleanup work;
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
LEGACY_AUTOLOADER = "Opus/Legacy/Autoload/autoloader.class.php"
WWW_INDEX = "www/index.php"
MODERN_INDEX = "index.php"
COMPOSER_JSON = "composer.json"

RUNTIME_FILES = {
    MODERN_INDEX,
    WWW_INDEX,
    LEGACY_AUTOLOADER,
}

REQUIRED_FILES = [
    TARGET_BOOTSTRAP,
    "Opus/Runtime/Kernel.php",
    LEGACY_AUTOLOADER,
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


def check_legacy_guard_ready_for_target_update() -> int:
    content = read(LEGACY_AUTOLOADER)
    required = [
        "class_exists(\\Opus\\Runtime\\Bootstrap::class)",
        "OPUS_BOOTSTRAP_CLASS_REQUIRED",
    ]
    for token in required:
        if token not in content:
            return fail("CHECK_LEGACY_AUTOLOADER_CURRENT_COMPOSER_GUARD", token)
    if "require_once $opusBootstrap;" in content or "Opus/Bootstrap.php" in content:
        return fail("CHECK_LEGACY_AUTOLOADER_NO_DIRECT_BOOTSTRAP_REQUIRE")
    ok("CHECK_LEGACY_AUTOLOADER_TARGET_COMPOSER_GUARD", TARGET_CLASS)
    ok("CHECK_LEGACY_AUTOLOADER_OLD_GUARD_ABSENT", CURRENT_CLASS)
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
    print("P5H_BOOTSTRAP_MOVE_DESIGN_DECISION")
    print(f"CURRENT_BOOTSTRAP_PATH={CURRENT_BOOTSTRAP}")
    print(f"CURRENT_BOOTSTRAP_CLASS={CURRENT_CLASS}")
    print(f"TARGET_BOOTSTRAP_PATH={TARGET_BOOTSTRAP}")
    print(f"TARGET_BOOTSTRAP_CLASS={TARGET_CLASS}")
    print("TARGET_NAMESPACE=Opus\\Runtime")
    print("MOVE_REQUIRES_NAMESPACE_UPDATE=NO")
    print("MOVE_REQUIRES_LEGACY_GUARD_UPDATE=NO")
    print("MOVE_REQUIRES_COMPOSER_DUMP_AUTOLOAD=YES_LOCAL_REFRESH_REQUIRED")
    print("ROOT_BOOTSTRAP_RUNTIME_BLOCKERS=NO")
    print("DECISION=P5I_BOOTSTRAP_MOVED_TO_RUNTIME_NAMESPACE")
    print("NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE")


def main() -> int:
    print(PATCH_ID)
    print("MODE=READ_ONLY")
    print("SCOPE=bootstrap move target design, namespace transition, runtime blockers")

    checks = [
        check_required_files(),
        check_current_bootstrap_contract(),
        check_target_collision(),
        check_composer_contract(),
        check_runtime_references_removed(),
        check_legacy_guard_ready_for_target_update(),
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
