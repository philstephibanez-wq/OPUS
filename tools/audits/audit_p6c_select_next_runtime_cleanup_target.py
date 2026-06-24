#!/usr/bin/env python3
"""P6C select next OPUS runtime cleanup target.

Read-only audit.

Purpose:
- validate that the P6A/P6B legacy runtime boundary removal is stable;
- inspect current runtime boundaries without mutating files;
- select the next safe cleanup target with explicit evidence.

Contract:
- no fallback;
- no mutation;
- only runtime-active files decide the next target;
- archived references are classified as non-runtime evidence, not blockers.
"""
from __future__ import annotations

import subprocess
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

REQUIRED_RUNTIME_FILES = (
    "index.php",
    "www/index.php",
    "Opus/Runtime/Bootstrap.php",
    "Opus/Runtime/Application.php",
    "Opus/Runtime/Kernel.php",
    "Opus/Routing/Router.php",
    "Opus/View/View.php",
)

FORBIDDEN_RUNTIME_PATHS = (
    "Opus/Legacy",
    "Opus/Bootstrap.php",
    "Opus/Application.class.php",
    "Opus/autoloader.class.php",
    "Opus/autoloader_new2.class.php",
)


@dataclass(frozen=True)
class Candidate:
    rank: int
    code: str
    title: str
    evidence: str
    risk: str
    next_step: str


def rel(path: Path) -> str:
    """Return a repository-relative forward-slash path."""
    return path.relative_to(ROOT).as_posix()


def read_text(rel_path: str) -> str:
    """Read a UTF-8 text file with replacement and fail loudly if absent."""
    path = ROOT / rel_path
    if not path.is_file():
        raise FileNotFoundError(rel_path)
    return path.read_text(encoding="utf-8", errors="replace")


def run_php_lint(rel_path: str) -> tuple[bool, str]:
    """Run php -l on a file and return status plus compact output."""
    proc = subprocess.run(
        ["php", "-l", rel_path],
        cwd=str(ROOT),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        check=False,
    )
    return proc.returncode == 0, proc.stdout.strip()


def check(condition: bool, label: str, details: str = "") -> bool:
    """Print an OPUS-style check result."""
    if condition:
        print(label + "=OK" + (" " + details if details else ""))
        return True
    print(label + "=FAIL" + (" " + details if details else ""))
    return False


def runtime_refs_to(token: str) -> list[str]:
    """Find active runtime references to a token, excluding archives and this audit."""
    results: list[str] = []
    ignored_parts = {".git", "vendor"}
    ignored_prefixes = (
        "tools/archive/",
        "tools/migrations/",
    )
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        relative = rel(path)
        parts = set(Path(relative).parts)
        if parts & ignored_parts:
            continue
        if relative.startswith(ignored_prefixes):
            continue
        if relative == "tools/audits/audit_p6c_select_next_runtime_cleanup_target.py":
            continue
        if path.suffix.lower() not in {".php", ".py", ".md", ".json", ".yml", ".yaml", ".txt"}:
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        if token in text:
            results.append(relative)
    return sorted(results)


def select_candidates() -> list[Candidate]:
    """Build ranked cleanup candidates from current runtime evidence."""
    app = read_text("Opus/Runtime/Application.php")
    www = read_text("www/index.php")
    bootstrap = read_text("Opus/Runtime/Bootstrap.php")

    candidates: list[Candidate] = []

    if "class OPUS_Application" in app and "namespace Opus\\Runtime;" not in app:
        candidates.append(
            Candidate(
                1,
                "P6D_RUNTIME_APPLICATION_NAMESPACE_CONTRACT",
                "Runtime Application still exposes the historical global OPUS_Application class.",
                "Opus/Runtime/Application.php contains class OPUS_Application without namespace Opus\\Runtime.",
                "MEDIUM",
                "P6D_AUDIT_RUNTIME_APPLICATION_NAMESPACE_READINESS",
            )
        )

    if "function opus_serve_package_asset" in www:
        candidates.append(
            Candidate(
                2,
                "P6E_WWW_ENTRYPOINT_RESPONSIBILITY_SPLIT",
                "www/index.php still owns package asset serving logic before booting the app.",
                "www/index.php contains opus_serve_package_asset().",
                "LOW_MEDIUM",
                "P6E_AUDIT_WWW_ENTRYPOINT_RESPONSIBILITY_SPLIT",
            )
        )

    manual_requires = bootstrap.count("require_once")
    if manual_requires > 0:
        candidates.append(
            Candidate(
                3,
                "P6F_RUNTIME_BOOTSTRAP_REQUIRE_LIST_REVIEW",
                "Runtime Bootstrap still owns a manual require list despite Composer being active.",
                f"Opus/Runtime/Bootstrap.php contains {manual_requires} require_once instruction(s).",
                "MEDIUM",
                "P6F_AUDIT_BOOTSTRAP_REQUIRE_LIST_COMPOSER_READINESS",
            )
        )

    return candidates


