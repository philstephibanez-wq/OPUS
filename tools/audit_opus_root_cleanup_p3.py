#!/usr/bin/env python3
"""
P3_OPUS_ROOT_CLEANUP_AUDIT

Read-only audit for OPUS reborn root cleanliness.

This script intentionally does not modify files. It classifies files directly under
Opus/ and root-level tools so the framework can return to an ASAP/OPUS-style
structure without deleting useful historical classes too early.
"""
from __future__ import annotations

import subprocess
from pathlib import Path
from typing import Iterable


REPO = Path.cwd()
OPUS = REPO / "Opus"

KEEP_CORE = {
    "Opus/Application.class.php": "Historical ASAP/OPUS application entry class.",
    "Opus/Singleton.class.php": "Historical OPUS singleton/accessor base; restored in P2.",
    "Opus/AccessorInterface.class.php": "Accessor contract added for protected variables and auto getter/setter policy.",
    "Opus/Bootstrap.php": "Framework bootstrap/autoload entry.",
    "Opus/Router.class.php": "Historical router class; keep until Router wrapper audit is complete.",
}

KEEP_FACADE_REVIEW = {
    "Opus/View.php": "High-level rendering facade wired to ScoreTemplate in P1B.",
    "Opus/Support.php": "Support helpers; review whether it should move under Opus/Support/ or be split.",
}

MODERN_LAYER_REVIEW = {
    "Opus/Kernel.php": "Modern runtime orchestrator added during reborn; not present in historical ASAP. Review against OPUS_Application.",
    "Opus/Package.php": "Modern site/application package object. Review before site extraction.",
    "Opus/PackageRepository.php": "Modern site package resolver currently tied to sites/. Review before site extraction.",
    "Opus/Request.php": "Modern HTTP request wrapper. Review whether framework needs it or site layer owns it.",
    "Opus/Response.php": "Modern HTTP response wrapper. Review whether framework needs it or site layer owns it.",
}

ROOT_WRAPPER_REVIEW = {
    "Opus/Acl.php": "Root namespace wrapper; should probably move under Opus/Acl/ or disappear if historical class is enough.",
    "Opus/Fsm.php": "Root namespace wrapper; should probably move under Opus/Fsm/ or disappear if historical class is enough.",
    "Opus/I18n.php": "Root namespace wrapper; should probably move under Opus/I18n/ or disappear if historical class is enough.",
    "Opus/Router.php": "Root namespace wrapper; duplicates historical Router.class.php role; review carefully.",
}

ROOT_FILE_ORDER = [
    ("KEEP_CORE", KEEP_CORE),
    ("KEEP_FACADE_REVIEW", KEEP_FACADE_REVIEW),
    ("MODERN_LAYER_REVIEW", MODERN_LAYER_REVIEW),
    ("ROOT_WRAPPER_REVIEW", ROOT_WRAPPER_REVIEW),
]


def run_git(args: list[str]) -> list[str]:
    result = subprocess.run(["git", *args], cwd=REPO, text=True, capture_output=True, check=False)
    if result.returncode != 0:
        raise SystemExit(f"git {' '.join(args)} failed:\n{result.stderr.strip()}")
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def git_files() -> list[str]:
    return run_git(["ls-files"])


def is_direct_opus_file(path: str) -> bool:
    parts = Path(path).parts
    return len(parts) == 2 and parts[0] == "Opus"


def is_root_file(path: str) -> bool:
    return len(Path(path).parts) == 1


def is_root_tool(path: str) -> bool:
    parts = Path(path).parts
    return len(parts) == 2 and parts[0] == "tools" and Path(path).suffix.lower() in {".py", ".php", ".cmd", ".bat"}


def classify_opus_root(path: str) -> tuple[str, str]:
    for label, bucket in ROOT_FILE_ORDER:
        if path in bucket:
            return label, bucket[path]
    return "REVIEW_UNKNOWN", "Direct file under Opus/ not known by this audit. Decide keep/move/delete before patching."


def grep_files(files: Iterable[str], needles: Iterable[str]) -> list[str]:
    results: list[str] = []
    needle_list = list(needles)
    for rel in files:
        if not rel.endswith((".php", ".md", ".score", ".json")):
            continue
        path = REPO / rel
        if not path.exists():
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            continue
        for index, line in enumerate(text.splitlines(), start=1):
            if any(needle in line for needle in needle_list):
                results.append(f"{rel}:{index}: {line.strip()}")
    return results


def main() -> int:
    tracked = git_files()
    print("P3_OPUS_ROOT_CLEANUP_AUDIT")
    print("MODE=READ_ONLY")
    print("SCOPE=repo root, Opus/ direct files, tools/ root scripts")
    print()

    if not OPUS.is_dir():
        print("CHECK_OPUS_ROOT=FAIL")
        return 1
    print("CHECK_OPUS_ROOT=OK")
    print()

    root_files = sorted(path for path in tracked if is_root_file(path))
    print("REPO_ROOT_TRACKED_FILES")
    if root_files:
        for rel in root_files:
            print(f"ROOT_FILE {rel}")
    else:
        print("NONE")
    print()

    opus_root_files = sorted(path for path in tracked if is_direct_opus_file(path))
    print("OPUS_ROOT_DIRECT_FILES")
    if opus_root_files:
        for rel in opus_root_files:
            label, reason = classify_opus_root(rel)
            print(f"{label} {rel} :: {reason}")
    else:
        print("NONE")
    print()

    print("ROOT_WRAPPER_REFERENCE_SCAN")
    wrapper_needles = ["new Acl", "new Fsm", "new I18n", "new Router", "Opus\\Acl", "Opus\\Fsm", "Opus\\I18n", "Opus\\Router"]
    refs = grep_files(tracked, wrapper_needles)
    if refs:
        for ref in refs:
            print(f"REF {ref}")
    else:
        print("NONE")
    print()

    print("ROOT_TOOL_SCRIPTS")
    tools = sorted(path for path in tracked if is_root_tool(path))
    if tools:
        for rel in tools:
            status = "KEEP_RECENT_SMOKE_OR_APPLY" if any(tag in rel for tag in ("p0", "p1", "p1b", "p1c", "p1d", "p2")) else "REVIEW_TOOL"
            print(f"{status} {rel}")
    else:
        print("NONE")
    print()

    print("PROPOSED_NEXT_ACTIONS")
    print("P3B: fix or remove root namespace wrappers only after reference scan is reviewed.")
    print("P3C: archive/delete obsolete tools only after their corresponding milestones are committed and no longer needed.")
    print("P3D: review Kernel/Package/Request/Response after singleton/accessor policy is stable.")
    print()
    print(f"FINDINGS_OPUS_ROOT_DIRECT_FILES={len(opus_root_files)}")
    print(f"FINDINGS_ROOT_TOOL_SCRIPTS={len(tools)}")
    print("P3_OPUS_ROOT_CLEANUP_AUDIT_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
