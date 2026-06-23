from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

PATCH_ID = "P4Q_MOVE_KERNEL_ROUTER_CLASSES"
ROOT = Path(__file__).resolve().parents[2]

KERNEL_OLD = ROOT / "Opus" / "Kernel.php"
KERNEL_NEW = ROOT / "Opus" / "Runtime" / "Kernel.php"
ROUTER_OLD = ROOT / "Opus" / "Router.php"
ROUTER_NEW = ROOT / "Opus" / "Routing" / "Router.php"
BOOTSTRAP = ROOT / "Opus" / "Bootstrap.php"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read(path: Path) -> str:
    if not path.exists():
        fail(f"FILE_NOT_FOUND={path.relative_to(ROOT)}")
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def move_once(old: Path, new: Path) -> None:
    if old.exists() and new.exists():
        fail(f"BOTH_OLD_AND_NEW_EXIST={old.relative_to(ROOT)}|{new.relative_to(ROOT)}")
    if old.exists():
        new.parent.mkdir(parents=True, exist_ok=True)
        old.rename(new)
        print(f"MOVED={old.relative_to(ROOT)} -> {new.relative_to(ROOT)}")
    elif new.exists():
        print(f"ALREADY_MOVED={new.relative_to(ROOT)}")
    else:
        fail(f"MOVE_SOURCE_AND_TARGET_MISSING={old.relative_to(ROOT)}|{new.relative_to(ROOT)}")


def add_use_after_namespace(content: str, use_line: str) -> str:
    if use_line in content:
        return content
    marker = "namespace "
    namespace_start = content.find(marker)
    if namespace_start < 0:
        fail(f"NAMESPACE_NOT_FOUND_FOR_USE={use_line}")
    semicolon = content.find(";", namespace_start)
    if semicolon < 0:
        fail(f"NAMESPACE_SEMICOLON_NOT_FOUND_FOR_USE={use_line}")
    insertion = semicolon + 1
    return content[:insertion] + "\n\n" + use_line + content[insertion:]


def patch_kernel() -> None:
    content = read(KERNEL_NEW)
    content = content.replace("namespace Opus;", "namespace Opus\\Runtime;")
    content = add_use_after_namespace(content, "use Opus\\Routing\\Router;")
    updated = content
    if updated != read(KERNEL_NEW):
        write(KERNEL_NEW, updated)
        print(f"PATCHED={KERNEL_NEW.relative_to(ROOT)}")


def patch_router() -> None:
    content = read(ROUTER_NEW)
    content = content.replace("namespace Opus;", "namespace Opus\\Routing;")
    content = add_use_after_namespace(content, "use Opus\\Runtime\\Kernel;")
    updated = content
    if updated != read(ROUTER_NEW):
        write(ROUTER_NEW, updated)
        print(f"PATCHED={ROUTER_NEW.relative_to(ROOT)}")


def patch_bootstrap() -> None:
    content = read(BOOTSTRAP)
    content = add_use_after_namespace(content, "use Opus\\Runtime\\Kernel;")
    content = content.replace("            'Router.php',\n            'Kernel.php',", "            'Routing/Router.php',\n            'Runtime/Kernel.php',")
    updated = content
    if updated != read(BOOTSTRAP):
        write(BOOTSTRAP, updated)
        print(f"PATCHED={BOOTSTRAP.relative_to(ROOT)}")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED={path.relative_to(ROOT)}")


def composer_dump_autoload() -> None:
    composer = shutil.which("composer") or shutil.which("composer.bat")
    if composer is None:
        fail("COMPOSER_NOT_FOUND_IN_PATH")
    result = subprocess.run(["cmd", "/c", "composer", "dump-autoload"], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail("COMPOSER_DUMP_AUTOLOAD_FAILED")


def assert_runtime_state() -> None:
    if KERNEL_OLD.exists():
        fail(f"LEGACY_FILE_STILL_EXISTS={KERNEL_OLD.relative_to(ROOT)}")
    if ROUTER_OLD.exists():
        fail(f"LEGACY_FILE_STILL_EXISTS={ROUTER_OLD.relative_to(ROOT)}")
    kernel = read(KERNEL_NEW)
    router = read(ROUTER_NEW)
    bootstrap = read(BOOTSTRAP)
    if "namespace Opus\\Runtime;" not in kernel:
        fail("KERNEL_NAMESPACE_NOT_UPDATED")
    if "namespace Opus\\Routing;" not in router:
        fail("ROUTER_NAMESPACE_NOT_UPDATED")
    if "use Opus\\Runtime\\Kernel;" not in bootstrap:
        fail("BOOTSTRAP_KERNEL_IMPORT_MISSING")
    if "Routing/Router.php" not in bootstrap or "Runtime/Kernel.php" not in bootstrap:
        fail("BOOTSTRAP_LOAD_PATHS_NOT_UPDATED")
    if "            'Router.php'," in bootstrap or "            'Kernel.php'," in bootstrap:
        fail("BOOTSTRAP_LEGACY_ROOT_LOAD_PATHS_LEFT")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    move_once(KERNEL_OLD, KERNEL_NEW)
    move_once(ROUTER_OLD, ROUTER_NEW)
    patch_kernel()
    patch_router()
    patch_bootstrap()
    assert_runtime_state()
    for path in (KERNEL_NEW, ROUTER_NEW, BOOTSTRAP):
        php_lint(path)
    composer_dump_autoload()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
