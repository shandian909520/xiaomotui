# 小魔推系统对标实现方案 —— 「人气好店/NFC 碰一碰」视频版

> **基线视频**：3:52，iPhone 录屏，HEVC 1080×1920，单声道静音
> **视角**：以视频为产品最终版，对标实现到 `D:\xiaomotui`（小魔推）
> **不再做**：挑竞品毛病、风险扫描、合规审查
> **要做的事**：把视频里出现的每个交互/页面/能力，复刻到我们系统里

---

## 0. 视频画面速览（按时间轴）

| 时间 | 场景 | 关键画面 |
|---|---|---|
| 0:00–0:08 | NFC 触发 | iOS 弹窗「网站 NFC 标签 在 Safari 浏览器中打开 121.40.163.187」 |
| 0:08–0:25 | 任务中心 | 商家主页：logo + 横幅 + 4 个 tab（打卡/视频/点评/AI 文案） |
| 0:25–0:55 | AI 文案工作台 | 视频解析 → AI 文案 5 套 → 一键应用到视频/图文 |
| 0:55–1:20 | 多平台发布 | 抖音/快手/小红书图文/小红书视频/朋友圈图文/朋友圈视频/视频号 7 通道 |
| 1:20–1:45 | 私域加粉 | 加店长微信/QQ/企微 三通道，含长按保存二维码 |
| 1:45–2:10 | Wi-Fi 一键连 | Wi-Fi 名称 + 密码 + 复制按钮 |
| 2:10–2:30 | 团购券 | 美团/抖音团购券弹窗 + 跳转 |
| 2:30–3:00 | 多平台点评 | 抖音/高德/百度/美团/大众 5 平台引导 |
| 3:00–3:30 | 抽奖（端午转盘） | 6 宫格转盘 + 中奖弹窗 + 留资 |
| 3:30–3:52 | 复刻任务完成页 | 任务清单 + 奖励发放 + 分享 |

---

## 1. 我们系统现有能力盘点（基线）

> 后端 `/d/xiaomotui/api`：55 个 controller + 80 个 service
> 前端 `/d/xiaomotui/admin/src/views`：32 个业务页面

