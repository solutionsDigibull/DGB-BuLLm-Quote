"""Run once to add Phase 2 columns to qw_prices."""
import asyncio, sys, os
sys.path.insert(0, os.path.dirname(__file__))
from database import engine
from sqlalchemy import text

SQL = """
ALTER TABLE qw_prices
  ADD COLUMN IF NOT EXISTS no_bid           BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS is_l1            BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS total_part_qty   FLOAT,
  ADD COLUMN IF NOT EXISTS uploaded_through VARCHAR(64) DEFAULT 'Direct';
"""

async def main():
    async with engine.begin() as conn:
        await conn.execute(text(SQL))
    print("Phase 2 migration complete.")

asyncio.run(main())
