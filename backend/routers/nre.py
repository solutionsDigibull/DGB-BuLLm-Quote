from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete
from pydantic import BaseModel
from typing import Optional, List
from database import get_db
from models.models import Project, NreLine

router = APIRouter()

class NreLineIn(BaseModel):
    nre_type: str
    cpn: str
    description: Optional[str] = ""
    commodity: Optional[str] = ""
    manufacturer: Optional[str] = ""
    mpn: Optional[str] = ""
    nre_charge_conv: float


@router.post("/{project_id}/upload")
async def upload_nre(project_id: int, lines: List[NreLineIn],
                     db: AsyncSession = Depends(get_db)):
    res = await db.execute(select(Project).where(Project.id == project_id))
    if not res.scalar_one_or_none():
        raise HTTPException(404, "Project not found")
    await db.execute(delete(NreLine).where(NreLine.project_id == project_id))
    objs = [NreLine(project_id=project_id, **l.dict()) for l in lines]
    db.add_all(objs)
    await db.commit()
    return {"msg": "NRE lines uploaded", "count": len(lines)}


@router.get("/{project_id}/lines")
async def get_nre_lines(project_id: int, db: AsyncSession = Depends(get_db)):
    res = await db.execute(
        select(NreLine).where(NreLine.project_id == project_id)
                       .order_by(NreLine.nre_type, NreLine.cpn)
    )
    rows = res.scalars().all()
    return [{c.key: getattr(r, c.key) for c in r.__table__.columns} for r in rows]


@router.delete("/{project_id}/lines")
async def delete_nre_lines(project_id: int, db: AsyncSession = Depends(get_db)):
    res = await db.execute(select(Project).where(Project.id == project_id))
    if not res.scalar_one_or_none():
        raise HTTPException(404, "Project not found")
    await db.execute(delete(NreLine).where(NreLine.project_id == project_id))
    await db.commit()
    return {"msg": "NRE lines cleared"}
