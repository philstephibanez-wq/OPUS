#!/usr/bin/env python3
"""
P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_AUDIT

Read-only audit for the legacy DirectoriesAutoloader bootstrap dependency.

Purpose:
- prove the modern www legacy entrypoint now loads Composer before legacy classes;
- classify the remaining Opus/Bootstrap.php dependency inside Opus/Legacy/Autoload/autoloader.class.php;
- decide whether the next safe migration can replace the direct file require with a composer-aware guard;
- never rewrite source, caches, Composer output, or entrypoints.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_AUDIT"
ROOT = Path.cwd()
LEGACY_AUTOLOADER = "Opus/Legacy/Autoload/autoloader.class.php"
WWW_INDEX = "www/index.php"

DIRECT_BRIDGE_TOKENS = [
    "$opusBootstrap = defined('ROOT') ? ROOT . '/Opus/Bootstrap.php' : dirname(__DIR__, 2) . '/Bootstrap.php';",
    "require_once $opusBootstrap;",
]

COMPOSER_AWARE_TOKENS = [
    "class_exists('\\\\Opus\\\\Bootstrap'",
    "class_exists('Opus\\\\Bootstrap'",
    "OPUS_BOOTSTRAP_CLASS_REQUIRED",
]

WWW_COMPOSER_TOKENS = [
    "$composerAutoload = ROOT . '/vendor/autoload.php';",
    "throw new RuntimeException('OPUS_COMPOSER_AUTOLOAD_REQUIRED: ' . $composerAutoload);",
    "require_once $composerAutoload;",
]

WWW_LEGACY_TOKENS = [
    "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';",
    "require_once ROOT . '/Opus/Legacy/Application/Application.class.php';",
    "OPUS_Application::getInstance()",
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
    missing = [rel for rel in [LEGACY_AUTOLOADER, WWW_INDEX, "vendor/autoload.php"] if not (ROOT / rel).is_file()]
    if missing:
        return fail("CHECK_REQUIRED_FILES", ",".join(missing))
    ok("CHECK_REQUIRED_FILES")
    return 0


def check_www_entrypoint_composer_before_legacy() -> int:
    content = read(WWW_INDEX)
    for token in WWW_COMPOSER_TOKENS:
        if token not in content:
            return fail("CHECK_WWW_COMPOSER_TOKEN", token)
    for token in WWW_LEGACY_TOKENS:
        if token not in content:
            return fail("CHECK_WWW_LEGACY_TOKEN", token)
    if "require_once ROOT . '/Opus/Bootstrap.php';" in content:
        return fail("CHECK_WWW_NO_DIRECT_BOOTSTRAP_REQUIRE", WWW_INDEX)

    composer_pos = content.index("require_once $composerAutoload;")
    legacy_pos = content.index("require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';")
    if composer_pos > legacy_pos:
        return fail("CHECK_WWW_COMPOSER_BEFORE_LEGACY_AUTOLOADER")
    ok("CHECK_WWW_COMPOSER_BEFORE_LEGACY_AUTOLOADER")

    if not php_lint(WWW_INDEX):
        return fail("CHECK_WWW_PHP_LINT", WWW_INDEX)
    return 0


def check_legacy_autoloader_bridge() -> int:
    content = read(LEGACY_AUTOLOADER)
    direct_missing = [token for token in DIRECT_BRIDGE_TOKENS if token not in content]
    composer_aware = any(token in content for token in COMPOSER_AWARE_TOKENS)

    if direct_missing and not composer_aware:
        return fail("CHECK_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_UNKNOWN", ",".join(direct_missing))

    if not direct_missing:
        ok("CHECK_LEGACY_AUTOLOADER_DIRECT_BOOTSTRAP_BRIDGE", LEGACY_AUTOLOADER)
    else:
        ok("CHECK_LEGACY_AUTOLOADER_DIRECT_BOOTSTRAP_BRIDGE_ABSENT")

    if composer_aware:
        ok("CHECK_LEGACY_AUTOLOADER_COMPOSER_AWARE_BOOTSTRAP_GUARD")
    else:
        ok("CHECK_LEGACY_AUTOLOADER_COMPOSER_AWARE_BOOTSTRAP_GUARD_ABSENT")

    required_classes = [
        "class ExtensionFilterIteratorDecorator extends FilterIterator",
        "class DirectoriesAutoloaderException extends Exception",
        "class DirectoriesAutoloader",
        "spl_autoload_register(array($autoloader, 'autoload'));",
    ]
    for token in required_classes:
        if token not in content:
            return fail("CHECK_LEGACY_AUTOLOADER_CLASS_TOKEN", token)
    ok("CHECK_LEGACY_AUTOLOADER_CLASS_CONTRACT")

    if not php_lint(LEGACY_AUTOLOADER):
        return fail("CHECK_LEGACY_AUTOLOADER_PHP_LINT", LEGACY_AUTOLOADER)
    return 0


def scan_bootstrap_runtime_refs() -> int:
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
        if "Opus/Bootstrap.php" not in content:
            continue
        if rel in {WWW_INDEX, LEGACY_AUTOLOADER}:
            runtime_refs.append(rel)
        else:
            ignored_refs.append(rel)

    print("BOOTSTRAP_REFERENCE_CLASSIFICATION")
    for rel in sorted(runtime_refs):
        print(f"RUNTIME_OR_BRIDGE_REF={rel}")
    for rel in sorted(ignored_refs):
        print(f"NON_RUNTIME_REF={rel}")

    if runtime_refs == [LEGACY_AUTOLOADER] or sorted(runtime_refs) == [LEGACY_AUTOLOADER]:
        ok("CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES", LEGACY_AUTOLOADER)
        return 0
    return fail("CHECK_BOOTSTRAP_RUNTIME_OR_BRIDGE_REFERENCES", ",".join(sorted(runtime_refs)))


def print_decision() -> None:
    content = read(LEGACY_AUTOLOADER)
    direct_bridge = all(token in content for token in DIRECT_BRIDGE_TOKENS)
    composer_aware = any(token in content for token in COMPOSER_AWARE_TOKENS)

    print()
    print("P5G_LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_DECISION")
    print("WWW_ENTRYPOINT_LOADS_COMPOSER_BEFORE_LEGACY=YES")
    print(f"LEGACY_AUTOLOADER_DIRECTLY_REQUIRES_BOOTSTRAP={'YES' if direct_bridge else 'NO'}")
    print(f"LEGACY_AUTOLOADER_COMPOSER_AWARE_GUARD={'YES' if composer_aware else 'NO'}")
    if direct_bridge and not composer_aware:
        print("DECISION=P5G_SAFE_MIGRATION_AVAILABLE")
        print("NEXT_SAFE_STEP=P5G_MIGRATE_LEGACY_AUTOLOADER_TO_COMPOSER_AWARE_BOOTSTRAP_GUARD")
    elif composer_aware:
        print("DECISION=LEGACY_AUTOLOADER_BOOTSTRAP_BRIDGE_ALREADY_COMPOSER_AWARE")
        print("NEXT_SAFE_STEP=RERUN_P5E_AND_P5B")
    else:
        print("DECISION=MANUAL_REVIEW_REQUIRED")
        print("NEXT_SAFE_STEP=INSPECT_LEGACY_AUTOLOADER_BOOTSTRAP_HEADER")


def main() -> int:
    print(PATCH_ID)
    print("MODE=READ_ONLY")
    print("SCOPE=legacy autoloader bootstrap bridge, composer-aware readiness")

    checks = [
        check_required_files(),
        check_www_entrypoint_composer_before_legacy(),
        check_legacy_autoloader_bridge(),
        scan_bootstrap_runtime_refs(),
    ]
    if any(code != 0 for code in checks):
        print(f"{PATCH_ID}_FAIL")
        return 1

    print_decision()
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
