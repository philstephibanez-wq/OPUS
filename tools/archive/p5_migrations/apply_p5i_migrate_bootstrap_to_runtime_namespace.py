#!/usr/bin/env python3
"""
P5I_MIGRATE_BOOTSTRAP_TO_RUNTIME_NAMESPACE

Move the modern OPUS Bootstrap from the Opus root namespace to the runtime
namespace after P5H proved that no runtime direct path blocker remains.

Contract:
- move Opus/Bootstrap.php to Opus/Runtime/Bootstrap.php using git mv;
- update namespace Opus => Opus\Runtime;
- update legacy Composer guard to Opus\Runtime\Bootstrap;
- update active smoke/audits for the new official runtime Bootstrap location;
- no fallback bridge, no compatibility shim, no hidden alternate path;
- Composer autoload refresh is explicit and must be run after this migration.
"""
from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P5I_MIGRATE_BOOTSTRAP_TO_RUNTIME_NAMESPACE"
ROOT = Path.cwd()

OLD_BOOTSTRAP = ROOT / "Opus/Bootstrap.php"
NEW_BOOTSTRAP = ROOT / "Opus/Runtime/Bootstrap.php"
LEGACY_AUTOLOADER = ROOT / "Opus/Legacy/Autoload/autoloader.class.php"
P5B_SMOKE = ROOT / "tools/smokes/smoke_p5b_current_runtime_layout.php"
P5E_AUDIT = ROOT / "tools/audits/audit_p5e_bootstrap_readiness.py"
P5G_AUDIT = ROOT / "tools/audits/audit_p5g_legacy_autoloader_bootstrap_bridge.py"
P5H_AUDIT = ROOT / "tools/audits/audit_p5h_bootstrap_move_design.py"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def require_file(path: Path) -> None:
    if not path.is_file():
        fail(f"REQUIRED_FILE_MISSING path={rel(path)}")


def run(args: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, cwd=ROOT, text=True, capture_output=True, check=False)


def replace_once(path: Path, old: str, new: str, label: str) -> None:
    content = read(path)
    if new in content:
        print(f"ALREADY_PATCHED={rel(path)}::{label}")
        return
    if old not in content:
        fail(f"PATTERN_NOT_FOUND path={rel(path)} label={label}")
    write(path, content.replace(old, new, 1))
    print(f"PATCHED={rel(path)}::{label}")


def replace_all(path: Path, old: str, new: str, label: str) -> None:
    content = read(path)
    if old not in content:
        if new in content:
            print(f"ALREADY_PATCHED={rel(path)}::{label}")
            return
        fail(f"PATTERN_NOT_FOUND path={rel(path)} label={label}")
    write(path, content.replace(old, new))
    print(f"PATCHED={rel(path)}::{label}")


def move_bootstrap() -> None:
    if NEW_BOOTSTRAP.is_file() and not OLD_BOOTSTRAP.exists():
        print("ALREADY_MOVED=Opus/Runtime/Bootstrap.php")
        return
    if not OLD_BOOTSTRAP.is_file():
        fail("OLD_BOOTSTRAP_MISSING")
    if NEW_BOOTSTRAP.exists():
        fail("TARGET_BOOTSTRAP_ALREADY_EXISTS")

    result = run(["git", "mv", rel(OLD_BOOTSTRAP), rel(NEW_BOOTSTRAP)])
    if result.returncode != 0:
        fail("GIT_MV_FAILED " + result.stderr.strip())
    print("MOVED=Opus/Bootstrap.php=>Opus/Runtime/Bootstrap.php")


def patch_bootstrap_namespace() -> None:
    require_file(NEW_BOOTSTRAP)
    replace_once(NEW_BOOTSTRAP, "namespace Opus;", "namespace Opus\\Runtime;", "namespace_runtime")
    replace_all(NEW_BOOTSTRAP, "\nuse Opus\\Runtime\\Kernel;\n", "\n", "remove_self_namespace_kernel_use")


