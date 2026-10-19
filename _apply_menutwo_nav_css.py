# -*- coding: utf-8 -*-
from pathlib import Path

root = Path(r"c:\Users\user\Desktop\Market Clearance System")
mt_lines = (root / "menu.php").read_text(encoding="utf-8").splitlines()
chunk_a = "\n".join(mt_lines[72:548])
chunk_b = "\n".join(mt_lines[1339:1694])
extra = """
    .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
"""
new_css = chunk_a + "\n" + extra + "\n" + chunk_b + "\n"

path = root / "notification.php"
text = path.read_text(encoding="utf-8")
start = text.find("    body {")
if start == -1:
    raise SystemExit("    body { not found")
end = text.find("\n\n    main {", start)
if end == -1:
    end = text.find("\r\n\r\n    main {", start)
if end == -1:
    raise SystemExit("\\n\\n    main { not found")

path.write_text(text[:start] + new_css + text[end:], encoding="utf-8")
print("OK: replaced body{...} through before main {")
