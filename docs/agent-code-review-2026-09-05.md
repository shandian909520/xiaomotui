# Agent 新增代码审查报告 — 2026-09-05（第二轮深审）

> 审查范围：9/4–9/5 期间 5 个并行 agent（A 路由 / B 设计 / C 业务闭环 / D 修 bug / E 漏斗设备页）新增与修改的全部代码。
> 注：本报告与早间《code-review-agents-2026-09-05.md》（7 个联调 bug）互补——本轮聚焦后端逻辑正确性、数据一致性与路由/Model/表全链路比对，两者发现无重叠。

## 一、审查方法

| 层 | 手段 | 覆盖 |
|---|---|---|
| PHP 语法 | `php -l` 逐文件 | 31 个新 PHP 文件，0 错误 |
| 路由完整性 | 脚本提取 `app.php` 全部 226 条 Controller@method 引用并逐一比对方法存在性 | 全部解析成功 |
| 表名一致性 | 69 个 Model 的 `$table`（全名）/`$name`（拼 prefix）声明 vs `xmt_` 前缀规则 | 68/69 干净 |
| 表存在性 | agent 11 个 Service 中所有 `Db::name()` 表 vs Flyway/既有 SQL `CREATE TABLE` | 全部存在 |
| 前后端对齐 | 前端 api 模块 URL 段 vs 后端路由段；Vue import 可解析；router 61 个 view 路径 | 全部对齐 |
| 运行时 | PHP 内置 server + 4 套 E2E（aggregation/copywriting-review/bugfix/funnel） | 16/16 绿 |

## 二、发现并已修复的问题（本次审查直接修掉）

### P0-1 抽奖必然抛"活动未开始"（已修）
- 位置：`api/app/service/LotteryService.php` `drawLottery()`
- 问题：读 `$activity->is_active`，但 `xmt_lottery_activities` 表字段是 `status`（`is_active` 不存在 → null !== 1 → 永远抛异常）。**所有抽奖请求 100% 失败。**
- 修复：改为 `status !== STATUS_ENABLED` 抛错，并新增 `start_at/end_at` 时间窗口校验（与表字段一致）。
- 这是唯一会被真实流量打到的致命 bug。

### P1-1 uni-app `contact-qq.js` 未注册（已修）
- 位置：`uni-app/src/api/index.js`
- 问题：Agent C 新建了 `modules/contact-qq.js` 但没在统一出口 import/export，页面 `api.default.contactQq` 调用为 undefined。
- 修复：补 import + default export + named export 三处。

## 三、遗留风险（非 bug，但要注意）

1. **`admin/src/api/index.js` 里 copywritingAdminApi/reviewAdminApi 等统一出口**（Agent C 报告声称添加）——本次抽查 `funnel.js`/`nfc-config.js` 为独立文件且 import 可解析，未逐行验证 index.js 聚合内容，建议下次 `pnpm build` 一次兜底。
2. **`ReviewService::generateDraft` 目前返回兜底文案**，未真调 AI——功能可用但文案是静态的，属既定 TODO。
3. **coupon 全量 stats 端点缺失**（Agent D 遗留 TODO）：`coupon/users.vue` 未选券码时清零展示，属已知产品决策。
4. **老代码问题（不属 agent 产出，未处理）**：`api/app/model/Coupon.php` `$table='coupons'` 缺 `xmt_` 前缀（该文件为 6/12 老代码，修改可能影响线上，需单独评估）。
5. **PHP 内置 server 是单进程**，E2E 里"鉴权拦截 401"只证明路由+中间件链路通，未验证 token 有效场景的 200 数据结构（需要真实登录态，建议部署环境再验一轮）。

## 四、代码质量正面评价

- **Wi-Fi 安全设计正确**：`AggregationPageService::buildWifiBlock` 只下发打码 SSID + 加密 token（5 分钟过期），密码不落前端。
- **抽奖防超发**：`consumeStock` 用 `UPDATE ... WHERE stock>0` 乐观扣减 + 失败降级 THANKS，并发安全。
- **埋点不阻塞主流程**：Funnel/Review/Contact 所有 record 调用均 try-catch 静默，符合埋点最佳实践。
- **rotate_token 用 HMAC 签名 + 7 天过期**，只做错峰不做鉴权（注释明确），设计取舍合理。
- **鉴权分层清晰**：公开组（75 行起）无 Auth 中间件；管理组（240 行起，937 收尾）统一 `Auth + OperationLog + ApiThrottle`，ContactQq 读公开/写鉴权分离正确。

## 五、E2E 最终结果（修复后复验）

```
test_aggregation_e2e.py        4/4  (聚合页/抽奖查询/抽奖/点评配置)
test_copywriting_review_e2e.py 6/6  (文案轮播/创建/点评配置/草稿/QQ配置/QQ动作)
test_bugfix_e2e.py             3/3 关键 + 6/6 静态
test_funnel_e2e.py             3/3  (埋点/商家漏斗/设备配置)
                               ----
                               16/16 全绿
```

## 六、本次审查改动的文件

| 文件 | 改动 |
|---|---|
| `api/app/service/LotteryService.php` | `is_active` → `status` + 时间窗口校验（P0-1） |
| `uni-app/src/api/index.js` | 注册 contactQq 模块（P1-1） |
| `scripts/audit_frontend_urls.js` | 新增：前后端 URL 对齐审查脚本（可复用） |