def main() -> int:
    print("P6C_SELECT_NEXT_RUNTIME_CLEANUP_TARGET_AUDIT")
    print("MODE=READ_ONLY")
    print("SCOPE=post-legacy runtime cleanup target selection")

    failures: list[str] = []

    if not check((ROOT / "Opus").is_dir(), "CHECK_OPUS_ROOT", str(ROOT / "Opus")):
        failures.append("CHECK_OPUS_ROOT")

    for rel_path in REQUIRED_RUNTIME_FILES:
        if not check((ROOT / rel_path).is_file(), "CHECK_REQUIRED_RUNTIME_FILE_" + rel_path.replace("/", "_").replace(".", "_").upper(), rel_path):
            failures.append("CHECK_REQUIRED_RUNTIME_FILE")

    for rel_path in FORBIDDEN_RUNTIME_PATHS:
        if not check(not (ROOT / rel_path).exists(), "CHECK_FORBIDDEN_RUNTIME_PATH_ABSENT_" + rel_path.replace("/", "_").replace(".", "_").upper(), rel_path):
            failures.append("CHECK_FORBIDDEN_RUNTIME_PATH")

    for rel_path in ("index.php", "www/index.php", "Opus/Runtime/Bootstrap.php", "Opus/Runtime/Application.php"):
        ok, output = run_php_lint(rel_path)
        if not check(ok, "CHECK_PHP_LINT_" + rel_path.replace("/", "_").replace(".", "_").upper(), output):
            failures.append("CHECK_PHP_LINT")

    legacy_refs = runtime_refs_to("Opus/Legacy") + runtime_refs_to("OPUS_Application")
    # OPUS_Application is currently expected in www/index.php and Runtime/Application.php;
    # Legacy path references are not expected in active runtime.
    active_legacy_path_refs = runtime_refs_to("Opus/Legacy")
    if not check(not active_legacy_path_refs, "CHECK_ACTIVE_LEGACY_PATH_REFERENCES_ABSENT", ", ".join(active_legacy_path_refs)):
        failures.append("CHECK_ACTIVE_LEGACY_PATH_REFERENCES_ABSENT")

    if failures:
        print("P6C_SELECT_NEXT_RUNTIME_CLEANUP_TARGET_AUDIT_FAIL")
        for failure in failures:
            print(" - " + failure)
        return 1

    candidates = select_candidates()
    print("")
    print("P6C_RUNTIME_CLEANUP_CANDIDATES")
    if not candidates:
        print("CANDIDATE_COUNT=0")
        print("DECISION=NO_RUNTIME_CLEANUP_TARGET_SELECTED")
        print("NEXT_SAFE_STEP=P6C_REVIEW_SCOPE_MANUALLY")
    else:
        for candidate in candidates:
            print(f"CANDIDATE_{candidate.rank}={candidate.code}")
            print(f"TITLE_{candidate.rank}={candidate.title}")
            print(f"EVIDENCE_{candidate.rank}={candidate.evidence}")
            print(f"RISK_{candidate.rank}={candidate.risk}")
            print(f"NEXT_STEP_{candidate.rank}={candidate.next_step}")
        selected = candidates[0]
        print("")
        print("P6C_SELECTED_NEXT_TARGET")
        print("DECISION=" + selected.code)
        print("NEXT_SAFE_STEP=" + selected.next_step)

    print("P6C_SELECT_NEXT_RUNTIME_CLEANUP_TARGET_AUDIT_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
