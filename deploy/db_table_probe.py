# -*- coding: utf-8 -*-
"""
探测生产数据库中「本次新增模块」依赖的表是否真实存在。

原理：调用依赖各表的业务接口，捕获 MySQL 1146 错误并提取缺失表名。
"""
import json
import re
import sys
import urllib.request
import urllib.error

BASE = "http://pengh5.moban8.top"
TIMEOUT = 15

# (方法, 路径, body, 说明)
PROBES = [
    ("GET",  "/api/copywriting/rotate?device_id=1",                None, "文案池"),
    ("GET",  "/api/publish/copywriting?device_id=1",               None, "H5聚合页文案"),
    ("GET",  "/api/lottery/by-device?device_code=NFC-COUNTER-001", None, "抽奖-按设备"),
    ("POST", "/api/lottery/draw",                                  {"activity_id": 1, "user_hash": "probe", "device_id": 1}, "抽奖执行"),
    ("GET",  "/api/lottery/my-records?device_id=1&user_hash=p",    None, "抽奖记录"),
    ("POST", "/api/funnel/record",                                 {"device_id": 1, "step": "view", "block": "probe", "action": "t"}, "漏斗埋点"),
    ("GET",  "/api/review/draft?device_id=1&platform=dianping",    None, "点评草稿"),
    ("POST", "/api/review/action",                                 {"device_id": 1, "platform": "dianping", "action": "view"}, "点评埋点"),
    ("GET",  "/api/contact/qq-config?device_id=1",                 None, "QQ配置"),
    ("GET",  "/api/task/bundle/list",                              None, "任务宝列表"),
    ("GET",  "/api/lottery-admin/activities",                      None, "抽奖活动-管理"),
    ("GET",  "/api/group-buy-admin/items",                         None, "团购商品-管理"),
    ("GET",  "/api/copywriting/pool",                              None, "文案池-管理"),
    ("GET",  "/api/funnel/stats",                                  None, "漏斗统计"),
]

MISSING_RE = re.compile(r"Table '([^']+)' doesn't exist")


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


def main():
    missing = {}
    broken = []
    healthy = []

    print("=" * 76)
    print("生产数据库表存在性探测")
    print("=" * 76)

    for method, path, body, desc in PROBES:
        code, text = request(method, path, body)
        m = MISSING_RE.search(text)
        if m:
            table = m.group(1)
            missing.setdefault(table, []).append(desc)
            print(f"[缺表] {desc:16} {method:4} {path}")
            print(f"         -> 缺失表: {table}")
        elif code >= 500:
            broken.append((desc, path, code, " ".join(text.split())[:120]))
            print(f"[异常] {desc:16} {method:4} {path} -> {code}")
            print(f"         -> {' '.join(text.split())[:120]}")
        else:
            healthy.append((desc, code))
            print(f"[正常] {desc:16} {method:4} {path} -> {code}")

    print("=" * 76)
    print(f"正常 {len(healthy)} / 异常 {len(broken)} / 涉及缺表 {len(missing)} 张")
    if missing:
        print("\n【缺失的数据表】")
        for t, users in sorted(missing.items()):
            print(f"  - {t}")
            print(f"      影响: {', '.join(sorted(set(users)))}")
    if broken:
        print("\n【非缺表类异常】")
        for desc, path, code, msg in broken:
            print(f"  - {desc} ({path}) -> {code}: {msg}")
    print("=" * 76)
    return 1 if (missing or broken) else 0


if __name__ == "__main__":
    sys.exit(main())
