#!/usr/bin/env python3
"""Extract text from PDF (handles empty-password encryption). Requires pypdf."""
import sys

from pypdf import PdfReader

path = sys.argv[1]
reader = PdfReader(path)
if reader.is_encrypted:
    reader.decrypt("")
parts = []
for page in reader.pages:
    text = page.extract_text()
    if text:
        parts.append(text)
print("\n".join(parts))
