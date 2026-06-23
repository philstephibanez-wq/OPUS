from __future__ import annotations

import json
import shutil
from pathlib import Path
from typing import Any

PATCH_ID = "P4K_MOVE_HTTP_CLASSES"
ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "tools" / "migrations" / "p4k_http_boundary_manifest.json"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8", newline="\n")


def load_manifest() -> dict[str, Any]:
    if not MANIFEST.is_file():
        fail(f"MANIFEST_NOT_FOUND: {rel(MANIFEST)}")
    data = json.loads(read_text(MANIFEST))
    if data.get("patch_id") != PATCH_ID:
        fail("MANIFEST_PATCH_ID_MISMATCH")
    return data


def assert_contract(manifest: dict[str, Any]) -> None:
    contract = manifest.get("contract")
    if not isinstance(contract, dict):
        fail("CONTRACT_MISSING")
    required = [
        "typed_variables",
        "zero_hardcode",
        "zero_bidouille",
        "zero_silent_fallback",
        "wrappers_are_forbidden_fallbacks",
        "no_alias",
        "no_stub",
        "no_relay_file",
    ]
    missing = [key for key in required if contract.get(key) is not True]
    if missing:
        fail("CONTRACT_FLAGS_NOT_TRUE: " + ", ".join(missing))


def assert_move_inputs(manifest: dict[str, Any]) -> None:
    moves = manifest.get("moves")
    if not isinstance(moves, list) or not moves:
        fail("MOVES_MISSING")

    for move in moves:
        if not isinstance(move, dict):
            fail("MOVE_ENTRY_NOT_OBJECT")
        source = ROOT / str(move.get("source", ""))
        target = ROOT / str(move.get("target", ""))
        if not source.is_file():
            fail(f"SOURCE_NOT_FOUND: {rel(source)}")
        if target.exists():
            fail(f"TARGET_ALREADY_EXISTS: {rel(target)}")
        if source.name not in {"Request.php", "Response.php"}:
            fail(f"UNEXPECTED_SOURCE_FOR_P4K: {rel(source)}")


def patch_namespace(source: Path, symbol: str, new_symbol: str) -> str:
    content = read_text(source)
    old_namespace = "namespace Opus;"
    new_namespace = "namespace Opus\\Http;"
    if old_namespace not in content:
        fail(f"OLD_NAMESPACE_NOT_FOUND: {rel(source)}")
    if new_namespace in content:
        fail(f"NEW_NAMESPACE_ALREADY_PRESENT_IN_SOURCE: {rel(source)}")

    expected_class = source.stem
    if f"final class {expected_class}" not in content:
        fail(f"EXPECTED_FINAL_CLASS_NOT_FOUND: {rel(source)}")

    patched = content.replace(old_namespace, new_namespace, 1)

    if source.name == "Request.php":
        if "Support::" not in patched:
            fail("REQUEST_SUPPORT_USAGE_NOT_FOUND")
        patched = patched.replace(
            "namespace Opus\\Http;\n",
            "namespace Opus\\Http;\n\nuse Opus\\Support;\n",
            1,
        )

    if "namespace Opus;" in patched:
        fail(f"LEGACY_NAMESPACE_STILL_PRESENT: {rel(source)}")

    if f"class_alias" in patched or "extends \\Opus\\" in patched:
        fail(f"FORBIDDEN_ALIAS_OR_RELAY_CONTENT: {rel(source)}")

    return patched


def move_http_classes(manifest: dict[str, Any]) -> None:
    for move in manifest["moves"]:
        source = ROOT / str(move["source"])
        target = ROOT / str(move["target"])
        patched = patch_namespace(source, str(move["symbol"]), str(move["new_symbol"]))
        target.parent.mkdir(parents=True, exist_ok=True)
        write_text(target, patched)
        source.unlink()
        print(f"MOVED={rel(source)} -> {rel(target)}")


def insert_use_after_namespace(content: str, use_line: str, file_label: str) -> str:
    if use_line in content:
        return content
    marker = "namespace Opus;\n"
    if marker not in content:
        fail(f"NAMESPACE_MARKER_NOT_FOUND: {file_label}")
    return content.replace(marker, marker + "\n" + use_line + "\n", 1)


def patch_kernel() -> None:
    path = ROOT / "Opus" / "Kernel.php"
    content = read_text(path)
    original = content
    content = insert_use_after_namespace(content, "use Opus\\Http\\Request;", rel(path))
    content = insert_use_after_namespace(content, "use Opus\\Http\\Response;", rel(path))
    if content == original:
        fail("KERNEL_IMPORTS_NOT_CHANGED")
    write_text(path, content)
    print(f"PATCHED={rel(path)}")


def patch_router() -> None:
    path = ROOT / "Opus" / "Router.php"
    content = read_text(path)
    original = content
    content = insert_use_after_namespace(content, "use Opus\\Http\\Request;", rel(path))
    content = insert_use_after_namespace(content, "use Opus\\Http\\Response;", rel(path))
    if content == original:
        fail("ROUTER_IMPORTS_NOT_CHANGED")
    write_text(path, content)
    print(f"PATCHED={rel(path)}")


def assert_no_wrappers(manifest: dict[str, Any]) -> None:
    for move in manifest["moves"]:
        source = ROOT / str(move["source"])
        if source.exists():
            fail(f"SOURCE_STILL_EXISTS_AFTER_MOVE: {rel(source)}")

    for target_name in ["Opus/Http/Request.php", "Opus/Http/Response.php"]:
        target = ROOT / target_name
        content = read_text(target)
        forbidden = ["class_alias", "extends Request", "extends Response", "require_once __DIR__"]
        found = [item for item in forbidden if item in content]
        if found:
            fail(f"FORBIDDEN_WRAPPER_OR_DIRECT_REQUIRE_IN_TARGET: {target_name}: {', '.join(found)}")


def main() -> None:
    manifest = load_manifest()
    assert_contract(manifest)
    assert_move_inputs(manifest)
    move_http_classes(manifest)
    patch_kernel()
    patch_router()
    assert_no_wrappers(manifest)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
