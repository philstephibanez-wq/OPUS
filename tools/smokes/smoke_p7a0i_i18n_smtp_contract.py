#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""P7A0I smoke: I18N and SMTP contract guards."""
from pathlib import Path
import re, sys
CONTRACT = Path("DOC/CONTRACTS/OPUS_I18N_SMTP_CONTRACT.md")
REQUIRED_MARKERS = [
    "I18N is mandatory even when an application uses only one language.",
    "Every user-visible text emitted by a public application path must pass through I18N",
    "mail subjects",
    "mail HTML bodies",
    "mail text bodies",
    "SMTP is optional only for sites that send no email.",
    "official OPUS SMTP/mailer service is mandatory",
    "no direct `mail(...)`",
    "no silent fallback from SMTP to PHP `mail()`",
    "Page = Route + Controller/Action + FSM + ACL + ViewModel + Layout + I18N",
]
SCAN_DIRS = [Path("Opus"), Path("application"), Path("sites"), Path("packages")]
EXCLUDED_PARTS = {"vendor", ".git", "tools", "DOC", "logs", "tmp"}
FORBIDDEN_PATTERNS = [
    ("DIRECT_PHP_MAIL", re.compile(r"(?<![A-Za-z0-9_])mail\s*\(", re.IGNORECASE)),
    ("ADHOC_PHPMAILER", re.compile(r"new\s+\\\\?PHPMailer\b", re.IGNORECASE)),
]
def should_scan(path: Path) -> bool:
    if not path.is_file(): return False
    if path.suffix.lower() not in {".php", ".phtml", ".html"}: return False
    return not any(part in EXCLUDED_PARTS for part in path.parts)
def main() -> int:
    print("P7A0I_I18N_SMTP_CONTRACT_SMOKE")
    if not CONTRACT.exists():
        print("CHECK_CONTRACT_EXISTS=FAIL"); return 1
    text = CONTRACT.read_text(encoding="utf-8", errors="replace")
    missing = [m for m in REQUIRED_MARKERS if m not in text]
    if missing:
        print("CHECK_CONTRACT_MARKERS=FAIL")
        for m in missing: print(m)
        return 1
    print("CHECK_CONTRACT_MARKERS=OK")
    findings = []
    for base in SCAN_DIRS:
        if not base.exists(): continue
        for path in base.rglob("*"):
            if not should_scan(path): continue
            content = path.read_text(encoding="utf-8", errors="ignore")
            for label, pattern in FORBIDDEN_PATTERNS:
                for match in pattern.finditer(content):
                    findings.append((label, str(path), content.count("\n", 0, match.start()) + 1))
    if findings:
        print("CHECK_NO_DIRECT_MAIL_DELIVERY=FAIL")
        for label, path, line in findings[:50]: print(f"{label} {path}:{line}")
        return 1
    print("CHECK_NO_DIRECT_MAIL_DELIVERY=OK")
    print("P7A0I_I18N_SMTP_CONTRACT_SMOKE_OK")
    return 0
if __name__ == "__main__":
    raise SystemExit(main())
