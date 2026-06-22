#!/usr/bin/env python3
"""
P1C OPUS naming audit.

Read-only audit for OPUS reborn naming consistency.
It does not modify files.

Scope:
- framework only: Opus/ and www/
- sites/, vendor/, var/cache/, var/log/, var/tmp/ are intentionally out of scope

Goal:
- identify legacy uppercase folder names such as VIEW, URL, SMTP
- identify legacy class segments such as OPUS_VIEW_*, OPUS_URL_*, OPUS_SMTP_*
- document proposed target names without changing runtime behavior
"""
from __future__ import annotations

import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, List, Tuple

ROOT_MARKERS = ("Opus", "www", "composer.json")
FRAMEWORK_SCOPE = ("Opus", "www")
TEXT_SUFFIXES = {".php", ".json", ".md", ".score", ".xml"}

# Deliberately not exhaustive. P1C starts from the visibly inconsistent names.
PROPOSED_RENAMES = {
    "VIEW": {
        "kind": "folder_and_legacy_class_segment",
        "target_folder": "Html",
        "target_segment": "Html",
        "reason": "View.php is already the high-level rendering facade; legacy VIEW/Html.class.php is the HTML view implementation.",
    },
    "URL": {
        "kind": "folder_and_legacy_class_segment",
        "target_folder": "Url",
        "target_segment": "Url",
        "reason": "Url is not a UI component; it is a URL value/resolver used by Link/Menu/Router.",
    },
    "SMTP": {
        "kind": "folder_name_only_first",
        "target_folder": "Smtp",
        "target_segment": "Smtp",
        "reason": "SMTP is a mail transport/infrastructure concern, not a UI component. Its internal third-party namespace must be reviewed before class renaming.",
    },
}

IGNORED_DIR_PARTS = {
    ".git",
    "vendor",
    "sites",
    "var/cache",
    "var/log",
    "var/tmp",
}

LEGACY_CLASS_SEGMENT_RE = re.compile(r"\bOPUS_([A-Z]{2,})(?=_)|\bOPUS_([A-Z]{2,})\b")
NAMESPACE_RE = re.compile(r"\bnamespace\s+([^;{]+)")


@dataclass(frozen=True)
class Finding:
    code: str
    path: str
    line: int
    text: str


def is_repo_root(root: Path) -> bool:
    return all((root / marker).exists() for marker in ROOT_MARKERS)


def is_ignored(path: Path, root: Path) -> bool:
    rel = path.relative_to(root).as_posix()
    for ignored in IGNORED_DIR_PARTS:
        if rel == ignored or rel.startswith(ignored + "/"):
            return True
    return False


def iter_text_files(root: Path) -> Iterable[Path]:
    for scope in FRAMEWORK_SCOPE:
        base = root / scope
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path.is_file() and path.suffix.lower() in TEXT_SUFFIXES and not is_ignored(path, root):
                yield path
    for path in (root / "composer.json", root / "README.md"):
        if path.exists() and path.is_file():
            yield path


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def audit_uppercase_directories(root: Path) -> List[Tuple[str, str, str]]:
    findings: List[Tuple[str, str, str]] = []
    opus = root / "Opus"
    if not opus.exists():
        return findings
    for path in sorted(opus.rglob("*")):
        if not path.is_dir() or is_ignored(path, root):
            continue
        name = path.name
        if any(ch.isalpha() for ch in name) and name == name.upper() and len(name) > 1:
            target = PROPOSED_RENAMES.get(name, {}).get("target_folder", "REVIEW")
            findings.append((path.relative_to(root).as_posix(), name, str(target)))
    return findings


def audit_legacy_class_segments(root: Path) -> List[Finding]:
    findings: List[Finding] = []
    for path in iter_text_files(root):
        text = read_text(path)
        rel = path.relative_to(root).as_posix()
        for line_no, line in enumerate(text.splitlines(), start=1):
            for match in LEGACY_CLASS_SEGMENT_RE.finditer(line):
                segment = match.group(1) or match.group(2) or ""
                if segment in PROPOSED_RENAMES:
                    findings.append(Finding("LEGACY_CLASS_SEGMENT", rel, line_no, line.strip()))
    return findings


def audit_namespace_fragments(root: Path) -> List[Finding]:
    findings: List[Finding] = []
    for path in iter_text_files(root):
        text = read_text(path)
        rel = path.relative_to(root).as_posix()
        for line_no, line in enumerate(text.splitlines(), start=1):
            match = NAMESPACE_RE.search(line)
            if not match:
                continue
            namespace = match.group(1).strip()
            parts = [part.strip() for part in namespace.split("\\")]
            for part in parts:
                if part in PROPOSED_RENAMES:
                    findings.append(Finding("UPPERCASE_NAMESPACE_SEGMENT", rel, line_no, line.strip()))
    return findings


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    if not is_repo_root(root):
        print("P1C_OPUS_NAMING_AUDIT_FAIL")
        print(f"Repository root not detected from {root}")
        return 2

    uppercase_dirs = audit_uppercase_directories(root)
    legacy_segments = audit_legacy_class_segments(root)
    namespace_segments = audit_namespace_fragments(root)

    print("P1C_OPUS_NAMING_AUDIT")
    print("SCOPE=Opus/, www/, composer.json, README.md")
    print("MODE=READ_ONLY")
    print("")

    print("DECISIONS")
    print("URL_IS_COMPONENT=NO")
    print("URL_TARGET=Opus/Url + OPUS_Url_Url")
    print("LINK_IS_COMPONENT=YES")
    print("LINK_TARGET=Opus/Componants/Link")
    print("")

    print("PROPOSED_RENAMES")
    for source, meta in PROPOSED_RENAMES.items():
        print(f"{source} -> folder:{meta['target_folder']} segment:{meta['target_segment']} :: {meta['reason']}")
    print("")

    print("UPPERCASE_DIRECTORIES")
    if uppercase_dirs:
        for rel, source, target in uppercase_dirs:
            print(f"DIR {rel} :: {source} -> {target}")
    else:
        print("NONE")
    print("")

    print("LEGACY_CLASS_SEGMENTS")
    if legacy_segments:
        for finding in legacy_segments:
            print(f"{finding.code} {finding.path}:{finding.line}: {finding.text}")
    else:
        print("NONE")
    print("")

    print("UPPERCASE_NAMESPACE_SEGMENTS")
    if namespace_segments:
        for finding in namespace_segments:
            print(f"{finding.code} {finding.path}:{finding.line}: {finding.text}")
    else:
        print("NONE")
    print("")

    total = len(uppercase_dirs) + len(legacy_segments) + len(namespace_segments)
    print(f"FINDINGS_TOTAL={total}")
    print("P1C_OPUS_NAMING_AUDIT_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
