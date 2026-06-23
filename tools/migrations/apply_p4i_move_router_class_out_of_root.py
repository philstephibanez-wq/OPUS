from __future__ import annotations

from pathlib import Path
from typing import Iterable


PATCH_ID: str = "P4I_MOVE_ROUTER_CLASS_OUT_OF_ROOT"
ROOT: Path = Path(__file__).resolve().parents[2]
SOURCE: Path = ROOT / "Opus" / "Router.class.php"
TARGET: Path = ROOT / "Opus" / "Router" / "Router.class.php"

FORBIDDEN_LEGACY_PATHS: tuple[str, ...] = (
    "Opus/Router.class.php",
    "Opus\\Router.class.php",
    "Router.class.php",
)

SCAN_SUFFIXES: tuple[str, ...] = (
    ".php",
    ".json",
    ".xml",
    ".score",
    ".md",
    ".cmd",
    ".py",
)

IGNORED_DIR_PARTS: frozenset[str] = frozenset({
    ".git",
    "vendor",
    "var",
    "node_modules",
})

SELF_FILES: frozenset[Path] = frozenset({
    Path("tools/migrations/apply_p4i_move_router_class_out_of_root.py"),
    Path("RUN_P4I_MOVE_ROUTER_CLASS_OUT_OF_ROOT.cmd"),
})


def rel(path: Path) -> Path:
    return path.resolve().relative_to(ROOT.resolve())


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def iter_scanned_files() -> Iterable[Path]:
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        relative: Path = rel(path)
        if any(part in IGNORED_DIR_PARTS for part in relative.parts):
            continue
        if relative in SELF_FILES:
            continue
        if path.suffix.lower() not in SCAN_SUFFIXES:
            continue
        yield path


def assert_no_legacy_path_references() -> None:
    offenders: list[str] = []
    for path in iter_scanned_files():
        text: str = path.read_text(encoding="utf-8", errors="ignore")
        for needle in FORBIDDEN_LEGACY_PATHS:
            if needle in text:
                offenders.append(f"{rel(path)} contains {needle}")
    if offenders:
        print("LEGACY_ROUTER_PATH_REFERENCES_FOUND")
        for offender in offenders:
            print(offender)
        fail("REFUSING_MOVE_BECAUSE_DIRECT_FILE_PATH_REFERENCE_EXISTS")


def main() -> None:
    print(f"== {PATCH_ID} ==")

    if not SOURCE.is_file():
        fail(f"SOURCE_NOT_FOUND: {rel(SOURCE)}")
    if TARGET.exists():
        fail(f"TARGET_ALREADY_EXISTS: {rel(TARGET)}")

    assert_no_legacy_path_references()

    TARGET.parent.mkdir(parents=True, exist_ok=True)
    SOURCE.replace(TARGET)

    if SOURCE.exists():
        fail(f"SOURCE_STILL_EXISTS_AFTER_MOVE: {rel(SOURCE)}")
    if not TARGET.is_file():
        fail(f"TARGET_NOT_FOUND_AFTER_MOVE: {rel(TARGET)}")

    print(f"MOVED={rel(SOURCE)} -> {rel(TARGET)}")
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
