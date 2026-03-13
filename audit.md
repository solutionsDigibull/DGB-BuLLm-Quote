# BuLLMQuote UI Audit — Findings & Applied Fixes

**Audit date:** 2026-03-13
**Files audited:** `frontend/index.html` (8,267 lines), `frontend/api_bridge.js` (704 lines)

---

## Critical Broken Flows (Phase 1)

### Fix 1 — NRE/FAI inputs never saved to DB ✅
- **Finding:** `bqNreRowEdit` and `bqNreTotalEdit` (index.html ~6503-6525) only persisted to in-memory `window.BQ` state. Values were lost on page reload.
- **Fix:** Added debounced (800 ms) `window.bqSaveNre()` call inside both functions so every edit triggers a DB write.
- **Verification:** Edit NRE value → wait 800ms → `GET /nre/{pid}/lines` returns updated value.

### Fix 2 — Workbook button used `alert()` stub ✅
- **Finding:** index.html ~7820: `onclick="alert('Opening Excel Workbook export...')"` — relied on the fragile alert-interception shim in api_bridge.
- **Fix:** Changed to `onclick="window.BQ_API.downloadWorkbook()"` — direct API call.

### Fix 3a — `serverFallback` fetch had no auth header ✅
- **Finding:** index.html ~1507: `fetch('/api/bom/parse-preview', { method:'POST', body:fd })` — no `Authorization` header. Would silently 401 once auth is required.
- **Fix:** Reads `bq_token` from localStorage and adds `Authorization: Bearer …` header.

### Fix 3b — `verify-template` fetch duplicated api_bridge logic ✅
- **Finding:** index.html ~2348-2358: inline `fetch('/api/bom/verify-template', …)` duplicated `window.bqVerifyBOMTemplate()`. No 401 auto-retry.
- **Fix:** Replaced with `const result = await window.bqVerifyBOMTemplate(file)` — routes through api_bridge with retry.

### Fix 4 — Silent NRE error before CBOM compute ✅
- **Finding:** api_bridge.js ~437: `await window.bqSaveNre().catch(function () {})` — NRE upload failures were invisible; CBOM was computed with stale/missing NRE data.
- **Fix:** Catch handler now calls `_err(...)` to show a toast warning.

### Fix 5 — `bqVerifyBOMTemplate` and `downloadWorkbook` lacked 401 retry ✅
- **Finding:** Both functions used `fetch()` directly without the auto-retry-on-401 pattern used by `_upload()`.
- **Fix:** Added the same `if (res.status === 401) { clear token → re-auth → retry }` pattern to both functions.

---

## Orphaned / Non-Functional Actions (Phase 2)

### Fix 6 — "Price Lookup" button had fake animation ✅
- **Finding:** index.html ~4867: button swapped its own text to `…` then back to `↺ Price Lookup` after 1.2s — implied a real API call without making one.
- **Fix:** Replaced with `disabled` button styled as inactive, with `title="Live price lookup — coming soon"`.

### Fix 7 — `lcConfigure` fired an alert ✅
- **Finding:** index.html 6034 + 6075: `window.lcConfigure = function() { alert('Configure lifecycle thresholds...'); }` and the Configure button called it.
- **Fix:** No-op stub for the function; Configure button is now `disabled` with a tooltip.

### Fix 8 — `bqBulkDownload` only alerted ✅
- **Finding:** index.html ~7784: `alert('Downloading …')` stub — no real download happened.
- **Fix:** When CBOM/workbook report is in selection (or nothing selected), calls `window.BQ_API.downloadWorkbook()`. Non-workbook selections log to console until per-report export is built.

---

## API Bridge Hardening (Phase 4)

### Fix 11 — Alert-interception shim removed ✅
- **Finding:** api_bridge.js ~503-510: `window.alert` was overridden to intercept `"excel workbook"` strings and route to `downloadWorkbook()`. Fragile; could intercept unrelated alerts.
- **Fix:** Deleted the shim. After Fix 2 the workbook button calls `downloadWorkbook()` directly.

---

## Skipped / Deferred

| Fix | Reason |
|-----|--------|
| Fix 9 — Delete duplicate Approve CBOM button (line 5503) | The two buttons are in distinct UI panels (CG-4 summary vs. Sourcing Manager sign-off). Both call the same `bqApproveCBOM()` — removing either would break one of the two views. |
| Fix 10 — Standardise Proceed button styles | Line 1765 is in `renderParseResults()` which has no `s` (step) parameter. The green `#2ecc71` is intentional for the upload step. All other Proceed buttons already use `s.color`. |

---

## Files Modified
| File | Fixes Applied |
|------|--------------|
| `frontend/api_bridge.js` | Fix 4, Fix 5 (verify + workbook), Fix 11 |
| `frontend/index.html` | Fix 1, Fix 2, Fix 3a, Fix 3b, Fix 6, Fix 7, Fix 8 |
| `audit.md` | Created (this file) |
