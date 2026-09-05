# 代码审查报告 — Agents 新增代码（2026-09-05）

> 审查范围：5 个并行 agent（A 路由层 / B 设计token+改版 / C 业务闭环 / D 修bug / E 漏斗+设备页）新增/修改的全部代码。
> 方法：静态审查 + 起 PHP 内置服务跑真实 HTTP 回归 + 直连 MySQL 验证落库。

## 一、总评

Agent 产出整体质量不错：命名规范、注释清晰、错误处理完整、有兜底设计。**但联调层有 7 个真 bug**（3 个 P0 / 4 个 P1），全部是「前后端各写各的，路径/字段对不上」类问题——单测各自能过，串起来就断。本次审查已全部修复并回归验证。

## 二、发现并已修复的问题

### P0-1 前端下划线路由 vs 后端斜杠路由（4 个模块全断）
- **现象**：`admin/src/api/index.js` 调 `/copywriting_admin/*`、`/lottery_admin/*`、`/group_buy_admin/*`，后端注册的是 `/copywriting/admin/*` 等（斜杠）。请求全部落到 API 兜底首页（返回 200 + version 信息），前端拿不到数据还**不报错**。
- **波及页面**：文案池 PoolList、抽奖 ActivityList/PrizeList/RecordList、团购 ItemList、点评 ConfigList —— 全部新增页面。
- **修复**：统一改为后端实际注册的斜杠路径。

### P0-2 `nfcApi.getDevices` 返回的是门店而非 NFC 设备
- **现象**：公开组 `/api/nfc/devices` 映射到 `AdminCompat/stores`（门店列表）。4 个新页面用它做「设备下拉框」，用户选到的是门店名，`device_id` 全是错的。
- **修复**：改指鉴权组真设备列表 `/api/merchant/nfc/devices`（`Nfc@deviceList`）。

### P0-3 H5 聚合页两个核心功能断链
- `/api/publish/copywriting`（换一批文案）→ 无此路由，落到兜底。**修复**：新增 `Nfc@getPublishCopywriting` 转发 `CopywritingPoolService::rotateCopywriting`。
- `/api/wifi/mobileconfig`（iOS 一键连 Wi-Fi）→ 无此路由。**修复**：新增 `Nfc@getWifiMobileconfig`，走 `WifiService::generateWifiConfig($device,'ios')` 生成描述文件并 302。

### P1-1 Wi-Fi 密码永远为空（历史遗留，agent 未踩到但被本次审查揪出）
- **现象**：`NfcDevice::getWifiPasswordAttr` 出于安全永远返回空串，但 `WifiService` 8 处直接读 `$device->wifi_password` → 生成的 mobileconfig 密码恒空、Android WIFI:URI 恒空 → **Wi-Fi 一键连功能从未真正可用**。
- **修复**：WifiService 内需要明文的地方全部改走 `getDecryptedWifiPassword()`（显式解密）。

### P1-2 漏斗埋点 device_id 恒为 0（归因失效）
- **现象**：`hub/index.vue::trackFunnel` 硬编码 `device_id: 0`，而 `TaskEngineService::getDetail` 响应里根本没有 device_id 字段 → 漏斗数据全是孤儿，按设备/商家聚合全部为空。
- **修复**：getDetail 的 instance 增加 `device_id` 字段；hub 埋点优先取 `detail.instance.device_id`。已验证落库 `device_id=1, merchant_id=1` 归因正确。

### P1-3 `PUT /api/contact/qq-config` 挂在公开组（未鉴权可写）
- **现象**：Agent C 把写接口放进了免认证路由组，任何人可改任意设备的 QQ 配置。
- **修复**：迁移到鉴权组 `PUT /api/contact/admin/qq-config`，前端同步更新。

### P1-4 `password_set` 恒为 false
- **现象**：`NfcConfig@extractWifiBlock` 用 `!empty($device->wifi_password)` 判断，但访问器返回空串 → 商家后台永远显示「未设置密码」。
- **修复**：改用 `$device->getData('wifi_password')` 读原始密文判断。

