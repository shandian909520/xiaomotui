# 回归测试问题日志

**测试执行时间:** 2026-02-13 08:40:11
**测试类型:** 回归测试
**问题总数:** 4个

---

## 问题汇总

| 问题ID | 关联功能点 | 严重程度 | 状态 | 问题描述 |
|--------|----------|---------|------|---------|
| ISSUE-007 | RT-003 | 严重 | 新发现 | 数据库表xmt_device_triggers不存在 |
| ISSUE-008 | RT-004 | 严重 | 未完全修复 | 用户未登录(认证问题) |
| ISSUE-009 | RT-005 | 严重 | 新发现 | 数据库表xmt_coupon_users不存在 |
| ISSUE-010 | RT-008 | 严重 | 新发现 | 数据库表xmt_operation_logs不存在 |

---

## 详细问题描述

### ISSUE-007: NFC触发记录表不存在

**问题ID:** ISSUE-007
**关联功能点:** RT-003 - NFC触发记录
**发现时间:** 2026-02-13 08:40:13
**严重程度:** 严重 (P1)
**状态:** 新发现

**问题描述:**
访问NFC触发记录接口时,数据库提示表 `xmt_device_triggers` 不存在。

**复现步骤:**
1. 登录管理员账号
2. 发送GET请求: `/api/merchant/nfc/trigger-records`
3. 携带有效的JWT token

**实际结果:**
```json
{
  "code": 400,
  "message": "获取触发记录失败: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'xiaomotui_dev.xmt_device_triggers' doesn't exist"
}
```

**预期结果:**
- HTTP状态码: 200
- 返回NFC触发记录列表(可能为空列表)

**错误分析:**
- 控制器方法 `Merchant::getTriggerRecords()` 已实现
- 但依赖的数据库表 `xmt_device_triggers` 不存在
- 需要执行数据库迁移创建该表

**修复建议:**
1. 检查 `api/database/migrations/` 目录中是否有对应的迁移文件
2. 执行数据库迁移: `php think migrate:run`
3. 如果没有迁移文件,需要创建表结构

---

### ISSUE-008: 内容任务列表认证问题

**问题ID:** ISSUE-008
**关联功能点:** RT-004 - 生成任务列表
**关联原问题:** ISSUE-004
**发现时间:** 2026-02-13 08:40:13
**严重程度:** 严重 (P1)
**状态:** 未完全修复

**问题描述:**
虽然请求携带了有效的JWT token,但接口仍然返回"用户未登录"错误。

**复现步骤:**
1. 登录管理员账号,获取JWT token
2. 发送GET请求: `/api/content/my`
3. Header中携带: `Authorization: Bearer {token}`

**实际结果:**
```json
{
  "code": 401,
  "message": "用户未登录",
  "error": "unauthorized"
}
```

**预期结果:**
- HTTP状态码: 200
- 返回内容任务列表

**错误分析:**
- JWT token是有效的(其他接口正常)
- Auth中间件可能对管理员账号(user_id=0)有特殊判断
- 或者Content控制器的认证逻辑有问题

**修复建议:**
1. 检查 `api/app/controller/Content.php` 中的 `my` 方法
2. 检查中间件如何处理user_id=0的情况
3. 确保从request中正确获取user_id

---

### ISSUE-009: 用户优惠券表不存在

**问题ID:** ISSUE-009
**关联功能点:** RT-005 - 用户领取记录
**关联原问题:** ISSUE-005
**发现时间:** 2026-02-13 08:40:14
**严重程度:** 严重 (P1)
**状态:** 部分修复(代码错误已修复,但数据库表缺失)

**问题描述:**
原问题 `$this->userId` 的代码错误已修复,但现在遇到数据库表 `xmt_coupon_users` 不存在的问题。

**复现步骤:**
1. 登录管理员账号
2. 发送GET请求: `/api/coupon/my`
3. 携带有效的JWT token

**实际结果:**
```json
{
  "code": 500,
  "msg": "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'xiaomotui_dev.xmt_coupon_users' doesn't exist"
}
```

**预期结果:**
- HTTP状态码: 200
- 返回用户领取的优惠券列表

**错误分析:**
- 控制器代码错误 `$this->userId` 已修复为 `$this->request->userId`
- 但CouponUser模型对应的表不存在
- 需要创建数据库表

**修复建议:**
1. 检查数据库迁移文件
2. 执行迁移创建 `xmt_coupon_users` 表
3. 或者创建缺失的迁移文件

---

### ISSUE-010: 操作日志表不存在

**问题ID:** ISSUE-010
**关联功能点:** RT-008 - 操作日志
**关联原问题:** ISSUE-006
**发现时间:** 2026-02-13 08:40:16
**严重程度:** 严重 (P1)
**状态:** 部分修复(中间件已修复,但数据库表缺失)

**问题描述:**
系统管理的中间件配置错误已修复,但操作日志接口依赖的数据库表不存在。

**复现步骤:**
1. 登录管理员账号
2. 发送GET请求: `/api/admin/operation-logs`
3. 携带有效的JWT token

**实际结果:**
```json
{
  "code": 400,
  "message": "获取操作日志失败：SQLSTATE[42S02]: Base table or view not found: 1146 Table 'xiaomotui_dev.xmt_operation_logs' doesn't exist"
}
```

**预期结果:**
- HTTP状态码: 200
- 返回操作日志列表

**错误分析:**
- 系统管理模块的中间件配置已修复(ISSUE-006)
- 系统用户管理(RT-006)和系统设置(RT-007)接口正常
- 但操作日志接口依赖的表不存在

**修复建议:**
1. 检查是否有操作日志表的迁移文件
2. 执行数据库迁移
3. 或创建缺失的表结构

---

## 问题分类统计

### 按严重程度统计
- **致命 (P0):** 0个
- **严重 (P1):** 4个
- **一般 (P2):** 0个
- **轻微 (P3):** 0个

### 按问题类型统计
- **数据库表缺失:** 3个 (ISSUE-007, ISSUE-009, ISSUE-010)
- **认证逻辑问题:** 1个 (ISSUE-008)

---

## 修复进度跟踪

| 原问题ID | 问题描述 | 修复状态 | 剩余工作 |
|---------|---------|---------|---------|
| ISSUE-001 | 仪表盘缺少商家ID | ✅ 完全修复 | 无 |
| ISSUE-002 | Redis连接失败 | ✅ 完全修复 | 无 |
| ISSUE-003 | NFC触发记录方法不存在 | ⚠️ 部分修复 | 需创建数据库表 |
| ISSUE-004 | RequestService用户ID传递 | ⚠️ 部分修复 | 仍存在认证问题 |
| ISSUE-005 | Coupon控制器代码错误 | ⚠️ 部分修复 | 需创建数据库表 |
| ISSUE-006 | 系统管理中间件错误 | ⚠️ 部分修复 | 操作日志表缺失 |

---

## 下一步行动计划

### 立即执行 (P0)
无

### 尽快执行 (P1)
1. **执行数据库迁移**
   - 预计时间: 15分钟
   - 影响问题: ISSUE-007, ISSUE-009, ISSUE-010
   - 执行命令: `php think migrate:run`

2. **修复内容任务认证逻辑**
   - 预计时间: 30分钟
   - 影响问题: ISSUE-008
   - 需要检查Auth中间件和Content控制器

### 计划执行 (P2)
无

---

**日志更新时间:** 2026-02-13 08:40:16
**下次更新:** 待问题修复后
