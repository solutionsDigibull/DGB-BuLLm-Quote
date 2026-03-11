from fastapi import APIRouter, Depends, HTTPException
from fastapi.responses import StreamingResponse
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from collections import defaultdict
import io

from database import get_db
from models.models import Project, BomLine, QwPrice, CbomRow, NreLine, ExInvRow
from services.cbom_engine import assembly_summary
from services.qw_parser import _resolve_vol
from services.export_engine import build_workbook

router = APIRouter()

def _rows_to_dicts(rows):
    return [{c.key: getattr(r, c.key) for c in r.__table__.columns} for r in rows]

@router.get("/{project_id}/cbom-xlsx")
async def export_cbom_xlsx(project_id: int,
                           db: AsyncSession = Depends(get_db)):
    # Project
    res = await db.execute(select(Project).where(Project.id == project_id))
    proj = res.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, "Project not found")

    project_dict = {c.key: getattr(proj, c.key) for c in proj.__table__.columns}

    # BOM lines
    res = await db.execute(
        select(BomLine).where(BomLine.project_id == project_id)
    )
    bom_lines = _rows_to_dicts(res.scalars().all())

    # QW resolved
    res = await db.execute(
        select(QwPrice).where(QwPrice.project_id == project_id)
    )
    qw_raw = res.scalars().all()
    parts: dict = defaultdict(list)
    for r in qw_raw:
        parts[r.cpn].append({
            "mpn": r.mpn, "supp": r.supp_name, "currency": r.currency,
            "cost1": r.cost1_conv, "cost2": r.cost2_conv, "cost3": r.cost3_conv,
            "moq": r.moq, "lt": r.lead_time,
            "award1": r.award1, "award2": r.award2, "award3": r.award3,
            "ncnr": r.ncnr, "payment_term": r.payment_term,
            "long_comment": r.long_comment,
        })
    qw_resolved = {}
    for cpn, rows in parts.items():
        qw_resolved[cpn] = {
            "v1": _resolve_vol(rows, "cost1", "award1"),
            "v2": _resolve_vol(rows, "cost2", "award2"),
            "v3": _resolve_vol(rows, "cost3", "award3"),
        }

    # CBOM rows (already computed)
    cbom: dict = {"PROTO": [], "VL1": [], "VL2": []}
    for vol in ["PROTO", "VL1", "VL2"]:
        res = await db.execute(
            select(CbomRow).where(
                CbomRow.project_id == project_id,
                CbomRow.volume == vol,
            ).order_by(CbomRow.assembly, CbomRow.cpn)
        )
        cbom[vol] = _rows_to_dicts(res.scalars().all())

    if not cbom["PROTO"]:
        raise HTTPException(400, "CBOM not yet computed — run /cbom/{id}/compute first")

    # NRE lines
    res = await db.execute(
        select(NreLine).where(NreLine.project_id == project_id)
    )
    nre_lines = _rows_to_dicts(res.scalars().all())

    # Build workbook
    xlsx_bytes = build_workbook(
        project     = project_dict,
        bom_lines   = bom_lines,
        qw_resolved = qw_resolved,
        cbom        = cbom,
        nre_lines   = nre_lines,
    )

    filename = f'{project_dict["code"]}_CBOM_{project_dict.get("rev_no",0)}.xlsx'
    return StreamingResponse(
        io.BytesIO(xlsx_bytes),
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )
