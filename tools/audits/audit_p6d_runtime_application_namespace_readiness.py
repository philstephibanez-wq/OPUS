#!/usr/bin/env python3
"""
P6D Runtime Application namespace readiness + RefBook PHPDoc coverage audit.

Read-only audit:
- Proves current OPUS_Application runtime references before any namespace migration.
- Verifies class/interface/trait PHPDoc coverage used by RefBook ingestion.
- Uses a lightweight PHP lexical scanner to avoid false positives from comments/strings.
"""

from __future__ import annotations

import os
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable, List, Tuple


REPO_ROOT = Path.cwd()
OPUS_ROOT = REPO_ROOT / "Opus"

REQUIRED_RUNTIME_FILES = [
    Path("index.php"),
    Path("www/index.php"),
    Path("Opus/Runtime/Bootstrap.php"),
    Path("Opus/Runtime/Application.php"),
    Path("Opus/Runtime/Kernel.php"),
    Path("Opus/Routing/Router.php"),
    Path("Opus/View/View.php"),
]

FORBIDDEN_RUNTIME_PATHS = [
    Path("Opus/Legacy"),
    Path("Opus/Bootstrap.php"),
    Path("Opus/Application.class.php"),
    Path("Opus/autoloader.class.php"),
    Path("Opus/autoloader_new2.class.php"),
]

NON_RUNTIME_PREFIXES = (
    "DOC/",
    "vendor/",
    "tools/archive/",
    "tools/audits/",
    "tools/smokes/",
    "tools/migrations/",
    "var/",
    ".git/",
)

RUNTIME_REF_EXTENSIONS = {".php"}


@dataclass(frozen=True)
class ClassLike:
    kind: str
    short_name: str
    fqcn: str
    file: Path
    line: int
    docblock: bool


def rel(path: Path) -> str:
    return path.relative_to(REPO_ROOT).as_posix()


def emit_check(name: str, ok: bool, detail: str = "") -> bool:
    print(f"{name}={'OK' if ok else 'FAIL'}{(' ' + detail) if detail else ''}")
    return ok


