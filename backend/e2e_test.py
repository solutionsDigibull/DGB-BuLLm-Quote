"""
BuLLMQuote E2E API Test Suite
Run: python backend/e2e_test.py
"""
import requests, json

BASE = "http://localhost:8000"
API  = BASE + "/api"
results = []
pid = None

def log(tid, label, passed, data=""):
    sym = "✓" if passed else "✗"
    results.append({"id": tid, "label": label, "pass": passed, "data": str(data)[:200]})
    print(f"  {sym} {tid:<5} {label:<48} {str(data)[:70]}")

def get_token(user="admin", pwd="bullm@2025"):
    r = requests.post(f"{API}/auth/login", data={"username": user, "password": pwd})
    return r.json().get("access_token") if r.ok else None

TOKEN = get_token()
H = {"Authorization": f"Bearer {TOKEN}"} if TOKEN else {}

print("\n" + "═"*70)
print("  BuLLMQuote E2E API Test Suite")
print("═"*70 + "\n")

# ── Phase 0: Pre-flight ────────────────────────────────────────────────────────
r = requests.get(f"{BASE}/health")
log("T00", "Backend /health alive", r.ok and r.json().get("status") == "ok", r.json())

# ── Phase 1: Auth ──────────────────────────────────────────────────────────────
# T01: health check via /health (fixed bridge path — was /api/health, now /health)
r_h = requests.get(f"{BASE}/health")
log("T01", "BQ_API.healthCheck (/health path — fixed from /api/health)", r_h.ok,
    f"status={r_h.status_code}")

log("T02", "Token from login (non-empty)", bool(TOKEN), "non-empty" if TOKEN else "FAILED")

if TOKEN:
    r = requests.get(f"{API}/auth/me", headers=H)
    me = r.json() if r.ok else {}
    log("T03", "getMe() has id, username, role",
        r.ok and all(k in me for k in ("id", "username", "role")), me)
else:
    log("T03", "getMe()", False, "no token — skipped")

# ── Phase 2: Project CRUD ──────────────────────────────────────────────────────
r = requests.get(f"{API}/projects", headers=H)
log("T05", "listProjects → array", r.ok and isinstance(r.json(), list),
    f"count={len(r.json()) if r.ok else 'ERR'}")

r = requests.post(f"{API}/projects",
                  headers={**H, "Content-Type": "application/json"},
                  json={"code": "E2E-TEST", "customer": "Test", "description": "e2e run"})
proj = r.json() if r.ok else {}
pid = proj.get("id")
log("T06", "createProject → numeric id", r.ok and isinstance(pid, int), f"id={pid}")

if pid:
    r = requests.get(f"{API}/projects/{pid}/summary", headers=H)
    s = r.json() if r.ok else {}
    log("T07", "getProjectSummary has bom_line_count/qw_price_count/cbom_row_count",
        r.ok and "bom_line_count" in s and "qw_price_count" in s and "cbom_row_count" in s, s)

    r = requests.patch(f"{API}/projects/{pid}",
                       headers={**H, "Content-Type": "application/json"},
                       json={"customer": "Updated"})
    u = r.json() if r.ok else {}
    # Server returns full object after patch (includes customer) or {"msg":"updated"}
    log("T08", "updateProject → 200 OK",
        r.ok, f"status={r.status_code} body={str(u)[:60]}")

# ── Phase 3: BOM & QW (read-only) ──────────────────────────────────────────────
if pid:
    r = requests.get(f"{API}/bom/{pid}/lines", headers=H)
    log("T10", "getBomLines (empty ok) → array",
        r.ok and isinstance(r.json(), list),
        f"count={len(r.json()) if r.ok else r.status_code}")

    r = requests.get(f"{API}/qw/{pid}/prices", headers=H)
    log("T11", "getQwPrices (empty ok) → array",
        r.ok and isinstance(r.json(), list),
        f"count={len(r.json()) if r.ok else r.status_code}")

    r = requests.get(f"{API}/qw/{pid}/resolved", headers=H)
    log("T12", "getQwResolved (empty ok) → dict/array",
        r.ok and isinstance(r.json(), (dict, list)),
        f"type={type(r.json()).__name__} len={len(r.json()) if r.ok else r.status_code}")

