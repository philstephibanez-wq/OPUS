from __future__ import annotations

import subprocess
import sys
from pathlib import Path

PATCH_ID = "P4U_MOVE_UNUSED_AUTOLOADER_NEW2_TO_LEGACY"
ROOT = Path(__file__).resolve().parents[2]
OLD_PATH = ROOT / "Opus" / "autoloader_new2.class.php"
NEW_PATH = ROOT / "Opus" / "Legacy" / "Autoload" / "autoloader_new2.class.php"

RUNTIME_SCAN_EXTENSIONS = {".php"}
NON_RUNTIME_PREFIXES = (
    "DOC/",
    "tools/",
)
NON_RUNTIME_FILES = {
    "RUN_P4U_MOVE_UNUSED_AUTOLOADER_NEW2_TO_LEGACY.cmd",
}

# Strict legacy-only markers. Do not scan for the generic token "Autoloader",
# because OPUS now has a modern namespaced Opus\Autoload\Autoloader and
# a different legacy DirectoriesAutoloader. Those are not uses of
# autoloader_new2.class.php.
TOKENS = (
    "autoloader_new2.class.php",
    "_import(",
)


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def is_non_runtime(path: Path) -> bool:
    relative = rel(path)
    return relative.startswith(NON_RUNTIME_PREFIXES) or relative in NON_RUNTIME_FILES


def runtime_php_files() -> list[Path]:
    return [
        path
        for path in ROOT.rglob("*.php")
        if ".git" not in path.parts
        and "vendor" not in path.parts
        and not is_non_runtime(path)
    ]


def assert_no_external_runtime_usage() -> None:
    owners = {OLD_PATH.resolve(), NEW_PATH.resolve()}
    hits: list[str] = []
    for path in runtime_php_files():
        if path.resolve() in owners:
            continue
        try:
            text = read_text(path)
        except UnicodeDecodeError:
            continue
        for line_no, line in enumerate(text.splitlines(), start=1):
            stripped = line.strip()
            for token in TOKENS:
                if token in stripped:
                    hits.append(f"{rel(path)}:{line_no} | {token} | {stripped}")
    if hits:
        print("RUNTIME_UNUSED_AUTOLOADER_NEW2_REFERENCES_FOUND")
        for hit in hits:
            print(f"LEGACY_AUTOLOADER_NEW2_REFERENCE={hit}")
        fail("REFUSING_TO_MOVE_AUTOLOADER_NEW2_WITH_RUNTIME_REFERENCES")


def move_file() -> None:
    if NEW_PATH.exists() and OLD_PATH.exists():
        fail("BOTH_OLD_AND_NEW_AUTOLOADER_NEW2_EXIST")
    if NEW_PATH.exists():
        print(f"ALREADY_MOVED={rel(NEW_PATH)}")
        return
    if not OLD_PATH.exists():
        fail("SOURCE_AUTOLOADER_NEW2_NOT_FOUND")
    content = read_text(OLD_PATH)
    write_text(NEW_PATH, content)
    OLD_PATH.unlink()
    print(f"MOVED={rel(OLD_PATH)} -> {rel(NEW_PATH)}")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED={rel(path)}")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    assert_no_external_runtime_usage()
    move_file()
    php_lint(NEW_PATH)
    assert_no_external_runtime_usage()
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
