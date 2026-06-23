#!/usr/bin/env python3
"""
P5E_BOOTSTRAP_READINESS_AUDIT

Read-only audit for the last OPUS direct PHP file under Opus/.

Purpose:
- prove that Opus/Bootstrap.php is the only remaining direct PHP file in Opus/;
- classify whether Bootstrap can be moved now or must remain as the legacy bridge;
- keep modern and legacy entrypoints explicit;
- never rewrite files, caches, Composer output, or entrypoints.
"""
from __future__ import annotations

import json
import subprocess
from pathlib import Path

PATCH_ID = "P5E_BOOTSTRAP_READINESS_AUDIT"
ROOT = Path.cwd()
OPUS = ROOT / "Opus"

REQUIRED_BOOTSTRAP_LOADS = [
    "Foundation/Support.php",
    "Http/Request.php",
    "Http/Response.php",
    "Application/ApplicationDefinition.php",
    "Application/ApplicationRegistry.php",
    "I18n/I18n.php",
    "View/View.php",
    "Security/Acl.php",
    "FSM/Fsm.php",
    "Routing/Router.php",
    "Runtime/Kernel.php",
]

RUNTIME_SCAN_FILES = [
    "index.php",
    "www/index.php",
]

EXPECTED_WWW_REQUIRES = [
    "require_once ROOT . '/Opus/Bootstrap.php';",
    "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';",
    "require_once ROOT . '/Opus/Legacy/Application/Application.class.php';",
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


def tracked_files() -> list[str]:
    return run_git(["ls-files"])


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8", errors="ignore")


def php_lint(rel: str) -> bool:
    result = subprocess.run(["php", "-l", rel], cwd=ROOT, text=True, capture_output=True, check=False)
    if result.returncode == 0:
        ok(f"CHECK_PHP_LINT_{rel.upper().replace('/', '_').replace('.', '_')}")
        return True
    print(result.stdout.strip())
    print(result.stderr.strip())
    return False


def direct_opus_php_files(files: list[str]) -> list[str]:
    output: list[str] = []
    for rel in files:
        parts = Path(rel).parts
        if len(parts) == 2 and parts[0] == "Opus" and Path(rel).suffix.lower() == ".php":
            output.append(rel)
    return sorted(output)


def check_root_boundary(files: list[str]) -> int:
    if not OPUS.is_dir():
        return fail("CHECK_OPUS_ROOT", str(OPUS))
    ok("CHECK_OPUS_ROOT", OPUS.as_posix())

    direct = direct_opus_php_files(files)
    if direct != ["Opus/Bootstrap.php"]:
        print("OPUS_DIRECT_PHP_FILES=" + ",".join(direct))
        return fail("CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP")
    ok("CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP", "Opus/Bootstrap.php")
    return 0


def check_bootstrap_contract() -> int:
    rel = "Opus/Bootstrap.php"
    path = ROOT / rel
    if not path.is_file():
        return fail("CHECK_BOOTSTRAP_EXISTS", rel)

    content = read(rel)
    required_tokens = [
        "namespace Opus;",
        "use Opus\\Runtime\\Kernel;",
        "use Opus\\Http\\Request;",
        "use Opus\\Http\\Response;",
        "final class Bootstrap",
        "public static function run(string $rootDir): void",
        "private static function loadFramework(string $rootDir): void",
        "$kernel = new Kernel($rootDir);",
        "Request::fromGlobals($rootDir)",
        "Response::html(self::renderFatal($e), 500)->send();",
    ]
    for token in required_tokens:
        if token not in content:
            return fail("CHECK_BOOTSTRAP_CONTRACT_TOKEN", token)
    ok("CHECK_BOOTSTRAP_CONTRACT_TOKENS")

    missing_loads = [item for item in REQUIRED_BOOTSTRAP_LOADS if item not in content]
    if missing_loads:
        print("MISSING_BOOTSTRAP_LOADS=" + ",".join(missing_loads))
        return fail("CHECK_BOOTSTRAP_REQUIRED_LOAD_LIST")
    ok("CHECK_BOOTSTRAP_REQUIRED_LOAD_LIST", str(len(REQUIRED_BOOTSTRAP_LOADS)))

    if not php_lint(rel):
        return fail("CHECK_BOOTSTRAP_PHP_LINT", rel)
    return 0


def check_modern_entrypoint() -> int:
    rel = "index.php"
    content = read(rel)
    if "Opus/Bootstrap.php" in content:
        return fail("CHECK_MODERN_INDEX_DOES_NOT_REQUIRE_BOOTSTRAP", rel)
    ok("CHECK_MODERN_INDEX_DOES_NOT_REQUIRE_BOOTSTRAP")

    for token in ["\\Opus\\Autoload\\Autoloader::boot", "\\Opus\\Runtime\\NativeHttpKernel", "\\Opus\\Runtime\\NativeHttpEmitter"]:
        if token not in content:
            return fail("CHECK_MODERN_INDEX_RUNTIME_TOKEN", token)
    ok("CHECK_MODERN_INDEX_RUNTIME_TOKENS")

    if not php_lint(rel):
        return fail("CHECK_MODERN_INDEX_PHP_LINT", rel)
    return 0


def check_legacy_entrypoint() -> int:
    rel = "www/index.php"
    content = read(rel)
    for token in EXPECTED_WWW_REQUIRES:
        if token not in content:
            return fail("CHECK_LEGACY_WWW_REQUIRE", token)
    ok("CHECK_LEGACY_WWW_EXPLICIT_REQUIRES", str(len(EXPECTED_WWW_REQUIRES)))

    if "OPUS_Application::getInstance()" not in content or "$app->run();" not in content:
        return fail("CHECK_LEGACY_WWW_BOOT_SEQUENCE", rel)
    ok("CHECK_LEGACY_WWW_BOOT_SEQUENCE")

    if not php_lint(rel):
        return fail("CHECK_LEGACY_WWW_PHP_LINT", rel)
    return 0


def check_composer_contract() -> int:
    rel = "composer.json"
    data = json.loads(read(rel))
    autoload = data.get("autoload", {})
    psr4 = autoload.get("psr-4", {})
    classmap = autoload.get("classmap", [])

    if psr4.get("Opus\\") != "Opus/":
        return fail("CHECK_COMPOSER_PSR4_OPUS", str(psr4.get("Opus\\")))
    ok("CHECK_COMPOSER_PSR4_OPUS", "Opus/ => Opus\\")

    if "Opus/" not in classmap:
        return fail("CHECK_COMPOSER_CLASSMAP_OPUS", str(classmap))
    ok("CHECK_COMPOSER_CLASSMAP_OPUS", "Opus/")
    return 0


def scan_bootstrap_references(files: list[str]) -> int:
    print("BOOTSTRAP_REFERENCE_CLASSIFICATION")
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
        if rel in RUNTIME_SCAN_FILES:
            runtime_refs.append(rel)
        else:
            ignored_refs.append(rel)

    for rel in sorted(runtime_refs):
        print(f"RUNTIME_REF={rel}")
    for rel in sorted(ignored_refs):
        print(f"NON_RUNTIME_REF={rel}")

    if runtime_refs != ["www/index.php"]:
        return fail("CHECK_BOOTSTRAP_RUNTIME_REFERENCES", ",".join(runtime_refs))
    ok("CHECK_BOOTSTRAP_RUNTIME_REFERENCES", "www/index.php")
    return 0


def print_decision() -> None:
    print()
    print("BOOTSTRAP_READINESS_DECISION")
    print("MODERN_ENTRYPOINT_BLOCKS_BOOTSTRAP_MOVE=NO")
    print("COMPOSER_AUTOLOAD_CAN_LOAD_OPUS_BOOTSTRAP=YES")
    print("LEGACY_WWW_ENTRYPOINT_DIRECTLY_REQUIRES_BOOTSTRAP=YES")
    print("DECISION=KEEP_OPUS_BOOTSTRAP_PHP_AT_ROOT_FOR_NOW")
    print("NEXT_SAFE_STEP=P5F_DESIGN_BOOTSTRAP_COMPATIBILITY_OR_LEGACY_ENTRYPOINT_AUTLOAD_SWITCH")


def main() -> int:
    print(PATCH_ID)
    print("MODE=READ_ONLY")
    print("SCOPE=bootstrap readiness, modern entrypoint, legacy entrypoint, composer autoload")

    files = tracked_files()
    checks = [
        check_root_boundary(files),
        check_bootstrap_contract(),
        check_modern_entrypoint(),
        check_legacy_entrypoint(),
        check_composer_contract(),
        scan_bootstrap_references(files),
    ]
    if any(code != 0 for code in checks):
        print(f"{PATCH_ID}_FAIL")
        return 1

    print_decision()
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
