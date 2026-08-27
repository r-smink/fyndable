import re
import json
import gzip
import base64
import html
from pathlib import Path

root = Path(__file__).parent
src = root / "Fyndable Mobile App Design.html"
out = root / ".design-extract.html"

html_text = src.read_text(encoding="utf-8")

def extract_script(type_value):
    pattern = rf'<script type="{re.escape(type_value)}">\s*(.*?)\s*</script>'
    m = re.search(pattern, html_text, re.S)
    if not m:
        raise RuntimeError(f"Script {type_value} not found")
    return m.group(1)

template = json.loads(extract_script("__bundler/template"))
manifest = json.loads(extract_script("__bundler/manifest"))

# decode manifest assets, build data URLs
for uuid, entry in manifest.items():
    raw = base64.b64decode(entry["data"])
    if entry.get("compressed"):
        raw = gzip.decompress(raw)
    b64 = base64.b64encode(raw).decode()
    data_url = f"data:{entry['mime']};base64,{b64}"
    template = template.replace(uuid, data_url)

out.write_text(template, encoding="utf-8")
print(f"Extracted {out} ({len(template)} chars)")
