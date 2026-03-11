"""
Parses QuoteWin Award export Excel file.
- Metadata header: rows 1-16 (skip)
- Data header: row 17
- Data rows: row 18 onwards

Column layout (0-indexed after reading row 17):
  0=Project, 1=Part Number, 2=Part Description, 3=Commodity,
  4=Mfg Name, 5=Mfg Part Number,
  6=Cost#1(Conv.), 7=Price(Original)#1,
  8=Cost#2(Conv.), 9=Price(Original)#2,
  10=Cost#3(Conv.), 11=Price(Original)#3,
  12=Currency(Original), 13=Supp Name, 14=Pkg Qty, 15=MOQ,
  16=Lead Time,
  17=Awarded Volume#1, 18=Award#1,
  19=Awarded Volume#2, 20=Award#2,
  21=Awarded Volume#3, 22=Award#3,
  23=Part Qty, 24=Corrected MPN, 25=Long Comment,
  32=NCNR, 37=Part Status
"""
import openpyxl
from collections import defaultdict
from typing import Dict, Any, List
import io

DATA_START_ROW = 18

def _safe_float(v):
    if v is None: return None
    try:    return float(v)
    except: return None

def _safe_int(v):
    if v is None: return None
    try:    return int(float(v))
    except: return None

def _resolve_vol(rows: List[Dict], cost_key: str, award_key: str) -> Dict:
    """
    Per-volume-break price selection:
    1. Award = 100 AND cost > 0  → Awarded
    2. Award = 100 but cost null/0 → fallback to lowest non-zero
    3. No Award = 100 row        → lowest non-zero
    4. All zero/null             → Not Quoted
    """
    awarded_row = next((r for r in rows if r.get(award_key) == 100), None)

    if awarded_row and awarded_row.get(cost_key) and awarded_row[cost_key] > 0:
        return {
            "cost": awarded_row[cost_key],
            "supp": awarded_row["supp"],
            "mpn":  awarded_row["mpn"],
            "moq":  awarded_row["moq"],
            "lt":   awarded_row["lt"],
            "ncnr": awarded_row["ncnr"],
            "currency": awarded_row["currency"],
            "payment_term": awarded_row.get("payment_term", ""),
            "long_comment": awarded_row.get("long_comment", ""),
            "status": "Awarded",
            "note": "",
        }

    quoted = [(r[cost_key], r) for r in rows
              if r.get(cost_key) and r[cost_key] > 0]
    if quoted:
        best_cost, best_row = min(quoted, key=lambda x: x[0])
        if awarded_row:
            note   = f'Award=100 ({awarded_row["supp"]}) had no price — lowest selected'
            status = "Lowest (Award had no price)"
        else:
            note   = "Award=100 not marked for this CPN — lowest price selected"
            status = "Lowest (100 not marked)"
        return {
            "cost": best_cost,
            "supp": best_row["supp"],
            "mpn":  best_row["mpn"],
            "moq":  best_row["moq"],
            "lt":   best_row["lt"],
            "ncnr": best_row["ncnr"],
            "currency": best_row["currency"],
            "payment_term": best_row.get("payment_term", ""),
            "long_comment": best_row.get("long_comment", ""),
            "status": status,
            "note": note,
        }

    return {
        "cost": None, "supp": "", "mpn": "", "moq": None,
        "lt": None, "ncnr": "", "currency": "", "payment_term": "",
        "long_comment": "", "status": "Not Quoted", "note": "",
    }

def parse_qw_file(file_bytes: bytes) -> Dict[str, Any]:
    wb = openpyxl.load_workbook(io.BytesIO(file_bytes), data_only=True)
    ws = wb.active

    # Read volume break board-level quantities from summary rows
    vol1_qty = _safe_int(ws.cell(row=13, column=2).value)
    vol2_qty = _safe_int(ws.cell(row=13, column=6).value)
    vol3_qty = _safe_int(ws.cell(row=13, column=10).value)

    # Group rows by CPN
    parts: Dict[str, List[Dict]] = defaultdict(list)
    for row in ws.iter_rows(min_row=DATA_START_ROW, values_only=True):
        cpn = row[1]
        if not cpn:
            continue
        cpn = str(cpn).strip()
        parts[cpn].append({
            "mpn":          str(row[5] or "").strip(),
            "cost1":        _safe_float(row[6]),
            "cost2":        _safe_float(row[8]),
            "cost3":        _safe_float(row[10]),
            "price1_orig":  _safe_float(row[7]),
            "price2_orig":  _safe_float(row[9]),
            "price3_orig":  _safe_float(row[11]),
            "currency":     str(row[12] or "USD").strip(),
            "supp":         str(row[13] or "").strip(),
            "pkg_qty":      _safe_int(row[14]),
            "moq":          _safe_int(row[15]),
            "lt":           _safe_int(row[16]),
            "av1":          _safe_int(row[17]),
            "award1":       _safe_int(row[18]),
            "av2":          _safe_int(row[19]),
            "award2":       _safe_int(row[20]),
            "av3":          _safe_int(row[21]),
            "award3":       _safe_int(row[22]),
            "ncnr":         str(row[32] or "").strip(),
            "part_status":  str(row[37] or "").strip(),
            "payment_term": str(row[30] or "").strip() if len(row) > 30 else "",
            "long_comment": str(row[25] or "").strip() if len(row) > 25 else "",
        })

    # Resolve each CPN per volume break
    resolved: Dict[str, Dict] = {}
    for cpn, rows in parts.items():
        awarded_supp = next(
            (r["supp"] for r in rows if r["award1"] == 100), ""
        )
        v1 = _resolve_vol(rows, "cost1", "award1")
        v2 = _resolve_vol(rows, "cost2", "award2")
        v3 = _resolve_vol(rows, "cost3", "award3")
        resolved[cpn] = {
            "cpn":          cpn,
            "awarded_supp": awarded_supp,
            "v1": v1, "v2": v2, "v3": v3,
        }

    # Build flat list for DB insert
    db_rows: List[Dict] = []
    for cpn, rows in parts.items():
        for r in rows:
            db_rows.append({
                "cpn":         cpn,
                "mpn":         r["mpn"],
                "supp_name":   r["supp"],
                "currency":    r["currency"],
                "cost1_conv":  r["cost1"],
                "cost2_conv":  r["cost2"],
                "cost3_conv":  r["cost3"],
                "price1_orig": r["price1_orig"],
                "price2_orig": r["price2_orig"],
                "price3_orig": r["price3_orig"],
                "moq":         r["moq"],
                "pkg_qty":     r["pkg_qty"],
                "lead_time":   r["lt"],
                "award1":      r["award1"],
                "award2":      r["award2"],
                "award3":      r["award3"],
                "awarded_vol1": r["av1"],
                "awarded_vol2": r["av2"],
                "awarded_vol3": r["av3"],
                "ncnr":        r["ncnr"],
                "part_status": r["part_status"],
                "payment_term":r["payment_term"],
                "long_comment":r["long_comment"],
            })

    return {
        "vol1_qty":  vol1_qty,
        "vol2_qty":  vol2_qty,
        "vol3_qty":  vol3_qty,
        "resolved":  resolved,
        "db_rows":   db_rows,
        "cpn_count": len(resolved),
    }
