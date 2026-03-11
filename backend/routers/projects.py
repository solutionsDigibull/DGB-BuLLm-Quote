from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from pydantic import BaseModel
from typing import Optional
from database import get_db
from models.models import Project

router = APIRouter()

class ProjectIn(BaseModel):
    code:        str
    customer:    str
    description: Optional[str] = ""
    currency:    Optional[str] = "USD"
    eur_rate:    Optional[float] = 0.8585
    inr_rate:    Optional[float] = 89.47
    proto_qty:   Optional[int]   = 10
    vl1_qty:     Optional[int]   = 300
    vl2_qty:     Optional[int]   = 1000

@router.post("")
async def create_project(data: ProjectIn, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.code == data.code))
    if result.scalar_one_or_none():
        raise HTTPException(400, detail=f"Project {data.code} already exists")
    proj = Project(**data.dict())
    db.add(proj)
    await db.commit()
    await db.refresh(proj)
    return {"id": proj.id, "code": proj.code}

@router.get("")
async def list_projects(db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).order_by(Project.created_at.desc()))
    projs = result.scalars().all()
    return [{"id":p.id,"code":p.code,"customer":p.customer,
             "created_at":p.created_at.isoformat()} for p in projs]

@router.get("/{project_id}")
async def get_project(project_id: int, db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    proj = result.scalar_one_or_none()
    if not proj:
        raise HTTPException(404, detail="Project not found")
    return proj.__dict__

@router.patch("/{project_id}")
async def update_project(project_id: int, data: dict,
                         db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(Project).where(Project.id == project_id))
    proj = result.scalar_one_or_none()
    if not proj:
        raise HTTPException(404)
    allowed = {"customer","description","currency","eur_rate","inr_rate",
               "proto_qty","vl1_qty","vl2_qty","rev_no"}
    for k,v in data.items():
        if k in allowed:
            setattr(proj, k, v)
    await db.commit()
    return {"msg": "updated"}
