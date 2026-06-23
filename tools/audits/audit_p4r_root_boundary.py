from __future__ import annotations

import re
from pathlib import Path

AUDIT_ID = "P4R_ROOT_BOUNDARY_AUDIT"
ROOT = Path(__file__).resolve().parents[2]
OPUS_ROOT = ROOT / "Opus"


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def rel(path: Path) -> str:
    return str(path.relative_to(ROOT)).replace("\\", "/")


def immediate_php_files() -> list[Path]:
    if not OPUS_ROOT.is_dir():
        raise RuntimeError(f"{AUDIT_ID}: OPUS_ROOT_NOT_FOUND={OPUS_ROOT}")
    return sorted(
        [p for p in OPUS_ROOT.iterdir() if p.is_file() and (p.suffix == ".php" or p.name.endswith(".class.php"))],
        key=lambda item: item.name.lower(),
    )


def classify_file(path: Path) -> dict[str, object]:
    content = read_text(path)
    class_names = re.findall(r"(?m)^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)", content)
    interfaces = re.findall(r"(?m)^\s*interface\s+([A-Za-z_][A-Za-z0-9_]*)", content)
    namespace_match = re.search(r"(?m)^\s*namespace\s+([^;]+);", content)
    require_matches = re.findall(r"(?m)\b(?:require_once|require|include_once|include)\s+([^;]+);", content)
    opus_symbols = sorted(set(re.findall(r"\bOPUS_[A-Za-z0-9_]+\b", content)))
    return {
        "path": rel(path),
        "namespace": namespace_match.group(1).strip() if namespace_match else "<global>",
        "classes": class_names,
        "interfaces": interfaces,
        "legacy_filename": path.name.endswith(".class.php"),
        "legacy_symbols": [name for name in class_names + interfaces if name.startswith("OPUS_")],
        "requires": [entry.strip() for entry in require_matches],
        "opus_symbols": opus_symbols,
        "lines": content.count("\n") + 1,
    }


def print_section(title: str) -> None:
    print(f"\n== {title} ==")


def main() -> int:
    print(f"== {AUDIT_ID} ==")
    files = immediate_php_files()
    records = [classify_file(path) for path in files]

    print_section("ROOT_PHP_FILES")
    for record in records:
        classes = ",".join(record["classes"] or record["interfaces"] or ["<none>"])
        print(f"{record['path']} | ns={record['namespace']} | symbols={classes} | lines={record['lines']}")

    print_section("LEGACY_ROOT_FILES")
    legacy_records = [record for record in records if record["legacy_filename"] or record["legacy_symbols"]]
    if not legacy_records:
        print("NONE")
    for record in legacy_records:
        symbols = ",".join(record["legacy_symbols"] or record["classes"] or record["interfaces"] or ["<none>"])
        print(f"{record['path']} | symbols={symbols}")

    print_section("ROOT_MANUAL_REQUIRES")
    require_records = [record for record in records if record["requires"]]
    if not require_records:
        print("NONE")
    for record in require_records:
        for entry in record["requires"]:
            print(f"{record['path']} | {entry}")

    print_section("BOUNDARY_CHECKS")
    checks = {
        "Runtime kernel": ROOT / "Opus" / "Runtime" / "Kernel.php",
        "Routing router": ROOT / "Opus" / "Routing" / "Router.php",
        "Application definition": ROOT / "Opus" / "Application" / "ApplicationDefinition.php",
        "Application registry": ROOT / "Opus" / "Application" / "ApplicationRegistry.php",
        "I18n service": ROOT / "Opus" / "I18n" / "I18n.php",
        "View service": ROOT / "Opus" / "View" / "View.php",
        "Security ACL": ROOT / "Opus" / "Security" / "Acl.php",
        "FSM engine": ROOT / "Opus" / "FSM" / "Fsm.php",
        "Root Bootstrap": ROOT / "Opus" / "Bootstrap.php",
    }
    for label, path in checks.items():
        print(f"{label}: {'OK' if path.is_file() else 'MISSING'} | {rel(path) if path.exists() else path}")

    print_section("RECOMMENDED_NEXT_BOUNDARY")
    if any(record["path"] == "Opus/Bootstrap.php" for record in records):
        print("KEEP_BOOTSTRAP_STABLE_UNTIL_ENTRYPOINTS_ARE_AUDITED")
    if legacy_records:
        print("NEXT_SAFE_WORK=LEGACY_ROOT_CLASS_AUDIT_BEFORE_MOVE")
    else:
        print("NEXT_SAFE_WORK=BOOTSTRAP_ENTRYPOINT_MOVE_REVIEW")

    print(f"\n{AUDIT_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
