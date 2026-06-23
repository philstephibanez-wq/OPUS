#!/usr/bin/env python3
"""
P4O_MOVE_I18N_VIEW_CLASSES

Move the remaining lightweight runtime presentation services out of the OPUS
root namespace without wrappers or aliases.

Contract:
- no wrapper class is created or preserved;
- source files are removed from Opus/ root;
- namespaces are real target namespaces;
- Bootstrap is updated to load the new paths explicitly;
- Kernel/Router imports are updated explicitly;
- the script is idempotent and refuses incomplete runtime states.
"""
from __future__ import annotations

from pathlib import Path

PATCH_ID = "P4O_MOVE_I18N_VIEW_CLASSES"
ROOT = Path(__file__).resolve().parents[2]


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: str) -> Path:
    return ROOT / path


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def ensure_use(content: str, use_line: str) -> str:
    if use_line in content:
        return content

    namespace_end = content.find(";\n", content.find("namespace "))
    if namespace_end < 0:
        fail(f"NAMESPACE_DECLARATION_NOT_FOUND_FOR_USE={use_line}")

    insert_at = namespace_end + 2
    return content[:insert_at] + "\n" + use_line + content[insert_at:]


def move_php_class(source_rel: str, target_rel: str, old_namespace: str, new_namespace: str) -> None:
    source = rel(source_rel)
    target = rel(target_rel)

    if target.exists() and not source.exists():
        content = read_text(target)
    elif source.exists() and not target.exists():
        target.parent.mkdir(parents=True, exist_ok=True)
        content = read_text(source)
        source.unlink()
        print(f"MOVED={source_rel} -> {target_rel}")
    elif source.exists() and target.exists():
        fail(f"BOTH_SOURCE_AND_TARGET_EXIST={source_rel}|{target_rel}")
    else:
        fail(f"NEITHER_SOURCE_NOR_TARGET_EXISTS={source_rel}|{target_rel}")

    if old_namespace in content:
        content = content.replace(old_namespace, new_namespace, 1)
        print(f"PATCHED_NAMESPACE={target_rel}")
    elif new_namespace not in content:
        fail(f"TARGET_NAMESPACE_NOT_FOUND={target_rel}")

    write_text(target, content)


def patch_i18n() -> None:
    move_php_class(
        "Opus/I18n.php",
        "Opus/I18n/I18n.php",
        "namespace Opus;",
        "namespace Opus\\I18n;",
    )


def patch_view() -> None:
    move_php_class(
        "Opus/View.php",
        "Opus/View/View.php",
        "namespace Opus;",
        "namespace Opus\\View;",
    )

    path = rel("Opus/View/View.php")
    content = read_text(path)
    content = ensure_use(content, "use Opus\\I18n\\I18n;\n")
    content = ensure_use(content, "use Opus\\Kernel;\n")
    content = content.replace("__DIR__ . '/Score/", "__DIR__ . '/../Score/")
    content = content.replace("__DIR__ . '/Score/templates/view'", "__DIR__ . '/../Score/templates/view'")
    write_text(path, content)
    print("PATCHED=Opus/View/View.php")


def patch_kernel() -> None:
    path = rel("Opus/Kernel.php")
    if not path.exists():
        fail("KERNEL_FILE_NOT_FOUND")
    content = read_text(path)
    content = ensure_use(content, "use Opus\\I18n\\I18n;\n")
    content = ensure_use(content, "use Opus\\View\\View;\n")
    write_text(path, content)
    print("PATCHED=Opus/Kernel.php")


def patch_router() -> None:
    path = rel("Opus/Router.php")
    if not path.exists():
        fail("ROUTER_FILE_NOT_FOUND")
    content = read_text(path)
    content = ensure_use(content, "use Opus\\View\\View;\n")
    write_text(path, content)
    print("PATCHED=Opus/Router.php")


def patch_bootstrap() -> None:
    path = rel("Opus/Bootstrap.php")
    if not path.exists():
        fail("BOOTSTRAP_FILE_NOT_FOUND")
    content = read_text(path)
    replacements = {
        "'I18n.php'": "'I18n/I18n.php'",
        "'View.php'": "'View/View.php'",
    }
    for old, new in replacements.items():
        if old in content:
            content = content.replace(old, new)
    if "'I18n.php'" in content or "'View.php'" in content:
        fail("BOOTSTRAP_LEGACY_I18N_VIEW_PATH_STILL_PRESENT")
    write_text(path, content)
    print("PATCHED=Opus/Bootstrap.php")


def assert_clean() -> None:
    forbidden_existing = [
        "Opus/I18n.php",
        "Opus/View.php",
    ]
    for item in forbidden_existing:
        if rel(item).exists():
            fail(f"LEGACY_ROOT_FILE_STILL_EXISTS={item}")

    required_existing = [
        "Opus/I18n/I18n.php",
        "Opus/View/View.php",
    ]
    for item in required_existing:
        if not rel(item).exists():
            fail(f"REQUIRED_TARGET_FILE_MISSING={item}")

    bootstrap = read_text(rel("Opus/Bootstrap.php"))
    if "'I18n.php'" in bootstrap or "'View.php'" in bootstrap:
        fail("BOOTSTRAP_STILL_LOADS_ROOT_I18N_OR_VIEW")

    kernel = read_text(rel("Opus/Kernel.php"))
    if "use Opus\\I18n\\I18n;" not in kernel:
        fail("KERNEL_MISSING_I18N_IMPORT")
    if "use Opus\\View\\View;" not in kernel:
        fail("KERNEL_MISSING_VIEW_IMPORT")

    router = read_text(rel("Opus/Router.php"))
    if "use Opus\\View\\View;" not in router:
        fail("ROUTER_MISSING_VIEW_IMPORT")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    patch_i18n()
    patch_view()
    patch_kernel()
    patch_router()
    patch_bootstrap()
    assert_clean()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
