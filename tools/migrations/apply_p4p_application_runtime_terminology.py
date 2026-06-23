#!/usr/bin/env python3
"""
P4P_APPLICATION_RUNTIME_TERMINOLOGY

Purpose:
- Complete the runtime terminology migration from Package to Application.
- Keep Package reserved for future delivery/install artifacts.

Rules:
- No wrapper.
- No alias method left behind.
- No silent fallback.
- Idempotent: already migrated files are accepted and verified.
"""
from __future__ import annotations

import os
import shutil
import subprocess
from pathlib import Path

PATCH_ID = "P4P_APPLICATION_RUNTIME_TERMINOLOGY"
ROOT = Path(__file__).resolve().parents[2]


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read_text(path: Path) -> str:
    if not path.is_file():
        fail(f"FILE_NOT_FOUND={path.relative_to(ROOT)}")
    return path.read_text(encoding="utf-8")


def write_if_changed(path: Path, content: str) -> None:
    old = read_text(path)
    if old != content:
        path.write_text(content, encoding="utf-8", newline="\n")
        print(f"PATCHED={path.relative_to(ROOT)}")


def replace_required(content: str, old: str, new: str, path: Path) -> str:
    if old not in content:
        if new in content:
            return content
        fail(f"REQUIRED_PATTERN_NOT_FOUND={path.relative_to(ROOT)}::{old}")
    return content.replace(old, new)


def replace_optional(content: str, old: str, new: str) -> str:
    return content.replace(old, new)


def move_site_application_configs() -> None:
    sites_dir = ROOT / "sites"
    if not sites_dir.is_dir():
        fail("SITES_DIR_MISSING")

    for site_dir in sorted(p for p in sites_dir.iterdir() if p.is_dir()):
        src = site_dir / "package.php"
        dst = site_dir / "application.php"
        if src.exists() and dst.exists():
            fail(f"BOTH_PACKAGE_AND_APPLICATION_CONFIG_EXIST={site_dir.relative_to(ROOT)}")
        if src.exists():
            shutil.move(str(src), str(dst))
            print(f"MOVED={src.relative_to(ROOT)} -> {dst.relative_to(ROOT)}")
        elif not dst.exists():
            # Some folders under sites may be support folders; do not invent configs.
            continue


def patch_application_registry() -> None:
    path = ROOT / "Opus" / "Application" / "ApplicationRegistry.php"
    content = read_text(path)
    content = replace_required(content, "/*/package.php", "/*/application.php", path)
    write_if_changed(path, content)


def patch_kernel() -> None:
    path = ROOT / "Opus" / "Kernel.php"
    content = read_text(path)
    content = replace_required(content, "public function packageUrl(", "public function applicationUrl(", path)
    content = replace_optional(content, "$this->packageUrl(", "$this->applicationUrl(")
    write_if_changed(path, content)


def patch_router() -> None:
    path = ROOT / "Opus" / "Router.php"
    content = read_text(path)
    content = replace_optional(content, "$this->kernel->packageUrl(", "$this->kernel->applicationUrl(")
    content = replace_optional(content, "'package' => $application->slug", "'application' => $application->slug")
    content = replace_optional(content, "'package_dir' => $application->dir", "'application_dir' => $application->dir")
    content = replace_optional(content, "Retour package", "Retour application")
    write_if_changed(path, content)


def iter_runtime_php_files() -> list[Path]:
    roots = [ROOT / "Opus", ROOT / "sites"]
    files: list[Path] = []
    for base in roots:
        if not base.exists():
            continue
        files.extend(sorted(p for p in base.rglob("*.php") if p.is_file()))
        files.extend(sorted(p for p in base.rglob("*.class.php") if p.is_file()))
    return files


def assert_no_runtime_package_leftovers() -> None:
    errors: list[str] = []

    for package_config in sorted((ROOT / "sites").glob("*/package.php")):
        errors.append(f"LEGACY_SITE_PACKAGE_CONFIG={package_config.relative_to(ROOT)}")

    forbidden_tokens = [
        "packageUrl(",
        "PackageRepository",
        "PackageContent_helper",
        "'package_dir' =>",
        "\"package_dir\" =>",
        "Retour package",
    ]

    for path in iter_runtime_php_files():
        rel = path.relative_to(ROOT).as_posix()
        content = path.read_text(encoding="utf-8", errors="ignore")
        for token in forbidden_tokens:
            if token in content:
                errors.append(f"LEGACY_PACKAGE_TOKEN={rel}::{token}")
        if rel.startswith("Opus/") and "class Package" in content:
            errors.append(f"LEGACY_PACKAGE_CLASS={rel}")

    if errors:
        print("RUNTIME_PACKAGE_TERMINOLOGY_LEFTOVERS_FOUND")
        for error in errors:
            print(error)
        fail("REFUSING_APPLICATION_TERMINOLOGY_MIGRATION_INCOMPLETE")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED={path.relative_to(ROOT)}")


def composer_dump_autoload() -> None:
    result = subprocess.run(["composer", "dump-autoload"], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail("COMPOSER_DUMP_AUTOLOAD_FAILED")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    move_site_application_configs()
    patch_application_registry()
    patch_kernel()
    patch_router()
    assert_no_runtime_package_leftovers()

    lint_targets = [
        ROOT / "Opus" / "Application" / "ApplicationRegistry.php",
        ROOT / "Opus" / "Kernel.php",
        ROOT / "Opus" / "Router.php",
    ]
    lint_targets.extend(sorted((ROOT / "sites").glob("*/application.php")))
    for path in lint_targets:
        if path.exists():
            php_lint(path)

    composer_dump_autoload()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