def patch_legacy_autoloader_guard() -> None:
    replace_all(
        LEGACY_AUTOLOADER,
        "class_exists(\\Opus\\Bootstrap::class)",
        "class_exists(\\Opus\\Runtime\\Bootstrap::class)",
        "target_runtime_bootstrap_guard",
    )


def patch_p5b_smoke() -> None:
    content = read(P5B_SMOKE)

    replacements = [
        (
            " * - Legacy autoloader requires Composer-loaded Opus\\\\Bootstrap instead of requiring Opus/Bootstrap.php directly.\n"
            " * - Opus/ root contains Bootstrap.php as the only remaining root PHP runtime file.\n",
            " * - Legacy autoloader requires Composer-loaded Opus\\\\Runtime\\\\Bootstrap.\n"
            " * - Opus/ root contains no direct PHP runtime file after Bootstrap moved to Opus/Runtime.\n",
            "doc_contract_runtime_bootstrap",
        ),
        (
            "        if ($relative !== ['Opus/Bootstrap.php']) {\n"
            "            $this->fail('CHECK_OPUS_ROOT_PHP_BOUNDARY', implode(', ', $relative));\n"
            "            return;\n"
            "        }\n"
            "        $this->ok('CHECK_OPUS_ROOT_PHP_BOUNDARY', 'Opus/Bootstrap.php');",
            "        if ($relative !== []) {\n"
            "            $this->fail('CHECK_OPUS_ROOT_PHP_BOUNDARY', implode(', ', $relative));\n"
            "            return;\n"
            "        }\n"
            "        $this->ok('CHECK_OPUS_ROOT_PHP_BOUNDARY', 'none');",
            "root_php_boundary_none",
        ),
        (
            "            'Opus/Kernel.php',\n"
            "            'Opus/Router.php',\n"
            "            'Opus/View.php',",
            "            'Opus/Kernel.php',\n"
            "            'Opus/Router.php',\n"
            "            'Opus/View.php',\n"
            "            'Opus/Bootstrap.php',",
            "former_root_bootstrap_absent",
        ),
        (
            "        $this->contains($content, 'class_exists(\\Opus\\Bootstrap::class)', 'CHECK_LEGACY_AUTOLOADER_COMPOSER_BOOTSTRAP_GUARD');",
            "        $this->contains($content, 'class_exists(\\Opus\\Runtime\\Bootstrap::class)', 'CHECK_LEGACY_AUTOLOADER_COMPOSER_BOOTSTRAP_GUARD');",
            "legacy_autoloader_runtime_guard",
        ),
        (
            "        $file = $this->root . '/Opus/Bootstrap.php';\n"
            "        $content = $this->read($file);\n"
            "        if ($content === null) { return; }\n\n"
            "        $this->contains($content, 'namespace Opus;', 'CHECK_BOOTSTRAP_NAMESPACE');",
            "        $file = $this->root . '/Opus/Runtime/Bootstrap.php';\n"
            "        $content = $this->read($file);\n"
            "        if ($content === null) { return; }\n\n"
            "        $this->contains($content, 'namespace Opus\\Runtime;', 'CHECK_BOOTSTRAP_NAMESPACE');",
            "bootstrap_contract_runtime_path_namespace",
        ),
        (
            "            'Opus/Bootstrap.php',",
            "            'Opus/Runtime/Bootstrap.php',",
            "lint_runtime_bootstrap_path",
        ),
    ]

    for old, new, label in replacements:
        if new in content:
            print(f"ALREADY_PATCHED={rel(P5B_SMOKE)}::{label}")
            continue
        if old not in content:
            fail(f"P5B_PATTERN_NOT_FOUND label={label}")
        content = content.replace(old, new, 1)
        print(f"PATCHED={rel(P5B_SMOKE)}::{label}")

    write(P5B_SMOKE, content)


