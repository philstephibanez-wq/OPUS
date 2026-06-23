from __future__ import annotations

import subprocess
from pathlib import Path

PATCH_ID = "P4W_MOVE_LEGACY_ROOT_AUTOLOADER_BOUNDARY"
ROOT = Path(__file__).resolve().parents[2]
SRC = ROOT / "Opus" / "autoloader.class.php"
DST = ROOT / "Opus" / "Legacy" / "Autoload" / "autoloader.class.php"
WWW_INDEX = ROOT / "www" / "index.php"


def fail(message: str) -> None:
    raise RuntimeError(f"{PATCH_ID}: {message}")


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def write_text(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8", newline="\n")


def patch_autoloader(content: str) -> str:
    content = content.replace(
        "require_once __DIR__ . '/Bootstrap.php';",
        "$opusBootstrap = defined('ROOT') ? ROOT . '/Opus/Bootstrap.php' : dirname(__DIR__, 2) . '/Bootstrap.php';\n"
        "require_once $opusBootstrap;",
    )
    content = content.replace(
        "$tmpPath = defined('ROOT') ? ROOT . '/tmp/' : __DIR__ . '/../tmp/';\n"
        "$base = defined('ROOT') ? ROOT : realpath(__DIR__ . '/..');",
        "$legacyRoot = dirname(__DIR__, 3);\n"
        "$tmpPath = defined('ROOT') ? ROOT . '/tmp/' : $legacyRoot . '/tmp/';\n"
        "$base = defined('ROOT') ? ROOT : $legacyRoot;",
    )
    return content


def move_autoloader() -> None:
    if DST.exists():
        if SRC.exists():
            fail("BOTH_SOURCE_AND_DESTINATION_EXIST")
        content = patch_autoloader(read_text(DST))
        write_text(DST, content)
        print(f"ALREADY_MOVED={DST.relative_to(ROOT).as_posix()}")
        return

    if not SRC.exists():
        fail("SOURCE_AUTOLOADER_NOT_FOUND")

    content = patch_autoloader(read_text(SRC))
    write_text(DST, content)
    SRC.unlink()
    print(f"MOVED={SRC.relative_to(ROOT).as_posix()} -> {DST.relative_to(ROOT).as_posix()}")


def patch_www_index() -> None:
    if not WWW_INDEX.exists():
        fail("WWW_INDEX_NOT_FOUND")
    content = read_text(WWW_INDEX)
    old = "require_once ROOT . '/Opus/autoloader.class.php';"
    new = "require_once ROOT . '/Opus/Legacy/Autoload/autoloader.class.php';"
    if old in content:
        content = content.replace(old, new)
        write_text(WWW_INDEX, content)
        print("PATCHED=www/index.php")
    elif new in content:
        print("ALREADY_PATCHED=www/index.php")
    else:
        fail("WWW_INDEX_AUTOLOADER_REQUIRE_NOT_FOUND")


def assert_no_active_root_autoloader_refs() -> None:
    allowed_prefixes = (
        "DOC/",
        "tools/",
        "RUN_",
        ".git/",
        "var/cache/",
    )
    offenders: list[str] = []
    needles = ["Opus/autoloader.class.php", "Opus\\autoloader.class.php"]
    for path in ROOT.rglob("*.php"):
        rel = path.relative_to(ROOT).as_posix()
        if rel == "Opus/Legacy/Autoload/autoloader.class.php":
            continue
        if rel.startswith(allowed_prefixes):
            continue
        text = read_text(path)
        for needle in needles:
            if needle in text:
                offenders.append(f"{rel}::{needle}")
    if offenders:
        print("ACTIVE_ROOT_AUTOLOADER_REFERENCES_FOUND")
        for offender in offenders:
            print(f"ROOT_AUTOLOADER_REFERENCE={offender}")
        fail("REFUSING_ROOT_AUTOLOADER_MOVE_INCOMPLETE")


def php_lint(path: Path) -> None:
    result = subprocess.run(["php", "-l", str(path)], cwd=ROOT, text=True)
    if result.returncode != 0:
        fail(f"PHP_LINT_FAILED={path.relative_to(ROOT).as_posix()}")


def main() -> None:
    print(f"== {PATCH_ID} ==")
    move_autoloader()
    patch_www_index()
    assert_no_active_root_autoloader_refs()
    php_lint(DST)
    php_lint(WWW_INDEX)
    print(f"{PATCH_ID}_OK")


if __name__ == "__main__":
    main()
