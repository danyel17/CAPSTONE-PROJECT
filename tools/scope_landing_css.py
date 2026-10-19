"""Scope soda/index.html hero CSS under .menu-landing-viewport."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
html = (ROOT / "soda" / "index.html").read_text(encoding="utf-8")
css = re.search(r"<style>(.*?)</style>", html, re.S).group(1)

SKIP = {
    "*",
    "body",
    "body.blue-theme",
    ":root",
}

NAV_PREFIXES = (
    ".navbar",
    ".nav-pills",
    ".nav-pill",
    ".nav-icon-pill",
    ".login-btn",
    ".logout-btn",
)


def split_css_blocks(text: str) -> list[str]:
    blocks: list[str] = []
    i = 0
    n = len(text)
    while i < n:
        while i < n and text[i].isspace():
            i += 1
        if i >= n:
            break
        if text[i : i + 11] == "@keyframes":
            j = text.find("{", i)
            depth = 0
            k = j
            while k < n:
                if text[k] == "{":
                    depth += 1
                elif text[k] == "}":
                    depth -= 1
                    if depth == 0:
                        blocks.append(text[i : k + 1].strip())
                        i = k + 1
                        break
                k += 1
            else:
                break
            continue
        if text[i : i + 6] == "@media":
            j = text.find("{", i)
            depth = 0
            k = j
            while k < n:
                if text[k] == "{":
                    depth += 1
                elif text[k] == "}":
                    depth -= 1
                    if depth == 0:
                        blocks.append(text[i : k + 1].strip())
                        i = k + 1
                        break
                k += 1
            else:
                break
            continue
        j = text.find("{", i)
        if j == -1:
            break
        sel = text[i:j].strip()
        depth = 0
        k = j
        while k < n:
            if text[k] == "{":
                depth += 1
            elif text[k] == "}":
                depth -= 1
                if depth == 0:
                    blocks.append(text[i : k + 1].strip())
                    i = k + 1
                    break
            k += 1
        else:
            break
    return blocks


def should_skip_selector(sel: str) -> bool:
    parts = [p.strip() for p in sel.split(",")]
    for p in parts:
        base = p.split()[0] if p else p
        if base in SKIP:
            return True
        for nav in NAV_PREFIXES:
            if base.startswith(nav):
                return True
    return False


def prefix_selector(sel: str) -> str:
    out = []
    for p in [x.strip() for x in sel.split(",")]:
        if p.startswith("@") or p.startswith(".menu-landing-viewport"):
            out.append(p)
        else:
            out.append(f".menu-landing-viewport {p}")
    return ", ".join(out)


def scope_block(block: str) -> str | None:
    block = block.strip()
    if block.startswith("@keyframes") or block.startswith("@media"):
        if block.startswith("@media"):
            inner = block[block.find("{") + 1 : -1]
            inner_blocks = split_css_blocks(inner)
            scoped_inner = []
            for ib in inner_blocks:
                s = scope_block(ib)
                if s:
                    scoped_inner.append(s)
            header = block[: block.find("{") + 1]
            return header + "\n" + "\n".join(scoped_inner) + "\n}"
        return block
    j = block.find("{")
    if j == -1:
        return None
    sel = block[:j].strip()
    body = block[j:]
    if should_skip_selector(sel):
        return None
    return prefix_selector(sel) + body


blocks = split_css_blocks(css)
header = """/* Scoped hero styles from soda/index.html */
.menu-landing-viewport {
  --bg-inner: #6b63c9;
  --bg-mid: #3f4191;
  --bg-outer: #2e236c;
  --text-color: #ffffff;
  --muted-color: rgba(255, 255, 255, 0.7);
  --glass-bg: rgba(255, 255, 255, 0.05);
  --glass-border: rgba(255, 255, 255, 0.1);
  --font-main: 'Inter', sans-serif;
  --font-heading: 'Galada', cursive;
  background: radial-gradient(circle at center, var(--bg-inner) 0%, var(--bg-mid) 50%, var(--bg-outer) 100%);
  min-height: 100vh;
  position: relative;
  overflow: hidden;
  color: var(--text-color);
  transition: background 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.menu-landing-viewport.blue-theme {
  --bg-inner: #7870e8;
  --bg-mid: #4a44a0;
  --bg-outer: #1a1640;
}
.menu-landing-viewport .hero-welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 50px;
  padding: 0.4rem 1rem;
  font-size: 0.9rem;
  font-weight: 500;
  color: #ffffff;
  width: fit-content;
  margin-bottom: 1.25rem;
  backdrop-filter: blur(8px);
}
.menu-landing-viewport .hero-welcome-badge .badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ff6b35;
  box-shadow: 0 0 8px #ff6b35;
}
"""

scoped = [header]
for b in blocks:
    s = scope_block(b)
    if s:
        scoped.append(s)

out = ROOT / "assets" / "css" / "menu-landing-hero.css"
out.write_text("\n\n".join(scoped) + "\n", encoding="utf-8")
print(f"Wrote {out} ({len(scoped)} blocks)")
