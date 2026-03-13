"""
Migration: add missing columns to bom_details table.
Run once: python migrate_bom_details.py
"""
import asyncio
import os
import sys

# Allow running from the backend/ directory
sys.path.insert(0, os.path.dirname(__file__))

from database import Settings
import asyncpg


async def main():
    dsn = Settings().database_url.replace("+asyncpg", "")
    conn = await asyncpg.connect(dsn)
    try:
        await conn.execute("""
            ALTER TABLE bom_details
              ADD COLUMN IF NOT EXISTS project_id      INTEGER REFERENCES projects(id),
              ADD COLUMN IF NOT EXISTS proto           INTEGER DEFAULT 0,
              ADD COLUMN IF NOT EXISTS volume_count    INTEGER DEFAULT 2,
              ADD COLUMN IF NOT EXISTS total_fg_count  INTEGER DEFAULT 0,
              ADD COLUMN IF NOT EXISTS total_cpn_count INTEGER DEFAULT 0,
              ADD COLUMN IF NOT EXISTS is_valid_bom    BOOLEAN DEFAULT FALSE,
              ADD COLUMN IF NOT EXISTS settings_json   TEXT;
        """)
        print("Migration complete.")
    finally:
        await conn.close()


if __name__ == "__main__":
    asyncio.run(main())
