# -*- coding: utf-8 -*-
"""
线上 API 冒烟测试

判定标准：
  PASS  - 200/400/401/422 等正常业务响应（路由存在、无 PHP 致命错误）
  FAIL  - 404（路由未注册）或 5xx（PHP 异常）
"""
import json
import sys
import urllib.request
import urllib.error
from urllib.parse import urlencode

BASE = "http://pengh5.moban8.top"
TIMEOUT = 15

# (方法, 路径, 期望说明, 允许的响应码)
OK_CODES = {200, 201, 400, 401, 403, 404, 422, 429}

CASES = [
    # ---------- 基础 ----------
    ("GET",  "/api/health",                                  "健康检查",              {200}),
    ("GET",  "/api/version",                                 "版本信息",              {200}),

    # ---------- H5 顾客端公开接口（本次新增/修复）----------
    ("GET",  "/api/publish/copywriting?device_id=1",         "H5聚合页-文案",         None),
    ("GET",  "/api/wifi/mobileconfig?device_code=TEST001",   "Wi-Fi mobileconfig",    None),
    ("GET",  "/api/copywriting/rotate?device_id=1",          "文案池换一批",          None),
    ("GET",  "/api/lottery/by-device?device_code=TEST001",   "抽奖-按设备",           None),
    ("GET",  "/api/review/config?device_id=1",               "点评配置",              None),
    ("GET",  "/api/review/draft?device_id=1&platform=dianping", "点评草稿",           None),
    ("GET",  "/api/contact/qq-config?device_id=1",           "QQ联系方式配置",        None),
    ("POST", "/api/funnel/record",                           "漏斗埋点",              None),
    ("POST", "/api/contact/qq-action",                       "QQ埋点",                None),
    ("POST", "/api/review/action",                           "点评行为埋点",          None),
    ("POST", "/api/lottery/draw",                            "抽奖执行",              None),

    # ---------- 鉴权接口（未登录应返回 401，而非 404/500）----------
    ("GET",  "/api/device/list",                             "设备列表-需鉴权",       {401}),
    ("GET",  "/api/merchant/list",                           "商户列表-需鉴权",       {401}),
    ("GET",  "/api/content/tasks",                           "内容任务-需鉴权",       {401}),
    ("GET",  "/api/statistics/dashboard",                    "统计看板-需鉴权",       {401}),
    ("GET",  "/api/stats/dashboard",                         "统计看板(兼容)-需鉴权", {401}),
    ("GET",  "/api/coupons",                                 "卡券(兼容)-需鉴权",     {401}),
    ("GET",  "/api/merchants",                               "商户(兼容)-需鉴权",     {401}),
    ("GET",  "/api/material/list",                           "素材列表-需鉴权",       {401}),
    ("GET",  "/api/system/users",                            "系统用户-需鉴权",       {401}),
    ("GET",  "/api/admin/dashboard",                         "管理看板-需鉴权",       {401}),

    # ---------- 管理端新增模块（需鉴权，验证路由已注册）----------
    ("GET",  "/api/lottery-admin/activities",                "抽奖活动-管理端",       None),
    ("GET",  "/api/copywriting/pool",                        "文案池-管理端",         None),
    ("GET",  "/api/review/admin-config",                     "点评配置-管理端",       None),
    ("GET",  "/api/funnel/stats",                            "漏斗统计",             None),
    ("GET",  "/api/group-buy-admin/items",                   "团购商品-管理端",       None),
    ("GET",  "/api/task/bundle/list",                        "任务宝-管理端",         None),
]

BODY = {
    "/api/funnel/record": {"device_id": 1, "step": "view", "block": "test", "action": "smoke"},
    "/api/contact/qq-action": {"device_id": 1, "action": "copy"},
    "/api/review/action": {"device_id": 1, "platform": "dianping", "action": "view"},
    "/api/lottery/draw": {"activity_id": 1, "user_hash": "smoke_test", "device_id": 1},
}


def request(method, path, body=None):
    url = BASE + path
    data = None
    headers = {"Accept": "application/json"}
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
            return resp.status, resp.read().decode("utf-8", "replace")[:220]
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")[:220]
    except Exception as e:
        return 0, f"{type(e).__name__}: {e}"


def main():
    passed, failed = [], []
    print("=" * 78)
    print(f"线上 API 冒烟测试  {BASE}")
    print("=" * 78)

    for method, path, desc, expect in CASES:
        body = BODY.get(path)
        code, snippet = request(method, path, body)
        snippet = " ".join(snippet.split())

        if code == 0:
            status, note = "FAIL", f"请求异常 {snippet}"
        elif expect is not None:
            if code in expect:
                status = "PASS"
                note = "符合预期"
            elif code == 404:
                status, note = "FAIL", "路由未注册(404)"
            elif code >= 500:
                status, note = "FAIL", f"服务端错误 {code}: {snippet}"
            else:
                status, note = "FAIL", f"期望{expect} 实际{code}: {snippet}"
        else:
            if code >= 500:
                status, note = "FAIL", f"服务端错误 {code}: {snippet}"
            elif code == 404:
                status, note = "FAIL", "路由未注册(404)"
            else:
                status = "PASS"
                note = f"{code}"

        line = f"[{status}] {method:4} {path:44} {desc}"
        print(f"{line}\n         -> {note}")
        (passed if status == "PASS" else failed).append((path, desc, note))

    print("=" * 78)
    print(f"通过 {len(passed)} / {len(CASES)}")
    if failed:
        print("\n失败项：")
        for path, desc, note in failed:
            print(f"  - {desc} ({path})\n    {note}")
    print("=" * 78)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
