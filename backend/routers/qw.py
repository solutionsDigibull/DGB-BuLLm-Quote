from fastapi import APIRouter, Depends, UploadFile, File, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete, func
from typing import Dict
from database import get_db
from models.models import Project, QwPrice, BomLine, BomDetail
from services.qw_parser import parse_qw_file

router = APIRouter()

@router.post("/{project_id}/upload")
async def upload_qw(project_id: int, file: UploadFile = File(...),
                    db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    proj = result.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, detail="Project not found")

    try:
        # BOM validation gate (non-blocking warning)
        bom_check = await db.execute(
            select(func.count(BomLine.id)).where(BomLine.project_id == project_id)
        )
        bom_line_count = bom_check.scalar() or 0
        bom_warning = None if bom_line_count > 0 else "No BOM lines found for this project — upload a BOM first for accurate total_part_qty computation"

        # Load BomDetail to determine has_proto and volume_count
        bd_result = await db.execute(
            select(BomDetail).where(BomDetail.project_id == project_id)
        )
        bom_detail = bd_result.scalar_one_or_none()
        has_proto = (bom_detail is not None and bom_detail.proto > 0)
        volume_count = bom_detail.volume_count if bom_detail else 2

        content = await file.read()
        parsed  = parse_qw_file(content, has_proto=has_proto)

        # Compute total_part_qty per CPN from BOM
        bom_qty_result = await db.execute(
            select(BomLine.cpn, func.sum(BomLine.qty).label("total_qty"))
            .where(BomLine.project_id == project_id)
            .group_by(BomLine.cpn)
        )
        cpn_total_qty: Dict[str, float] = {row.cpn: row.total_qty for row in bom_qty_result}

        # Update project volume quantities from QW file
        # When has_proto: proto_qty was already set correctly by BOM upload (from Settings sheet).
        # QW vol1_qty (row 13 col 2) maps to production VL1 in the proto case.
        if has_proto:
            # proto_qty already correct from BOM; map QW vols to production volumes
            if parsed["vol1_qty"]:
                proj.vl1_qty = parsed["vol1_qty"]
            if parsed["vol2_qty"]:
                proj.vl2_qty = parsed["vol2_qty"]
        else:
            if parsed["vol1_qty"]:
                proj.proto_qty = parsed["vol1_qty"]
            if parsed["vol2_qty"]:
                proj.vl1_qty   = parsed["vol2_qty"]
            if parsed["vol3_qty"]:
                proj.vl2_qty   = parsed["vol3_qty"]

        # Capture max revision_no before deleting
        rev_result = await db.execute(
            select(func.max(QwPrice.revision_no))
            .where(QwPrice.project_id == project_id)
        )
        prev_max_rev = rev_result.scalar() or -1
        next_rev = prev_max_rev + 1

        # Delete existing QW prices
        await db.execute(delete(QwPrice).where(QwPrice.project_id == project_id))

        rows = []
        for row in parsed["db_rows"]:
            # is_l1: awarded at vol1 (first production volume after proto shift)
            is_l1 = (row.get("award1") == 100)
            rows.append(QwPrice(
                project_id=project_id,
                revision_no=next_rev,
                is_l1=is_l1,
                total_part_qty=cpn_total_qty.get(row["cpn"]),
                uploaded_through="Direct",
                **row,
            ))
        db.add_all(rows)
        await db.commit()
    except Exception as exc:
        try:
            await db.rollback()
        except Exception:
            pass
        raise HTTPException(status_code=422, detail=str(exc))

    # Volume count validation
    # expected = production volumes + proto flag
    expected_vol_count = volume_count + (1 if has_proto else 0)
    found_vol_count = parsed.get("found_vol_count", 0)
    volume_validation = {
        "expected": expected_vol_count,
        "found":    found_vol_count,
        "ok":       expected_vol_count == found_vol_count,
    }

    response = {
        "msg":               "QW file uploaded",
        "cpn_count":         parsed["cpn_count"],
        "vol1_qty":          parsed["vol1_qty"],
        "vol2_qty":          parsed["vol2_qty"],
        "vol3_qty":          parsed["vol3_qty"],
        "revision_no":       next_rev,
        "volume_validation": volume_validation,
        "resolved":          parsed["resolved"],
    }
    if bom_warning:
        response["bom_warning"] = bom_warning
    return response

@router.get("/{project_id}/resolved")
async def get_resolved_prices(project_id: int, db: AsyncSession = Depends(get_db)):
    """Return the resolved price selection per CPN for frontend display."""
    result = await db.execute(
        select(QwPrice).where(QwPrice.project_id == project_id)
    )
    rows = result.scalars().all()
    if not rows:
        return {}

    from collections import defaultdict
    from services.qw_parser import _resolve_vol

    parts = defaultdict(list)
    for r in rows:
        parts[r.cpn].append({
            "mpn": r.mpn, "supp": r.supp_name, "currency": r.currency,
            "cost1": r.cost1_conv, "cost2": r.cost2_conv, "cost3": r.cost3_conv,
            "moq": r.moq, "lt": r.lead_time,
            "award1": r.award1, "award2": r.award2, "award3": r.award3,
            "ncnr": r.ncnr, "part_status": r.part_status,
            "payment_term": r.payment_term, "long_comment": r.long_comment,
            "price_control": r.price_control or "centum",
        })

    resolved = {}
    for cpn, cpn_rows in parts.items():
        resolved[cpn] = {
            "cpn": cpn,
            "awarded_supp": next((r["supp"] for r in cpn_rows if r["award1"]==100),""),
            "v1": _resolve_vol(cpn_rows,"cost1","award1"),
            "v2": _resolve_vol(cpn_rows,"cost2","award2"),
            "v3": _resolve_vol(cpn_rows,"cost3","award3"),
        }
    return resolved


@router.get("/{project_id}/prices")
async def get_qw_prices(project_id: int, db: AsyncSession = Depends(get_db)):
    result = await db.execute(
        select(QwPrice).where(QwPrice.project_id == project_id)
                       .order_by(QwPrice.cpn, QwPrice.supp_name)
    )
    rows = result.scalars().all()
    return [{c.key: getattr(r, c.key) for c in r.__table__.columns} for r in rows]


@router.delete("/{project_id}/prices")
async def delete_qw_prices(project_id: int, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    if not result.scalar_one_or_none():
        raise HTTPException(404, detail="Project not found")
    await db.execute(delete(QwPrice).where(QwPrice.project_id == project_id))
    await db.commit()
    return {"msg": "QW prices cleared"}
