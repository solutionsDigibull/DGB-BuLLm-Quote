# CLAUDE.md — BuLLMQuote

## Project Overview

BuLLMQuote is a full-stack web application for electronics quote processing. It parses SCRUB BOM and QuoteWin award files, computes pricing across volume breaks, and generates multi-sheet Excel reports.

## Tech Stack

- **Backend:** Python 3.12, FastAPI 0.111, SQLAlchemy 2.0 (async), asyncpg, Uvicorn
- **Frontend:** Vanilla JavaScript (ES6+), single-page HTML app with custom CAM 2.0 UI framework
- **Database:** PostgreSQL (async via asyncpg)
- **Auth:** JWT (python-jose, HS256, 12h expiry), bcrypt via passlib
- **Data Processing:** pandas 2.2, openpyxl 3.1
- **Containerization:** Docker (python:3.12-slim), Docker Compose

## Project Structure

```
backend/
  main.py              # FastAPI app, CORS, static file serving, router registration
  database.py          # SQLAlchemy async engine, Settings (from .env), session factory
  init_db.py           # Creates all DB tables on startup
  models/models.py     # SQLAlchemy ORM models (projects, bom_lines, qw_prices, cbom_rows, etc.)
  routers/             # API endpoint handlers
    auth.py            # /api/auth — login, token, init-admin
    projects.py        # /api/projects — project CRUD
    bom.py             # /api/bom — SCRUB BOM file upload & parsing
    qw.py              # /api/qw — QuoteWin file upload & price resolution
    cbom.py            # /api/cbom — computed BOM generation
    export.py          # /api/export — Excel workbook generation
  services/            # Business logic
    bom_parser.py      # SCRUB BOM Excel parsing (fuzzy header matching)
    qw_parser.py       # QuoteWin award file parsing (row 17 headers, 3 volume tiers)
    cbom_engine.py     # BOM + QW price merging, scrap factor, MOQ ceiling
    export_engine.py   # 19-sheet Excel workbook generation (745 lines)
frontend/
  index.html           # Main UI (8k+ lines)
  api_bridge.js        # Patches stub functions with real API fetch calls
  lib/                 # xlsx.full.min.js, jszip.min.js
QWController-Related/  # Legacy Laravel codebase (reference only, not deployed)
```

## Key Commands

```bash
# Run locally
uvicorn backend.main:app --host 0.0.0.0 --port 8000

# Docker
docker compose up --build

# Initialize database tables
python -m backend.init_db
```

## Environment Variables

| Variable       | Description                          | Default / Example                                                       |
|---------------|--------------------------------------|-------------------------------------------------------------------------|
| `DATABASE_URL` | PostgreSQL async connection string   | `postgresql+asyncpg://postgres:DigiBull@localhost:5433/bullmquote`       |
| `SECRET_KEY`   | JWT signing key                      | `changeme-in-production`                                                |

## Database Tables

| Table         | Purpose                                    |
|---------------|--------------------------------------------|
| `projects`    | Quote projects (code, customer, currency, volumes) |
| `bom_lines`   | Bill of Materials entries from SCRUB BOM   |
| `qw_prices`   | Supplier pricing from QuoteWin award files |
| `cbom_rows`   | Computed BOM with merged pricing           |
| `nre_lines`   | Non-recurring engineering charges          |
| `ex_inv_rows` | Excess inventory analysis                  |
| `users`       | Authentication (username, email, role)     |

## Application Workflow

1. Create/select a project
2. Upload SCRUB BOM file → parsed into `bom_lines`
3. Upload QuoteWin award file → parsed into `qw_prices`
4. Compute CBOM → merges BOM + QW prices into `cbom_rows`
5. Export → generates multi-sheet Excel workbook

## Architecture Conventions

- **Async everywhere:** All DB operations use `async/await` with SQLAlchemy async sessions
- **Dependency injection:** FastAPI `Depends()` for DB sessions and auth
- **Pydantic models** for request/response validation
- **HTTPException** for error handling (not custom exception classes)
- **Private helpers** prefixed with `_` (e.g., `_safe_float`, `_resolve_vol`)
- **Column mapping** via dictionaries and regex patterns for fuzzy Excel header matching
- **No automated tests** — manual testing via curl and debug scripts

## Coding Style

- Python: snake_case, type hints on function signatures, async def for all endpoints
- JavaScript: camelCase, Fetch API with JWT token from localStorage (`bq_token`)
- Frontend uses IIFE pattern for API bridge isolation
- Auto-retry on 401 responses (token expiration)

## Business Logic Notes

- **Scrap factor:** Class A (>=1.25 unit price → 1%), B (0.25-1.25 → 2%), C (<0.25 → 5%)
- **Buy quantity:** `ceil(ext_qty / MOQ) * MOQ` (ceiling to MOQ)
- **Volume breaks:** PROTO=10, VL1=300, VL2=1000 (defaults)
- **Currency rates:** EUR=0.8585, INR=89.47, USD=1.0
- **QuoteWin format:** 17 metadata rows, data starts row 18, 3 cost tiers per volume

## API Base Path

All API endpoints are under `/api/`. Frontend is served from `/` via FastAPI static files.

## Port

Default: `8000`
