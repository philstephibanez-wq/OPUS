from pathlib import Path
import re
import sys

ROOT = Path.cwd()
SCAN_EXTENSIONS = {".php", ".md", ".json", ".yml", ".yaml", ".score", ".txt"}
EXCLUDED_PARTS = {".git", "vendor", "node_modules", ".idea", ".vscode", "sites"}
TERMS = [
    "module",
    "modules",
    "Module",
    "Modules",
    "MODULE",
    "ModuleScaffoldPlan",
    "module_scaffold",
    "MOD_",
]

def rel(path):
    return str(path.relative_to(ROOT)).replace("\\", "/")

def excluded(path):
    return any(part in EXCLUDED_PARTS for part in path.parts)

def scope(path):
    r = rel(path)
    if r.startswith("tools/archive/"):
        return "ARCHIVE"
    if r.startswith("DOC/") or r.startswith("CONTEXT/") or r.endswith(".md"):
        return "DOC"
    if r.startswith("tools/"):
        return "TOOL"
    if r.startswith("application/") or r.startswith("sites/"):
        return "APPLICATION"
    if r.startswith("Opus/Scaffold/"):
        return "SCAFFOLD"
    if r.startswith("Opus/"):
        return "RUNTIME_OR_FRAMEWORK"
    return "OTHER"

def severity(path, line):
    s = scope(path)
    text = line.strip()
    if s == "ARCHIVE":
        return "KEEP_ARCHIVE_REFERENCE"
    if s == "DOC":
        return "DOC_REWRITE_OR_HISTORY"
    if s == "TOOL":
        return "TOOL_UPDATE"
    if s == "SCAFFOLD":
        return "REMOVE_OR_REPLACE_SCAFFOLD"
    if s == "RUNTIME_OR_FRAMEWORK":
        return "REMOVE_FROM_FRAMEWORK"
    if s == "APPLICATION":
        return "APPLICATION_REWRITE"
    return "REVIEW"

pattern = re.compile(r"\b(module|modules|Module|Modules|MODULE|ModuleScaffoldPlan|module_scaffold|MOD_)\b")

matches = []
for path in sorted(ROOT.rglob("*")):
    if not path.is_file():
        continue
    if excluded(path):
        continue
    if path.suffix not in SCAN_EXTENSIONS:
        continue
    try:
        lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
    except Exception as exc:
        print("READ_ERROR=" + rel(path) + " ERROR=" + str(exc))
        continue
    for number, line in enumerate(lines, start=1):
        if pattern.search(line):
            matches.append((path, number, scope(path), severity(path, line), " ".join(line.strip().split())))

print("P6E_MODULE_CONCEPT_REMOVAL_AUDIT")
print("MODE=READ_ONLY")
print("TARGET=REMOVE_MODULE_CONCEPT_COMPLETELY")
print("MATCH_TOTAL=" + str(len(matches)))

by_scope = {}
by_severity = {}
for path, number, sc, sev, text in matches:
    by_scope[sc] = by_scope.get(sc, 0) + 1
    by_severity[sev] = by_severity.get(sev, 0) + 1

for key in sorted(by_scope):
    print("SCOPE_" + key + "=" + str(by_scope[key]))

for key in sorted(by_severity):
    print("SEVERITY_" + key + "=" + str(by_severity[key]))

for path, number, sc, sev, text in matches:
    print("MATCH=" + rel(path) + ":" + str(number) + " SCOPE=" + sc + " ACTION=" + sev + " TEXT=" + text)

blocking = [m for m in matches if m[3] in {"REMOVE_FROM_FRAMEWORK", "REMOVE_OR_REPLACE_SCAFFOLD", "APPLICATION_REWRITE", "TOOL_UPDATE"}]

print("BLOCKING_ACTIVE_MATCHES=" + str(len(blocking)))

if blocking:
    print("DECISION=P6E_MODULE_REMOVAL_PLAN_REQUIRED")
    print("NEXT_SAFE_STEP=P6E1_REMOVE_MODULE_CONCEPT_FROM_ACTIVE_CODE")
else:
    print("DECISION=P6E_MODULE_CONCEPT_ALREADY_ABSENT_FROM_ACTIVE_CODE")
    print("NEXT_SAFE_STEP=P6E_DOC_CLEANUP_MODULE_REFERENCES")

print("P6E_MODULE_CONCEPT_REMOVAL_AUDIT_OK")