def php_lint(path: Path) -> bool:
    proc = subprocess.run(
        ["php", "-l", str(path)],
        cwd=REPO_ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    return emit_check(
        "CHECK_PHP_LINT_" + rel(path).upper().replace("/", "_").replace(".", "_"),
        proc.returncode == 0,
        proc.stdout.strip(),
    )


def iter_php_files(root: Path) -> Iterable[Path]:
    for path in root.rglob("*.php"):
        rel_path = rel(path)
        if rel_path.startswith(NON_RUNTIME_PREFIXES):
            continue
        yield path
    for path in root.rglob("*.class.php"):
        rel_path = rel(path)
        if rel_path.startswith(NON_RUNTIME_PREFIXES):
            continue
        yield path


def strip_comments_and_strings_preserve_length(src: str) -> str:
    """Return PHP source with comments and strings blanked while preserving offsets."""
    out = list(src)
    i = 0
    n = len(src)
    while i < n:
        ch = src[i]
        nxt = src[i + 1] if i + 1 < n else ""

        # PHP / C comments.
        if ch == "/" and nxt == "/":
            j = i
            while j < n and src[j] not in "\r\n":
                out[j] = " "
                j += 1
            i = j
            continue
        if ch == "#":
            j = i
            while j < n and src[j] not in "\r\n":
                out[j] = " "
                j += 1
            i = j
            continue
        if ch == "/" and nxt == "*":
            j = i
            out[j] = " "
            out[j + 1] = " "
            j += 2
            while j < n:
                out[j] = " " if src[j] not in "\r\n" else src[j]
                if src[j] == "*" and j + 1 < n and src[j + 1] == "/":
                    out[j] = " "
                    out[j + 1] = " "
                    j += 2
                    break
                j += 1
            i = j
            continue

        # Quoted strings.
        if ch in ("'", '"'):
            quote = ch
            j = i
            out[j] = " "
            j += 1
            while j < n:
                current = src[j]
                out[j] = " " if current not in "\r\n" else current
                if current == "\\":
                    j += 1
                    if j < n:
                        out[j] = " " if src[j] not in "\r\n" else src[j]
                elif current == quote:
                    j += 1
                    break
                j += 1
            i = j
            continue

        i += 1

    return "".join(out)


NAMESPACE_RE = re.compile(r"(?m)^\s*namespace\s+([A-Za-z_\\][A-Za-z0-9_\\]*)\s*;")
CLASSLIKE_RE = re.compile(
    r"(?<![:A-Za-z0-9_\\])(?:abstract\s+|final\s+)?(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)\b",
    re.MULTILINE,
)


def namespace_at(code: str, position: int) -> str:
    namespace = ""
    for match in NAMESPACE_RE.finditer(code):
        if match.start() > position:
            break
        namespace = match.group(1)
    return namespace


def line_at(src: str, position: int) -> int:
    return src.count("\n", 0, position) + 1


def has_docblock_before(src: str, position: int) -> bool:
    prefix = src[:position].rstrip()
    if not prefix.endswith("*/"):
        return False
    start = prefix.rfind("/**")
    if start < 0:
        return False
    between = prefix[start:]
    return between.startswith("/**") and between.endswith("*/")


def scan_classlikes(path: Path) -> List[ClassLike]:
    src = path.read_text(encoding="utf-8", errors="replace")
    code = strip_comments_and_strings_preserve_length(src)
    found: List[ClassLike] = []
    for match in CLASSLIKE_RE.finditer(code):
        kind = match.group(1)
        short_name = match.group(2)
        namespace = namespace_at(code, match.start())
        fqcn = f"{namespace}\\{short_name}" if namespace else short_name
        found.append(
            ClassLike(
                kind=kind,
                short_name=short_name,
                fqcn=fqcn,
                file=path,
                line=line_at(src, match.start()),
                docblock=has_docblock_before(src, match.start()),
            )
        )
    return found


def find_opus_application_refs() -> Tuple[List[Path], List[Path]]:
    runtime_refs: List[Path] = []
    non_runtime_refs: List[Path] = []

    for path in REPO_ROOT.rglob("*"):
        if not path.is_file():
            continue
        rel_path = rel(path)
        if path.suffix not in {".php", ".py", ".md"} and ".class.php" not in path.name:
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        if "OPUS_Application" not in text:
            continue
        if rel_path.startswith(NON_RUNTIME_PREFIXES):
            non_runtime_refs.append(path)
        else:
            runtime_refs.append(path)

    return sorted(runtime_refs), sorted(non_runtime_refs)


def main() -> int:
    print("P6D_RUNTIME_APPLICATION_NAMESPACE_AND_REFBOOK_DOC_AUDIT")
    print("MODE=READ_ONLY")
    print("SCOPE=runtime application namespace readiness + RefBook PHPDoc class coverage")

    ok = True

    ok &= emit_check("CHECK_OPUS_ROOT", OPUS_ROOT.is_dir(), str(OPUS_ROOT))

    for required in REQUIRED_RUNTIME_FILES:
        path = REPO_ROOT / required
        ok &= emit_check(
            "CHECK_REQUIRED_FILE_" + required.as_posix().upper().replace("/", "_").replace(".", "_"),
            path.is_file(),
            required.as_posix(),
        )

    for forbidden in FORBIDDEN_RUNTIME_PATHS:
        path = REPO_ROOT / forbidden
        ok &= emit_check(
            "CHECK_FORBIDDEN_RUNTIME_PATH_ABSENT_" + forbidden.as_posix().upper().replace("/", "_").replace(".", "_"),
            not path.exists(),
            forbidden.as_posix(),
        )

    app_path = REPO_ROOT / "Opus/Runtime/Application.php"
    if app_path.is_file():
        app_text = app_path.read_text(encoding="utf-8", errors="replace")
        ok &= emit_check(
            "CHECK_RUNTIME_APPLICATION_GLOBAL_CLASS_PRESENT",
            "class OPUS_Application" in app_text,
            "OPUS_Application",
        )
        ok &= emit_check(
            "CHECK_RUNTIME_APPLICATION_NAMESPACE_ABSENT",
            "namespace Opus\\Runtime" not in app_text,
        )
        ok &= emit_check(
            "CHECK_RUNTIME_APPLICATION_TARGET_CLASS_ABSENT",
            "class Application" not in app_text,
        )

    for path in [
        REPO_ROOT / "index.php",
        REPO_ROOT / "www/index.php",
        REPO_ROOT / "Opus/Runtime/Application.php",
        REPO_ROOT / "Opus/Runtime/Bootstrap.php",
        REPO_ROOT / "Opus/Runtime/Kernel.php",
        REPO_ROOT / "Opus/Routing/Router.php",
        REPO_ROOT / "Opus/View/View.php",
    ]:
        if path.is_file():
            ok &= php_lint(path)

    runtime_refs, non_runtime_refs = find_opus_application_refs()

    print("OPUS_APPLICATION_REFERENCE_CLASSIFICATION")
    for path in runtime_refs:
        print(f"RUNTIME_REF={rel(path)}")
    for path in non_runtime_refs:
        print(f"NON_RUNTIME_REF={rel(path)}")
    ok &= emit_check("CHECK_OPUS_APPLICATION_RUNTIME_REFS_DETECTED", bool(runtime_refs), str(len(runtime_refs)))

    classlikes: List[ClassLike] = []
    for php_file in sorted(set(iter_php_files(OPUS_ROOT))):
        classlikes.extend(scan_classlikes(php_file))

    classlikes.sort(key=lambda item: (rel(item.file), item.line, item.fqcn))
    missing = [item for item in classlikes if not item.docblock]

    print("REFBOOK_CLASS_CATALOG_SOURCE_COVERAGE")
    print(f"CLASS_LIKE_TOTAL={len(classlikes)}")
    print(f"CLASS_LIKE_NAMESPACED={sum(1 for item in classlikes if '\\\\' in item.fqcn)}")
    print(f"CLASS_LIKE_GLOBAL={sum(1 for item in classlikes if '\\\\' not in item.fqcn)}")
    print(f"CLASS_LIKE_WITH_DOCBLOCK={sum(1 for item in classlikes if item.docblock)}")
    print(f"CLASS_LIKE_MISSING_DOCBLOCK={len(missing)}")

    for item in classlikes:
        print(
            f"CLASS={item.fqcn} KIND={item.kind} DOCBLOCK={'YES' if item.docblock else 'NO'} "
            f"FILE={rel(item.file)} LINE={item.line}"
        )

    for item in missing:
        print(f"MISSING_REFBOOK_DOCBLOCK={item.fqcn} FILE={rel(item.file)} LINE={item.line}")

    ok &= emit_check("CHECK_REFBOOK_CLASS_DOCBLOCK_COVERAGE_100_PERCENT", len(missing) == 0)
    ok &= emit_check("CHECK_REFBOOK_CLASS_CATALOG_HAS_CLASSES", len(classlikes) > 0, str(len(classlikes)))

    print()
    print("P6D_RUNTIME_APPLICATION_NAMESPACE_READINESS_DECISION")
    if ok:
        print("DECISION=P6D_READY_FOR_RUNTIME_APPLICATION_NAMESPACE_MIGRATION")
        print("NEXT_SAFE_STEP=P6D_APPLY_RUNTIME_APPLICATION_NAMESPACE_CONTRACT")
        print("P6D_RUNTIME_APPLICATION_NAMESPACE_AND_REFBOOK_DOC_AUDIT_OK")
        return 0

    print("DECISION=P6D_BLOCKED_REVIEW_REQUIRED")
    print("NEXT_SAFE_STEP=P6D_REVIEW_DOCBLOCK_AND_APPLICATION_REFERENCES")
    print("P6D_RUNTIME_APPLICATION_NAMESPACE_AND_REFBOOK_DOC_AUDIT_FAIL")
    if missing:
        print(" - refbook_docblock_coverage_not_100_percent")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
