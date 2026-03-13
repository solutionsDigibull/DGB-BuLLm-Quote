
Update the information about "What is a QuoteWin File?" to enable you to read file and update your business logic.
Import output from QuoteWin - Also known as QW or Quoting Tool or Supplier Quoting Tool
This contains pricing & lead time information from Suppliers responding to Quote Request via Quoting Tool
Refer Quoting Tool Template
Template will be different for each quoting tool
Row 1 - 9: Metadata (project info, volume quantities)
Column A: Project Headings 
Column B & C: Empty for Rows 1 - 9
Column D: Project Details
Row 10: Always Empty
Row 11: Heading "Assembly Summary"
Row 12:
Column A - E - Volume Headings
Row 13: Board quantities for each volume tier (cols 2, 6, 10)
Row 14: Volume Total
Row 15: Always Empty
Row 16: Heading "Group Detail"
Row 17: QuoteWin Tool Supplier Quote Column headers (detected via regex, with hardcoded fallback)
Row 18+: Supplier Quote Details (Data — one row per supplier offer per component)

