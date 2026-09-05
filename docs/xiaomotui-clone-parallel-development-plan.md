# 小魔推后台复刻系统并行开发方案

## 目标

基于当前项目开发一个与 `https://volcengine.xiaomotui.com/` 登录后后台高度一致的系统，技术栈使用 `Vue 3 + Element Plus + Vite`，并逐步接入现有 `api` 后端。

当前已完成：

- `admin` 项目已改为 hash 路由，首页路径为 `/#/home`
- 已完成登录后首页的第一版静态复刻
- 已完成左侧菜单、顶部栏、首页数据区、智能员工区、创作广场区
- `npm.cmd run build` 已通过

后续目标：

- 批量补齐所有菜单页面
- 抽取公共组件，减少重复开发
- 定义统一 API 与 mock 数据
- 逐步接入真实后端
- 做到页面、交互、数据结构、业务流程都与目标系统一致

## 当前项目位置

前端项目：

```text
admin/
```

核心文件：

```text
admin/src/router/index.js
admin/src/layout/Sidebar.vue
admin/src/layout/Header.vue
admin/src/layout/Main.vue
admin/src/views/dashboard/index.vue
admin/src/views/common/placeholder.vue
admin/src/assets/styles/main.scss
```

后端项目：

```text
api/
```

## 开发原则

1. 页面优先与线上视觉一致，后端数据可先 mock。
2. 所有新页面必须使用 Element Plus。
3. 不要大改现有后端结构，先新增接口和服务。
4. 公共布局、筛选栏、表格、卡片、弹窗必须组件化。
5. 每个 agent 只负责自己的模块，避免交叉修改同一文件。
6. 所有页面完成后必须通过 `npm.cmd run build`。

## 并行 Agent 分工

### Agent A：公共组件与样式体系

负责目录：

```text
admin/src/components/
admin/src/assets/styles/
```

任务：

- 抽取后台通用页面头部组件 `PageHeader.vue`
- 抽取筛选工具栏组件 `FilterToolbar.vue`
- 抽取统计卡片组件 `StatTile.vue`
- 抽取数据分组卡片组件 `MetricGroupCard.vue`
- 抽取员工卡片组件 `AiStaffCard.vue`
- 抽取作品卡片组件 `WorkCard.vue`
- 抽取空状态组件 `EmptyPanel.vue`
- 补充统一 CSS 变量与颜色规范

建议文件：

```text
admin/src/components/xmt/PageHeader.vue
admin/src/components/xmt/FilterToolbar.vue
admin/src/components/xmt/StatTile.vue
admin/src/components/xmt/MetricGroupCard.vue
admin/src/components/xmt/AiStaffCard.vue
admin/src/components/xmt/WorkCard.vue
admin/src/components/xmt/EmptyPanel.vue
admin/src/assets/styles/theme.scss
```

验收标准：

- 组件支持 props 配置
- 组件视觉与当前首页一致
- 不引入额外 UI 框架
- 构建通过

### Agent B：门店列表模块

负责页面：

```text
admin/src/views/stores/index.vue
```

路由：

```text
/#/stores
```

页面功能：

- 门店列表
- 搜索门店名称
- 按状态筛选
- 新增门店弹窗
- 编辑门店弹窗
- 门店装修入口
- NFC 配置状态
- 门店套餐和剩余权益展示

建议数据字段：

```js
{
  id: 1,
  name: '测试门店',
  logo: '',
  address: '上海市...',
  manager: '李存轮',
  phone: '13800000000',
  status: 'enabled',
  nfcConfigured: true,
  materialCount: 24,
  videoCount: 132,
  createdAt: '2026-05-25'
}
```

建议 API：

```text
GET    /api/admin/stores
POST   /api/admin/stores
PUT    /api/admin/stores/:id
DELETE /api/admin/stores/:id
```

验收标准：

- 页面与线上后台风格一致
- 表格、筛选、新增、编辑弹窗可操作
- 可使用 mock 数据
- 不影响首页

### Agent C：素材管理模块

负责页面：

```text
admin/src/views/materials/index.vue
```

路由：

```text
/#/materials
```

页面功能：

- 素材列表
- 图片、视频、图文分类筛选
- 上传素材弹窗
- 素材预览
- 批量删除
- 关联门店
- 素材状态
- 存储空间展示

建议数据字段：

```js
{
  id: 1,
  title: '门店环境视频',
  type: 'video',
  url: '',
  cover: '',
  size: '12.4MB',
  duration: 18,
  storeName: '测试门店',
  status: 'ready',
  createdAt: '2026-05-25'
}
```

建议 API：

```text
GET    /api/admin/materials
POST   /api/admin/materials/upload
PUT    /api/admin/materials/:id
DELETE /api/admin/materials/:id
```

