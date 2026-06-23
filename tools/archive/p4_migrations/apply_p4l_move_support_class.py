#!/usr/bin/env python3
"""P4L migration: move Opus\Support out of framework root without wrappers."""

from __future__ import annotations

import json
from pathlib import Path

PATCH_ID = "P4L_MOVE_SUPPORT_CLASS_TO_FOUNDATION"
ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "tools" / "migrations" / "p4l_support_boundary_manifest.json"
PHP_SUFFIXES = {".php"}
NON_RUNTIME_DIRS = {
    "DOC",
    "docs",
    "tools/audits",
    "tools/migrations",
    "tools/smokes",
    "vendor",
    ".git",
}


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def is_non_runtime(path: Path) -> bool:
    relative = rel(path)
    if relative.startswith("RUN_"):
        return True
    return any(relative == prefix or relative.startswith(prefix + "/") for prefix in NON_RUNTIME_DIRS)


def load_manifest() -> dict:
    if not MANIFEST.exists():
        fail("MANIFEST_NOT_FOUND")
    data = json.loads(read(MANIFEST))
    if data.get("patch_id") != PATCH_ID:
        fail("MANIFEST_PATCH_ID_MISMATCH")
    contract = data.get("contract")
    if not isinstance(contract, dict):
        fail("MANIFEST_CONTRACT_MISSING")
    for key in ("typed_variables", "no_hardcoded_legacy_path", "no_wrapper", "no_alias", "no_stub", "no_silent_fallback", "wrapper_is_fallback"):
        if contract.get(key) is not True:
            fail(f"CONTRACT_FLAG_NOT_TRUE={key}")
    return data


def php_files() -> list[Path]:
    files: list[Path] = []
    for path in ROOT.rglob("*"):
        if path.is_file() and path.suffix in PHP_SUFFIXES:
            if ".git" not in path.parts and "vendor" not in path.parts:
                files.append(path)
    return files


def move_support(manifest: dict) -> None:
    move = manifest["move"]
    source = ROOT / move["source"]
    target = ROOT / move["target"]
    old_namespace = move["old_namespace"]
    new_namespace = move["new_namespace"]

    if source.exists() and target.exists():
        fail("SOURCE_AND_TARGET_BOTH_EXIST")

    if source.exists():
        content = read(source)
        if old_namespace not in content:
            fail("OLD_NAMESPACE_NOT_FOUND_IN_SOURCE")
        content = content.replace(old_namespace, new_namespace, 1)
        write(target, content)
        source.unlink()
        print(f"MOVED={rel(source)} -> {rel(target)}")
    elif target.exists():
        content = read(target)
        if new_namespace not in content:
            fail("TARGET_EXISTS_WITHOUT_NEW_NAMESPACE")
        print(f"ALREADY_MOVED={rel(target)}")
    else:
        fail("SOURCE_AND_TARGET_MISSING")


def apply_import_replacements(manifest: dict) -> None:
    replacements = manifest.get("import_replacements", [])
    for path in php_files():
        if rel(path) == manifest["move"]["target"]:
            continue
        content = read(path)
        updated = content
        for item in replacements:
            old = item["old"]
            new = item["new"]
            updated = updated.replace(old, new)
        if updated != content:
            write(path, updated)
            print(f"PATCHED_IMPORT={rel(path)}")


def add_support_import_when_needed(manifest: dict) -> None:
    target = manifest["move"]["target"]
    symbol = manifest["direct_call_symbol"]
    import_line = "use Opus\\Foundation\\Support;"
    for path in php_files():
        relative = rel(path)
        if relative == target:
            continue
        content = read(path)
        if symbol not in content:
            continue
        if import_line in content:
            continue
        if "namespace Opus;" not in content:
            continue
        marker = "namespace Opus;\n"
        updated = content.replace(marker, marker + "\n" + import_line + "\n", 1)
        if updated == content:
            fail(f"SUPPORT_IMPORT_INSERT_FAILED={relative}")
        write(path, updated)
        print(f"PATCHED_SUPPORT_IMPORT={relative}")


def assert_no_legacy_support_runtime_references(manifest: dict) -> None:
    source = manifest["move"]["source"]
    old_import = manifest["import_replacements"][0]["old"]
    runtime_hits: list[str] = []
    non_runtime_hits: list[str] = []
    needles = [source, source.replace("/", "\\"), old_import]
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        if ".git" in path.parts or "vendor" in path.parts:
            continue
        try:
            content = read(path)
        except UnicodeDecodeError:
            continue
        for needle in needles:
            if needle in content:
                hit = f"{rel(path)} contains {needle}"
                if is_non_runtime(path):
                    non_runtime_hits.append(hit)
                else:
                    runtime_hits.append(hit)
    if non_runtime_hits:
        print("NON_RUNTIME_LEGACY_SUPPORT_REFERENCES_FOUND")
        for hit in non_runtime_hits:
            print(hit)
    if runtime_hits:
        print("RUNTIME_LEGACY_SUPPORT_REFERENCES_FOUND")
        for hit in runtime_hits:
            print(hit)
        fail("REFUSING_MOVE_BECAUSE_RUNTIME_LEGACY_SUPPORT_REFERENCE_EXISTS")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    manifest = load_manifest()
    move_support(manifest)
    apply_import_replacements(manifest)
    add_support_import_when_needed(manifest)
    assert_no_legacy_support_runtime_references(manifest)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