def patch_p5e_audit() -> None:
    content = read(P5E_AUDIT)
    replacements = [
        (
            "Purpose:\n- prove that Opus/Bootstrap.php is the only remaining direct PHP file in Opus/;\n- classify whether Bootstrap can be moved now or must remain as the legacy bridge;",
            "Purpose:\n- prove that Opus/Bootstrap.php has been moved out of the Opus root;\n- classify the runtime Bootstrap namespace/location after migration;",
            "doc_purpose_after_move",
        ),
        (
            "    if direct != [\"Opus/Bootstrap.php\"]:\n        print(\"OPUS_DIRECT_PHP_FILES=\" + \",\".join(direct))\n        return fail(\"CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP\")\n    ok(\"CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP\", \"Opus/Bootstrap.php\")",
            "    if direct != []:\n        print(\"OPUS_DIRECT_PHP_FILES=\" + \",\".join(direct))\n        return fail(\"CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP\")\n    ok(\"CHECK_BOOTSTRAP_ONLY_DIRECT_OPUS_PHP\", \"none\")",
            "root_php_boundary_none",
        ),
        (
            "    rel = \"Opus/Bootstrap.php\"",
            "    rel = \"Opus/Runtime/Bootstrap.php\"",
            "bootstrap_contract_runtime_path",
        ),
        (
            "        \"namespace Opus;\",\n        \"use Opus\\\\Runtime\\\\Kernel;\",",
            "        \"namespace Opus\\\\Runtime;\",",
            "bootstrap_contract_runtime_namespace",
        ),
        (
            "    print(\"DECISION=BOOTSTRAP_DIRECT_RUNTIME_REFERENCES_REMOVED\")\n    print(\"NEXT_SAFE_STEP=P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT\")",
            "    print(\"DECISION=BOOTSTRAP_MOVED_TO_RUNTIME_NAMESPACE\")\n    print(\"NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE\")",
            "decision_after_p5i",
        ),
    ]
    for old, new, label in replacements:
        if new in content:
            print(f"ALREADY_PATCHED={rel(P5E_AUDIT)}::{label}")
            continue
        if old not in content:
            fail(f"P5E_PATTERN_NOT_FOUND label={label}")
        content = content.replace(old, new, 1)
        print(f"PATCHED={rel(P5E_AUDIT)}::{label}")
    write(P5E_AUDIT, content)


def patch_p5g_audit() -> None:
    replace_all(
        P5G_AUDIT,
        "class_exists(\\\\Opus\\\\Bootstrap::class)",
        "class_exists(\\\\Opus\\\\Runtime\\\\Bootstrap::class)",
        "composer_aware_token_runtime_bootstrap",
    )
    replace_all(
        P5G_AUDIT,
        "NEXT_SAFE_STEP=P5H_BOOTSTRAP_MOVE_DESIGN_AUDIT",
        "NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE",
        "decision_after_p5i",
    )


