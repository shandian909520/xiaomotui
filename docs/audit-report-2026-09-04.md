# 代码审核报告 — 对标实现方案 v2

> 时间：2026-09-04 19:45
> 基线：方案 v2 + 用户方案 `代码改进方案_对标视频需求.md`
> 命令：`git diff --stat HEAD` 显示 156 文件 +14792/-4682 行

---

## 一、用户已实现清单（✅ 70%）

### 数据层（100%）
- ✅ `api/database/migrations/20260904000001_create_copywriting_pool_table.sql` —— 文案池
- ✅ `20260904000002_create_review_actions_table.sql` —— 点评埋点
- ✅ `20260904000003_create_groupbuy_items_table.sql` —— 团购商品
- ✅ `20260904000004_create_lottery_tables.sql` —— 抽奖 3 表（活动/奖品/记录）
- ✅ `20260904000005_add_qq_contact_fields.sql` —— QQ 联系字段

### Service 层（95%）
- ✅ `AggregationPageService` —— 6 个 buildBlock 全有（wifi/publish/groupbuy/review/contact/lottery）+ highlight + qq
- ✅ `LotteryService` —— getActiveByDevice / drawLottery / myRecords
- ✅ `GroupBuyService` —— 7 个新方法（items CRUD + itemRedirect + parseGroupBuyConfig 等）
- ✅ `WifiService` 789 行 —— 已就绪 iOS mobileconfig / Android WIFI: URI
- ✅ `ContactService` —— 已扩 qq_contact_config
- ✅ `NfcService` —— 已调用 WifiService + AggregationPageService

### Controller 层（95%）
- ✅ `NfcController` 加 196 行：getAggregationPage / getGroupBuyItems / configureGroupBuy / getGroupBuyStatistics
- ✅ `LotteryController` —— getLotteryByDevice / draw / myRecords
- ✅ `LotteryAdminController` —— 10 个活动/奖品/记录方法
- ✅ `ReviewController` —— getReviewConfig / getReviewDraft / recordReviewAction

### H5 模板（100%）
- ✅ `api/public/h5/aggregate.html` —— 按视频风格手写 #FF6B35 + 圆角 12px + shop-card/wifi-card 等
- ✅ `api/public/h5/lottery.html` + `lottery-share.html` —— 抽奖页

### uni-app 移动端（40%）
- ✅ `uni-app/src/components/hub/{hub-header,action-card,reward-panel,env-detect}.vue`
- ✅ `uni-app/src/pages/hub/index.vue` —— 任务中心（TaskBundle）
- ❌ 未调 `/api/nfc/aggregation-page` 聚合页接口
- ❌ 缺 `uni-app/src/api/modules/{nfc-aggregation,lottery,review,groupbuy}.js`

### admin 后台（90%）
- ✅ `admin/src/views/lottery/{ActivityList,PrizeList,RecordList}.vue` —— 抽奖后台三件套
- ✅ `admin/src/views/groupbuy/ItemList.vue` —— 团购商品
- ✅ `admin/src/views/activity/SceneConfigMatrix.vue` 1088 行
- ✅ `admin/src/views/dashboard/index.vue` —— 重写 1171 行
- ✅ `admin/src/router/index.js` —— lottery/groupbuy/group-buy 路径已注册
- ❌ `admin/src/views/nfc/triggers.vue` 只改 7 行（B1 没做）
- ❌ **找不到 NfcDevice 设备配置入口**（admin/views/ 下 0 个引用）
- ❌ promo-campaign/detail / materials / ai/staff / publish 没动

---

## 二、缺口清单（❌ 30%）

### 🔴 P0 阻塞（不修不能跑）
| # | 缺口 | 影响 |
|---|---|---|
| **R1** | **api/route/app.php 0 条新接口路由** | Lottery/Review/GroupBuyItem/AggregationPage/CopywritingPool 全部 404 |
| **R2** | uni-app 没调聚合页接口 | uni-app 端 NFC 触发后只跳 hub 任务中心，不展示视频那种聚合页 |

### 🟡 P1 重要
| # | 缺口 | 影响 |
|---|---|---|
| **D1** | 设计 token 还是老紫色 #834eff，#FF6B35 没进 variables.scss | 商家后台各页风格不统一，与 aggregate.html 不一致 |
| **D2** | admin 商家后台 6 页未改版 | B2 promo-campaign/detail / B3 materials / B4 ai/staff / B5 publish / B6 coupon(users) |
| **D3** | nfc/triggers.vue 触发记录页未卡片化 | 仍是 217 行表格风格 |

### 🟢 P2 可后做
| # | 缺口 | 影响 |
|---|---|---|
| **P1** | CopywritingPool Service/Controller 缺失 | Flyway 表建了但无 CRUD/rotate API |
| **P2** | ReviewController 路由未注册 | 草稿生成 + 跳转 + 埋点跑不通 |
| **P3** | ContactService 未暴露 QQ QR 配置 API | 聚合页 buildContactBlock 调不到数据 |
| **P4** | 缺漏斗埋点 funnel_event 表 + FunnelService | 数据看板缺漏斗图 |
| **P5** | 缺 NfcDevice 设备配置 admin 页 | 商家配 Wi-Fi/抽奖/团购/点评配置没入口 |

---

## 三、Agent 并行分工

按缺口依赖关系，3 个并行 agent + 后续轮：

### Agent A — 路由层 + 移动端联通（**P0 阻塞**）
> 必须最先完成，阻塞 Agent C 的接口调用

- 注册 api/route/app.php 中的路由（20+ 条）
- 新增 uni-app/src/api/modules/{nfc-aggregation,lottery,review,groupbuy,copywriting}.js
- 写一个 Python E2E 脚本验证 aggregate.html 全链路
- 不动 admin / 不动 H5 / 不动设计 token

### Agent B — 设计 token + admin 商家后台 6 页改版
> 设计系统地基 + B 端视觉对齐视频风格

- admin/src/styles/variables.scss 推 #FF6B35/圆角/阴影
- 新建 5 个组件：SheetPanel / PlatformGrid / CopywriterCompare / LotteryWheel / LongPressSave
- 改 nfc/triggers.vue / promo-campaign/detail.vue / materials/index.vue / ai/staff.vue / publish/index.vue
- 不动后端 / 不动 Flyway / 不动 H5

### Agent C — 业务闭环（CopywritingPool + Review + Contact）
> 补齐三个新业务 Service/Controller 完整化（路由由 Agent A 注册后即可联通）

- 新建 CopywritingPool Controller/Service/Model
- ReviewController 完整化（生成草稿 + 跳转 + 埋点 + 商家配置）
- ContactService 暴露 QQ QR 配置 API
- 写商家后台文案池管理页 + 点评配置页

### 后续轮（Agent D、E）
- D：NfcDevice 设备配置 admin 页 + 漏斗埋点 + 数据看板漏斗图
- E：真实设备 E2E + 灰度方案

---

## 四、风险

1. **R1 路由缺失会让所有 H5/uni-app 联调失败** —— 必须 Agent A 优先
2. **uni-app 包大小**：每加一个 api 模块要 < 5KB，否则影响首屏
3. **#FF6B35 改 SCSS token 可能影响其他 admin 页面** —— 灰度发布
4. **Flyway 脚本是 09-04 新增，但库可能还没跑过** —— 上线前 `php think migrate:run` 验证

---

## 五、变更记录

- 2026-09-04 19:45 —— 审核报告 v1