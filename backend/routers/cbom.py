from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete
from collections import defaultdict
from database import get_db
from models.models import Project, BomLine, QwPrice, CbomRow, NreLine, ExInvRow
from services.cbom_engine import compute_cbom
from services.qw_parser import _resolve_vol

router = APIRouter()

@router.post("/{project_id}/compute")
async def compute(project_id: int, db: AsyncSession = Depends(get_db)):
    # Load project
    res = await db.execute(select(Project).where(Project.id == project_id))
    proj = res.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, "Project not found")

    # Load BOM lines
    res = await db.execute(
        select(BomLine).where(BomLine.project_id == project_id)
    )
    bom_lines = [
        {c.key: getattr(r, c.key) for c in r.__table__.columns}
        for r in res.scalars().all()
    ]
    if not bom_lines:
        raise HTTPException(400, "No BOM lines found — upload SCRUB BOM first")

    # Load QW prices and rebuild resolved dict
    res = await db.execute(
        select(QwPrice).where(QwPrice.project_id == project_id)
    )
    qw_raw = res.scalars().all()
    if not qw_raw:
        raise HTTPException(400, "No QW prices found — upload QW file first")

    parts: dict = defaultdict(list)
    for r in qw_raw:
        parts[r.cpn].append({
            "mpn": r.mpn, "supp": r.supp_name, "currency": r.currency,
            "cost1": r.cost1_conv, "cost2": r.cost2_conv, "cost3": r.cost3_conv,
            "moq": r.moq, "lt": r.lead_time,
            "award1": r.award1, "award2": r.award2, "award3": r.award3,
            "ncnr": r.ncnr, "part_status": r.part_status,
            "payment_term": r.payment_term, "long_comment": r.long_comment,
        })

    qw_resolved = {}
    for cpn, rows in parts.items():
        qw_resolved[cpn] = {
            "v1": _resolve_vol(rows, "cost1", "award1"),
            "v2": _resolve_vol(rows, "cost2", "award2"),
            "v3": _resolve_vol(rows, "cost3", "award3"),
        }

    # Load NRE lines for lookup
    res = await db.execute(
        select(NreLine).where(NreLine.project_id == project_id)
    )
    nre_lookup = {r.cpn: r.nre_charge_conv for r in res.scalars().all()}

    # Run computation
    cbom = compute_cbom(
        bom_lines   = bom_lines,
        qw_resolved = qw_resolved,
        nre_lookup  = nre_lookup,
        proto_qty   = proj.proto_qty or 10,
        vl1_qty     = proj.vl1_qty   or 300,
        vl2_qty     = proj.vl2_qty   or 1000,
    )

    # Persist CBOM rows
    await db.execute(delete(CbomRow).where(CbomRow.project_id == project_id))
    await db.execute(delete(ExInvRow).where(ExInvRow.project_id == project_id))

    cbom_objs = []
    ex_inv_objs = []
    vol_map = {"PROTO": "PROTO", "VL1": "VL1", "VL2": "VL2"}

    for vol_key, rows in cbom.items():
        for r in rows:
            cbom_objs.append(CbomRow(
                project_id=project_id, volume=vol_map[vol_key],
                fg_part=r["fg_part"], assembly=r["assembly"],
                cpn=r["cpn"], description=r["description"],
                commodity=r["commodity"], manufacturer=r["manufacturer"],
                mpn=r["mpn"], uom=r["uom"],
                part_qty=r["part_qty"], ext_vol_qty=r["ext_vol_qty"],
                unit_price_conv=r["unit_price_conv"],
                price_orig=r["price_orig"], currency_orig=r["currency_orig"],
                ext_price_conv=r["ext_price_conv"],
                ext_vol_price=r["ext_vol_price"],
                supp_name=r["supp_name"], pkg_qty=r.get("pkg_qty"),
                moq=r.get("moq"), lead_time=r.get("lead_time"),
                stock=r.get("stock",0), nre_charge=r.get("nre_charge",0),
                nre_charge_conv=r.get("nre_charge_conv",0),
                ncnr=r.get("ncnr",""), price_control=r.get("price_control","centum"),
                scrap_factor=r.get("scrap_factor",0),
                scrap_qty=r.get("scrap_qty",0),
                payment_term=r.get("payment_term",""),
                price_status=r.get("price_status",""),
                price_note=r.get("price_note",""),
                long_comment=r.get("long_comment",""),
            ))
            ex_inv_objs.append(ExInvRow(
                project_id=project_id, volume=vol_map[vol_key],
                cpn=r["cpn"], description=r["description"],
                commodity=r["commodity"], manufacturer=r["manufacturer"],
                mpn=r["mpn"], part_qty=r["part_qty"],
                ext_vol=r["ext_vol_qty"],
                excess_in_stk=r.get("excess_in_stk",0),
                excess_after_dem=max(0,(r.get("excess_in_stk",0)-r.get("shortage_qty",0))),
                shortage_qty=r.get("shortage_qty",0),
                scrap=r.get("scrap_qty",0),
                scrap_factor=r.get("scrap_factor",0),
                cost_conv=r.get("unit_price_conv",0),
                ext_vol_cost=r.get("ext_vol_price",0),
                supp_name=r.get("supp_name",""),
                moq=r.get("moq",0),
            ))

    db.add_all(cbom_objs)
    db.add_all(ex_inv_objs)
    await db.commit()

    # Summary stats
    from services.cbom_engine import assembly_summary
    return {
        "msg": "CBOM computed",
        "proto_lines": len(cbom["PROTO"]),
        "vl1_lines":   len(cbom["VL1"]),
        "vl2_lines":   len(cbom["VL2"]),
        "assembly_summary": {
            "PROTO": assembly_summary(cbom["PROTO"]),
            "VL1":   assembly_summary(cbom["VL1"]),
            "VL2":   assembly_summary(cbom["VL2"]),
        }
    }

@router.get("/{project_id}/rows")
async def get_cbom_rows(project_id: int, volume: str = "PROTO",
                        db: AsyncSession = Depends(get_db)):
    res = await db.execute(
        select(CbomRow)
        .where(CbomRow.project_id == project_id, CbomRow.volume == volume)
        .order_by(CbomRow.assembly, CbomRow.cpn)
    )
    rows = res.scalars().all()
    return [
        {c.key: getattr(r, c.key) for c in r.__table__.columns}
        for r in rows
    ]
