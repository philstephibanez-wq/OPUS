from __future__ import annotations

import json
from pathlib import Path
from typing import Any, Dict, Iterable, List, Tuple

PATCH_ID: str = "P4J_MOVE_LEGACY_ROOT_CLASSES"
REPO_ROOT: Path = Path(__file__).resolve().parents[2]
MANIFEST_PATH: Path = REPO_ROOT / "tools" / "migrations" / "p4j_legacy_root_cleanup_manifest.json"
TEXT_SUFFIXES: Tuple[str, ...] = (
    ".php",
    ".json",
    ".md",
    ".txt",
    ".cmd",
    ".py",
    ".xml",
    ".score",
    ".yml",
    ".yaml",
)
EXCLUDED_DIRS: Tuple[str, ...] = (
    ".git",
    "vendor",
    "var/cache",
    "node_modules",
)


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def rel(path: Path) -> str:
    return path.relative_to(REPO_ROOT).as_posix()


def read_manifest() -> Dict[str, Any]:
    if not MANIFEST_PATH.is_file():
        fail(f"MANIFEST_NOT_FOUND={rel(MANIFEST_PATH)}")
    payload: Any = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    if not isinstance(payload, dict):
        fail("MANIFEST_ROOT_MUST_BE_OBJECT")
    if payload.get("patch_id") != PATCH_ID:
        fail("MANIFEST_PATCH_ID_MISMATCH")
    moves: Any = payload.get("moves")
    if not isinstance(moves, list) or not moves:
        fail("MANIFEST_MOVES_MUST_BE_NON_EMPTY_LIST")
    return payload


def is_text_file(path: Path) -> bool:
    return path.is_file() and path.suffix.lower() in TEXT_SUFFIXES


def is_excluded(path: Path) -> bool:
    relative: str = rel(path)
    return any(relative == item or relative.startswith(item + "/") for item in EXCLUDED_DIRS)


def iter_text_files() -> Iterable[Path]:
    for path in REPO_ROOT.rglob("*"):
        if is_excluded(path):
            continue
        if is_text_file(path):
            yield path


def is_non_runtime_reference(relative: str, non_runtime_roots: List[str]) -> bool:
    normalized: str = relative.replace("\\", "/")
    for root in non_runtime_roots:
        root_normalized: str = root.replace("\\", "/")
        if root_normalized.endswith("/"):
            if normalized.startswith(root_normalized):
                return True
        elif normalized == root_normalized:
            return True
    return False


def assert_no_runtime_legacy_path_references(manifest: Dict[str, Any]) -> None:
    moves: List[Dict[str, str]] = [dict(item) for item in manifest["moves"]]
    non_runtime_roots: List[str] = [str(item) for item in manifest.get("non_runtime_reference_roots", [])]
    needles: List[str] = []
    for move in moves:
        source: str = str(move["source"]).replace("\\", "/")
        needles.extend([source, source.replace("/", "\\"), Path(source).name])

    runtime_hits: List[str] = []
    non_runtime_hits: List[str] = []
    for file_path in iter_text_files():
        relative: str = rel(file_path)
        content: str
        try:
            content = file_path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        for needle in needles:
            if needle in content:
                hit: str = f"{relative} contains {needle}"
                if is_non_runtime_reference(relative, non_runtime_roots):
                    non_runtime_hits.append(hit)
                else:
                    runtime_hits.append(hit)

    if non_runtime_hits:
        print("NON_RUNTIME_LEGACY_ROOT_REFERENCES_FOUND")
        for hit in sorted(set(non_runtime_hits)):
            print(hit)

    if runtime_hits:
        print("RUNTIME_LEGACY_ROOT_REFERENCES_FOUND")
        for hit in sorted(set(runtime_hits)):
            print(hit)
        fail("REFUSING_MOVE_BECAUSE_RUNTIME_DIRECT_FILE_PATH_REFERENCE_EXISTS")


def assert_manifest_targets_are_unique(manifest: Dict[str, Any]) -> None:
    sources: List[str] = [str(item["source"]) for item in manifest["moves"]]
    targets: List[str] = [str(item["target"]) for item in manifest["moves"]]
    symbols: List[str] = [str(item["symbol"]) for item in manifest["moves"]]
    if len(sources) != len(set(sources)):
        fail("DUPLICATE_SOURCE_IN_MANIFEST")
    if len(targets) != len(set(targets)):
        fail("DUPLICATE_TARGET_IN_MANIFEST")
    if len(symbols) != len(set(symbols)):
        fail("DUPLICATE_SYMBOL_IN_MANIFEST")


def move_classes(manifest: Dict[str, Any]) -> None:
    for raw_move in manifest["moves"]:
        move: Dict[str, str] = {str(key): str(value) for key, value in raw_move.items()}
        source: Path = REPO_ROOT / move["source"]
        target: Path = REPO_ROOT / move["target"]
        if not source.is_file():
            fail(f"SOURCE_NOT_FOUND={rel(source)}")
        if target.exists():
            fail(f"TARGET_ALREADY_EXISTS={rel(target)}")
        target.parent.mkdir(parents=True, exist_ok=True)
        source.rename(target)
        if source.exists():
            fail(f"SOURCE_STILL_EXISTS_AFTER_MOVE={rel(source)}")
        print(f"MOVED={rel(source)}=>{rel(target)}")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    manifest: Dict[str, Any] = read_manifest()
    assert_manifest_targets_are_unique(manifest)
    assert_no_runtime_legacy_path_references(manifest)
    move_classes(manifest)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
