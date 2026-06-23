#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path

PATCH_ID = "P4N3_REPAIR_APPLICATION_RUNTIME_REFS"
ROOT = Path(__file__).resolve().parents[2]

TEXT_SUFFIXES = {".php", ".cmd", ".py", ".md", ".score", ".twig", ".json", ".txt"}
SKIP_PARTS = {".git", "vendor", "node_modules"}


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_if_changed(path: Path, content: str) -> None:
    old = read(path)
    if old != content:
        path.write_text(content, encoding="utf-8", newline="\n")
        print(f"PATCHED={rel(path)}")


def text_files() -> list[Path]:
    files: list[Path] = []
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        parts = set(path.relative_to(ROOT).parts)
        if parts & SKIP_PARTS:
            continue
        if path.suffix.lower() in TEXT_SUFFIXES:
            files.append(path)
    return sorted(files)


def add_use_after_namespace(content: str, use_line: str) -> str:
    if use_line in content:
        return content
    lines = content.splitlines(keepends=True)
    namespace_index: int | None = None
    for index, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith("namespace ") and stripped.endswith(";"):
            namespace_index = index
            break
    if namespace_index is None:
        fail(f"NAMESPACE_NOT_FOUND_FOR_USE={use_line}")
    insert_index = namespace_index + 1
    while insert_index < len(lines) and lines[insert_index].strip() == "":
        insert_index += 1
    lines.insert(insert_index, use_line + "\n")
    return "".join(lines)


def patch_application_registry() -> None:
    path = ROOT / "Opus/Application/ApplicationRegistry.php"
    if not path.is_file():
        fail("APPLICATION_REGISTRY_NOT_FOUND")
    content = read(path)
    content = content.replace("public function get(string $slug): Package", "public function get(string $slug): ApplicationDefinition")
    content = content.replace("$explicitApplicationDefinition", "$explicitApplication")
    content = content.replace("$explicitPackage", "$explicitApplication")
    content = content.replace("ApplicationDefinition config must return array", "Application definition config must return array")
    write_if_changed(path, content)


def patch_kernel_router_imports() -> None:
    kernel = ROOT / "Opus/Kernel.php"
    if not kernel.is_file():
        fail("KERNEL_NOT_FOUND")
    content = read(kernel)
    content = add_use_after_namespace(content, "use Opus\\Security\\Acl;")
    content = add_use_after_namespace(content, "use Opus\\FSM\\Fsm;")
    content = content.replace("public function getApplication(string $slug): Package", "public function getApplication(string $slug): ApplicationDefinition")
    write_if_changed(kernel, content)

    router = ROOT / "Opus/Router.php"
    if not router.is_file():
        fail("ROUTER_NOT_FOUND")
    content = read(router)
    content = add_use_after_namespace(content, "use Opus\\Security\\Acl;")
    content = add_use_after_namespace(content, "use Opus\\FSM\\Fsm;")
    write_if_changed(router, content)


def ensure_fsm_class() -> None:
    target = ROOT / "Opus/FSM/Fsm.php"
    if target.is_file():
        return
    legacy = ROOT / "Opus/Fsm.php"
    if legacy.is_file():
        content = read(legacy).replace("namespace Opus;", "namespace Opus\\FSM;", 1)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8", newline="\n")
        legacy.unlink()
        print("MOVED=Opus/Fsm.php -> Opus/FSM/Fsm.php")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text("""<?php
declare(strict_types=1);

namespace Opus\FSM;

final class Fsm
{
    /** @return list<array{state:string,signal:string,action:string,next:string}> */
    public function demoFlow(string $lang): array
    {
        return [
            [
                'state' => 'HTTP_REQUEST',
                'signal' => 'RESOLVE_APPLICATION',
                'action' => 'ApplicationRegistry::resolve',
                'next' => 'ROUTE_DISPATCH',
            ],
            [
                'state' => 'ROUTE_DISPATCH',
                'signal' => 'CHECK_ACL',
                'action' => 'Acl::canView',
                'next' => 'RENDER_VIEW',
            ],
            [
                'state' => 'RENDER_VIEW',
                'signal' => 'BUILD_SECTIONS',
                'action' => 'View::render',
                'next' => 'HTTP_RESPONSE',
            ],
        ];
    }
}
""", encoding="utf-8", newline="\n")
    print("CREATED=Opus/FSM/Fsm.php")


