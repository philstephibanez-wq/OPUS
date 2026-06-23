#!/usr/bin/env python3
"""
P4V_ENTRYPOINTS_RUNTIME_SPLIT

Non-destructive audit for OPUS runtime entrypoints.

The root runtime has already moved most modern boundaries out of Opus/.
Before moving Bootstrap.php, Application.class.php, or autoloader.class.php,
we must classify all visible entrypoints and identify which runtime they boot.
"""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parents[2]
AUDIT_ID = "P4V_ENTRYPOINTS_RUNTIME_SPLIT"

ENTRYPOINT_CANDIDATES = [
    Path("index.php"),
    Path("www/index.php"),
    Path("public/index.php"),
]

RUNTIME_MARKERS = {
    "modern_autoloader": "\\Opus\\Autoload\\Autoloader::boot",
    "modern_native_kernel": "\\Opus\\Runtime\\NativeHttpKernel",
    "modern_native_emitter": "\\Opus\\Runtime\\NativeHttpEmitter",
    "root_bootstrap": "Opus/Bootstrap.php",
    "legacy_root_autoloader": "Opus/autoloader.class.php",
    "legacy_application": "OPUS_Application::getInstance",
    "legacy_asset_function": "opus_serve_package_asset",
}

SEARCH_TOKENS = [
    "Opus/autoloader.class.php",
    "autoloader.class.php",
    "OPUS_Application::getInstance",
    "Application.class.php",
    "Opus/Bootstrap.php",
    "\\Opus\\Autoload\\Autoloader::boot",
    "\\Opus\\Runtime\\NativeHttpKernel",
    "NativeHttpKernel",
    "NativeHttpEmitter",
]

IGNORE_DIRS = {
    ".git",
    "vendor",
    "node_modules",
    "tmp",
    "var/cache",
}

NON_RUNTIME_PREFIXES = (
    "DOC/",
    "tools/audits/",
    "tools/migrations/",
    "RUN_",
)


@dataclass(frozen=True)
class Hit:
    path: Path
    line_no: int
    token: str
    line: str


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def iter_php_like_files() -> Iterable[Path]:
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        rel_path = rel(path)
        if any(part in IGNORE_DIRS for part in path.parts):
            continue
        if "/.git/" in rel_path or rel_path.startswith(".git/"):
            continue
        if path.suffix.lower() not in {".php", ".cmd", ".md", ".py"}:
            continue
        yield path


def is_non_runtime(path: Path) -> bool:
    rel_path = rel(path)
    return rel_path.startswith(NON_RUNTIME_PREFIXES)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def classify_entrypoint(path: Path) -> str:
    if not path.exists():
        return "MISSING"
    text = read_text(path)
    has_modern = any(text.find(RUNTIME_MARKERS[key]) >= 0 for key in ("modern_autoloader", "modern_native_kernel", "modern_native_emitter"))
    has_legacy = any(text.find(RUNTIME_MARKERS[key]) >= 0 for key in ("legacy_root_autoloader", "legacy_application"))
    if has_modern and has_legacy:
        return "MIXED_MODERN_AND_LEGACY"
    if has_modern:
        return "MODERN_RUNTIME_ENTRYPOINT"
    if has_legacy:
        return "LEGACY_RUNTIME_ENTRYPOINT"
    return "UNKNOWN_OR_ASSET_ONLY"


def entrypoint_markers(path: Path) -> list[str]:
    if not path.exists():
        return []
    text = read_text(path)
    markers = []
    for name, needle in RUNTIME_MARKERS.items():
        if needle in text:
            markers.append(name)
    return markers


def find_token_hits() -> tuple[list[Hit], list[Hit]]:
    runtime_hits: list[Hit] = []
    non_runtime_hits: list[Hit] = []
    for path in iter_php_like_files():
        text = read_text(path)
        if not any(token in text for token in SEARCH_TOKENS):
            continue
        for idx, line in enumerate(text.splitlines(), start=1):
            stripped = line.strip()
            for token in SEARCH_TOKENS:
                if token in line:
                    hit = Hit(path=path, line_no=idx, token=token, line=stripped)
                    if is_non_runtime(path):
                        non_runtime_hits.append(hit)
                    else:
                        runtime_hits.append(hit)
    return runtime_hits, non_runtime_hits


def print_hit(hit: Hit) -> None:
    print(f"{rel(hit.path)}:{hit.line_no} | {hit.token} | {hit.line}")


def main() -> None:
    print(f"== {AUDIT_ID} ==")

    print("\n== ENTRYPOINT_CLASSIFICATION ==")
    for candidate in ENTRYPOINT_CANDIDATES:
        path = ROOT / candidate
        print(f"{candidate.as_posix()} | {classify_entrypoint(path)}")
        markers = entrypoint_markers(path)
        if markers:
            print(f"  MARKERS={','.join(markers)}")

    runtime_hits, non_runtime_hits = find_token_hits()

    print("\n== RUNTIME_ENTRYPOINT_REFERENCES ==")
    if runtime_hits:
        for hit in runtime_hits:
            print_hit(hit)
    else:
        print("NONE")

    print("\n== NON_RUNTIME_ENTRYPOINT_REFERENCES ==")
    if non_runtime_hits:
        for hit in non_runtime_hits:
            print_hit(hit)
    else:
        print("NONE")

    legacy_boot_refs = [
        h for h in runtime_hits
        if h.token in {"Opus/autoloader.class.php", "autoloader.class.php", "OPUS_Application::getInstance"}
        and rel(h.path) not in {"Opus/autoloader.class.php", "Opus/Application.class.php"}
    ]
    app_file_refs = [h for h in runtime_hits if h.token == "Application.class.php" and rel(h.path) != "Opus/Application.class.php"]

    print("\n== MOVE_READINESS ==")
    print(f"legacy_boot_refs={len(legacy_boot_refs)}")
    print(f"application_file_refs={len(app_file_refs)}")
    print(f"root_autoloader_exists={(ROOT / 'Opus/autoloader.class.php').exists()}")
    print(f"root_application_exists={(ROOT / 'Opus/Application.class.php').exists()}")
    print(f"root_bootstrap_exists={(ROOT / 'Opus/Bootstrap.php').exists()}")

    print("\n== RECOMMENDED_NEXT_BOUNDARY ==")
    if legacy_boot_refs:
        print("LEGACY_WWW_ENTRYPOINT_STILL_ACTIVE")
        print("NEXT_SAFE_WORK=DECIDE_OR_SPLIT_WWW_LEGACY_ENTRYPOINT_BEFORE_MOVING_AUTOLOADER")
        print("KEEP=Opus/Bootstrap.php")
        print("KEEP=Opus/Application.class.php")
        print("KEEP=Opus/autoloader.class.php")
    else:
        print("LEGACY_WWW_ENTRYPOINT_NOT_DETECTED")
        print("NEXT_SAFE_WORK=MOVE_AUTOLOADER_CLASS_TO_LEGACY_BOUNDARY")

    print(f"\n{AUDIT_ID}_OK")


if __name__ == "__main__":
    main()