| 模块 | 已实现 | 对应文件 |
|---|---|---|
| NFC 触发 | `NfcController` + `NfcService` + `views/nfc/triggers.vue` | api/app/controller/Nfc.php, admin/src/views/nfc/triggers.vue |
| 营销活动 | `PromoCampaignController/Service` + `Material/Template/Variant/Stats` 4 套 | views/promo-campaign/* |
| 多平台发布 | `PlatformController` + `PublishService` + `PlatformOAuthService` + `DouyinService` | views/publish/index.vue (710 行) |
| 视频解析/剪辑 | `ClipProjectController` + `JianyingVideoService` + `views/video/*` | api/app/service/JianyingVideoService.php |
| AI 文案 | `AiContentController/Service` + `views/ai/*` | api/app/service/AiContentService.php |
| 素材库 | `MaterialController/Service` + `MaterialImportService` + `views/materials/*` | views/materials/index.vue |
| 优惠券 | `CouponController` + `GroupBuyService` + `RedpacketActivityController` | views/coupon/index.vue |
| 设备监控 | `DeviceManageController` + `AlertController` + `views/device/*` | views/device/index.vue |
| 任务引擎 | `TaskBundleController/Service` + `TaskInstanceController` + `TaskEngineService` + `TaskVerifyService` | views/task/BundleList.vue, views/tasks/index.vue |
| 内容审核 | `ContentController` + `ContentAuditService` + `ContentModerationService` | views/content/audit.vue |
| 数据看板 | `DashboardController/Service` + `PromoStatsService` + `EmployeeStatsService` | views/dashboard/index.vue, views/promo-stats/index.vue |
| 触达通知 | `NotificationController/Service` + `FeatureNotificationService` | views/notifications/* |
| AI 员工 | `AdminAiController` + `AiStaffController` + `views/ai/staff.vue` | views/ai/StaffRoles.vue |

**结论**：12 大模块全部已实现，主要差在「**流程串联 + 视觉设计 + C 端 H5 模板**」。这次改造重点是**把现有零件拼成视频里那种一体化体验**。

---

## 2. 改造方案（按视频场景 → 系统改造动作）

### 2.1 用户端（C 端 H5）—— 复刻视频 0:00–3:52 的完整链路

#### 改造目标
> 视频里 C 端只有 1 个 URL（`121.40.163.187`），进去就是商家个性化主页。我们要复刻同等体验。

#### 新增文件
```
api/app/controller/H5/NfcLanding.php         # NFC 落地页入口
api/app/controller/H5/TaskCenter.php         # 任务中心（H5 壳）
api/app/controller/H5/WifiConnect.php        # Wi-Fi 弹窗
api/app/controller/H5/Redpacket.php          # 抽奖转盘
admin/src/mobile/                            # H5 模板目录（移动端 Vue3 组件库）
  ├── NfcLanding.vue
  ├── TaskCenter.vue
  ├── AiCopywriter.vue
  ├── PlatformPicker.vue
  ├── WifiSheet.vue
  ├── CouponSheet.vue
  ├── ReviewPicker.vue
  ├── LotteryWheel.vue
  └── components/
      ├── SheetPanel.vue                     # 视频里那种底部抽屉
      ├── LongPressSave.vue                  # 长按保存图片
      └── QrCode.vue                         # 二维码 + 复制微信号
```

#### 改造点
| # | 改造项 | 说明 | 涉及代码 |
|---|---|---|---|
| C1 | **NFC 落地页 H5 模板** | 视频里 iOS 弹窗后跳到一个商家个性化页（logo/横幅/4 个 tab），我们要建一套商家可配置模板 | 新建 `admin/src/mobile/NfcLanding.vue` |
| C2 | **任务中心骨架** | 视频里 4 个 tab（打卡/视频/点评/AI 文案），下方为任务卡片流 | 复用 `TaskBundleService` 输出多 tab 数据 |
| C3 | **AI 文案工作台 H5** | 视频里把视频贴进去 → 5 套候选文案 → 一键套用到视频/图文 | 复用 `AiContentService.generate()` |
| C4 | **平台选择器** | 视频里 7 通道矩阵图标（抖音/快手/小红书图文/小红书视频/朋友圈图文/朋友圈视频/视频号），全选/部分选 | 新建 `mobile/PlatformPicker.vue` |
| C5 | **Wi-Fi 弹窗** | 视频里底部抽屉，标题 + Wi-Fi 名 + 密码 + 复制按钮 | 新建 `mobile/WifiSheet.vue` + 复用 `NfcService` |
| C6 | **团购券 Sheet** | 视频里点团购 → 弹美团/抖音券 → 一键跳转 | 复用 `GroupBuyService.listByMerchant()` |
| C7 | **多平台点评 Sheet** | 视频里点点评 → 5 平台图标 → 跳官方 App/网页 | 新建 `mobile/ReviewPicker.vue` + 复用 `PlatformService` |
| C8 | **抽奖转盘** | 视频里 6 宫格转盘，旋转 + 中奖弹窗 + 留资 | 复用 `RedpacketActivityController.spin()` |
| C9 | **私域加粉 Sheet** | 视频里点加微 → 弹微信/QQ/企微 + 长按保存二维码 | 新建 `mobile/QrCode.vue` |
| C10 | **任务完成页** | 视频最后展示任务完成清单 + 奖励发放 | 复用 `TaskInstanceService.complete()` |

---

### 2.2 商家后台（B 端）—— 复刻视频里的「活动配置 + AI 文案 + 发布数据」链路

#### 改造目标
> 视频里商家后台主要是配置抽奖/优惠券/团购/分享文案、AI 二次创作视频、多平台发布、看数据。我们 B 端已有，但**视觉风格、流程串联、字段命名**要对齐视频。

#### 改造点
| # | 改造项 | 说明 | 涉及文件 |
|---|---|---|---|
| B1 | **NFC 触发器页改版** | 视频里是卡片式 + 二维码预览 + 复制链接。我们的 `nfc/triggers.vue` 217 行太朴素，要重做 | `admin/src/views/nfc/triggers.vue` |
| B2 | **活动详情页改版** | 视频里商家改抽奖奖品、文案、Wi-Fi 名/密码、团购券，全部在一个详情页内。我们的 575 行列表页，要新增 `detail.vue` 已经存在但要按视频重做字段顺序和分组 | `admin/src/views/promo-campaign/detail.vue` |
| B3 | **素材库改版** | 视频里素材库是网格瀑布流，支持视频/图文混排 + 一键「拿去生成文案」。我们的 `materials/index.vue` 要对标 | `admin/src/views/materials/index.vue` |
| B4 | **AI 文案工作台改版** | 视频里是 5 套文案左右滑动对比 + 一键应用。我们的 `ai/staff.vue` 是表单式，要重做 | `admin/src/views/ai/staff.vue` + 新建 `ai/Copywriter.vue` |
| B5 | **多平台发布页改版** | 视频里 7 通道矩阵勾选 + 进度条轮播。我们的 `publish/index.vue` 710 行已有，但 UI 要对标视频卡片化 | `admin/src/views/publish/index.vue` |
| B6 | **数据看板改版** | 视频结尾有发布数/参与数/转化漏斗。我们的 `dashboard/index.vue` + `promo-stats/index.vue` 已有，对齐字段 | `admin/src/views/dashboard/index.vue` |

#### 字段对齐（视频里出现的所有字段 → 我们数据库/API）

| 视频字段 | 表 | 字段 | 状态 |
|---|---|---|---|
| 商家 logo | merchant | logo_url | ✅ 已有 |
| 商家横幅 | merchant | banner_url | ✅ 已有 |
| NFC 标签 URL | nfc_label | nfc_url | ✅ 已有 |
| Wi-Fi 名称 | nfc_label | wifi_ssid | ⚠️ 需新增 |
| Wi-Fi 密码 | nfc_label | wifi_password | ⚠️ 需新增 |
| Wi-Fi 加密方式 | nfc_label | wifi_security | ⚠️ 需新增 |
| 抖音团购券 | group_buy | douyin_url | ⚠️ 需新增 |
| 美团团购券 | group_buy | meituan_url | ⚠️ 需新增 |
| 加微二维码 | nfc_label | wechat_qr / qq_qr / wework_qr | ⚠️ 需补 |
| 抽奖活动 ID | redpacket_activity | activity_id | ✅ 已有 |
| 6 宫格奖品 | redpacket_prize | name / image / stock / probability | ✅ 已有 |
| 任务清单 | task_bundle | tasks | ✅ 已有 |
| AI 文案 5 套 | ai_copywriting | variants | ✅ 已有 |
| 多平台账号 | publish_account | platform / account_id / auth_token | ✅ 已有 |
| 点评跳转链接 | review_link | platform / url | ⚠️ 需新增 |
| 任务奖励 | task_reward | type / amount / stock | ✅ 已有 |

**Flyway 增量**：`api/database/migrations/2026_09_04_nfc_extend.sql`
```sql
ALTER TABLE nfc_label
  ADD COLUMN wifi_ssid VARCHAR(64) DEFAULT NULL COMMENT 'Wi-Fi 名',
  ADD COLUMN wifi_password VARCHAR(128) DEFAULT NULL COMMENT 'Wi-Fi 密码',
  ADD COLUMN wifi_security ENUM('WPA','WEP','nopass') DEFAULT 'WPA',
  ADD COLUMN wechat_qr VARCHAR(512) DEFAULT NULL,
  ADD COLUMN qq_qr VARCHAR(512) DEFAULT NULL,
  ADD COLUMN wework_qr VARCHAR(512) DEFAULT NULL;

CREATE TABLE review_link (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  merchant_id BIGINT NOT NULL,
  platform VARCHAR(32) NOT NULL COMMENT 'douyin|gaode|baidu|meituan|dianping',
  url VARCHAR(512) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_merchant (merchant_id)
) ENGINE=InnoDB COMMENT '商家多平台点评跳转链接';

ALTER TABLE group_buy
  ADD COLUMN douyin_url VARCHAR(512) DEFAULT NULL,
  ADD COLUMN meituan_url VARCHAR(512) DEFAULT NULL;
```

---

### 2.3 后端改造 —— 流程串联 + AI + 多平台 worker

#### 改造点
| # | 改造项 | 说明 | 涉及文件 |
|---|---|---|---|
| S1 | **任务串联引擎** | 视频里 C 端进店 → 触发「打卡+AI 文案+视频发布+加微+点评」多任务并行。我们已有 `TaskBundleService`，但**任务依赖关系/并行调度**要补 | `api/app/service/TaskEngineService.php` 加 `dispatchBundle()` |
| S2 | **平台适配器抽象** | 视频里 7 通道（抖音/快手/小红书图文/小红书视频/朋友圈图文/朋友圈视频/视频号），每条要封装成本地 adapter，统一接口 | 新建 `api/app/service/Platform/{DouyinAdapter,KuaishouAdapter,XiaohongshuImageAdapter,XiaohongshuVideoAdapter,WechatMomentImageAdapter,WechatMomentVideoAdapter,ShipinhaoAdapter}.php` |
| S3 | **平台调度器** | 用户提交发布 → 调度器拆任务到 7 个 worker → 每个 worker 异步执行 → 回写状态 | 新建 `api/app/service/Platform/Dispatcher.php` + `api/app/job/PlatformPublishJob.php` |
| S4 | **AI 文案服务升级** | 视频里是「视频 → 5 套候选文案 → 一键套用」。要新增：视频解析（已实现）→ 文案风格枚举（小吃/火锅/奶茶/中餐/酒店）→ 合规词过滤 | 升级 `api/app/service/AiContentService.php` |
| S5 | **Wi-Fi NDEF 写入** | 视频里 iPhone 碰卡直接弹窗连 Wi-Fi（前提是 NDEF 写了 `WIFI:T:WPA;S:ssid;P:password;;`）。我们后台要能生成这个 NDEF 二进制 | 新建 `api/app/service/Nfc/NdefWifiEncoder.php` |
| S6 | **抽奖转盘接口** | 视频里转盘旋转 + 中奖记录 + 留资。已有 `RedpacketActivityService`，补 `spin()` 防止并发超中奖 | `api/app/service/RedpacketActivityService.php` 加乐观锁 |
| S7 | **点评链接服务** | 视频里 5 平台点评跳转。补 model/service | 新建 `api/app/controller/ReviewLink.php` + `api/app/service/ReviewLinkService.php` |
| S8 | **数据回流** | 视频最后展示漏斗：曝光 → 进店 → 任务参与 → 任务完成 → 复购。新增 `funnel_event` 表 + 埋点 SDK | `api/app/service/FunnelService.php` + `api/app/job/FunnelEventJob.php` |

#### 新增表（Flyway）
```sql
-- 2026_09_04_funnel_event.sql
CREATE TABLE funnel_event (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  merchant_id BIGINT NOT NULL,
  user_id_hash CHAR(32) NOT NULL COMMENT '脱敏 user id',
  event VARCHAR(32) NOT NULL COMMENT 'impression|enter|task_start|task_done|reward|review',
  task_code VARCHAR(64) DEFAULT NULL,
  reward_type VARCHAR(32) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_merchant_event (merchant_id, event, created_at)
) ENGINE=InnoDB COMMENT 'C 端用户行为漏斗';

-- 2026_09_04_platform_publish.sql
CREATE TABLE platform_publish_task (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  task_id BIGINT NOT NULL COMMENT 'task_instance.id',
  platform VARCHAR(32) NOT NULL,
  status TINYINT NOT NULL DEFAULT 0 COMMENT '0 待执行 1 成功 2 失败',
  retry_count TINYINT NOT NULL DEFAULT 0,
  external_url VARCHAR(512) DEFAULT NULL,
  error_msg VARCHAR(512) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_task (task_id),
  INDEX idx_platform_status (platform, status)
) ENGINE=InnoDB COMMENT '多平台发布子任务';
```

---

### 2.4 前端设计系统 —— 复刻视频里那种「小红点 + 圆角卡片 + 底部抽屉」风格

#### 改造点
| # | 改造项 | 说明 | 涉及文件 |
|---|---|---|---|
| D1 | **设计 token 补齐** | 视频里主色是 `#FF4D4F` 橘红 + 圆角 16px + 阴影 `0 4px 12px rgba(0,0,0,0.08)`。统一进 design tokens | `admin/src/styles/variables.scss` |
| D2 | **Sheet 组件库** | 视频里全是底部抽屉（Wi-Fi/团购/点评/抽奖/加微） | 新建 `admin/src/components/SheetPanel/` |
| D3 | **平台图标矩阵组件** | 视频里 7 通道图标 + 名字 + 状态徽标 | 新建 `admin/src/components/PlatformGrid/` |
| D4 | **AI 文案对比组件** | 视频里左右滑 5 套文案 + 点赞/复制 | 新建 `admin/src/components/CopywriterCompare/` |
| D5 | **转盘组件** | 视频里 6 宫格旋转 + 中奖动画 | 新建 `admin/src/components/LotteryWheel/`（含 CSS conic-gradient + transform） |
| D6 | **长按保存组件** | 视频里点加微 → 长按二维码保存 | 新建 `admin/src/components/LongPressSave/`（原生 touchstart/touchend + canvas toBlob） |
| D7 | **移动端 H5 模板** | 视频里 C 端是 H5，要单独打包一份 mobile bundle | 新增 `admin/src/mobile/` + `admin/vite.config.mobile.ts` |

---

## 3. 落地节奏（5 周）

### Week 1：基础 + 数据
- D1 设计 token
- Flyway 增量（nfc_extend / funnel_event / platform_publish_task）
- B1 NFC 触发器改版
- S5 Wi-Fi NDEF 编码器

### Week 2：C 端 H5 模板
- 新建 `admin/src/mobile/` 整套 H5 页面
- C1–C10 全打通
- D6 长按保存组件
- 接 `NfcService` 出 NFC 落地 URL

### Week 3：AI + 多平台
- S2 7 个平台适配器
- S3 调度器 + Job
- S4 AI 文案升级（5 套候选 + 合规过滤）
- D4 AI 文案对比组件

### Week 4：商家后台改版
- B2 活动详情页改版
- B3 素材库改版
- B4 AI 文案工作台改版
- B5 多平台发布页改版
- D3 平台图标矩阵

### Week 5：抽奖 + 数据
- S6 转盘乐观锁
- S8 漏斗埋点
- B6 数据看板改版
- D5 转盘组件
- 联调 + 灰度

---

## 4. 验收清单

| 验收项 | 测试方法 | 责任模块 |
|---|---|---|
| NFC 落地页 1 秒打开 | 真机 NFC 触发，监控 `landing → first contentful paint` < 1s | C1 + S5 |
| AI 文案 5 套候选 | 提交视频 → 返回 ≥5 条 → 一键应用到视频 | S4 + D4 |
| 7 平台发布成功率 | 勾选 7 平台 → 后台日志全部 success | S2 + S3 |
| Wi-Fi 弹窗即用 | NDEF 写入 → iPhone 碰卡 → 自动连 Wi-Fi | S5 + C5 |
| 团购券跳转 | 点击 → 唤起美团/抖音 App | C6 |
| 5 平台点评跳转 | 点击 → 跳官方 | C7 |
| 抽奖转盘无超发 | 并发 100 请求 → 中奖数 ≤ 库存 | S6 + D5 |
| 漏斗数据完整 | 进店→任务→完成→奖励 全链路埋点 | S8 |
| 商家后台 4 页改版视觉一致 | 截图比对视频关键帧 | B1–B6 |

---

## 5. 不在本期范围

> 视频里出现但本期不做：
> - 抖音/快手/小红书平台 OAuth 真实对接（依赖第三方开放平台授权，本期只做适配器骨架 + mock）
> - 美团/抖音团购券**真实核销**（只做链接跳转）
> - 剪映视频模板云端渲染（已用 `JianyingVideoService`，本期只打通入口）
> - AI 文案风格枚举（小吃/火锅/奶茶/中餐/酒店）的 prompt 调优（先跑通流程，下个迭代再 A/B）

---

## 6. 风险与回滚

| 风险 | 应对 |
|---|---|
| C 端 H5 模板首屏加载慢 | Vite 拆 chunk + 关键 CSS inline + 图片 webp + 微信 CDN |
| 多平台 worker 失败率高 | 调度器自带重试 3 次 + 死信队列 + 商家后台可见 |
| 抽奖超发 | MySQL 行锁 + Redis 原子计数器双重保险 |
| NFC 标签兼容性 | iOS 13+ 支持 NDEF Wi-Fi；Android 用 DPP 或二维码兜底 |
| 商家后台改版破坏现有流程 | 灰度 10% → 50% → 100%，出问题秒级回滚到旧版 |

---

> **变更记录**
> - v1 (2026-09-04 18:44) — 初稿（挑毛病视角，已弃用）
> - v2 (2026-09-04 19:10) — 按视频对标实现视角重写