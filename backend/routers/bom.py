from fastapi import APIRouter, Depends, UploadFile, File, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete
from database import get_db
from models.models import Project, BomLine
from services.bom_parser import parse_scrub_bom
import io

router = APIRouter()


@router.post("/parse-preview")
async def parse_bom_preview(file: UploadFile = File(...)):
    """Quick parse: returns headers + rows for S1 UI preview (no DB save)."""
    ext = file.filename.split('.')[-1].lower()
    content = await file.read()
    if ext in ('csv', 'tsv'):
        import csv as csvmod
        sep = '\t' if ext == 'tsv' else ','
        reader = csvmod.reader(io.StringIO(content.decode('utf-8-sig')), delimiter=sep)
        rows = list(reader)
        if not rows:
            raise HTTPException(400, "Empty file")
        headers = [h.strip() for h in rows[0]]
        data_rows = [r for r in rows[1:] if any(c.strip() for c in r)]
        return {"headers": headers, "dataRows": data_rows, "sheetName": "CSV", "totalRows": len(data_rows)}
    elif ext in ('xlsx', 'xls'):
        import openpyxl
        wb = openpyxl.load_workbook(io.BytesIO(content), read_only=True, data_only=True)
        ws = wb.active
        all_rows = list(ws.iter_rows(values_only=True))
        wb.close()
        if not all_rows:
            raise HTTPException(400, "Empty file")
        headers = [str(h).strip() if h is not None else '' for h in all_rows[0]]
        data_rows = [[str(c).strip() if c is not None else '' for c in r] for r in all_rows[1:] if any(c is not None for c in r)]
        return {"headers": headers, "dataRows": data_rows, "sheetName": ws.title, "totalRows": len(data_rows)}
    raise HTTPException(400, "Unsupported format")

@router.post("/{project_id}/upload")
async def upload_bom(project_id: int, file: UploadFile = File(...),
                     db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    proj = result.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, detail="Project not found")

    content = await file.read()
    try:
        parsed = parse_scrub_bom(content)
    except Exception as e:
        raise HTTPException(400, detail=f"Failed to parse BOM file: {e}")

    try:
        await db.execute(delete(BomLine).where(BomLine.project_id == project_id))
        lines = []
        for row in parsed["rows"]:
            lines.append(BomLine(project_id=project_id, **row))
        db.add_all(lines)
        await db.commit()
    except Exception as e:
        await db.rollback()
        raise HTTPException(400, detail=f"Failed to save BOM lines: {e}")

    return {
        "msg":        "BOM uploaded",
        "total":      parsed["total"],
        "fg_parts":   parsed["fg_parts"],
        "assemblies": parsed["assemblies"],
    }

@router.get("/{project_id}/lines")
async def get_bom_lines(project_id: int, db: AsyncSession = Depends(get_db),
                        ):
    result = await db.execute(
        select(BomLine).where(BomLine.project_id == project_id)
                       .order_by(BomLine.fg_part, BomLine.assembly, BomLine.cpn)
    )
    lines = result.scalars().all()
    return [l.__dict__ for l in lines]
