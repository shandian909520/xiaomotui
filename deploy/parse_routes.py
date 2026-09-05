# -*- coding: utf-8 -*-
"""
解析 ThinkPHP 路由文件，还原嵌套 Route::group 的完整路径。
输出：method, 完整路径, 控制器@方法, 是否位于鉴权组内
"""
import re
import sys

ROUTE_FILE = sys.argv[1] if len(sys.argv) > 1 else "api/route/app.php"

with open(ROUTE_FILE, encoding="utf-8", errors="replace") as f:
    lines = f.read().split("\n")

# 记录每个 group 起始行的缩进与名称
GROUP_RE = re.compile(r"Route::group\(\s*'([^']+)'(.*)$")
VERB_RE = re.compile(
    r"Route::(get|post|put|delete|any|patch)\(\s*'([^']*)'\s*,\s*'?\\?([^'\),]+)"
)
MW_RE = re.compile(r"middleware\(\[(.*?)\]\)")

routes = []
stack = []          # [(indent, name)]
pending_auth = []   # 每个 group 结束时判定：该组是否含 Auth 中间件


def current_prefix():
    return "/".join(n for _, n in stack)


# 先扫描：找出所有带 ->middleware(...) 的闭合行，记录其缩进
mw_by_indent = {}
for i, line in enumerate(lines):
    m = MW_RE.search(line)
    if m and line.strip().startswith("})"):
        indent = len(line) - len(line.lstrip())
        mw_by_indent[i] = ("Auth" if "Auth" in m.group(1) else "public", m.group(1))

for i, line in enumerate(lines):
    stripped = line.strip()
    if not stripped or stripped.startswith("//"):
        continue
    indent = len(line) - len(line.lstrip())

    # 闭合括号：弹出栈
    if stripped.startswith("})") or stripped == "}":
        if stack and indent <= stack[-1][0]:
            stack.pop()
        continue

    m = GROUP_RE.search(line)
    if m:
        stack.append((indent, m.group(1)))
        continue

    v = VERB_RE.search(line)
    if v:
        method, path, handler = v.group(1).upper(), v.group(2), v.group(3).strip("'\\")
        full = "/".join(x for x in [current_prefix(), path] if x)
        routes.append((method, full, handler))

# 判定每个路由是否受 Auth 保护（依据其所属最外层 api 组的中间件）
# 重新扫描：记录每个 api 组的行范围与是否含 Auth
groups = []
for i, line in enumerate(lines):
    m = GROUP_RE.search(line)
    if m and m.group(1).split("/")[0] == "api":
        indent = len(line) - len(line.lstrip())
        # 向后找匹配的闭合 + middleware
        has_auth = False
        for j in range(i, min(i + 4000, len(lines))):
            l2 = lines[j]
            ind2 = len(l2) - len(l2.lstrip())
            if j > i and ind2 == indent and (l2.strip().startswith("})") or l2.strip() == "}"):
                mw = MW_RE.search(l2)
                if mw:
                    has_auth = "Auth" in mw.group(1)
                break
        groups.append((i, has_auth))


def in_auth_group(route_line_idx):
    res = False
    for start, has_auth in groups:
        if start <= route_line_idx:
            res = has_auth
        else:
            break
    return res


# 关联路由行号
out = []
for i, line in enumerate(lines):
    v = VERB_RE.search(line)
    if not v:
        continue
    # 重新计算 full path（复用 stack 逻辑太复杂，这里简单重跑）
    pass

# 简化：直接输出路由，auth 判定用路由所属顶层 api 组
# 再次遍历，这次维护 group 栈并同时记录 group 起始行
stack = []
final = []
for i, line in enumerate(lines):
    stripped = line.strip()
    if not stripped or stripped.startswith("//"):
        continue
    indent = len(line) - len(line.lstrip())
    if stripped.startswith("})") or stripped == "}":
        if stack and indent <= stack[-1][0]:
            stack.pop()
        continue
    m = GROUP_RE.search(line)
    if m:
        stack.append((indent, m.group(1), i))
        continue
    v = VERB_RE.search(line)
    if v:
        method, path, handler = v.group(1).upper(), v.group(2), v.group(3).strip("'\\")
        full = "/".join(x for x in ["/".join(n for _, n, _ in stack), path] if x)
        auth = in_auth_group(i)
        final.append((method, full, handler, auth))

print(f"共解析出 {len(final)} 条路由\n")
print("=" * 100)

pub = [r for r in final if not r[3]]
auth = [r for r in final if r[3]]
print(f"公开路由 {len(pub)} 条 / 鉴权路由 {len(auth)} 条")
print("=" * 100)

if "--public" in sys.argv:
    print("\n【公开路由（无需登录）】")
    for m, p, h, _ in pub:
        print(f"  {m:6} /{p:52} {h}")

if "--auth" in sys.argv:
    print("\n【鉴权路由】")
    for m, p, h, _ in auth:
        print(f"  {m:6} /{p:52} {h}")

if "--all" in sys.argv or len(sys.argv) == 1:
    print("\n【全部路由】")
    for m, p, h, a in final:
        print(f"  [{'AUTH' if a else 'PUB '}] {m:6} /{p:52} {h}")
