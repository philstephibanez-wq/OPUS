#!/usr/bin/env python3
"""P4N_MOVE_PACKAGE_CLASSES.

Moves package-domain classes out of the OPUS root without wrappers, aliases,
stubs, or silent fallback. The migration is idempotent: already moved files are
validated rather than treated as a fallback path.
"""
from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path
from typing import Any

PATCH_ID = "P4N_MOVE_PACKAGE_CLASSES"
ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "tools" / "migrations" / "p4n_package_boundary_manifest.json"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read_manifest() -> dict[str, Any]:
    data = json.loads(MANIFEST.read_text(encoding="utf-8"))
    if data.get("patch_id") != PATCH_ID:
        fail("MANIFEST_PATCH_ID_MISMATCH")
    contract = data.get("contract", {})
    for key in ("typed_variables", "no_hardcode", "no_wrapper", "no_alias", "no_stub", "no_silent_fallback"):
        if contract.get(key) is not True:
            fail(f"CONTRACT_FLAG_NOT_TRUE={key}")
    return data


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        fail(f"EXPECTED_ONE_REPLACEMENT={label};COUNT={count}")
    return content.replace(old, new, 1)


def ensure_use(content: str, use_line: str) -> str:
    if use_line in content:
        return content
    marker = "namespace Opus;\n"
    if marker not in content:
        fail("NAMESPACE_OPUS_NOT_FOUND_FOR_USE_INSERT")
    return content.replace(marker, marker + "\n" + use_line + "\n", 1)


def ensure_use_after_namespace(content: str, namespace_line: str, use_line: str) -> str:
    if use_line in content:
        return content
    if namespace_line not in content:
        fail("TARGET_NAMESPACE_NOT_FOUND_FOR_USE_INSERT")
    return content.replace(namespace_line + "\n", namespace_line + "\n\n" + use_line + "\n", 1)


def move_domain_file(move: dict[str, Any]) -> None:
    source = ROOT / str(move["source"])
    target = ROOT / str(move["target"])
    old_namespace = str(move["old_namespace"])
    new_namespace = str(move["new_namespace"])

    if target.exists() and not source.exists():
        content = read_text(target)
        if new_namespace not in content:
            fail(f"TARGET_EXISTS_WITHOUT_NEW_NAMESPACE={target.relative_to(ROOT)}")
        print(f"ALREADY_MOVED={source.relative_to(ROOT)} -> {target.relative_to(ROOT)}")
        return

    if not source.exists():
        fail(f"SOURCE_FILE_NOT_FOUND={source.relative_to(ROOT)}")
    if target.exists():
        fail(f"TARGET_ALREADY_EXISTS={target.relative_to(ROOT)}")

    content = read_text(source)
    content = replace_once(content, old_namespace, new_namespace, str(source.relative_to(ROOT)))
    required_use = move.get("required_use")
    if required_use:
        content = ensure_use_after_namespace(content, new_namespace, str(required_use))

    target.parent.mkdir(parents=True, exist_ok=True)
    write_text(target, content)
    source.unlink()
    print(f"MOVED={source.relative_to(ROOT)} -> {target.relative_to(ROOT)}")


def patch_runtime_uses(patch: dict[str, Any]) -> None:
    path = ROOT / str(patch["file"])
    if not path.exists():
        fail(f"RUNTIME_PATCH_FILE_NOT_FOUND={path.relative_to(ROOT)}")
    content = read_text(path)
    original = content
    for use_line in patch.get("uses", []):
        content = ensure_use(content, str(use_line))
    if content != original:
        write_text(path, content)
        print(f"PATCHED={path.relative_to(ROOT)}")
    else:
        print(f"UNCHANGED={path.relative_to(ROOT)}")


def assert_no_root_package_files(manifest: dict[str, Any]) -> None:
    for move in manifest["moves"]:
        source = ROOT / str(move["source"])
        target = ROOT / str(move["target"])
        if source.exists():
            fail(f"ROOT_SOURCE_STILL_EXISTS={source.relative_to(ROOT)}")
        if not target.exists():
            fail(f"TARGET_MISSING_AFTER_MOVE={target.relative_to(ROOT)}")


def assert_no_wrappers(manifest: dict[str, Any]) -> None:
    for move in manifest["moves"]:
        source = ROOT / str(move["source"])
        if source.exists():
            fail(f"WRAPPER_OR_LEGACY_FILE_STILL_PRESENT={source.relative_to(ROOT)}")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    manifest = read_manifest()
    for move in manifest["moves"]:
        move_domain_file(move)
    for patch in manifest["runtime_patches"]:
        patch_runtime_uses(patch)
    assert_no_root_package_files(manifest)
    assert_no_wrappers(manifest)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise
