# -*- coding: utf-8 -*-
path = r"c:\Users\user\Desktop\Market Clearance System\notification.php"
with open(path, "r", encoding="utf-8") as f:
    lines = f.readlines()

new_css = open(
    r"c:\Users\user\Desktop\Market Clearance System\_notification_nav_menu_css.txt",
    "r",
    encoding="utf-8",
).read()

before = "".join(lines[:727])
after = "".join(lines[1220:])
with open(path, "w", encoding="utf-8") as f:
    f.write(before + new_css + "\n" + after)
print("OK")