def patch_p5h_audit() -> None:
    content = read(P5H_AUDIT)
    replacements = [
        (
            "Purpose:\n- prove that Opus/Bootstrap.php has no remaining runtime direct path dependency;\n- verify that Composer can support a namespace-correct runtime Bootstrap target;\n- identify the official target path/class for a future controlled migration;\n- never move files, rewrite sources, regenerate Composer output, or clean caches.",
            "Purpose:\n- prove that Opus/Bootstrap.php has been moved to the runtime namespace;\n- verify that Composer can load the runtime Bootstrap class;\n- identify any remaining post-move cleanup work;\n- never move files, rewrite sources, regenerate Composer output, or clean caches.",
            "doc_after_move",
        ),
        (
            "    CURRENT_BOOTSTRAP,\n    \"Opus/Runtime/Kernel.php\",",
            "    TARGET_BOOTSTRAP,\n    \"Opus/Runtime/Kernel.php\",",
            "required_files_target_bootstrap",
        ),
        (
            "    \"namespace Opus;\",",
            "    \"namespace Opus\\\\Runtime;\",",
            "current_contract_runtime_namespace",
        ),
        (
            "def check_current_bootstrap_contract() -> int:\n    content = read(CURRENT_BOOTSTRAP)\n    for token in CURRENT_BOOTSTRAP_TOKENS:\n        if token not in content:\n            return fail(\"CHECK_CURRENT_BOOTSTRAP_TOKEN\", token)\n    ok(\"CHECK_CURRENT_BOOTSTRAP_CONTRACT\")\n\n    if not php_lint(CURRENT_BOOTSTRAP):\n        return fail(\"CHECK_CURRENT_BOOTSTRAP_PHP_LINT\", CURRENT_BOOTSTRAP)\n    return 0",
            "def check_current_bootstrap_contract() -> int:\n    content = read(TARGET_BOOTSTRAP)\n    for token in CURRENT_BOOTSTRAP_TOKENS:\n        if token not in content:\n            return fail(\"CHECK_TARGET_BOOTSTRAP_TOKEN\", token)\n    ok(\"CHECK_TARGET_BOOTSTRAP_CONTRACT\")\n\n    if not php_lint(TARGET_BOOTSTRAP):\n        return fail(\"CHECK_TARGET_BOOTSTRAP_PHP_LINT\", TARGET_BOOTSTRAP)\n    return 0",
            "target_bootstrap_contract_function",
        ),
        (
            "def check_target_collision() -> int:\n    if (ROOT / TARGET_BOOTSTRAP).exists():\n        return fail(\"CHECK_TARGET_BOOTSTRAP_ABSENT\", TARGET_BOOTSTRAP)\n    ok(\"CHECK_TARGET_BOOTSTRAP_ABSENT\", TARGET_BOOTSTRAP)\n    return 0",
            "def check_target_collision() -> int:\n    if (ROOT / CURRENT_BOOTSTRAP).exists():\n        return fail(\"CHECK_CURRENT_BOOTSTRAP_ABSENT\", CURRENT_BOOTSTRAP)\n    ok(\"CHECK_CURRENT_BOOTSTRAP_ABSENT\", CURRENT_BOOTSTRAP)\n    if not (ROOT / TARGET_BOOTSTRAP).is_file():\n        return fail(\"CHECK_TARGET_BOOTSTRAP_PRESENT\", TARGET_BOOTSTRAP)\n    ok(\"CHECK_TARGET_BOOTSTRAP_PRESENT\", TARGET_BOOTSTRAP)\n    return 0",
            "target_present_current_absent",
        ),
        (
            "        \"class_exists(\\\\Opus\\\\Bootstrap::class)\",",
            "        \"class_exists(\\\\Opus\\\\Runtime\\\\Bootstrap::class)\",",
            "legacy_guard_target_token",
        ),
        (
            "    ok(\"CHECK_LEGACY_AUTOLOADER_CURRENT_COMPOSER_GUARD\", CURRENT_CLASS)\n    ok(\"CHECK_LEGACY_AUTOLOADER_TARGET_GUARD_UPDATE_REQUIRED\", TARGET_CLASS)",
            "    ok(\"CHECK_LEGACY_AUTOLOADER_TARGET_COMPOSER_GUARD\", TARGET_CLASS)\n    ok(\"CHECK_LEGACY_AUTOLOADER_OLD_GUARD_ABSENT\", CURRENT_CLASS)",
            "legacy_guard_decision_checks",
        ),
        (
            "    print(\"MOVE_REQUIRES_NAMESPACE_UPDATE=YES\")\n    print(\"MOVE_REQUIRES_LEGACY_GUARD_UPDATE=YES\")\n    print(\"MOVE_REQUIRES_COMPOSER_DUMP_AUTOLOAD=YES\")\n    print(\"ROOT_BOOTSTRAP_RUNTIME_BLOCKERS=NO\")\n    print(\"DECISION=P5H_MOVE_DESIGN_READY_TARGET_OPUS_RUNTIME_BOOTSTRAP\")\n    print(\"NEXT_SAFE_STEP=P5I_MIGRATE_BOOTSTRAP_TO_RUNTIME_NAMESPACE\")",
            "    print(\"MOVE_REQUIRES_NAMESPACE_UPDATE=NO\")\n    print(\"MOVE_REQUIRES_LEGACY_GUARD_UPDATE=NO\")\n    print(\"MOVE_REQUIRES_COMPOSER_DUMP_AUTOLOAD=YES_LOCAL_REFRESH_REQUIRED\")\n    print(\"ROOT_BOOTSTRAP_RUNTIME_BLOCKERS=NO\")\n    print(\"DECISION=P5I_BOOTSTRAP_MOVED_TO_RUNTIME_NAMESPACE\")\n    print(\"NEXT_SAFE_STEP=P5J_ARCHIVE_COMPLETED_P5_MIGRATIONS_OR_RUNTIME_SMOKE\")",
            "decision_after_p5i",
        ),
    ]
    for old, new, label in replacements:
        if new in content:
            print(f"ALREADY_PATCHED={rel(P5H_AUDIT)}::{label}")
            continue
        if old not in content:
            fail(f"P5H_PATTERN_NOT_FOUND label={label}")
        content = content.replace(old, new, 1)
        print(f"PATCHED={rel(P5H_AUDIT)}::{label}")
    write(P5H_AUDIT, content)


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True, check=False)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED path={rel(path)}")


