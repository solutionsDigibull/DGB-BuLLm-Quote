from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from jose import JWTError, jwt
from passlib.context import CryptContext
from datetime import datetime, timedelta
from pydantic import BaseModel
from typing import Optional
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

class UserCreate(BaseModel):
    username: str
    email: str
    password: str
    role: str = "viewer"

class UserUpdate(BaseModel):
    email: Optional[str] = None
    role: Optional[str] = None
    is_active: Optional[bool] = None
    password: Optional[str] = None

class UserOut(BaseModel):
    id: int
    username: str
    email: str
    role: str
    is_active: bool

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


async def require_admin(user: User = Depends(current_user)):
    if user.role != "admin":
        raise HTTPException(403, "Admin access required")
    return user


@router.get("/me")
async def get_me(user: User = Depends(current_user)):
    return UserOut(id=user.id, username=user.username, email=user.email,
                   role=user.role, is_active=user.is_active)


@router.post("/register")
async def register_user(data: UserCreate, db: AsyncSession = Depends(get_db),
                        admin: User = Depends(require_admin)):
    result = await db.execute(select(User).where(User.username == data.username))
    if result.scalar_one_or_none():
        raise HTTPException(400, "Username already exists")
    result = await db.execute(select(User).where(User.email == data.email))
    if result.scalar_one_or_none():
        raise HTTPException(400, "Email already exists")
    user = User(username=data.username, email=data.email,
                hashed_pw=pwd_ctx.hash(data.password), role=data.role)
    db.add(user)
    await db.commit()
    await db.refresh(user)
    return UserOut(id=user.id, username=user.username, email=user.email,
                   role=user.role, is_active=user.is_active)


@router.get("/users")
async def list_users(db: AsyncSession = Depends(get_db),
                     admin: User = Depends(require_admin)):
    result = await db.execute(select(User).order_by(User.created_at))
    users = result.scalars().all()
    return [UserOut(id=u.id, username=u.username, email=u.email,
                    role=u.role, is_active=u.is_active) for u in users]


@router.patch("/users/{user_id}")
async def update_user(user_id: int, data: UserUpdate,
                      db: AsyncSession = Depends(get_db),
                      admin: User = Depends(require_admin)):
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(404, "User not found")
    if data.email is not None:
        user.email = data.email
    if data.role is not None:
        user.role = data.role
    if data.is_active is not None:
        user.is_active = data.is_active
    if data.password is not None:
        user.hashed_pw = pwd_ctx.hash(data.password)
    await db.commit()
    return {"msg": "updated"}


@router.delete("/users/{user_id}")
async def delete_user(user_id: int, db: AsyncSession = Depends(get_db),
                      admin: User = Depends(require_admin)):
    if user_id == admin.id:
        raise HTTPException(400, "Cannot delete yourself")
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(404, "User not found")
    await db.delete(user)
    await db.commit()
    return {"msg": "deleted"}
