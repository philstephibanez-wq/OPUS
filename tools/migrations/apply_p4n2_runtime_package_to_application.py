from __future__ import annotations

from pathlib import Path

PATCH_ID = "P4N2_RUNTIME_PACKAGE_TO_APPLICATION"
ROOT = Path(__file__).resolve().parents[2]

MOVES = [
    ("Opus/Package.php", "Opus/Application/ApplicationDefinition.php"),
    ("Opus/PackageRepository.php", "Opus/Application/ApplicationRegistry.php"),
]

PATCH_FILES = [
    "Opus/Kernel.php",
    "Opus/Router.php",
    "Opus/View.php",
    "Opus/I18n.php",
    "Opus/Application/ApplicationDefinition.php",
    "Opus/Application/ApplicationRegistry.php",
]


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_if_changed(path: Path, content: str) -> None:
    old = read(path)
    if old != content:
        path.write_text(content, encoding="utf-8", newline="\n")
        print(f"PATCHED={path.relative_to(ROOT).as_posix()}")


def move_file(source_rel: str, target_rel: str) -> None:
    source = ROOT / source_rel
    target = ROOT / target_rel
    if target.exists():
        if source.exists():
            fail(f"SOURCE_AND_TARGET_BOTH_EXIST={source_rel}|{target_rel}")
        print(f"ALREADY_MOVED={target_rel}")
        return
    if not source.exists():
        fail(f"SOURCE_FILE_NOT_FOUND={source_rel}")
    target.parent.mkdir(parents=True, exist_ok=True)
    source.rename(target)
    print(f"MOVED={source_rel} -> {target_rel}")


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


def patch_application_definition() -> None:
    path = ROOT / "Opus/Application/ApplicationDefinition.php"
    content = read(path)
    content = content.replace("namespace Opus;", "namespace Opus\\Application;")
    content = content.replace("final class Package", "final class ApplicationDefinition")
    content = content.replace("Package config missing key", "Application definition config missing key")
    content = content.replace("Routes file missing for package", "Routes file missing for application")
    content = content.replace("Routes file must return array for package", "Routes file must return array for application")
    content = content.replace("Content file missing for package", "Content file missing for application")
    content = content.replace("Content file must return array for package", "Content file must return array for application")
    write_if_changed(path, content)


def patch_application_registry() -> None:
    path = ROOT / "Opus/Application/ApplicationRegistry.php"
    content = read(path)
    content = content.replace("namespace Opus;", "namespace Opus\\Application;")
    content = add_use_after_namespace(content, "use Opus\\Http\\Request;")
    content = content.replace("final class PackageRepository", "final class ApplicationRegistry")
    content = content.replace("array<string,Package>", "array<string,ApplicationDefinition>")
    content = content.replace("array{0:Package,1:list<string>,2:bool}", "array{0:ApplicationDefinition,1:list<string>,2:bool}")
    content = content.replace("private array $packages", "private array $applications")
    content = content.replace("$this->packages", "$this->applications")
    content = content.replace("$packages", "$applications")
    content = content.replace("PackageRepository", "ApplicationRegistry")
    content = content.replace("new Package(", "new ApplicationDefinition(")
    content = content.replace("Package ", "ApplicationDefinition ")
    content = content.replace("$package", "$application")
    content = content.replace("$application = new ApplicationDefinition", "$application = new ApplicationDefinition")
    content = content.replace("Package config must return array", "Application definition config must return array")
    content = content.replace("Default package logandplay is required", "Default application logandplay is required")
    content = content.replace("Unknown package:", "Unknown application:")
    write_if_changed(path, content)


def patch_kernel_router_view_i18n() -> None:
    for rel in ["Opus/Kernel.php", "Opus/Router.php", "Opus/View.php", "Opus/I18n.php"]:
        path = ROOT / rel
        content = read(path)
        content = add_use_after_namespace(content, "use Opus\\Application\\ApplicationDefinition;")
        if rel == "Opus/Kernel.php":
            content = add_use_after_namespace(content, "use Opus\\Application\\ApplicationRegistry;")
            content = content.replace("private PackageRepository $packages;", "private ApplicationRegistry $applications;")
            content = content.replace("$this->packages = new PackageRepository", "$this->applications = new ApplicationRegistry")
            content = content.replace("$this->packages->", "$this->applications->")
            content = content.replace("getPackage(", "getApplication(")
        content = content.replace("PackageRepository", "ApplicationRegistry")
        content = content.replace("Package $package", "ApplicationDefinition $application")
        content = content.replace("Package $", "ApplicationDefinition $")
        content = content.replace("$package", "$application")
        content = content.replace("package {$application", "application {$application")
        write_if_changed(path, content)


def assert_no_runtime_legacy_classes() -> None:
    runtime_roots = ["Opus", "application", "config", "public", "sites"]
    failures: list[str] = []
    for root in runtime_roots:
        base = ROOT / root
        if not base.exists():
            continue
        for path in base.rglob("*.php"):
            rel = path.relative_to(ROOT).as_posix()
            content = read(path)
            if rel in {"Opus/Package.php", "Opus/PackageRepository.php"}:
                failures.append(f"LEGACY_FILE_STILL_EXISTS={rel}")
            if "class Package" in content or "class PackageRepository" in content:
                failures.append(f"LEGACY_CLASS_DECLARATION={rel}")
            if "PackageRepository" in content or "Package $" in content or "new Package(" in content:
                failures.append(f"LEGACY_PACKAGE_RUNTIME_REFERENCE={rel}")
    if failures:
        print("RUNTIME_LEGACY_PACKAGE_REFERENCES_FOUND")
        for failure in failures:
            print(failure)
        fail("REFUSING_PACKAGE_TO_APPLICATION_MIGRATION_INCOMPLETE")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    for source, target in MOVES:
        move_file(source, target)
    patch_application_definition()
    patch_application_registry()
    patch_kernel_router_view_i18n()
    assert_no_runtime_legacy_classes()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
