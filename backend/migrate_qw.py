"""Run once to add new columns to qw_prices."""
import asyncio
import sys
import os

sys.path.insert(0, os.path.dirname(__file__))
from database import engine
from sqlalchemy import text

SQL = """
ALTER TABLE qw_prices
  ADD COLUMN IF NOT EXISTS corrected_mpn       VARCHAR(256),
  ADD COLUMN IF NOT EXISTS part_description    VARCHAR(512),
  ADD COLUMN IF NOT EXISTS effective_from_date VARCHAR(32),
  ADD COLUMN IF NOT EXISTS expiry_date         VARCHAR(32),
  ADD COLUMN IF NOT EXISTS quote_validity_weeks INTEGER,
  ADD COLUMN IF NOT EXISTS price_control       VARCHAR(32) DEFAULT 'centum',
  ADD COLUMN IF NOT EXISTS revision_no         INTEGER DEFAULT 0;
"""

async def main():
    async with engine.begin() as conn:
        await conn.execute(text(SQL))
    print("Migration complete.")

asyncio.run(main())
