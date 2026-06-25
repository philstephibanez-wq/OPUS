#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""P7A0J smoke: clean-clone validation gate for the P7A0I I18N/SMTP contract.

This smoke intentionally validates the already committed source through a temporary
clone, then validates the current working tree. It is safe to run just after
applying this patch: uncommitted P7A0J files do not prevent the clean-clone check
for the previously committed P7A0I contract.
"""

from __future__ import annotations

from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
from typing import Iterable, List, Sequence

MILESTONE = "P7A0J_CLEAN_CLONE_I18N_SMTP_GATES"
BASE_SMOKE = Path("tools/smokes/smoke_p7a0i_i18n_smtp_contract.py")
CONTRACT = Path("DOC/CONTRACTS/OPUS_I18N_SMTP_CONTRACT.md")
WORKSPACE_STATUS = Path("DOC/WORKSPACE_STATUS.md")

FORBIDDEN_TRACKED_ROOT_NAMES = {
    "tar",
    "http:",
    "https:",
    "HTTP_STATUS",
    "ExitCode",
}

FORBIDDEN_TRACKED_SUFFIXES = {
    ".log",
    ".tmp",
    ".temp",
}

ALLOWED_ROOT_SUFFIXES = {
    ".md",
    ".cmd",
    ".json",
    ".lock",
    ".php",
    ".gitignore",
}


def run(command: Sequence[str], cwd: Path, label: str, *, echo: bool = False) -> subprocess.CompletedProcess[str]:
    completed = subprocess.run(
        list(command),
        cwd=str(cwd),
        check=False,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if echo or completed.returncode != 0:
        if completed.stdout.strip():
            print(completed.stdout.rstrip())
        if completed.stderr.strip():
            print(completed.stderr.rstrip(), file=sys.stderr)
    if completed.returncode != 0:
        print(f"{label}=FAIL")
    else:
        print(f"{label}=OK")
    return completed


def require_ok(completed: subprocess.CompletedProcess[str]) -> None:
    if completed.returncode != 0:
        raise SystemExit(completed.returncode)


def git_text(args: Sequence[str], cwd: Path) -> str:
    completed = subprocess.run(
        ["git", *args],
        cwd=str(cwd),
        check=False,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    if completed.returncode != 0:
        if completed.stderr.strip():
            print(completed.stderr.rstrip(), file=sys.stderr)
        raise SystemExit(completed.returncode)
    return completed.stdout.strip()


def split_git_lines(text: str) -> List[str]:
    return [line.strip() for line in text.splitlines() if line.strip()]


def ensure_files_exist(root: Path, paths: Iterable[Path]) -> None:
    missing = [str(path) for path in paths if not (root / path).is_file()]
    if missing:
        print("CHECK_REQUIRED_FILES=FAIL")
        for path in missing:
            print(path)
        raise SystemExit(1)
    print("CHECK_REQUIRED_FILES=OK")


def check_contract_markers(root: Path) -> None:
    contract_text = (root / CONTRACT).read_text(encoding="utf-8", errors="replace")
    status_text = (root / WORKSPACE_STATUS).read_text(encoding="utf-8", errors="replace")
    required_contract_markers = [
        "Milestone: `P7A0I_I18N_SMTP_CONTRACT`",
        "I18N is mandatory even when an application uses only one language.",
        "official OPUS SMTP/mailer service is mandatory",
        "no silent fallback from SMTP to PHP `mail()`",
    ]
    required_status_markers = [
        "Latest validated milestone: `P7A0I_I18N_SMTP_CONTRACT`",
        "`P7A0I_I18N_SMTP_CONTRACT`: OK in source.",
    ]
    missing = [marker for marker in required_contract_markers if marker not in contract_text]
    missing += [marker for marker in required_status_markers if marker not in status_text]
    if missing:
        print("CHECK_P7A0I_HANDOFF_MARKERS=FAIL")
        for marker in missing:
            print(marker)
        raise SystemExit(1)
    print("CHECK_P7A0I_HANDOFF_MARKERS=OK")


def check_tracked_hygiene(root: Path) -> None:
    tracked = split_git_lines(git_text(["ls-files"], root))
    findings: List[str] = []
    for entry in tracked:
        path = Path(entry)
        parts = path.parts
        if not parts:
            continue
        if len(parts) == 1:
            name = parts[0]
            suffix = Path(name).suffix
            if name in FORBIDDEN_TRACKED_ROOT_NAMES:
                findings.append(entry)
            elif suffix in FORBIDDEN_TRACKED_SUFFIXES:
                findings.append(entry)
            elif suffix == ".zip":
                findings.append(entry)
            elif suffix and suffix not in ALLOWED_ROOT_SUFFIXES:
                # Root binary or capture artifacts are not expected in OPUS source.
                findings.append(entry)
    if findings:
        print("CHECK_TRACKED_ROOT_HYGIENE=FAIL")
        for item in findings[:80]:
            print(item)
        raise SystemExit(1)
    print("CHECK_TRACKED_ROOT_HYGIENE=OK")


def run_base_smoke(root: Path, label: str) -> None:
    completed = run([sys.executable, str(BASE_SMOKE)], root, label, echo=True)
    require_ok(completed)


def validate_clean_clone(source_root: Path, head_sha: str) -> None:
    temp_parent = Path(tempfile.mkdtemp(prefix="opus_p7a0j_"))
    clone_root = temp_parent / "OPUS_clean_clone"
    try:
        require_ok(run(["git", "clone", "--quiet", "--no-local", str(source_root), str(clone_root)], source_root, "CHECK_GIT_CLONE"))
        require_ok(run(["git", "checkout", "--quiet", head_sha], clone_root, "CHECK_GIT_CHECKOUT_HEAD"))
        ensure_files_exist(clone_root, [BASE_SMOKE, CONTRACT, WORKSPACE_STATUS])
        check_contract_markers(clone_root)
        run_base_smoke(clone_root, "CHECK_P7A0I_SMOKE_CLEAN_CLONE")
    finally:
        shutil.rmtree(temp_parent, ignore_errors=True)


def main() -> int:
    print(f"{MILESTONE}_SMOKE")

    repo_root_text = git_text(["rev-parse", "--show-toplevel"], Path.cwd())
    root = Path(repo_root_text).resolve()
    head_sha = git_text(["rev-parse", "HEAD"], root)
    print(f"REPO_ROOT={root}")
    print(f"HEAD={head_sha}")

    status_short = git_text(["status", "--short"], root)
    if status_short:
        print("CHECK_WORKTREE_STATUS=INFO_DIRTY_ALLOWED_FOR_PATCH_VALIDATION")
        print(status_short)
    else:
        print("CHECK_WORKTREE_STATUS=OK_CLEAN")

    ensure_files_exist(root, [BASE_SMOKE, CONTRACT, WORKSPACE_STATUS])
    check_tracked_hygiene(root)
    check_contract_markers(root)
    validate_clean_clone(root, head_sha)
    run_base_smoke(root, "CHECK_P7A0I_SMOKE_SOURCE_TREE")

    print(f"{MILESTONE}_SMOKE_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
