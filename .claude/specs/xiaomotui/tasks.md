# 实施计划

## 任务概述

小魔推碰一碰是一个基于NFC技术的智能营销内容生成平台。项目采用ThinkPHP 8.0框架，通过渐进式开发方式实现从NFC触发到AI内容生成再到全平台分发的完整链路。

---

## 阶段十四：商家推广功能完善（新增）

### Phase 1: 素材管理基础（并行）

#### TASK-101: 后端素材管理API
**状态**: ✅ 已完成
**负责人**: Backend Agent
**依赖**: 无
**描述**: 创建素材管理相关的数据库表和API接口

- [ ] 创建数据库迁移 `20260218000001_create_promo_materials_table.sql`
  - 表名: `xmt_promo_materials`
  - 字段: id, merchant_id, type(image/video/music), name, file_url, thumbnail_url, duration, file_size, width, height, sort_order, status, create_time

- [ ] 创建 Model `app/model/PromoMaterial.php`
  - 类型常量: IMAGE, VIDEO, MUSIC
  - 关联: belongsTo Merchant
  - 方法: getByMerchant(), getByType()

- [ ] 创建 Controller `app/controller/PromoMaterial.php`
  - upload() - 上传单个素材
  - batchUpload() - 批量上传
  - list() - 素材列表
  - detail() - 素材详情
  - delete() - 删除素材
  - updateSort() - 更新排序

- [ ] 创建 Service `app/service/PromoMaterialService.php`
  - 文件上传处理
  - 缩略图生成
  - 文件信息提取

- [ ] 添加路由到 `route/app.php`

**验收标准**:
- POST `/api/merchant/promo/materials` 上传成功返回素材ID
- GET `/api/merchant/promo/materials` 返回素材列表
- DELETE `/api/merchant/promo/materials/{id}` 删除成功

---

#### TASK-102: 管理后台素材库页面
**状态**: ✅ 已完成
**负责人**: Admin Frontend Agent
**依赖**: TASK-101
**描述**: 在管理后台添加素材库管理页面

- [ ] 创建 API 模块 `admin/src/api/promo-material.js`
  - uploadMaterial()
  - getMaterialList()
  - deleteMaterial()
  - batchUpload()

- [ ] 创建页面 `admin/src/views/promo-material/index.vue`
  - 素材卡片/列表视图切换
  - 类型筛选 Tab（图片/视频/音乐）
  - 上传按钮（支持拖拽）
  - 删除确认对话框
  - 预览弹窗

- [ ] 创建上传组件 `admin/src/components/MaterialUpload.vue`
  - 支持拖拽上传
  - 支持批量选择
  - 上传进度显示
  - 文件类型校验

- [ ] 添加路由 `admin/src/router/index.js`
  - path: '/promo/material'
  - name: 'PromoMaterial'

- [ ] 添加侧边栏菜单 `admin/src/layout/Sidebar.vue`
  - 推广管理 > 素材库

**验收标准**:
- 页面可正常访问
- 上传图片/视频成功
- 列表展示正常
- 删除功能正常

---

#### TASK-103: 移动端素材功能
**状态**: ✅ 已完成
**负责人**: UniApp Agent
**依赖**: TASK-101
**描述**: 在移动端添加素材拍摄和查看功能

- [ ] 创建 API 模块 `uni-app/src/api/modules/promoMaterial.js`
  - uploadMaterial()
  - getMaterialList()
  - deleteMaterial()

- [ ] 创建商家首页 `uni-app/src/pages/merchant/home.vue`
  - 数据概览卡片（今日触发/发布/奖励）
  - 快捷入口（拍素材/合成视频/扫设备/看数据）
  - TabBar 配置

- [ ] 创建素材库页面 `uni-app/src/pages/merchant/material.vue`
  - 素材网格展示
  - 类型筛选
  - 上传入口
  - 删除功能

- [ ] 创建拍摄页面 `uni-app/src/pages/merchant/capture.vue`
  - 相机取景框
  - 拍照/录像切换
  - 从相册选择
  - 拍摄引导提示
  - 批量拍摄模式

- [ ] 添加路由到 `uni-app/src/pages.json`

**验收标准**:
- 商家首页正常显示
- 拍照上传成功
- 相册选择上传成功
- 素材列表展示正常

---

### Phase 2: 视频合成与去重（串行）

#### TASK-104: 后端视频合成服务
**状态**: ✅ 已完成
**负责人**: Backend Agent
**依赖**: TASK-101
**描述**: 创建视频模板和变体相关的数据库和服务