def assert_contracts() -> None:
    if OLD_BOOTSTRAP.exists():
        fail("OLD_BOOTSTRAP_STILL_EXISTS")
    if not NEW_BOOTSTRAP.is_file():
        fail("NEW_BOOTSTRAP_MISSING")

    bootstrap = read(NEW_BOOTSTRAP)
    if "namespace Opus\\Runtime;" not in bootstrap:
        fail("TARGET_BOOTSTRAP_NAMESPACE_MISSING")
    if "namespace Opus;" in bootstrap:
        fail("OLD_BOOTSTRAP_NAMESPACE_STILL_PRESENT")

    autoloader = read(LEGACY_AUTOLOADER)
    if "class_exists(\\Opus\\Runtime\\Bootstrap::class)" not in autoloader:
        fail("LEGACY_AUTOLOADER_TARGET_BOOTSTRAP_GUARD_MISSING")
    if "class_exists(\\Opus\\Bootstrap::class)" in autoloader:
        fail("LEGACY_AUTOLOADER_OLD_BOOTSTRAP_GUARD_STILL_PRESENT")

    smoke = read(P5B_SMOKE)
    for token in [
        "Opus/Runtime/Bootstrap.php",
        "namespace Opus\\Runtime;",
        "CHECK_OPUS_ROOT_PHP_BOUNDARY",
    ]:
        if token not in smoke:
            fail(f"P5B_TOKEN_MISSING token={token}")

    print("P5I_MIGRATE_BOOTSTRAP_TO_RUNTIME_NAMESPACE_CONTRACT_OK")


def main() -> int:
    print(f"== {PATCH_ID} ==")
    for path in [LEGACY_AUTOLOADER, P5B_SMOKE, P5E_AUDIT, P5G_AUDIT, P5H_AUDIT]:
        require_file(path)

    move_bootstrap()
    patch_bootstrap_namespace()
    patch_legacy_autoloader_guard()
    patch_p5b_smoke()
    patch_p5e_audit()
    patch_p5g_audit()
    patch_p5h_audit()

    for path in [NEW_BOOTSTRAP, LEGACY_AUTOLOADER, P5B_SMOKE]:
        php_lint(path)

    assert_contracts()
    print("COMPOSER_DUMP_AUTOLOAD_REQUIRED=YES")
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
