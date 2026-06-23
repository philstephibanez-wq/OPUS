#!/usr/bin/env python3
"""P5M_OFFICIALIZE_P5L_ARCHIVE.

Officialize the archived P5L migration in the OPUS tools layout smoke.
"""
from __future__ import annotations

from pathlib import Path

ROOT = Path.cwd()
PATCH_ID = "P5M_OFFICIALIZE_P5L_ARCHIVE"
SMOKE = ROOT / "tools/smokes/smoke_opus_tools_layout_p3b.py"
ARCHIVE_TOKEN = '    "tools/archive/p5_migrations/apply_p5l_stabilize_post_move_audits.py",\n'
ACTIVE_TOKEN = '    "tools/migrations/apply_p5l_stabilize_post_move_audits.py",\n'
AFTER_ARCHIVE = '    "tools/archive/p5_migrations/apply_p5i_repair_runtime_bootstrap_checks.py",\n'
AFTER_ACTIVE = '    "tools/migrations/apply_p5i_repair_runtime_bootstrap_checks.py",\n'


def insert_once(content: str, anchor: str, token: str) -> tuple[str, str]:
    if token in content:
        return content, "ALREADY_PATCHED"
    if anchor not in content:
        raise RuntimeError(f"ANCHOR_NOT_FOUND={anchor.strip()}")
    return content.replace(anchor, anchor + token, 1), "PATCHED"


def main() -> int:
    print(f"== {PATCH_ID} ==")
    content = SMOKE.read_text(encoding="utf-8")
    content, archive_status = insert_once(content, AFTER_ARCHIVE, ARCHIVE_TOKEN)
    content, active_status = insert_once(content, AFTER_ACTIVE, ACTIVE_TOKEN)
    SMOKE.write_text(content, encoding="utf-8", newline="\n")
    print(f"{archive_status}=tools/smokes/smoke_opus_tools_layout_p3b.py::required_archived_p5l")
    print(f"{active_status}=tools/smokes/smoke_opus_tools_layout_p3b.py::forbidden_active_p5l")
    print(f"{PATCH_ID}_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
