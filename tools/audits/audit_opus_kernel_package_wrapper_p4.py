#!/usr/bin/env python3
"""
P4_OPUS_KERNEL_PACKAGE_WRAPPER_AUDIT

Read-only audit for OPUS reborn modern runtime wrappers.

The goal is to decide, before any patch, whether Kernel/Package/Request/Response
and the root namespace wrappers Acl/Fsm/I18n/Router belong in the framework root,
should move under responsibility folders, or should be replaced by historical
ASAP/OPUS classes.
"""
from __future__ import annotations

import re
import subprocess
from pathlib import Path
from typing import Iterable

REPO = Path.cwd()
OPUS = REPO / "Opus"

ROOT_WRAPPERS = {
    "Opus/Acl.php": "Root wrapper for access checks; currently injected by Kernel into Router.",
    "Opus/Fsm.php": "Root wrapper for demo flow; currently injected by Kernel into Router.",
    "Opus/I18n.php": "Root wrapper for package dictionaries; currently injected by Kernel into View.",
    "Opus/Router.php": "Root wrapper/router used by Kernel; overlaps with historical Router.class.php.",
}

MODERN_RUNTIME = {
    "Opus/Kernel.php": "Modern request orchestrator; not part of historical ASAP core.",
    "Opus/Package.php": "Modern site/application package value object.",
    "Opus/PackageRepository.php": "Modern package resolver; currently tied to root /sites and logandplay.",
    "Opus/Request.php": "Modern HTTP request wrapper.",
    "Opus/Response.php": "Modern HTTP response wrapper.",
    "Opus/Support.php": "Modern support helpers/facade candidate.",
    "Opus/View.php": "Modern high-level rendering facade wired to ScoreTemplate.",
}

HISTORICAL_HINTS = {
    "Acl": ["Opus/Acl", "OPUS_Acl"],
    "Fsm": ["Opus/Fsm", "OPUS_Fsm"],
    "I18n": ["Opus/I18n", "OPUS_I18N", "OPUS_I18n"],
    "Router": ["Opus/Router.class.php", "OPUS_Router"],
    "Application": ["Opus/Application.class.php", "OPUS_Application"],
    "Singleton": ["Opus/Singleton.class.php", "OPUS_Singleton"],
}

SCAN_EXTENSIONS = {".php", ".md", ".score", ".json", ".py"}
REFERENCE_NEEDLES = [
    "new Acl", "new Fsm", "new I18n", "new Router", "new Request", "new Response",
    "Opus\\Acl", "Opus\\Fsm", "Opus\\I18n", "Opus\\Router", "Opus\\Kernel",
    "PackageRepository", "Package(", "Request::", "Response::", "Support::", "View(",
]


def run_git(args: list[str]) -> list[str]:
    result = subprocess.run(["git", *args], cwd=REPO, text=True, capture_output=True, check=False)
    if result.returncode != 0:
        raise SystemExit(f"git {' '.join(args)} failed:\n{result.stderr.strip()}")
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]


def git_files() -> list[str]:
    return run_git(["ls-files"])


def read_text(rel: str) -> str:
    path = REPO / rel
    if not path.is_file():
        return ""
    return path.read_text(encoding="utf-8", errors="ignore")


def class_declarations(rel: str) -> list[str]:
    text = read_text(rel)
    declarations: list[str] = []
    for line in text.splitlines():
        if re.search(r"\b(class|interface|trait)\s+", line):
            declarations.append(line.strip())
    return declarations


def grep(files: Iterable[str], needles: Iterable[str]) -> list[str]:
    results: list[str] = []
    needle_list = list(needles)
    for rel in files:
        path = REPO / rel
        if path.suffix.lower() not in SCAN_EXTENSIONS:
            continue
        if not path.is_file():
            continue
        text = read_text(rel)
        for index, line in enumerate(text.splitlines(), start=1):
            if any(needle in line for needle in needle_list):
                results.append(f"{rel}:{index}: {line.strip()}")
    return results


def matching_paths(files: Iterable[str], fragment: str) -> list[str]:
    return sorted(rel for rel in files if fragment in rel)


def contains(rel: str, token: str) -> bool:
    return token in read_text(rel)


def print_file_block(label: str, files: dict[str, str], tracked: list[str]) -> None:
    print(label)
    for rel, reason in files.items():
        exists = rel in tracked
        status = "PRESENT" if exists else "MISSING"
        print(f"{status} {rel} :: {reason}")
        if exists:
            declarations = class_declarations(rel)
            if declarations:
                for declaration in declarations:
                    print(f"  DECL {declaration}")
            else:
                print("  DECL NONE")
    print()