- [ ] 创建数据库迁移 `20260218000002_create_promo_tables.sql`
  - `xmt_promo_templates` (视频模板)
  - `xmt_promo_variants` (视频变体)
  - `xmt_promo_campaigns` (推广活动)
  - `xmt_promo_campaign_devices` (活动设备关联)
  - `xmt_promo_distributions` (分发记录)

- [ ] 创建 Model
  - `app/model/PromoTemplate.php`
  - `app/model/PromoVariant.php`
  - `app/model/PromoCampaign.php`

- [ ] 创建 FFmpeg 服务 `app/service/VideoComposeService.php`
  - composeFromImages() - 图片轮播合成
  - addTransition() - 添加转场效果
  - addMusic() - 添加背景音乐
  - generateVideo() - 执行合成命令

- [ ] 创建去重服务 `app/service/VideoDedupService.php`
  - randomizeParams() - 随机参数变换
  - modifyFrame() - 帧级变换（亮度/对比度/饱和度）
  - addNoise() - 添加随机噪声
  - modifyMd5() - 修改文件MD5
  - generateVariants() - 批量生成变体

- [ ] 创建模板 Controller `app/controller/PromoTemplate.php`
  - create() - 创建模板
  - list() - 模板列表
  - generate() - 生成变体

- [ ] 创建变体 Controller `app/controller/PromoVariant.php`
  - list() - 变体列表
  - getNext() - 获取下一个可用变体
  - recordUse() - 记录使用

**验收标准**:
- 图片轮播合成视频成功
- 去重变体生成成功
- 变体MD5互不相同

---

#### TASK-105: 管理后台视频合成页面
**状态**: ✅ 已完成
**负责人**: Admin Frontend Agent
**依赖**: TASK-104
**描述**: 在管理后台添加视频合成配置页面

- [ ] 创建 API 模块 `admin/src/api/promo-template.js`

- [ ] 创建模板页面 `admin/src/views/promo-template/index.vue`
  - 模板列表
  - 创建模板对话框
  - 素材选择器
  - 参数配置表单
  - 生成变体按钮

- [ ] 创建变体页面 `admin/src/views/promo-variant/index.vue`
  - 变体列表
  - 预览播放
  - 使用次数统计
  - 批量生成

- [ ] 添加路由和菜单

**验收标准**:
- 模板创建成功
- 变体生成成功
- 预览播放正常

---

#### TASK-106: 移动端视频合成页面
**状态**: ✅ 已完成
**负责人**: UniApp Agent
**依赖**: TASK-104
**描述**: 在移动端添加视频合成功能

- [ ] 创建 API 模块 `uni-app/src/api/modules/promoTemplate.js`

- [ ] 创建合成页面 `uni-app/src/pages/merchant/compose.vue`
  - 素材选择（可排序）
  - 参数设置（时长/转场/音乐）
  - 变体数量设置
  - 文案编辑
  - 话题标签
  - 提交合成

- [ ] 创建结果页面 `uni-app/src/pages/merchant/compose-result.vue`
  - 合成进度
  - 变体预览
  - 发布到设备

- [ ] 添加路由配置

**验收标准**:
- 素材选择和排序正常
- 合成任务提交成功
- 结果预览正常

---

### Phase 3: 推广活动管理

#### TASK-107: 推广活动管理（全端）
**状态**: ✅ 已完成
**依赖**: TASK-104, TASK-105, TASK-106
**描述**: 完整的推广活动管理功能

- [x] 后端: 活动管理API
- [x] 后台: 活动列表/创建页面
- [x] 移动端: 活动列表/创建页面
- [x] 移动端: 扫码绑定设备

---

### Phase 4: 数据统计

#### TASK-108: 推广数据统计
**状态**: ✅ 已完成
**依赖**: TASK-107
**描述**: 推广数据统计和可视化

- [x] 后端: 统计聚合API
- [x] 后台: 统计报表页面
- [x] 移动端: 统计页面

---

## 执行计划

### 当前执行: 第一批次并行任务

**启动 3 个 Agent 并行开发:**

1. **Backend Agent** → TASK-101 (后端素材管理API)
2. **Admin Frontend Agent** → TASK-102 (管理后台素材库)
3. **UniApp Agent** → TASK-103 (移动端素材功能)

### 完成后检查点
- [ ] TASK-101 完成并通过API测试
- [ ] TASK-102 完成并通过UI测试
- [ ] TASK-103 完成并通过功能测试
- [ ] 前后端联调通过

### 下一批执行
启动 TASK-104, TASK-105, TASK-106 (视频合成)
