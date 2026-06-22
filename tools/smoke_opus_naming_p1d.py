#!/usr/bin/env python3
"""P1D OPUS naming standardization smoke.

Checks the OPUS framework naming convention after the P1D apply tool.
This script is read-only.

Windows note:
    Directory existence checks cannot be used to validate case-only renames
    such as Opus/URL -> Opus/Url because NTFS is commonly case-insensitive.
    Forbidden and required path casing is therefore checked through git ls-files.
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path
from typing import Iterable, List, Set, Tuple

FORBIDDEN_DIRS = (
    "Opus/VIEW",
    "Opus/URL",
    "Opus/SMTP",
    "Opus/Controler",
    "Opus/Scafold",
)
REQUIRED_DIRS = (
    "Opus/Html",
    "Opus/Url",
    "Opus/Smtp",
    "Opus/Controller",
    "Opus/Scaffold",
)
REQUIRED_FILES = (
    "Opus/Html/Html.class.php",
    "Opus/Url/Url.class.php",
    "Opus/Smtp/Smtp.class.php",
    "Opus/Controller/Controller.class.php",
)
FORBIDDEN_TEXT_TOKENS = (
    "OPUS_VIEW_",
    "OPUS_URL_",
    "Opus/VIEW",
    "Opus\\VIEW",
    "Opus/URL",
    "Opus\\URL",
    "Opus/SMTP",
    "Opus\\SMTP",
)
REQUIRED_TEXT_TOKENS = (
    ("Opus/Html/Html.class.php", "class OPUS_Html_Html"),
    ("Opus/Url/Url.class.php", "class OPUS_Url_Url"),
    ("Opus/Componants/Menu/Menu.class.php", "OPUS_Url_Url"),
)
TEXT_SUFFIXES = {
    ".php",
    ".json",
    ".md",
    ".score",
    ".xml",
    ".yml",
    ".yaml",
    ".ini",
    ".txt",
}


def root() -> Path:
    return Path(__file__).resolve().parents[1]


def rel(path: Path, repo_root: Path) -> str:
    return path.relative_to(repo_root).as_posix()


def git_ls_files(repo_root: Path) -> Set[str]:
    proc = subprocess.run(
        ["git", "ls-files"],
        cwd=str(repo_root),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError((proc.stdout + proc.stderr).strip() or "git ls-files failed")
    return {line.strip().replace("\\", "/") for line in proc.stdout.splitlines() if line.strip()}


def tracked_dir_exists(tracked_files: Set[str], directory: str) -> bool:
    prefix = directory.rstrip("/") + "/"
    return any(path.startswith(prefix) for path in tracked_files)


def tracked_file_exists(tracked_files: Set[str], file_rel: str) -> bool:
    return file_rel.replace("\\", "/") in tracked_files


def run_php_lint(repo_root: Path, file_rel: str) -> Tuple[bool, str]:
    proc = subprocess.run(
        ["php", "-l", file_rel],
        cwd=str(repo_root),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    return proc.returncode == 0, (proc.stdout + proc.stderr).strip()


def iter_scope_files(repo_root: Path) -> Iterable[Path]:
    for base_name in ("Opus", "www"):
        base = repo_root / base_name
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path.is_file() and path.suffix.lower() in TEXT_SUFFIXES:
                yield path
    for name in ("composer.json", "README.md"):
        path = repo_root / name
        if path.exists() and path.is_file():
            yield path


def check(condition: bool, label: str, details: str = "") -> Tuple[bool, str]:
    if condition:
        print(f"{label}=OK")
        return True, ""
    print(f"{label}=FAIL" + (f" {details}" if details else ""))
    return False, details


def main() -> int:
    repo_root = root()
    failures: List[Tuple[str, str]] = []

    try:
        tracked_files = git_ls_files(repo_root)
    except RuntimeError as exc:
        print(f"CHECK_GIT_LS_FILES=FAIL {exc}")
        print("P1D_OPUS_NAMING_SMOKE_FAIL")
        print(f" - CHECK_GIT_LS_FILES: {exc}")
        return 1

    for marker in ("Opus", "www", "composer.json"):
        ok, detail = check((repo_root / marker).exists(), "CHECK_REPO_ROOT_" + marker.replace(".", "_").upper(), marker)
        if not ok:
            failures.append(("CHECK_REPO_ROOT", detail))

    for directory in FORBIDDEN_DIRS:
        ok, detail = check(
            not tracked_dir_exists(tracked_files, directory),
            "CHECK_NO_TRACKED_" + directory.replace("/", "_").upper(),
            directory,
        )
        if not ok:
            failures.append(("CHECK_NO_FORBIDDEN_TRACKED_DIR", detail))

    for directory in REQUIRED_DIRS:
        ok, detail = check(
            tracked_dir_exists(tracked_files, directory),
            "CHECK_TRACKED_DIR_" + directory.replace("/", "_").upper(),
            directory,
        )
        if not ok:
            failures.append(("CHECK_REQUIRED_TRACKED_DIR", detail))

    for file_rel in REQUIRED_FILES:
        ok, detail = check(
            tracked_file_exists(tracked_files, file_rel) and (repo_root / file_rel).is_file(),
            "CHECK_FILE_" + file_rel.replace("/", "_").replace(".", "_").upper(),
            file_rel,
        )
        if not ok:
            failures.append(("CHECK_REQUIRED_FILE", detail))

    token_hits: List[str] = []
    for path in iter_scope_files(repo_root):
        text = path.read_text(encoding="utf-8")
        for token in FORBIDDEN_TEXT_TOKENS:
            if token in text:
                token_hits.append(f"{rel(path, repo_root)} :: {token}")
    ok, detail = check(not token_hits, "CHECK_NO_UPPERCASE_LEGACY_TOKENS", "; ".join(token_hits[:10]))
    if not ok:
        failures.append(("CHECK_NO_UPPERCASE_LEGACY_TOKENS", detail))

    for file_rel, token in REQUIRED_TEXT_TOKENS:
        path = repo_root / file_rel
        text = path.read_text(encoding="utf-8") if path.exists() else ""
        ok, detail = check(token in text, "CHECK_TOKEN_" + file_rel.replace("/", "_").replace(".", "_").upper(), token)
        if not ok:
            failures.append(("CHECK_REQUIRED_TOKEN", f"{file_rel} missing {token}"))

    for file_rel in REQUIRED_FILES:
        ok, output = run_php_lint(repo_root, file_rel)
        label = "CHECK_PHP_LINT_" + file_rel.replace("/", "_").replace(".", "_").upper()
        ok2, detail = check(ok, label, output)
        if not ok2:
            failures.append((label, detail))

    if failures:
        print("P1D_OPUS_NAMING_SMOKE_FAIL")
        for label, detail in failures:
            print(f" - {label}: {detail}")
        return 1

    print("P1D_OPUS_NAMING_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
