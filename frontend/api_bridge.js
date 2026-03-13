/**
 * BuLLMQuote API Bridge
 * Patches stub functions in the existing prototype with real FastAPI calls.
 * Injected at the end of index.html — no changes to the original 8k-line file needed.
 */
(function () {
  "use strict";

  const API_BASE = window.BULLMQUOTE_API_BASE || "/api";
  let _activeProjectId = null;
  let _token = localStorage.getItem("bq_token") || "";

  function _headers() {
    const h = { "Content-Type": "application/json" };
    if (_token) h.Authorization = "Bearer " + _token;
    return h;
  }

  async function _ensureAuth() {
    if (_token) return;
    try {
      await window.BQ_API.login("admin", "bullm@2025");
    } catch (_) {
      // silent — will fail on actual API call with proper error
    }
  }

  // ── Utility ───────────────────────────────────────────────────────────────
  function _extractErrorMsg(err, fallback) {
    var detail = err && err.detail;
    if (typeof detail === "string" && detail) return detail;
    if (Array.isArray(detail)) return detail.map(function(d) { return d.msg || JSON.stringify(d); }).join("; ");
    if (detail && typeof detail === "object") return JSON.stringify(detail);
    return fallback || "Unknown error";
  }

  async function _api(method, path, body) {
    await _ensureAuth();
    const opts = { method, headers: _headers() };
    if (body) opts.body = JSON.stringify(body);
    let res = await fetch(API_BASE + path, opts);
    // Auto-retry once on 401 (token may have expired)
    if (res.status === 401) {
      _token = "";
      localStorage.removeItem("bq_token");
      await _ensureAuth();
      opts.headers = _headers();
      res = await fetch(API_BASE + path, opts);
    }
    if (!res.ok) {
      const err = await res.json().catch(() => ({ detail: res.statusText || "Request failed" }));
      throw new Error(_extractErrorMsg(err, res.statusText || "Request failed"));
    }
    return res.json();
  }

  async function _upload(path, file) {
    await _ensureAuth();
    const form = new FormData();
    form.append("file", file);
    const authHeaders = _token ? { Authorization: "Bearer " + _token } : {};
    let res = await fetch(API_BASE + path, {
      method: "POST",
      headers: authHeaders,
      body: form,
    });
    // Auto-retry once on 401
    if (res.status === 401) {
      _token = "";
      localStorage.removeItem("bq_token");
      await _ensureAuth();
      res = await fetch(API_BASE + path, {
        method: "POST",
        headers: _token ? { Authorization: "Bearer " + _token } : {},
        body: form,
      });
    }
    if (!res.ok) {
      const err = await res.json().catch(() => ({ detail: res.statusText || "Upload failed" }));
      throw new Error(_extractErrorMsg(err, res.statusText || "Upload failed"));
    }
    return res.json();
  }

  function _toast(msg, color) {
    color = color || "#2ecc71";
    const t = document.createElement("div");
    t.style.cssText =
      "position:fixed;bottom:24px;right:24px;background:" + color +
      ";color:#fff;padding:12px 20px;border-radius:8px;font-size:12px;" +
      "font-weight:700;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);max-width:360px";
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  function _err(msg) { _toast("✕ " + msg, "#e74c3c"); }
  function _ok(msg)  { _toast("✓ " + msg, "#27ae60"); }

  // ── Auth ──────────────────────────────────────────────────────────────────
  window.BQ_API = {};

  window.BQ_API.login = async function (username, password) {
    const form = new FormData();
    form.append("username", username);
    form.append("password", password);
    const res = await fetch(API_BASE + "/auth/login", { method: "POST", body: form });
    if (!res.ok) throw new Error("Login failed");
    const data = await res.json();
    _token = data.access_token;
    localStorage.setItem("bq_token", _token);
    return data;
  };

  window.BQ_API.logout = function () {
    _token = "";
    localStorage.removeItem("bq_token");
    if (window.bqState) window.bqState.clearAll();
  };

  // ── LOGIN MODAL INTEGRATION ───────────────────────────────────────────────
  // Patch the existing bqDiagLogin to hit the real API
  const _origLogin = window.bqDiagLogin;
  window.bqDiagLogin = async function () {
    const userEl = document.getElementById("diag-email") ||
                   document.getElementById("bq-diag-user") ||
                   document.getElementById("bq-login-user");
    const passEl = document.getElementById("diag-pin") ||
                   document.getElementById("bq-diag-pass") ||
                   document.getElementById("bq-login-pass");
    if (!userEl || !passEl) { if (_origLogin) _origLogin(); return; }
    try {
      const data = await window.BQ_API.login(userEl.value, passEl.value);
      _ok("Logged in as " + userEl.value + " (" + data.role + ")");
      window.BQ_USER_ROLE = data.role;
      if (window.bqDiagClose) window.bqDiagClose();
    } catch (e) {
      _err("Login failed — check credentials");
    }
  };

  // ── PROJECT CREATE ────────────────────────────────────────────────────────
  const _origCreateProj = window.bqDoCreateProject;
  window.bqDoCreateProject = async function () {
    const nameEl  = document.getElementById("bq-proj-name");
    const descEl  = document.getElementById("bq-proj-desc");
    const custEl  = document.getElementById("bq-proj-customer") || { value: "" };
    if (!nameEl || !nameEl.value.trim()) {
      if (nameEl) { nameEl.style.borderColor = "#e74c3c"; nameEl.focus(); }
      return;
    }
    try {
      const result = await _api("POST", "/projects", {
        code:        nameEl.value.trim(),
        customer:    custEl.value.trim() || nameEl.value.trim(),
        description: descEl ? descEl.value.trim() : "",
      });
      _activeProjectId = result.id;
      window.BQ_ACTIVE_PROJECT_ID = result.id;
      window.BQ_ACTIVE_PROJECT = { id: result.id, name: result.code, desc: result.description || '', status: 'Active' };
      if (nameEl) nameEl.value = "";
      if (descEl) descEl.value = "";
      // Also run original UI update
      if (window.bqRenderProjectBar) window.bqRenderProjectBar();
      _ok("Project " + result.code + " created (ID: " + result.id + ")");
    } catch (e) {
      _err("Create project: " + e.message);
    }
  };

  // ── PROJECT CREATE (row-based S2 panel) ──────────────────────────────────
  // bqCreateRowProject is called by the S2 project panel's "Create" button.
  // The original only creates a local object with a string ID like "PRJ-001".
  // We override it to hit the API so we get a numeric DB id.
  const _origCreateRowProject = window.bqCreateRowProject;
  window.bqCreateRowProject = async function (rowId) {
    var nameEl = document.getElementById(rowId + '-name');
    var descEl = document.getElementById(rowId + '-desc');
    var pmEl   = document.getElementById(rowId + '-pm');
    var name = (nameEl || {}).value || '';
    var desc = (descEl || {}).value || '';
    var pm   = (pmEl   || {}).value || '';
    if (!name.trim()) {
      if (nameEl) { nameEl.style.borderColor = '#e74c3c'; nameEl.focus(); }
      return;
    }
    try {
      const result = await _api("POST", "/projects", {
        code:        name.trim(),
        customer:    name.trim(),
        description: desc.trim(),
      });
      // Create local project object with NUMERIC DB id
      var proj = {
        id: result.id, name: name.trim(), desc: desc.trim(), pm: pm,
        created: new Date().toISOString().split('T')[0],
        status: 'Active', files: 0
      };
      window.BQ_PROJECTS = window.BQ_PROJECTS || [];
      window.BQ_PROJECTS.unshift(proj);
      window.BQ_ACTIVE_PROJECT = proj;
      window.BQ_ACTIVE_PROJECT_ID = result.id;
      _activeProjectId = result.id;
      // Clear inputs and refresh UI
      if (nameEl) nameEl.value = '';
      if (descEl) descEl.value = '';
      if (pmEl)   pmEl.value = '';
      var panel = document.getElementById('bq-s2-project-panel');
      if (panel && window.bqBuildProjectPanel) panel.innerHTML = bqBuildProjectPanel();
      if (window.bqRenderProjectBar) bqRenderProjectBar();
      _ok("Project " + result.code + " created (ID: " + result.id + ")");
    } catch (e) {
      _err("Create project: " + (e && e.message ? e.message : String(e)));
    }
  };

  // Also patch bqSelectProject / bqOnProjectSelect to store numeric ID
  const _origSelectProject = window.bqSelectProject;
  window.bqSelectProject = function (proj) {
    if (proj && typeof proj.id === 'number') {
      _activeProjectId = proj.id;
      window.BQ_ACTIVE_PROJECT_ID = proj.id;
      if(window.bqState){window.bqState.clearNav();window.bqState.saveProject(proj.id);}
    }
    if (_origSelectProject) _origSelectProject(proj);
  };

  const _origOnProjectSelect = window.bqOnProjectSelect;
  window.bqOnProjectSelect = function (proj) {
    if (proj && typeof proj.id === 'number') {
      _activeProjectId = proj.id;
      window.BQ_ACTIVE_PROJECT_ID = proj.id;
      if(window.bqState){window.bqState.clearNav();window.bqState.saveProject(proj.id);}
    }
    if (_origOnProjectSelect) _origOnProjectSelect(proj);
  };

  // ── BOM SAVE & SYNC ───────────────────────────────────────────────────────
  window.bqSaveSyncBOM = async function () {
    const rawPid = window.BQ_ACTIVE_PROJECT_ID || _activeProjectId || (window.BQ_ACTIVE_PROJECT && window.BQ_ACTIVE_PROJECT.id);
    const pid = Number(rawPid);
    if (!pid || !Number.isInteger(pid)) { _err("No project selected — create or select a project first"); return; }
    // Get the uploaded file from the input or from BQ state
    const fileInput = document.querySelector('input[type="file"][accept*="xlsx"]') ||
                      document.querySelector('input[type="file"]');
    const file = (fileInput && fileInput.files[0]) ||
                 (window.BQ && window.BQ._bomFileRaw);
    if (!file) { _err("No BOM file found — re-upload the SCRUB BOM"); return; }
    try {
      _toast("⬆ Uploading BOM to project " + pid + "…", "#2980b9");
      const result = await _upload("/bom/" + pid + "/upload", file);
      window.BQ = window.BQ || {};
      window.BQ.bomSynced     = true;
      window.BQ.apiProjectId  = pid;
      window.BQ.apiBomLines   = result.total;
      const ok = document.getElementById("bq-dc-save-ok");
      if (ok) { ok.style.display = "inline"; setTimeout(() => ok.style.display = "none", 2500); }
      if (window.bqRenderProjectBar) window.bqRenderProjectBar();
      _ok("BOM synced — " + result.total + " lines across " + result.assemblies.length + " assemblies");
    } catch (e) {
      _err("BOM upload failed: " + (e && e.message ? e.message : String(e)));
    }
  };

  // ── BOM VERIFY TEMPLATE ───────────────────────────────────────────────────
  window.bqVerifyBOMTemplate = async function(file) {
    await _ensureAuth();
    function _mkFd() { const fd = new FormData(); fd.append("file", file); return fd; }
    let resp = await fetch(API_BASE + "/bom/verify-template", {
      method: "POST",
      headers: _token ? { Authorization: "Bearer " + _token } : {},
      body: _mkFd(),
    });
    // Auto-retry once on 401
    if (resp.status === 401) {
      _token = "";
      localStorage.removeItem("bq_token");
      await _ensureAuth();
      resp = await fetch(API_BASE + "/bom/verify-template", {
        method: "POST",
        headers: _token ? { Authorization: "Bearer " + _token } : {},
        body: _mkFd(),
      });
    }
    // 422 means validation issues — parse the body rather than throwing
    if (!resp.ok && resp.status !== 422) throw new Error("HTTP " + resp.status);
    return await resp.json();
  };

  // ── QW FILE UPLOAD HOOK ───────────────────────────────────────────────────
  // Called by S3 "Upload QW File" button
  window.BQ_API.uploadQW = async function (file, projectId) {
    const pid = projectId || window.BQ_ACTIVE_PROJECT_ID || _activeProjectId;
    if (!pid) throw new Error("No active project");
    _toast("⬆ Uploading QW file…", "#2980b9");
    const result = await _upload("/qw/" + pid + "/upload", file);
    window.BQ = window.BQ || {};
    window.BQ.qwResolved    = result.resolved;
    window.BQ.qwSynced      = true;
    window.BQ.qwVolQtys     = {
      proto: result.vol1_qty,
      vl1:   result.vol2_qty,
      vl2:   result.vol3_qty,
    };
    _ok("QW file uploaded — " + result.cpn_count + " CPNs resolved");
    return result;
  };

  // ── POLYDYNE / QUOTEWIN UPLOAD INTEGRATION ────────────────────────────────
  const _origHandlePolyDyne = window.bqHandlePolyDyne;
  window.bqHandlePolyDyne = async function(files) {
    // 1. Run original (updates upload-zone UI with filename)
    if (_origHandlePolyDyne) _origHandlePolyDyne(files);
    if (!files || !files.length) return;
    const file = files[0];

    // 2. Upload to API
    let result;
    try {
      result = await window.BQ_API.uploadQW(file);
    } catch (e) {
      _err("QW upload failed: " + (e && e.message ? e.message : String(e)));
      return;
    }

    // 3. Convert resolved → priceTrackData format
    const resolved = result.resolved || {};
    const priceTrackData = {};
    for (const [cpn, entry] of Object.entries(resolved)) {
      const v = entry.v1 || {};
      priceTrackData[cpn] = {
        supp:      v.supp      || '',
        lt:        v.lt        || null,
        ncnr:      v.ncnr      || '',
        curr:      v.currency  || 'USD',
        mfg:       '',
        mpn:       v.mpn       || cpn,
        moq:       v.moq       || null,
        unitPrice: v.cost      || 0,
      };
    }

    // 4. Store in BQ state
    window.BQ = window.BQ || {};
    window.BQ.priceTrackData = priceTrackData;
    window.BQ.quoteWinFile = {
      filename: file.name,
      parts:    result.cpn_count || Object.keys(priceTrackData).length,
      project:  (window.BQ_ACTIVE_PROJECT && window.BQ_ACTIVE_PROJECT.name) || '',
      currency: 'USD',
    };
    window.BQ_QW_LOADED = true;

    // 5. Re-render current screen so preview table shows live data
    if (window.render) window.render();
  };

  // Hook into any QW file input on the page
  document.addEventListener("change", function (e) {
    const el = e.target;
    if (el.type !== "file") return;
    if (el.id === 'bq-quotewin-input') return;  // handled by bqHandlePolyDyne
    const fname = (el.files[0] || {}).name || "";
    if (fname.toLowerCase().includes("qw") || fname.toLowerCase().includes("quotewin") ||
        fname.toLowerCase().includes("rev-float") || fname.toLowerCase().includes("award")) {
      const file = el.files[0];
      window.BQ = window.BQ || {};
      window.BQ._qwFileRaw = file;
      window.BQ_API.uploadQW(file).catch(err => _err("QW upload: " + err.message));
    } else if (fname.toLowerCase().includes("scrub") || fname.toLowerCase().includes("bom")) {
      window.BQ = window.BQ || {};
      window.BQ._bomFileRaw = el.files[0];
    }
  });

  // ── NRE ITEM DEFINITIONS ──────────────────────────────────────────────────
  var NRE_ITEMS = [
    { cat: 'Tooling',     label: 'SMT Stencil - Top Side',              unit: 320  },
    { cat: 'Tooling',     label: 'SMT Stencil - Bottom Side',           unit: 280  },
    { cat: 'Tooling',     label: 'Reflow Profile Development',          unit: 450  },
    { cat: 'Setup',       label: 'SMT Line Changeover',                 unit: 180  },
    { cat: 'Setup',       label: 'AOI Programming',                     unit: 220  },
    { cat: 'Setup',       label: 'ICT Fixture Design',                  unit: 1800 },
    { cat: 'Engineering', label: 'DFM Review',                          unit: 290  },
    { cat: 'Engineering', label: 'Programme Management',                unit: 580  },
  ];
  var FAI_ITEMS = [
    { cat: 'Inspection',    label: 'Full Dimensional Inspection (IPC-A-610)',  unit: 180 },
    { cat: 'Inspection',    label: 'Electrical Functional Test - FAI Board',   unit: 220 },
    { cat: 'Documentation', label: 'FAI Report Preparation (AS9102 format)',   unit: 190 },
    { cat: 'Documentation', label: 'Traceability Pack & Serialisation',        unit: 85  },
    { cat: 'Test',          label: 'Solderability Inspection (IPC-J-STD-001)', unit: 60  },
    { cat: 'Test',          label: 'X-Ray Inspection (BGA / QFN joints)',      unit: 110 },
  ];

  // ── NRE SAVE COLLECTOR ────────────────────────────────────────────────────
  window.bqSaveNre = async function () {
    const pid = window.BQ_ACTIVE_PROJECT_ID || _activeProjectId || (window.BQ_ACTIVE_PROJECT && window.BQ_ACTIVE_PROJECT.id);
    if (!pid) return;
    var lines = [];
    NRE_ITEMS.forEach(function (item, i) {
      var el = document.getElementById('nre-row-' + i);
      var val = el ? parseFloat(el.value) || 0
                   : (window.BQ && window.BQ._nre && window.BQ._nre[i] != null ? parseFloat(window.BQ._nre[i]) || 0 : item.unit);
      lines.push({
        nre_type: item.cat,
        cpn: '',
        description: item.label,
        commodity: '',
        manufacturer: '',
        mpn: '',
        nre_charge_conv: val,
      });
    });
    FAI_ITEMS.forEach(function (item, i) {
      var el = document.getElementById('fai-row-' + i);
      var val = el ? parseFloat(el.value) || 0
                   : (window.BQ && window.BQ._faiCost && window.BQ._faiCost[i] != null ? parseFloat(window.BQ._faiCost[i]) || 0 : item.unit);
      lines.push({
        nre_type: item.cat,
        cpn: '',
        description: item.label,
        commodity: '',
        manufacturer: '',
        mpn: '',
        nre_charge_conv: val,
      });
    });
    try {
      await window.BQ_API.uploadNre(pid, lines);
      _ok("NRE lines saved (" + lines.length + " items)");
    } catch (e) {
      _err("NRE save failed: " + (e && e.message ? e.message : String(e)));
    }
  };

  // ── CBOM GENERATE (replaces seed-data logic) ──────────────────────────────
  const _origGenerateCBOM = window.bqGenerateCBOM;
  window.bqGenerateCBOM = async function () {
    const pid = window.BQ_ACTIVE_PROJECT_ID || _activeProjectId || (window.BQ_ACTIVE_PROJECT && window.BQ_ACTIVE_PROJECT.id);
    if (!pid) {
      // Fall back to original seed-based generation
      if (_origGenerateCBOM) _origGenerateCBOM();
      return;
    }
    // Sync NRE data to DB before computing CBOM
    await window.bqSaveNre().catch(function (e) {
      _err("NRE save failed before CBOM: " + (e && e.message ? e.message : String(e)));
    });
    try {
      _toast("⚙ Computing CBOM…", "#8e44ad");
      const result = await _api("POST", "/cbom/" + pid + "/compute");
      window.BQ = window.BQ || {};
      window.BQ.cbomGenerated  = true;
      window.BQ.cbomApiResult  = result;
      window.BQ.cbomGenerated  = true;
      // Update UI elements that the original function updates
      const cbomTotalEl = document.getElementById("bq-cbom-total");
      if (cbomTotalEl && result.assembly_summary) {
        const proto = result.assembly_summary.PROTO || {};
        const totals = Object.values(proto).reduce(
          (acc, v) => acc + (v.ext_price_sum || 0), 0
        );
        cbomTotalEl.textContent = "$" + totals.toFixed(4);
      }
      if (window.bqRenderProjectBar) window.bqRenderProjectBar();
      _ok("CBOM computed — " + result.proto_lines + " Proto lines · " +
          result.vl1_lines + " VL1 · " + result.vl2_lines + " VL2");
      return result;
    } catch (e) {
      _err("CBOM compute failed: " + (e && e.message ? e.message : String(e)));
      // Fall back to seed-based for demo
      if (_origGenerateCBOM) _origGenerateCBOM();
    }
  };

  // ── EXCEL WORKBOOK DOWNLOAD ───────────────────────────────────────────────
  window.BQ_API.downloadWorkbook = async function (projectId) {
    const pid = projectId || window.BQ_ACTIVE_PROJECT_ID || _activeProjectId;
    if (!pid) { _err("No active project — cannot export"); return; }
    try {
      _toast("⬇ Generating Excel workbook…", "#1a6a4a");
      await _ensureAuth();
      let res = await fetch(API_BASE + "/export/" + pid + "/cbom-xlsx", {
        headers: _token ? { Authorization: "Bearer " + _token } : {},
      });
      // Auto-retry once on 401
      if (res.status === 401) {
        _token = "";
        localStorage.removeItem("bq_token");
        await _ensureAuth();
        res = await fetch(API_BASE + "/export/" + pid + "/cbom-xlsx", {
          headers: _token ? { Authorization: "Bearer " + _token } : {},
        });
      }
      if (!res.ok) {
        const err = await res.json().catch(() => ({ detail: res.statusText || "Export failed" }));
        throw new Error(_extractErrorMsg(err, res.statusText || "Export failed"));
      }
      const blob  = await res.blob();
      const url   = URL.createObjectURL(blob);
      const a     = document.createElement("a");
      const cd    = res.headers.get("Content-Disposition") || "";
      const match = cd.match(/filename="([^"]+)"/);
      a.href = url;
      a.download = match ? match[1] : "CBOM_Export.xlsx";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      _ok("Workbook downloaded — " + a.download);
    } catch (e) {
      _err("Export failed: " + (e && e.message ? e.message : String(e)));
    }
  };


  // ── PROJECTS LIST ─────────────────────────────────────────────────────────
  window.BQ_API.listProjects = async function () {
    return _api("GET", "/projects");
  };

  window.BQ_API.selectProject = async function (id) {
    _activeProjectId = id;
    window.BQ_ACTIVE_PROJECT_ID = id;
    if(window.bqState)window.bqState.saveProject(id);
    const proj = await _api("GET", "/projects/" + id);
    window.BQ = window.BQ || {};
    window.BQ.apiProject = proj;
    return proj;
  };

  // ── CBOM ROWS LOADER (for S7 review table) ────────────────────────────────
  window.BQ_API.getCbomRows = async function (volume) {
    const pid = window.BQ_ACTIVE_PROJECT_ID || _activeProjectId || (window.BQ_ACTIVE_PROJECT && window.BQ_ACTIVE_PROJECT.id);
    if (!pid) return [];
    return _api("GET", "/cbom/" + pid + "/rows?volume=" + (volume || "PROTO"));
  };

  // ── AUTH — extended ───────────────────────────────────────────────────────
  window.BQ_API.getMe = async function () {
    return _api("GET", "/auth/me");
  };

  window.BQ_API.registerUser = async function (data) {
    return _api("POST", "/auth/register", data);
  };

  window.BQ_API.listUsers = async function () {
    return _api("GET", "/auth/users");
  };

  window.BQ_API.updateUser = async function (id, data) {
    return _api("PATCH", "/auth/users/" + id, data);
  };

  window.BQ_API.deleteUser = async function (id) {
    return _api("DELETE", "/auth/users/" + id);
  };

  // ── PROJECT MANAGEMENT — extended ─────────────────────────────────────────
  window.BQ_API.updateProject = async function (id, data) {
    return _api("PATCH", "/projects/" + id, data);
  };

  window.BQ_API.deleteProject = async function (id) {
    return _api("DELETE", "/projects/" + id);
  };

  window.BQ_API.getProjectSummary = async function (id) {
    return _api("GET", "/projects/" + id + "/summary");
  };

  // Patch bqRemoveProjectRow to also delete from DB
  const _origRemoveProjectRow = window.bqRemoveProjectRow;
  window.bqRemoveProjectRow = async function (rowId) {
    // Try to find the numeric project id from the row element or BQ_PROJECTS
    var pid = null;
    var rowEl = document.getElementById(rowId) || document.querySelector('[data-row-id="' + rowId + '"]');
    if (rowEl) {
      pid = rowEl.getAttribute('data-project-id');
      if (pid) pid = parseInt(pid, 10) || null;
    }
    if (!pid && window.BQ_PROJECTS) {
      var match = window.BQ_PROJECTS.find(function (p) {
        return p.rowId === rowId || p.id === rowId || String(p.id) === String(rowId);
      });
      if (match) pid = match.id;
    }
    if (pid && Number.isInteger(pid)) {
      try {
        await window.BQ_API.deleteProject(pid);
        if (_activeProjectId === pid) {
          _activeProjectId = null;
          window.BQ_ACTIVE_PROJECT_ID = null;
        }
      } catch (e) {
        _err("Delete project failed: " + (e && e.message ? e.message : String(e)));
        return; // don't remove DOM row on failure
      }
    }
    if (_origRemoveProjectRow) _origRemoveProjectRow(rowId);
  };

  // ── BOM MANAGEMENT ────────────────────────────────────────────────────────
  window.BQ_API.getBomLines = async function (id) {
    return _api("GET", "/bom/" + id + "/lines");
  };

  window.BQ_API.clearBomLines = async function (id) {
    return _api("DELETE", "/bom/" + id + "/lines");
  };

  // ── QW MANAGEMENT ─────────────────────────────────────────────────────────
  window.BQ_API.getQwResolved = async function (id) {
    return _api("GET", "/qw/" + id + "/resolved");
  };

  window.BQ_API.getQwPrices = async function (id) {
    return _api("GET", "/qw/" + id + "/prices");
  };

  window.BQ_API.clearQwPrices = async function (id) {
    return _api("DELETE", "/qw/" + id + "/prices");
  };

  // ── CBOM MANAGEMENT — extended ────────────────────────────────────────────
  window.BQ_API.getExcessInventory = async function (id, vol) {
    return _api("GET", "/cbom/" + id + "/excess-inventory?volume=" + (vol || "PROTO"));
  };

  window.BQ_API.clearCbomRows = async function (id) {
    return _api("DELETE", "/cbom/" + id + "/rows");
  };

  // ── NRE MANAGEMENT ────────────────────────────────────────────────────────
  window.BQ_API.uploadNre = async function (id, lines) {
    return _api("POST", "/nre/" + id + "/upload", lines);
  };

  window.BQ_API.getNreLines = async function (id) {
    return _api("GET", "/nre/" + id + "/lines");
  };

  window.BQ_API.clearNreLines = async function (id) {
    return _api("DELETE", "/nre/" + id + "/lines");
  };

  // ── EXPORT SUMMARY ────────────────────────────────────────────────────────
  window.BQ_API.getExportSummary = async function (id) {
    return _api("GET", "/export/" + id + "/summary-json");
  };

  // ── HEALTH CHECK ──────────────────────────────────────────────────────────
  window.BQ_API.healthCheck = async function () {
    const res = await fetch("/health");
    if (!res.ok) throw new Error("Health check failed");
    return res.json();
  };

  // ── STARTUP ───────────────────────────────────────────────────────────────
  (async function init() {
    // Health check
    try {
      const health = await window.BQ_API.healthCheck();
      console.log("[BQ Bridge] Health:", health);
    } catch (_) {
      console.warn("[BQ Bridge] Backend health check failed — server may be offline.");
    }
    // Ensure default admin user exists
    try { await fetch(API_BASE + "/auth/init-admin", { method: "POST" }); } catch(_) {}
    // Auto-login if no token
    await _ensureAuth();
    if (!_token) return;
    // Populate window.BQ_USER with logged-in user profile
    try {
      const me = await window.BQ_API.getMe();
      window.BQ_USER = me;
      window.BQ_USER_ROLE = me.role;
      console.log("[BQ Bridge] Logged in as:", me.username, "(" + me.role + ")");
    } catch (_) {}
    try {
      // Verify token is still valid by loading project list
      const projs = await window.BQ_API.listProjects();
      if (projs.length) {
        console.log("[BQ Bridge] Authenticated. " + projs.length + " project(s) available.");
        // Restore saved project or auto-select most recent
        const savedPid = window.bqState ? window.bqState.getSavedProject() : null;
        const match = savedPid && projs.find(p => p.id === savedPid);
        if (savedPid && !match) {
          console.warn("[BQ Bridge] Saved project " + savedPid + " no longer exists.");
          if (window.bqState) { window.bqState.clearNav(); window.bqState.saveProject(null); }
          _toast("Previous project was removed — switched to " + projs[0].code, "#f0a500");
        }
        const pick = match || projs[0];
        _activeProjectId = pick.id;
        window.BQ_ACTIVE_PROJECT_ID = pick.id;
        if (window.bqState) window.bqState.saveProject(pick.id);
      }
    } catch (_) {
      // Token expired or invalid — clear it
      _token = "";
      localStorage.removeItem("bq_token");
      console.warn("[BQ Bridge] Auth token expired — re-login required.");
    }
  })();

  console.log("[BQ Bridge] API bridge loaded. Base:", API_BASE);
})();
