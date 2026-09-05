# -*- coding: utf-8 -*-
"""
对线上「公开路由」做全量冒烟测试（无需登录即可访问）。

路由清单由 deploy/parse_routes.py 从 api/route/app.php 解析得到，
避免手工猜测路径造成误判。

用法:
    python deploy/public_routes_test.py

判定:
  MISS_TABLE - MySQL 1146，依赖的数据表未创建
  ERROR5XX   - 服务端异常（PHP 错误）
  HEALTH_FB  - 返回 health 兜底，说明该路由线上未注册/未生效
  OK         - 正常业务响应

注意: 本项目大量接口会吞掉异常后返回 200 + 空数据，
      因此 200 不代表功能正常，需结合 runtime/logs 与 db_table_probe.py 判断。
"""
import json
import os
import re
import subprocess
import sys
import urllib.request
import urllib.error

BASE = "http://pengh5.moban8.top"
TIMEOUT = 15
REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

MISS_RE = re.compile(r"Table '([^']+)' doesn't exist")
HEALTH_MARK = "小磨推API服务"

# 跳过有副作用的接口（发短信等）
SKIP = {"sms/send", "sms/verify", "auth/send-code"}


def parse_public_routes():
    out = subprocess.run(
        [sys.executable, os.path.join(REPO, "deploy", "parse_routes.py"),
         "api/route/app.php", "--public"],
        cwd=REPO, capture_output=True, text=True, encoding="utf-8"
    ).stdout
    routes = []
    for line in out.split("\n"):
        m = re.match(r"\s*(GET|POST|PUT|DELETE|ANY|PATCH)\s+(/\S+)\s+(\S+)", line)
        if m:
            routes.append((m.group(1), m.group(2), m.group(3)))
    return routes


def request(method, path, body=None):
    url = BASE + path
    data = json.dumps(body).encode() if body is not None else None
    headers = {"Accept": "application/json"}
    if data:
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:
        return 0, f"{type(e).__name__}: {e}"


def fill_params(path):
    """把 :id 之类的占位符替换成 1，便于探测"""
    return re.sub(r":\w+", "1", path)


def main():
    routes = parse_public_routes()
    print(f"解析到公开路由 {len(routes)} 条\n" + "=" * 84)

    stats = {"OK": [], "MISS_TABLE": [], "ERROR5XX": [], "HEALTH_FB": [], "OTHER": []}
    tables = {}

    for method, path, handler in routes:
        if any(s in path for s in SKIP):
            continue
        real = fill_params(path)
        body = {} if method in ("POST", "PUT", "PATCH") else None
        code, text = request(method, real, body)
        flat = " ".join(text.split())

        m = MISS_RE.search(flat)
        if m:
            tables.setdefault(m.group(1), []).append(f"{method} {path}")
            stats["MISS_TABLE"].append((method, path, handler, m.group(1)))
            tag = "MISS_TABLE"
        elif code >= 500:
            stats["ERROR5XX"].append((method, path, handler, code, flat[:120]))
            tag = "ERROR5XX"
        elif HEALTH_MARK in flat and "version" in flat:
            stats["HEALTH_FB"].append((method, path, handler))
            tag = "HEALTH_FB"
        elif code in (200, 400, 401, 403, 404, 422, 429):
            stats["OK"].append((method, path, code))
            tag = "OK"
        else:
            stats["OTHER"].append((method, path, code, flat[:100]))
            tag = "OTHER"

        mark = {"OK": "  ", "MISS_TABLE": "!!", "ERROR5XX": "!!",
                "HEALTH_FB": "??", "OTHER": ".."}[tag]
        print(f"{mark} [{tag:10}] {method:6} {path:50} -> {code}")

    print("=" * 84)
    print(f"正常 {len(stats['OK'])} / 缺表 {len(stats['MISS_TABLE'])} / "
          f"5xx {len(stats['ERROR5XX'])} / 路由未生效 {len(stats['HEALTH_FB'])}")

    if tables:
        print("\n【缺失的数据表】")
        for t, users in sorted(tables.items()):
            print(f"  - {t}  (被 {len(users)} 个接口依赖)")
    if stats["ERROR5XX"]:
        print("\n【5xx 异常接口】")
        for method, path, handler, code, msg in stats["ERROR5XX"]:
            print(f"  - {method} {path} ({handler}) -> {code}")
            print(f"    {msg}")
    if stats["HEALTH_FB"]:
        print("\n【未生效路由（线上返回 health 兜底）】")
        for method, path, handler in stats["HEALTH_FB"]:
            print(f"  - {method} {path} -> {handler}")
    print("=" * 84)
    return 1 if (stats["MISS_TABLE"] or stats["ERROR5XX"]) else 0


if __name__ == "__main__":
    sys.exit(main())