def main() -> int:
    tracked = git_files()
    print("P4_OPUS_KERNEL_PACKAGE_WRAPPER_AUDIT")
    print("MODE=READ_ONLY")
    print("SCOPE=Kernel/Package/Request/Response/View/Support + root wrappers Acl/Fsm/I18n/Router")
    print()

    if not OPUS.is_dir():
        print("CHECK_OPUS_ROOT=FAIL")
        return 1
    print("CHECK_OPUS_ROOT=OK")
    print()

    print_file_block("ROOT_WRAPPER_FILES", ROOT_WRAPPERS, tracked)
    print_file_block("MODERN_RUNTIME_FILES", MODERN_RUNTIME, tracked)

    print("HISTORICAL_COUNTERPART_HINTS")
    for name, hints in HISTORICAL_HINTS.items():
        hits: list[str] = []
        for hint in hints:
            if hint.endswith(".php"):
                hits.extend(matching_paths(tracked, hint))
            else:
                hits.extend(matching_paths(tracked, hint))
                token_hits = [rel for rel in tracked if rel.endswith(".php") and hint in read_text(rel)]
                hits.extend(token_hits)
        hits = sorted(set(hits))
        if hits:
            print(f"{name}=FOUND {' | '.join(hits)}")
        else:
            print(f"{name}=NONE")
    print()

    print("RUNTIME_COUPLING_SCAN")
    refs = grep(tracked, REFERENCE_NEEDLES)
    if refs:
        for ref in refs:
            print(f"REF {ref}")
    else:
        print("NONE")
    print()

    print("PACKAGE_REPOSITORY_RISKS")
    package_repo = "Opus/PackageRepository.php"
    if package_repo in tracked:
        print("SITES_DIR_COUPLING=" + ("YES" if contains(package_repo, "/sites") or contains(package_repo, "'/sites'") else "NO"))
        print("DEFAULT_LOGANDPLAY_COUPLING=" + ("YES" if contains(package_repo, "logandplay") else "NO"))
        print("PACKAGE_CONFIG_DISCOVERY=" + ("YES" if contains(package_repo, "package.php") else "NO"))
    else:
        print("PACKAGE_REPOSITORY_MISSING")
    print()

    print("FIRST_PASS_DECISIONS")
    print("REVIEW_KEEP_OR_REPLACE Opus/Kernel.php :: Keep only if it becomes a thin launcher around historical OPUS_Application or if Application cannot own runtime orchestration.")
    print("REVIEW_MOVE_OR_DELETE Opus/Acl.php :: Root wrapper exists only for Kernel; decide after Kernel decision.")
    print("REVIEW_MOVE_OR_DELETE Opus/Fsm.php :: Root wrapper exists only for Kernel/demo flow; decide after Kernel decision.")
    print("REVIEW_MOVE_OR_DELETE Opus/I18n.php :: Root wrapper exists for Package dictionaries; compare with historical I18n class before patch.")
    print("REVIEW_MOVE_OR_DELETE Opus/Router.php :: Root wrapper overlaps with Opus/Router.class.php; likely not stable as root file.")
    print("REVIEW_SITE_LAYER Opus/Package.php :: Likely site/application descriptor, not core root file.")
    print("REVIEW_SITE_LAYER Opus/PackageRepository.php :: Depends on /sites and logandplay; not acceptable as final framework dependency.")
    print("REVIEW_HTTP_LAYER Opus/Request.php :: Could move under Opus/Http or be owned by application/site layer.")
    print("REVIEW_HTTP_LAYER Opus/Response.php :: Could move under Opus/Http or be owned by application/site layer.")
    print("KEEP_FOR_NOW Opus/View.php :: Facade validated by ScoreTemplate smoke; do not move before rendering contract is stable.")
    print("REVIEW_SUPPORT Opus/Support.php :: Helper/facade; should not remain a catch-all.")
    print()

    print("PROPOSED_NEXT_ACTIONS")
    print("P4B: inspect historical Application/Router/I18n/Acl/Fsm classes before changing root wrappers.")
    print("P4C: decide Kernel fate: keep thin wrapper, move under Opus/Runtime, or replace with OPUS_Application.")
    print("P4D: only then move/delete Acl/Fsm/I18n/Router wrappers.")
    print()

    print(f"FINDINGS_ROOT_WRAPPERS={sum(1 for path in ROOT_WRAPPERS if path in tracked)}")
    print(f"FINDINGS_MODERN_RUNTIME={sum(1 for path in MODERN_RUNTIME if path in tracked)}")
    print(f"FINDINGS_RUNTIME_REFERENCES={len(refs)}")
    print("P4_OPUS_KERNEL_PACKAGE_WRAPPER_AUDIT_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