def patch_bootstrap() -> None:
    path = ROOT / "Opus/Bootstrap.php"
    if not path.is_file():
        fail("BOOTSTRAP_NOT_FOUND")
    content = read(path)
    content = add_use_after_namespace(content, "use Opus\\Http\\Request;")
    content = add_use_after_namespace(content, "use Opus\\Http\\Response;")
    old_block = """        foreach ([
            'Support.php',
            'Request.php',
            'Response.php',
            'Package.php',
            'PackageRepository.php',
            'I18n.php',
            'View.php',
            'Acl.php',
            'Fsm.php',
            'Router.php',
            'Kernel.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
"""
    new_block = """        foreach ([
            'Foundation/Support.php',
            'Http/Request.php',
            'Http/Response.php',
            'Application/ApplicationDefinition.php',
            'Application/ApplicationRegistry.php',
            'I18n.php',
            'View.php',
            'Security/Acl.php',
            'FSM/Fsm.php',
            'Router.php',
            'Kernel.php',
        ] as $file) {
            require_once $rootDir . '/Opus/' . $file;
        }
"""
    if old_block in content:
        content = content.replace(old_block, new_block, 1)
    else:
        for old, new in [
            ("'Support.php'", "'Foundation/Support.php'"),
            ("'Request.php'", "'Http/Request.php'"),
            ("'Response.php'", "'Http/Response.php'"),
            ("'Package.php'", "'Application/ApplicationDefinition.php'"),
            ("'PackageRepository.php'", "'Application/ApplicationRegistry.php'"),
            ("'Acl.php'", "'Security/Acl.php'"),
            ("'Fsm.php'", "'FSM/Fsm.php'"),
        ]:
            content = content.replace(old, new)
    write_if_changed(path, content)


def rename_legacy_package_content_helpers() -> None:
    for source in sorted((ROOT / "sites").glob("*/helpers/PackageContent_helper.class.php")):
        target = source.with_name("ApplicationContent_helper.class.php")
        if target.exists():
            fail(f"HELPER_SOURCE_AND_TARGET_BOTH_EXIST={rel(source)}|{rel(target)}")
        content = read(source).replace("PackageContent_helper", "ApplicationContent_helper")
        target.write_text(content, encoding="utf-8", newline="\n")
        source.unlink()
        print(f"MOVED={rel(source)} -> {rel(target)}")

    for path in text_files():
        if path.name == "apply_p4n3_repair_application_runtime_refs.py":
            continue
        content = read(path)
        updated = content.replace("PackageContent_helper", "ApplicationContent_helper")
        if updated != content:
            path.write_text(updated, encoding="utf-8", newline="\n")
            print(f"PATCHED={rel(path)}")


def assert_runtime_clean() -> None:
    failures: list[str] = []
    forbidden_files = [
        "Opus/Package.php",
        "Opus/PackageRepository.php",
        "Opus/Support.php",
        "Opus/Request.php",
        "Opus/Response.php",
        "Opus/Acl.php",
        "Opus/Fsm.php",
    ]
    for rel_path in forbidden_files:
        if (ROOT / rel_path).exists():
            failures.append(f"FORBIDDEN_ROOT_FILE_EXISTS={rel_path}")

    required_files = [
        "Opus/Foundation/Support.php",
        "Opus/Http/Request.php",
        "Opus/Http/Response.php",
        "Opus/Application/ApplicationDefinition.php",
        "Opus/Application/ApplicationRegistry.php",
        "Opus/Security/Acl.php",
        "Opus/FSM/Fsm.php",
    ]
    for rel_path in required_files:
        if not (ROOT / rel_path).is_file():
            failures.append(f"REQUIRED_FILE_MISSING={rel_path}")

    runtime_roots = ["Opus", "application", "config", "public", "sites"]
    for root in runtime_roots:
        base = ROOT / root
        if not base.exists():
            continue
        for path in base.rglob("*.php"):
            content = read(path)
            rel_path = rel(path)
            if "PackageRepository" in content or "new Package(" in content or "Package $" in content or ": Package" in content:
                failures.append(f"LEGACY_PACKAGE_RUNTIME_REFERENCE={rel_path}")
            if "class Package" in content:
                failures.append(f"LEGACY_PACKAGE_CLASS_DECLARATION={rel_path}")

    if failures:
        print("P4N3_RUNTIME_REPAIR_FAILURES")
        for failure in failures:
            print(failure)
        fail("RUNTIME_REPAIR_INCOMPLETE")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    patch_application_registry()
    ensure_fsm_class()
    patch_kernel_router_imports()
    patch_bootstrap()
    rename_legacy_package_content_helpers()
    assert_runtime_clean()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
