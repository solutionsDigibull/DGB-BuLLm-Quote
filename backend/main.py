from pathlib import Path

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from routers import projects, bom, qw, cbom, export, auth

app = FastAPI(title="BuLLMQuote API", version="0.1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(auth.router,     prefix="/api/auth",    tags=["auth"])
app.include_router(projects.router, prefix="/api/projects",tags=["projects"])
app.include_router(bom.router,      prefix="/api/bom",     tags=["bom"])
app.include_router(qw.router,       prefix="/api/qw",      tags=["qw"])
app.include_router(cbom.router,     prefix="/api/cbom",    tags=["cbom"])
app.include_router(export.router,   prefix="/api/export",  tags=["export"])

@app.get("/health")
def health():
    return {"status": "ok"}

# Serve frontend static files (replaces Nginx)
frontend_dir = Path(__file__).resolve().parent.parent / "frontend"
if frontend_dir.is_dir():
    app.mount("/", StaticFiles(directory=str(frontend_dir), html=True), name="frontend")