### 其他修复
- `lotteryAdminApi.prizes` 路径修正（`/lottery/admin/prizes?activity_id=`，后端无 `/activities/{id}/prizes` 形态）。
- `reviewApi.saveConfig` 修正为 `/review/admin/config`。
- 补跑 7 个 Flyway 到本地库：copywriting_pool / review_actions / review_draft_templates / groupbuy_items / lottery×3 / funnel_event / nfc_devices 扩展列（qq/wechat_contact_config、shop_owner_qr、ai_copy_enabled，其中 2 列此前已建，报 Duplicate column 属幂等跳过）。

## 三、审查确认没问题的部分

| 模块 | 结论 |
|---|---|
| CopywritingPool 轮播 | HMAC rotate_token 防重、fallback 兜底、used_count 异步累加，设计合理 |
| FunnelEvent | 14 个 step 枚举 + 索引设计好，写失败不阻塞主流程 |
| NfcConfig 3-tab | 越权校验 checkDeviceAccess 完整，Wi-Fi 密码不回传明文 |
| ContactQq | 字段命名与 AggregationPageService 对齐，兼容旧数据 |
| publish 平台归一（Agent D）| 白名单+折叠策略对，但 WECHAT_*→XIAOHONGSHU 是临时占位（见遗留） |
| 5 个 UI 组件 / 6 页改版 | 引用一致，无断链 |

## 四、回归结果（真实 HTTP + MySQL）

```
/api/publish/copywriting     200 文案+rotate_token ✓（换一批链路恢复）
/api/copywriting/rotate      200 ✓
/api/nfc/aggregation-page    200 真实设备 NFC-COUNTER-001 六区块返回 ✓
/api/wifi/mobileconfig       302 → .mobileconfig 下载 ✓
/api/funnel/record           200 recorded=true,落库 device_id/merchant_id 归因正确 ✓
/api/lottery/by-device       200 ✓
鉴权面: copywriting/lottery/groupbuy/review/funnel/nfc-config 全部 401 拦截 ✓
公开面: contact GET、review GET、funnel POST 全部正常 ✓
php -l 8 个核心文件全过 ✓
```

## 五、遗留事项（不阻塞，建议排期）

1. **WECHAT_SHIPINHAO/WECHAT_FRIEND → XIAOHONGSHU 折叠是临时方案**：后端实装视频号/朋友圈通道后，要回来改 `normalizePlatformKeys` 映射，否则用户选「视频号」实际发到小红书。
2. **生产库 Flyway 未跑**：本地已验证 7 个迁移脚本可执行，部署时记得走正式迁移流程（脚本本身幂等性弱，重复执行会报 Duplicate column，建议加 IF NOT EXISTS）。
3. **mobileconfig 走 HTTP 明文**：iOS 对描述文件来源校验严格，生产必须 HTTPS + 备案域名，否则「不安全」红字 + 安装受限。
4. **coupon/users.vue 全量 stats 端点缺失**：后端只有按单券码统计，未选券码时前端显式清零（Agent D 的临时处理）。
5. **`/api/contact/qq-config` PUT 移除后**，未匹配路由会落 ThinkPHP PathInfo 兜底报 `controller not exists` 500 而非 404——无功能影响，介意的话可以加 miss 路由统一 404。

## 六、本次审查改动的文件

| 文件 | 改动 |
|---|---|
| `admin/src/api/index.js` | 4 组路由前缀修正 + prizes/review/nfcApi 路径修正 |
| `api/route/app.php` | PUT qq-config 迁鉴权组；新增 publish/copywriting、wifi/mobileconfig 公开路由 |
| `api/app/controller/Nfc.php` | +getPublishCopywriting、+getWifiMobileconfig |
| `api/app/service/WifiService.php` | 8 处密码读取改显式解密 |
| `api/app/service/TaskEngineService.php` | getDetail 响应补 device_id |
| `api/app/controller/NfcConfig.php` | password_set 改读原始密文 |
| `uni-app/src/pages/hub/index.vue` | trackFunnel 归因 device_id |
| `uni-app/src/api/modules/contact-qq.js` | saveQqConfig 路径更新 |