# ── Phase 4: CBOM & NRE ────────────────────────────────────────────────────────
if pid:
    r = requests.get(f"{API}/cbom/{pid}/rows?volume=PROTO", headers=H)
    log("T13", "getCbomRows (empty ok) → array",
        r.ok and isinstance(r.json(), list),
        f"count={len(r.json()) if r.ok else r.status_code}")

    r = requests.get(f"{API}/cbom/{pid}/excess-inventory?volume=PROTO", headers=H)
    log("T14", "getExcessInventory (empty ok) → array",
        r.ok and isinstance(r.json(), list),
        f"count={len(r.json()) if r.ok else r.status_code}")

    NRE_ITEMS = [
        {"cat": "Tooling",     "label": "SMT Stencil - Top Side",               "unit": 320},
        {"cat": "Tooling",     "label": "SMT Stencil - Bottom Side",             "unit": 280},
        {"cat": "Tooling",     "label": "Reflow Profile Development",            "unit": 450},
        {"cat": "Setup",       "label": "SMT Line Changeover",                   "unit": 180},
        {"cat": "Setup",       "label": "AOI Programming",                       "unit": 220},
        {"cat": "Setup",       "label": "ICT Fixture Design",                    "unit": 1800},
        {"cat": "Engineering", "label": "DFM Review",                            "unit": 290},
        {"cat": "Engineering", "label": "Programme Management",                  "unit": 580},
    ]
    FAI_ITEMS = [
        {"cat": "Inspection",    "label": "Full Dimensional Inspection (IPC-A-610)",  "unit": 180},
        {"cat": "Inspection",    "label": "Electrical Functional Test - FAI Board",   "unit": 220},
        {"cat": "Documentation", "label": "FAI Report Preparation (AS9102 format)",   "unit": 190},
        {"cat": "Documentation", "label": "Traceability Pack & Serialisation",        "unit": 85},
        {"cat": "Test",          "label": "Solderability Inspection (IPC-J-STD-001)", "unit": 60},
        {"cat": "Test",          "label": "X-Ray Inspection (BGA / QFN joints)",      "unit": 110},
    ]
    lines = [
        {"nre_type": i["cat"], "cpn": "", "description": i["label"],
         "commodity": "", "manufacturer": "", "mpn": "",
         "nre_charge_conv": i["unit"]}
        for i in (NRE_ITEMS + FAI_ITEMS)
    ]
    r = requests.post(f"{API}/nre/{pid}/upload",
                      headers={**H, "Content-Type": "application/json"},
                      json=lines)
    log("T15", "bqSaveNre → POST /nre/{id}/upload 14 lines",
        r.ok, f"status={r.status_code} {str(r.json())[:50] if r.ok else r.text[:50]}")

    r = requests.get(f"{API}/nre/{pid}/lines", headers=H)
    nre_data = r.json() if r.ok else []
    log("T16", "getNreLines → 14 items",
        r.ok and len(nre_data) == 14,
        f"count={len(nre_data) if r.ok else r.status_code}")

# ── Phase 5: Auth Management ───────────────────────────────────────────────────
r = requests.get(f"{API}/auth/users", headers=H)
users = r.json() if r.ok else []
has_admin = any(u.get("username") == "admin" for u in users) if isinstance(users, list) else False
log("T17", "listUsers → contains admin",
    r.ok and has_admin, f"count={len(users) if r.ok else r.status_code}")

r = requests.post(f"{API}/auth/register",
                  headers={**H, "Content-Type": "application/json"},
                  json={"username": "e2e_testuser", "email": "e2e@test.com",
                        "password": "Test@1234", "role": "viewer"})
new_user = r.json() if r.ok else {}
new_uid = new_user.get("id")
log("T18a", "registerUser e2e_testuser", r.ok and bool(new_uid), f"id={new_uid}")

if new_uid:
    r2 = requests.delete(f"{API}/auth/users/{new_uid}", headers=H)
    log("T18b", "deleteUser e2e_testuser", r2.ok, f"status={r2.status_code}")

# ── Phase 6: Export ────────────────────────────────────────────────────────────
if pid:
    r = requests.get(f"{API}/export/{pid}/summary-json", headers=H)
    log("T19", "getExportSummary → dict (empty ok)",
        r.ok and isinstance(r.json(), dict),
        f"keys={list(r.json().keys())[:6] if r.ok and isinstance(r.json(), dict) else r.status_code}")

# ── Phase 2 cleanup: deleteProject ────────────────────────────────────────────
if pid:
    r = requests.delete(f"{API}/projects/{pid}", headers=H)
    log("T09", "deleteProject → no error", r.ok, f"status={r.status_code}")
    if r.ok:
        r2 = requests.get(f"{API}/projects", headers=H)
        pids = [p["id"] for p in r2.json()] if r2.ok else []
        log("T09b", "listProjects no longer contains deleted pid",
            pid not in pids, f"pid={pid} present={pid in pids}")

# ── Summary ────────────────────────────────────────────────────────────────────
passed = sum(1 for x in results if x["pass"])
total  = len(results)
failed = [x for x in results if not x["pass"]]

print(f"\n{'═'*70}")
print(f"  RESULT: {passed}/{total} passed")
print(f"{'═'*70}")
if failed:
    print("\nFailed tests:")
    for f in failed:
        print(f"  ✗ {f['id']:<6} {f['label']}: {f['data'][:80]}")
print()
