from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]

doc = ROOT / "DOC" / "architecture" / "P6E4_PAGE_CONTRACT_BASELINE.md"
required = [
    "Route -> Controller/Action -> FSM -> ACL -> ViewModel -> Layout",
    "No public page without a route.",
    "No public page without a controller/action.",
    "No public page without an FSM state or transition.",
    "No public page without an ACL policy, even when public.",
    "ViewModel prepares render-ready data.",
    "Layout renders representation only.",
    "OPUS core must not depend on optional packages.",
]

print("P6E4_PAGE_CONTRACT_BASELINE_AUDIT")
print("MODE=READ_ONLY")

failures = []

if not doc.exists():
    print("CHECK_PAGE_CONTRACT_DOC=FAIL missing")
    failures.append("CHECK_PAGE_CONTRACT_DOC")
else:
    print("CHECK_PAGE_CONTRACT_DOC=OK")
    text = doc.read_text(encoding="utf-8")
    for item in required:
        key = "CHECK_DOC_HAS_" + item.upper().replace(" ", "_").replace("/", "_").replace("-", "_").replace(">", "TO").replace(".", "").replace(",", "")
        if item in text:
            print(key + "=OK")
        else:
            print(key + "=FAIL " + item)
            failures.append(key)

for rel in [
    "Opus/Scaffold/SiteScaffoldPlan.php",
    "Opus/Scaffold/FullstackApplicationScaffoldPlan.php",
]:
    path = ROOT / rel
    if not path.exists():
        print("CHECK_REQUIRED_FILE=FAIL " + rel)
        failures.append("CHECK_REQUIRED_FILE_" + rel)
        continue

    text = path.read_text(encoding="utf-8").lower()
    if "module" in text:
        print("CHECK_NO_MODULE_IN_" + rel.replace("/", "_").replace(".", "_").upper() + "=FAIL")
        failures.append("CHECK_NO_MODULE_IN_" + rel)
    else:
        print("CHECK_NO_MODULE_IN_" + rel.replace("/", "_").replace(".", "_").upper() + "=OK")

if failures:
    print("DECISION=P6E4_PAGE_CONTRACT_BASELINE_BLOCKED")
    print("BLOCKING_CHECKS=" + ",".join(failures))
    sys.exit(1)

print("DECISION=P6E4_PAGE_CONTRACT_BASELINE_READY")
print("NEXT_SAFE_STEP=P6E4C_CREATE_PAGE_CONTRACT_IMPLEMENTATION")
print("P6E4_PAGE_CONTRACT_BASELINE_AUDIT_OK")