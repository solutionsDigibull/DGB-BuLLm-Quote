"""
Migrate the legacy `projects` table and fix admin role.
- Adds missing columns expected by the FastAPI ORM model
- Copies existing `name` data into `code` and `customer`
- Sets admin user role to 'admin'
Run once: python backend/migrate_projects.py
"""
import asyncio, sys, os
sys.path.insert(0, os.path.dirname(__file__))
from database import engine
from sqlalchemy import text

SQL_PROJECTS = """
ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS code        VARCHAR(64),
  ADD COLUMN IF NOT EXISTS customer    VARCHAR(256),
  ADD COLUMN IF NOT EXISTS currency    VARCHAR(8)  DEFAULT 'USD',
  ADD COLUMN IF NOT EXISTS eur_rate    FLOAT       DEFAULT 0.8585,
  ADD COLUMN IF NOT EXISTS inr_rate    FLOAT       DEFAULT 89.47,
  ADD COLUMN IF NOT EXISTS usd_rate    FLOAT       DEFAULT 1.0,
  ADD COLUMN IF NOT EXISTS proto_qty   INTEGER     DEFAULT 10,
  ADD COLUMN IF NOT EXISTS vl1_qty     INTEGER     DEFAULT 300,
  ADD COLUMN IF NOT EXISTS vl2_qty     INTEGER     DEFAULT 1000,
  ADD COLUMN IF NOT EXISTS rev_no      INTEGER     DEFAULT 0;
"""

SQL_COPY_NAME = """
UPDATE projects
SET code     = COALESCE(code,     name),
    customer = COALESCE(customer, name)
WHERE code IS NULL OR customer IS NULL;
"""

SQL_UNIQUE_CODE = """
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_indexes
    WHERE tablename = 'projects' AND indexname = 'ix_projects_code'
  ) THEN
    CREATE UNIQUE INDEX ix_projects_code ON projects(code);
  END IF;
END$$;
"""

SQL_ADMIN_ROLE = """
UPDATE users SET role = 'admin' WHERE name = 'admin' AND role != 'admin';
"""

async def main():
    async with engine.begin() as conn:
        print("Adding missing columns to projects table...")
        await conn.execute(text(SQL_PROJECTS))

        print("Copying name -> code, customer for existing rows...")
        await conn.execute(text(SQL_COPY_NAME))

        print("Creating unique index on projects.code...")
        await conn.execute(text(SQL_UNIQUE_CODE))

        print("Fixing admin user role...")
        result = await conn.execute(text(SQL_ADMIN_ROLE))
        print(f"  Rows updated: {result.rowcount}")

    print("Migration complete.")

asyncio.run(main())