验收标准：

- 上传区使用 Element Plus `el-upload`
- 支持卡片视图和表格视图
- 素材预览弹窗可打开
- 页面视觉接近线上系统

### Agent D：视频创作模块

负责页面：

```text
admin/src/views/video/edit.vue
admin/src/views/video/project.vue
```

路由：

```text
/#/video/edit
/#/video/project
```

`新建剪辑` 功能：

- 选择门店
- 选择素材
- 选择模板
- 选择平台
- 配置标题、文案、发布时间
- 创建剪辑任务

`剪辑工程` 功能：

- 任务列表
- 状态筛选
- 进度展示
- 预览成片
- 重新生成
- 下载
- 发布记录

建议任务字段：

```js
{
  id: 1,
  title: '五一活动短视频',
  storeName: '测试门店',
  platform: 'douyin',
  status: 'processing',
  progress: 72,
  cover: '',
  duration: 15,
  createdAt: '2026-05-25'
}
```

建议 API：

```text
GET  /api/admin/video/tasks
POST /api/admin/video/tasks
GET  /api/admin/video/tasks/:id
POST /api/admin/video/tasks/:id/retry
```

验收标准：

- 创建流程至少分 3 步
- 工程列表可筛选、分页
- 进度条和状态标签完整
- 可先 mock，不要求真实生成视频

### Agent E：AI 实验室与 AI 成品库

负责页面：

```text
admin/src/views/ai/staff.vue
admin/src/views/library/videos.vue
admin/src/views/library/images.vue
admin/src/views/library/topics.vue
```

路由：

```text
/#/ai/staff
/#/library/videos
/#/library/images
/#/library/topics
```

AI 实验室功能：

- 智能员工列表
- 员工能力说明
- 安排工作弹窗
- 任务类型选择
- 文案生成结果展示

AI 成品库功能：

- 视频库
- 图文库
- 话题库
- 搜索、筛选、预览、复制、下载
- 按门店和平台筛选

建议 API：

```text
GET  /api/admin/ai/staff
POST /api/admin/ai/staff/:id/assign
GET  /api/admin/library/videos
GET  /api/admin/library/images
GET  /api/admin/library/topics
```

验收标准：

- 智能员工卡片与首页右侧一致
- 成品库支持卡片网格
- 预览弹窗完整
- 生成结果可复制

### Agent F：活动管理与数据监控

负责页面：

```text
admin/src/views/activity/scenes.vue
admin/src/views/activity/redpackets.vue
admin/src/views/monitor/topics.vue
```

路由：

```text
/#/activity/scenes
/#/activity/redpackets
/#/monitor/topics
```

活动管理功能：

- 场景配置列表
- 新增活动场景
- 配置活动素材
- 红包余额展示
- 发红包记录
- 红包规则设置

数据监控功能：

- 话题监控列表
- 热度趋势
- 平台筛选
- 关键词筛选
- 数据明细导出

建议 API：

```text
GET  /api/admin/activity/scenes
POST /api/admin/activity/scenes
GET  /api/admin/activity/redpackets
POST /api/admin/activity/redpackets/rules
GET  /api/admin/monitor/topics
```

验收标准：

- 表格、趋势图、筛选器完整
- 红包金额、余额、发放状态清晰
- 数据监控有图表展示

### Agent G：运营设计与任务中心

负责页面：

```text
admin/src/views/design/materials.vue
admin/src/views/tasks/index.vue
```

路由：

```text
/#/design/materials
/#/tasks
```

运营设计功能：

- 物料模板列表
- 海报、桌贴、二维码牌等分类
- 编辑入口
- 下载物料

任务中心功能：

- 顶部任务中心下拉入口
- 任务列表抽屉
- 任务状态：排队中、处理中、成功、失败
- 失败重试

建议 API：

```text
GET  /api/admin/design/materials
GET  /api/admin/tasks
POST /api/admin/tasks/:id/retry
```

验收标准：

- 顶部任务中心可以打开抽屉
- 任务中心和视频任务状态统一
- 物料设计页面可筛选和下载

### Agent H：后端接口与 Mock 数据

负责目录：

```text
api/
admin/src/api/
```

任务：

- 统一前端请求模块
- 新增 mock 数据文件
- 按模块补齐 API 文件
- 后端先提供基础 JSON 接口
- 保持响应格式统一

统一响应格式：

```json
{
  "code": 200,
  "message": "success",
  "data": {}
}
```

建议前端 API 文件：

```text
admin/src/api/stores.js
admin/src/api/materials.js
admin/src/api/video.js
admin/src/api/ai.js
admin/src/api/library.js
admin/src/api/activity.js
admin/src/api/monitor.js
admin/src/api/tasks.js
```

