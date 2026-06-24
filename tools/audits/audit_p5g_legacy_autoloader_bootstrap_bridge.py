#!/usr/bin/env python3
"""
P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_AUDIT

Read-only audit for the removed legacy autoloader boundary.

Purpose:
- prove that www/index.php is Composer-only before OPUS_Application boot;
- prove that the legacy DirectoriesAutoloader runtime file is absent;
- prove that Opus/Legacy does not participate in runtime loading anymore;
- never rewrite source, caches, Composer output, or entrypoints.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_AUDIT"
ROOT = Path.cwd()
WWW_INDEX = "www/index.php"
RUNTIME_APPLICATION = "Opus/Runtime/Application.php"
LEGACY_ROOT = "Opus/Legacy"
LEGACY_AUTOLOADER = "Opus/Legacy/Autoload/autoloader.class.php"
LEGACY_APPLICATION = "Opus/Legacy/Application/Application.class.php"

WWW_COMPOSER_TOKENS = [
    "$composerAutoload = ROOT . '/vendor/autoload.php';",
    "throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . $composerAutoload);",
    "require_once $composerAutoload;",
]

WWW_FORBIDDEN_TOKENS = [
    "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';",
    "require_once ROOT . '/Opus/Legacy/Application/Application.class.php';",
    "require_once ROOT . '/Opus/Bootstrap.php';",
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
    missing = [rel for rel in [WWW_INDEX, RUNTIME_APPLICATION, "vendor/autoload.php"] if not (ROOT / rel).is_file()]
    if missing:
        return fail("CHECK_REQUIRED_FILES", ",".join(missing))
    ok("CHECK_REQUIRED_FILES")
    return 0


def check_legacy_files_absent() -> int:
    existing = [rel for rel in [LEGACY_AUTOLOADER, LEGACY_APPLICATION, LEGACY_ROOT] if (ROOT / rel).exists()]
    if existing:
        return fail("CHECK_LEGACY_RUNTIME_PATHS_ABSENT", ",".join(existing))
    ok("CHECK_LEGACY_RUNTIME_PATHS_ABSENT")
    return 0


def check_www_composer_only_entrypoint() -> int:
    content = read(WWW_INDEX)
    for token in WWW_COMPOSER_TOKENS:
        if token not in content:
            return fail("CHECK_WWW_COMPOSER_TOKEN", token)
    for token in WWW_FORBIDDEN_TOKENS:
        if token in content:
            return fail("CHECK_WWW_FORBIDDEN_LEGACY_TOKEN", token)
    if "OPUS_Application::getInstance()" not in content or "$app->run();" not in content:
        return fail("CHECK_WWW_APPLICATION_BOOT_SEQUENCE", WWW_INDEX)
    ok("CHECK_WWW_COMPOSER_ONLY_ENTRYPOINT")
    if not php_lint(WWW_INDEX):
        return fail("CHECK_WWW_PHP_LINT", WWW_INDEX)
    return 0


def check_runtime_application_contract() -> int:
    content = read(RUNTIME_APPLICATION)
    required = [
        "class OPUS_Application",
        "dirname(__DIR__, 2) . '/config/fsm.boot.php'",
    ]
    for token in required:
        if token not in content:
            return fail("CHECK_RUNTIME_APPLICATION_TOKEN", token)
    if "dirname(__DIR__, 3) . '/config/fsm.boot.php'" in content:
        return fail("CHECK_RUNTIME_APPLICATION_NO_LEGACY_BOOT_FSM_ROOT")
    ok("CHECK_RUNTIME_APPLICATION_CONTRACT")
    if not php_lint(RUNTIME_APPLICATION):
        return fail("CHECK_RUNTIME_APPLICATION_PHP_LINT", RUNTIME_APPLICATION)
    return 0


def scan_legacy_runtime_refs() -> int:
    files = run_git(["ls-files"])
    runtime_refs: list[str] = []
    ignored_refs: list[str] = []
    for rel in files:
        if not rel.endswith((".php", ".py", ".md", ".json", ".cmd")):
            continue
        if rel.startswith(("vendor/", "var/", "tmp/")):
            continue
        try:
            content = read(rel)
        except OSError:
            continue
        has_legacy_ref = "Opus/Legacy" in content or "DirectoriesAutoloader" in content
        if not has_legacy_ref:
            continue
        if rel == WWW_INDEX or rel.startswith("Opus/"):
            runtime_refs.append(rel)
        else:
            ignored_refs.append(rel)

    print("LEGACY_REFERENCE_CLASSIFICATION")
    for rel in sorted(runtime_refs):
        print(f"RUNTIME_LEGACY_REF={rel}")
    for rel in sorted(ignored_refs):
        print(f"NON_RUNTIME_LEGACY_REF={rel}")

    if runtime_refs:
        return fail("CHECK_LEGACY_RUNTIME_REFERENCES", ",".join(sorted(runtime_refs)))
    ok("CHECK_LEGACY_RUNTIME_REFERENCES", "none")
    return 0


def print_decision() -> None:
    print()
    print("P5G_LEGACY_AUTOLOADER_REMOVAL_DECISION")
    print("WWW_ENTRYPOINT_COMPOSER_ONLY=YES")
    print("LEGACY_AUTOLOADER_RUNTIME_FILE_PRESENT=NO")
    print("RUNTIME_APPLICATION_LOCATION=Opus/Runtime/Application.php")
    print("DECISION=LEGACY_RUNTIME_BOUNDARY_REMOVED")
    print("NEXT_SAFE_STEP=P6B_ARCHIVE_P6A_MIGRATION_OR_REMOVE_STALE_LEGACY_AUDITS")


def main() -> int:
    print(PATCH_ID)
    print("MODE=READ_ONLY")
    print("SCOPE=removed legacy autoloader runtime boundary")

    checks = [
        check_required_files(),
        check_legacy_files_absent(),
        check_www_composer_only_entrypoint(),
        check_runtime_application_contract(),
        scan_legacy_runtime_refs(),
    ]
    if any(code != 0 for code in checks):
        print(f"{PATCH_ID}_FAIL")
        return 1

    print_decision()
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
