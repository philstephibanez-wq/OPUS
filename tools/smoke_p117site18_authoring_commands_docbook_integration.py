#!/usr/bin/env python3
# -*- coding: utf-8 -*-
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

OPUS_ROOT = Path(os.environ.get("OPUS_ROOT", r"H:\\OPUS"))
PACKAGE_ROOT = OPUS_ROOT / "packages" / "opus-8.1.0-lysenko-reference-book"
README_PATH = PACKAGE_ROOT / "README.md"
MANIFEST_PATH = PACKAGE_ROOT / "opus-package.json"
TOPICS_JSON = PACKAGE_ROOT / "resources" / "reference" / "index" / "reference_topics.json"
AUTHORING_JSON = PACKAGE_ROOT / "resources" / "reference" / "console" / "authoring_commands.json"
AUTHORING_MD = PACKAGE_ROOT / "resources" / "reference" / "console" / "authoring_commands.md"
AUTHORING_SCORE = PACKAGE_ROOT / "resources" / "reference" / "console" / "authoring_commands.score"
SKELETON_DIR = OPUS_ROOT / "sites" / "skeleton"

REQUIRED_COMMANDS = [
    "opus:create-site",
    "opus:validate-site",
    "opus:serve-site",
    "opus:list-routes",
    "opus:list-modules",
    "opus:create-module",
    "opus:create-page",
    "opus:create-rubric",
]


def fail(label: str, detail: str) -> int:
    print(f"{label}=FAIL: {detail}")
    return 1


def ok(label: str) -> None:
    print(f"{label}=OK")


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def load_json(path: Path) -> dict:
    return json.loads(read_text(path))


def main() -> int:
    print("P117SITE18_AUTHORING_COMMANDS_DOCBOOK_INTEGRATION_SMOKE_START")

    if not PACKAGE_ROOT.exists():
        return fail("CHECK_REFBOOK_PACKAGE_EXISTS", str(PACKAGE_ROOT))
    ok("CHECK_REFBOOK_PACKAGE_EXISTS")

    for path in [README_PATH, MANIFEST_PATH, TOPICS_JSON, AUTHORING_JSON, AUTHORING_MD, AUTHORING_SCORE]:
        if not path.exists():
            return fail("CHECK_REFBOOK_CONTENT_FILES", str(path))
    ok("CHECK_REFBOOK_CONTENT_FILES")

    manifest = load_json(MANIFEST_PATH)
    content = manifest.get("reference_book_content")
    if not isinstance(content, dict):
        return fail("CHECK_MANIFEST_REFERENCE_BOOK_CONTENT", "reference_book_content missing")
    if content.get("schema") != "OPUS_REFERENCE_BOOK_CONTENT_V1":
        return fail("CHECK_MANIFEST_REFERENCE_BOOK_CONTENT", "schema mismatch")
    ok("CHECK_MANIFEST_REFERENCE_BOOK_CONTENT")

    topics = load_json(TOPICS_JSON)
    topic_ids = [item.get("id") for item in topics.get("topics", [])]
    if "console.authoring_commands" not in topic_ids:
        return fail("CHECK_REFERENCE_TOPIC_INDEX", str(topic_ids))
    ok("CHECK_REFERENCE_TOPIC_INDEX")

    authoring = load_json(AUTHORING_JSON)
    command_names = [item.get("composer_script") for item in authoring.get("commands", [])]
    for command in REQUIRED_COMMANDS:
        if command not in command_names:
            return fail("CHECK_AUTHORING_COMMAND_MATRIX", command)
    ok("CHECK_AUTHORING_COMMAND_MATRIX")

    write_contract = authoring.get("write_contract", {})
    for key in ["requires_explicit_write_flag", "dry_run_without_write", "no_partial_write_on_error", "no_silent_overwrite", "no_fallback"]:
        if write_contract.get(key) is not True:
            return fail("CHECK_AUTHORING_WRITE_CONTRACT", key)
    ok("CHECK_AUTHORING_WRITE_CONTRACT")

    md = read_text(AUTHORING_MD)
    score = read_text(AUTHORING_SCORE)
    readme = read_text(README_PATH)
    for command in REQUIRED_COMMANDS:
        if command not in md or command not in score:
            return fail("CHECK_REFERENCE_TEXT_MENTIONS_COMMANDS", command)
    ok("CHECK_REFERENCE_TEXT_MENTIONS_COMMANDS")

    if "console.authoring_commands" not in readme:
        return fail("CHECK_README_LINKS_TOPIC", "console.authoring_commands")
    ok("CHECK_README_LINKS_TOPIC")

    for path in PACKAGE_ROOT.rglob("*"):
        rel = path.relative_to(PACKAGE_ROOT).as_posix().lower()
        if rel.startswith("framework/opus"):
            return fail("CHECK_NO_EMBEDDED_FRAMEWORK", str(path))
    ok("CHECK_NO_EMBEDDED_FRAMEWORK")

    for path in PACKAGE_ROOT.rglob("*"):
        if path.is_file() and path.suffix.lower() == ".twig":
            return fail("CHECK_NO_TWIG_IN_REFBOOK_CONTENT", str(path))
    ok("CHECK_NO_TWIG_IN_REFBOOK_CONTENT")

    if SKELETON_DIR.exists():
        return fail("CHECK_NO_GENERATED_SITE_LEFT", str(SKELETON_DIR))
    ok("CHECK_NO_GENERATED_SITE_LEFT")

    print("P117SITE18_AUTHORING_COMMANDS_DOCBOOK_INTEGRATION_SMOKE_OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
