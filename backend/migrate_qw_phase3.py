"""Run once to add Phase 3 columns to qw_prices."""
import asyncio, sys, os
sys.path.insert(0, os.path.dirname(__file__))
from database import engine
from sqlalchemy import text

SQL = """
ALTER TABLE qw_prices
  ADD COLUMN IF NOT EXISTS proto_cost_conv  FLOAT,
  ADD COLUMN IF NOT EXISTS proto_price_orig FLOAT;
"""

async def main():
    async with engine.begin() as conn:
        await conn.execute(text(SQL))
    print("Phase 3 migration complete.")

asyncio.run(main())
