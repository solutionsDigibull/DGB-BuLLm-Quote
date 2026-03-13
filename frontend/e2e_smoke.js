/**
 * BuLLMQuote UI Smoke Tests (T20–T22)
 * Paste into browser console at http://localhost:8000 after page loads.
 * Results logged to console and returned from the async IIFE.
 */
(async function bqSmokeTests() {
  const results = [];
  function log(tid, label, pass, data) {
    const sym = pass ? "✓" : "✗";
    results.push({ tid, label, pass, data });
    console.log(`${sym} ${tid} ${label}`, data);
  }

  // T20: Login modal element
  const diagEmail = document.getElementById("diag-email");
  log("T20", "Login modal element #diag-email present", !!diagEmail, !!diagEmail);

  // T21: Key workflow elements
  const elements = {
    "bq-proj-name":       document.getElementById("bq-proj-name"),
    "bq-quotewin-input":  document.getElementById("bq-quotewin-input"),
    "bq-cbom-total":      document.getElementById("bq-cbom-total"),
  };
  const allPresent = Object.values(elements).every(Boolean);
  log("T21", "Key workflow elements present", allPresent,
    Object.fromEntries(Object.entries(elements).map(([k, v]) => [k, !!v])));

  // T22: No uncaught JS errors (check window.__bqErrors if instrumented)
  const errors = window.__bqErrors || [];
  log("T22", "No uncaught JS errors", errors.length === 0,
    errors.length === 0 ? "0 errors" : errors);

  // Summary
  const passed = results.filter(r => r.pass).length;
  const total  = results.length;
  console.log(`\nUI Smoke: ${passed}/${total} passed`);
  results.forEach(r => {
    if (!r.pass) console.warn(`  ✗ ${r.tid} ${r.label}:`, r.data);
  });
  return results;
})();
