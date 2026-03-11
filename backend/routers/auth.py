from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from jose import JWTError, jwt
from passlib.context import CryptContext
from datetime import datetime, timedelta
from pydantic import BaseModel
from database import get_db, settings
from models.models import User

router = APIRouter()
pwd_ctx = CryptContext(schemes=["bcrypt"], deprecated="auto")
oauth2  = OAuth2PasswordBearer(tokenUrl="/api/auth/login")

SECRET    = settings.secret_key
ALGORITHM = "HS256"
EXPIRE_H  = 12

def _make_token(username: str):
    exp = datetime.utcnow() + timedelta(hours=EXPIRE_H)
    return jwt.encode({"sub": username, "exp": exp}, SECRET, algorithm=ALGORITHM)

async def current_user(token: str = Depends(oauth2), db: AsyncSession = Depends(get_db)):
    try:
        payload = jwt.decode(token, SECRET, algorithms=[ALGORITHM])
        username = payload.get("sub")
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
    result = await db.execute(select(User).where(User.username == username))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(status_code=401, detail="User not found")
    return user

class TokenOut(BaseModel):
    access_token: str
    token_type:   str = "bearer"
    role:         str

@router.post("/login", response_model=TokenOut)
async def login(form: OAuth2PasswordRequestForm = Depends(), db: AsyncSession = Depends(get_db)):
    result = await db.execute(select(User).where(User.username == form.username))
    user = result.scalar_one_or_none()
    if not user or not pwd_ctx.verify(form.password, user.hashed_pw):
        raise HTTPException(status_code=400, detail="Incorrect credentials")
    return TokenOut(access_token=_make_token(user.username), role=user.role)

@router.post("/init-admin")
async def init_admin(db: AsyncSession = Depends(get_db)):
    """Create default admin on first boot — disable after setup."""
    result = await db.execute(select(User).where(User.username == "admin"))
    if result.scalar_one_or_none():
        return {"msg": "admin already exists"}
    user = User(username="admin", email="admin@digibull.ai",
                hashed_pw=pwd_ctx.hash("bullm@2025"), role="admin")
    db.add(user)
    await db.commit()
    return {"msg": "admin created", "password": "bullm@2025"}
