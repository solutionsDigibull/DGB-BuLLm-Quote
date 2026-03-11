from fastapi import APIRouter, Depends, UploadFile, File, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete
from database import get_db
from models.models import Project, QwPrice
from services.qw_parser import parse_qw_file

router = APIRouter()

@router.post("/{project_id}/upload")
async def upload_qw(project_id: int, file: UploadFile = File(...),
                    db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    proj = result.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, detail="Project not found")

    content = await file.read()
    parsed  = parse_qw_file(content)

    # Update project volume quantities from QW file
    if parsed["vol1_qty"]:
        proj.proto_qty = parsed["vol1_qty"]
    if parsed["vol2_qty"]:
        proj.vl1_qty   = parsed["vol2_qty"]
    if parsed["vol3_qty"]:
        proj.vl2_qty   = parsed["vol3_qty"]

    # Delete existing QW prices
    await db.execute(delete(QwPrice).where(QwPrice.project_id == project_id))

    rows = []
    for row in parsed["db_rows"]:
        rows.append(QwPrice(project_id=project_id, **row))
    db.add_all(rows)
    await db.commit()

    return {
        "msg":         "QW file uploaded",
        "cpn_count":   parsed["cpn_count"],
        "vol1_qty":    parsed["vol1_qty"],
        "vol2_qty":    parsed["vol2_qty"],
        "vol3_qty":    parsed["vol3_qty"],
        "resolved":    parsed["resolved"],  # full price selection result
    }

@router.get("/{project_id}/resolved")
async def get_resolved_prices(project_id: int, db: AsyncSession = Depends(get_db)):
    """Return the resolved price selection per CPN for frontend display."""
    result = await db.execute(
        select(QwPrice).where(QwPrice.project_id == project_id)
    )
    rows = result.scalars().all()
    if not rows:
        raise HTTPException(404, detail="No QW prices found for this project")

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