建议 mock 文件：

```text
admin/src/mock/dashboard.js
admin/src/mock/stores.js
admin/src/mock/materials.js
admin/src/mock/video.js
admin/src/mock/ai.js
admin/src/mock/library.js
admin/src/mock/activity.js
admin/src/mock/monitor.js
admin/src/mock/tasks.js
```

验收标准：

- API 文件导出清晰
- 页面可用 mock 数据渲染
- 后续切真实接口只需要改请求实现

## 路由总表

```text
/#/home                         首页
/#/stores                       门店列表
/#/materials                    素材管理
/#/video/edit                   新建剪辑
/#/video/project                剪辑工程
/#/ai/staff                     智能员工
/#/library/videos               视频库
/#/library/images               图文库
/#/library/topics               话题库
/#/activity/scenes              场景配置
/#/activity/redpackets          发红包
/#/monitor/topics               话题监控
/#/design/materials             物料设计
/#/tasks                        任务中心
```

## 推荐目录结构

```text
admin/src/
  api/
    stores.js
    materials.js
    video.js
    ai.js
    library.js
    activity.js
    monitor.js
    tasks.js
  components/
    xmt/
      PageHeader.vue
      FilterToolbar.vue
      StatTile.vue
      MetricGroupCard.vue
      AiStaffCard.vue
      WorkCard.vue
      EmptyPanel.vue
  mock/
    stores.js
    materials.js
    video.js
    ai.js
    library.js
    activity.js
    monitor.js
    tasks.js
  views/
    stores/
      index.vue
    materials/
      index.vue
    video/
      edit.vue
      project.vue
    ai/
      staff.vue
    library/
      videos.vue
      images.vue
      topics.vue
    activity/
      scenes.vue
      redpackets.vue
    monitor/
      topics.vue
    design/
      materials.vue
    tasks/
      index.vue
```

## 视觉规范

基础风格：

- 背景色：`#f8f3ff`
- 卡片背景：`#ffffff`
- 主题紫：`#7b50ff`
- 高亮粉：`#ff2fb6`
- 辅助蓝：`#2c8cff`
- 成功绿：`#20d482`
- 警告橙：`#ff9860`
- 文字主色：`#181224`
- 文字次色：`#746b80`

布局要求：

- 左侧菜单宽度约 `236px`
- 顶部栏高度约 `50px`
- 主内容区卡片圆角 `14px - 18px`
- 页面最小宽度 `1280px`
- 表格页使用白色主卡片承载
- 筛选栏放在页面标题右侧或标题下方

## 开发顺序建议

第一批：

1. Agent A 公共组件
2. Agent B 门店列表
3. Agent C 素材管理
4. Agent H Mock 与 API

第二批：

1. Agent D 视频创作
2. Agent E AI 实验室与成品库

第三批：

1. Agent F 活动管理与数据监控
2. Agent G 运营设计与任务中心

第四批：

1. 集成所有页面路由
2. 替换首页内联卡片为公共组件
3. 接入真实接口
4. 全量构建和页面截图验收

## 每个 Agent 的交付要求

每个 agent 完成后必须提供：

```text
1. 修改文件列表
2. 新增路由列表
3. 使用的 mock 数据说明
4. 已完成的页面功能
5. 未完成或需要后端支持的功能
6. 构建结果
```

必须运行：

```bash
cd admin
npm.cmd run build
```

## 集成验收清单

全局：

- 左侧菜单点击都能进入页面
- 页面无明显乱码
- 页面无控制台致命错误
- 生产构建通过
- 页面宽度在 `1440px` 和 `1920px` 下都正常

首页：

- 顶部栏、侧栏、数据总览、智能员工、统计卡片、创作广场完整

门店：

- 可查看、新增、编辑、筛选门店

素材：

- 可上传、筛选、预览、删除素材

视频：

- 可创建剪辑任务
- 可查看任务进度

AI：

- 可安排智能员工工作
- 可查看生成结果

成品库：

- 可查看视频、图文、话题
- 可预览、复制、下载

活动：

- 可配置场景
- 可查看红包余额和发放记录

监控：

- 可查看话题趋势和明细

任务中心：

- 可查看全局任务
- 可重试失败任务

## 注意事项

- 不要把线上账号、cookie、token 写入代码。
- 不要提交真实用户数据。
- 页面可以先用 mock 数据，但数据字段要贴近真实业务。
- 如果多个 agent 同时改 `router/index.js`，最后由集成 agent 统一合并。
- 如果多个 agent 同时改 `main.scss`，优先让 Agent A 维护，其他 agent 只写 scoped 样式。
- 不要把所有页面写成一个大文件，按模块拆分。

