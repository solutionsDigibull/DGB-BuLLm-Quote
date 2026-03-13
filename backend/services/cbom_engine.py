"""
CBOM Computation Engine
-----------------------
Joins normalized BOM lines against resolved QW prices.
For each volume break:
  - ext_vol_qty   = part_qty * board_qty
  - scrap_class   = A (>1.25) / B (0.25–1.25) / C (<0.25) based on unit_price
  - scrap_qty     = ext_vol_qty * scrap_factor
  - buy_qty       = ceil_to_moq(ext_vol_qty + scrap_qty)
  - ext_price     = unit_price * part_qty          (per board)
  - ext_vol_price = unit_price * buy_qty
"""
import math
from typing import List, Dict, Any, Optional

SCRAP_TABLE = [
    # (min_excl, max_incl, factor)
    (1.25,  float("inf"), 0.01),   # Class A
    (0.25,  1.25,         0.02),   # Class B
    (0.0,   0.25,         0.05),   # Class C
]

def _scrap_factor(unit_price: Optional[float]) -> float:
    if not unit_price or unit_price <= 0:
        return 0.05  # default Class C
    for lo, hi, factor in SCRAP_TABLE:
        if unit_price > lo:
            return factor
    return 0.05

def _ceil_moq(qty: float, moq: int) -> float:
    if not moq or moq <= 0:
        return qty
    return math.ceil(qty / moq) * moq

def _compute_vol(
    bom_line: Dict,
    price_info: Optional[Dict],
    board_qty: int,
    nre_lookup: Dict[str, float],
    inv_stock: float,
) -> Dict:
    """Compute one volume break for one BOM line."""
    part_qty    = bom_line.get("qty", 0) or 0
    ext_vol_qty = part_qty * board_qty

    if price_info and price_info.get("cost"):
        unit_price  = price_info["cost"]
        moq         = price_info.get("moq") or 0
        lt          = price_info.get("lt") or 0
        supp        = price_info.get("supp", "")
        mpn_qw      = price_info.get("mpn", "")
        currency    = price_info.get("currency", "USD")
        ncnr        = price_info.get("ncnr", "")
        status      = price_info.get("status", "")
        note        = price_info.get("note", "")
        pay_term    = price_info.get("payment_term", "")
        long_cmt    = price_info.get("long_comment", "")
    else:
        unit_price  = 0.0
        moq         = 0
        lt          = 0
        supp        = ""
        mpn_qw      = bom_line.get("mpn", "")
        currency    = "USD"
        ncnr        = ""
        status      = "Not Quoted" if not price_info else price_info.get("status", "Not Quoted")
        note        = "" if not price_info else price_info.get("note", "")
        pay_term    = ""
        long_cmt    = ""

    scrap_f     = _scrap_factor(unit_price)
    scrap_qty   = ext_vol_qty * scrap_f
    net_demand  = max(0, ext_vol_qty - inv_stock)
    buy_qty     = _ceil_moq(net_demand + scrap_qty, moq) if moq > 0 else (net_demand + scrap_qty)
    excess_stk  = max(0, inv_stock - ext_vol_qty)
    shortage    = max(0, ext_vol_qty - inv_stock)

    ext_price_conv     = unit_price * part_qty
    ext_vol_price_conv = unit_price * buy_qty

    cpn = bom_line["cpn"]
    nre = nre_lookup.get(cpn, 0.0)

    return {
        "fg_part":         bom_line.get("fg_part", ""),
        "assembly":        bom_line.get("assembly", ""),
        "cpn":             cpn,
        "description":     bom_line.get("description", ""),
        "commodity":       bom_line.get("commodity", ""),
        "manufacturer":    bom_line.get("manufacturer", ""),
        "mpn":             mpn_qw or bom_line.get("mpn", ""),
        "uom":             bom_line.get("uom", "NUM"),
        "part_qty":        part_qty,
        "ext_vol_qty":     ext_vol_qty,
        "unit_price_conv": unit_price,
        "price_orig":      unit_price,   # same for USD base; expand for FX later
        "currency_orig":   currency,
        "ext_price_conv":  ext_price_conv,
        "ext_vol_price":   ext_vol_price_conv,
        "supp_name":       supp,
        "pkg_qty":         price_info.get("moq", 0) if price_info else 0,
        "moq":             moq,
        "lead_time":       lt,
        "stock":           inv_stock,
        "nre_charge":      nre,
        "nre_charge_conv": nre,
        "ncnr":            ncnr,
        "price_control":   price_info.get("price_control", "centum") if price_info else "centum",
        "scrap_factor":    scrap_f,
        "scrap_qty":       scrap_qty,
        "payment_term":    pay_term,
        "price_status":    status,
        "price_note":      note,
        "long_comment":    long_cmt,
        # Excess inventory
        "excess_in_stk":   excess_stk,
        "shortage_qty":    shortage,
        "buy_qty":         buy_qty,
    }

def compute_cbom(
    bom_lines:   List[Dict],
    qw_resolved: Dict[str, Dict],  # cpn → {v1, v2, v3}
    nre_lookup:  Dict[str, float], # cpn → nre_charge_conv
    proto_qty:   int,
    vl1_qty:     int,
    vl2_qty:     int,
) -> Dict[str, List[Dict]]:
    """
    Returns {PROTO: [...], VL1: [...], VL2: [...]}
    Each list is one row per BOM line.
    """
    result = {"PROTO": [], "VL1": [], "VL2": []}

    for line in bom_lines:
        cpn       = line["cpn"]
        inv_stock = line.get("inventory_stock", 0) or 0
        prices    = qw_resolved.get(cpn, {})

        for vol_key, board_qty, price_key in [
            ("PROTO", proto_qty, "v1"),
            ("VL1",   vl1_qty,  "v2"),
            ("VL2",   vl2_qty,  "v3"),
        ]:
            p_info = prices.get(price_key) if prices else None
            row = _compute_vol(line, p_info, board_qty, nre_lookup, inv_stock)
            result[vol_key].append(row)

    return result

def assembly_summary(cbom_vol: List[Dict]) -> Dict[str, Dict]:
    """
    Returns per-assembly totals:
      ext_price_sum, line_count, unique_cpns, shared_cpns
    """
    from collections import defaultdict
    asm_data: Dict[str, Dict] = defaultdict(lambda: {
        "ext_price_sum": 0.0,
        "cpns": [],
    })
    for row in cbom_vol:
        asm = row["assembly"]
        asm_data[asm]["ext_price_sum"] += row.get("ext_price_conv", 0) or 0
        asm_data[asm]["cpns"].append(row["cpn"])

    summary = {}
    all_cpns_flat = [c for d in asm_data.values() for c in d["cpns"]]
    cpn_counts = {}
    for c in all_cpns_flat:
        cpn_counts[c] = cpn_counts.get(c, 0) + 1

    for asm, d in asm_data.items():
        cpn_list = d["cpns"]
        unique  = len(set(cpn_list))
        shared  = sum(1 for c in set(cpn_list) if cpn_counts[c] > 1)
        summary[asm] = {
            "ext_price_sum": round(d["ext_price_sum"], 6),
            "line_count":    len(cpn_list),
            "unique_cpns":   unique,
            "shared_cpns":   shared,
        }
    return summary
