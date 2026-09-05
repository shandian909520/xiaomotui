# 开发检阅反馈 - 2026-05-26

## 本次已顺手修复

- 修复 `admin/src/views/video/EditWorkbench.vue` 构建失败：`getStoreList` 改为从 `@/api/video` 引入。
- 修复连锁版侧边栏缺少 `员工管理`：`admin/src/layout/sidebar.vue` 根据 `localStorage.user.version === 'chain'` 展示 `/chain/employees`。
- 修复退出登录和修改密码后 token 未清理：`Header.vue`、`ChangePassword.vue` 调用 `removeToken()`。
- 修复首页、通知铃铛、任务中心的部分响应解包兼容问题。
- 修复剪辑工程相关接口契约：
  - `saveAsTemplate` 路径改为 `/clip-project/save-as-template`，参数改为 `project_id`。
  - `exportClipProject` 参数改为 `project_id`。
  - 分镜接口路径改为 `/clip-project/shot/add|update|delete|sort`，删除参数改为 `shot_id`。
  - 剪辑工作台“保存为模板/导出”不再传固定 `0`，会先保存/复用当前工程 ID。
  - 一键成片提交给后端的 `mode` 改为后端允许的 `auto`。
- 修复部分内容库 API 路径与后端路由不一致：
  - 视频库 detail/update/delete 改为 `/content-library/video/:id`。
  - 图文库 detail/update/delete 改为 `/content-library/graphic/:id`。
  - 话题库 rename/delete 改为 `/content-library/topic/:id/rename`、`/content-library/topic/:id`。

## 验证结果

- `cd admin && npm run build` 通过。
- 冒烟点击已覆盖：
  - `/activity/scenes`
  - `/library/videos`
  - `/library/images`，含图文库、图片库、文案库 Tab
  - `/library/topics`
  - `/chain/employees`
  - `/monitor/topics`
  - `/video/edit`
- 预览环境控制台存在大量后端 CORS/API 请求失败，页面多数通过 fallback 数据展示，不能证明真实接口已可用。
- 本机 `git` 命令不可用，无法给出标准 diff，只能按文件内容和构建结果检阅。

## 必须处理的重大问题

### 1. API 响应解包规则没有统一

现状：`admin/src/utils/request.js` 成功时返回 `res.data !== undefined ? res.data : res`。也就是说页面拿到的是业务数据，不是完整 `{ code, data }`。

但大量页面仍然写成 `res.data?.list`、`res.data || res`、`result.value.data`。这会导致真实接口返回正常时，页面取不到列表、分页总数或详情，最后落到空数据/fallback 假数据。

重点文件：

- `admin/src/views/activity/SceneConfigMatrix.vue`
- `admin/src/views/library/VideoLibrary.vue`
- `admin/src/views/library/GraphicLibrary.vue`
- `admin/src/views/library/TopicLibrary.vue`
- `admin/src/views/chain/EmployeeManagement.vue`
- `admin/src/views/monitor/topics.vue`
- `admin/src/views/stores/index.vue`
- `admin/src/views/design/materials.vue`
- `admin/src/views/activity/redpackets.vue`

建议：

- 定一个唯一规范：页面层只处理已解包业务数据。
- 新增公共 helper，例如 `normalizeListPayload(res)`、`normalizePagination(res)`。
- 把剩余新增页面批量改为 `const data = res?.data ?? res` 或直接按已解包结构读取。
- 分页接口后端目前常见结构是 `{ list, pagination: { total } }`，前端不要只读 `data.total`。

### 2. 内容库前后端路由仍未完全对齐

已修复一部分，但还有明显不一致：

- 前端仍有：
  - `getImageLibraryDetail/update/delete`
  - `getTextLibraryDetail/update/delete`
  - `getTopicLibraryDetail`
- 后端 `api/route/app.php` 当前对 image/text 只暴露 create/add，没有 list/detail/update/delete；topic detail 路由也没有单独 `detail/:id`。

建议：

- 后端补齐 image/text/topic 的 detail/update/delete/list 路由，或前端移除不可用功能按钮。
- 用接口清单逐项对齐：前端 `admin/src/api/index.js` 每一个函数都要能在 `api/route/app.php` 找到对应路由、HTTP method、参数名。

### 3. fallback 假数据掩盖联调失败

新增页面大量 catch 后直接展示静态示例数据。冒烟时页面看起来可用，但控制台实际是 CORS/API 失败。

风险：

- 测试和产品验收容易误判“功能已完成”。
- 真实用户看到的可能是示例业务数据。
- 接口字段变更不会暴露。

建议：

- 开发环境可以 fallback，但生产环境必须显示空态/错误态，不允许静默展示假数据。
- fallback 数据加明显标记或只在 `import.meta.env.DEV` 下启用。
- 验收时必须接真实测试后端并关闭 fallback。

### 4. 顶部“权益信息”入口没有真正展示面板

`admin/src/views/account/BenefitsPanel.vue` 已实现，但 `Header.vue` 没有渲染/引用它。账号下拉里的“权益信息”点击后只 break，用户看不到权益详情。

建议：

- 在账号下拉内用 `el-popover` 或 `el-dialog` 挂载 `BenefitsPanel`。
- 卡密激活成功后调用 `BenefitsPanel.loadBenefits()` 或刷新账号权益。

### 5. 版本/权限体系还不是完整方案

当前仅根据 `localStorage.user.version` 展示连锁版 `员工管理`。其他菜单仍几乎全部静态展示。

风险：

- 基础版可能看到不该看的功能入口。
- 只控制菜单，不控制路由访问，用户可直接输入 URL 访问。
- `basic`、`standard`、`chain` 三种值在页面里混用，和后端返回值需确认。

建议：

- 后端返回用户版本和权限列表。
- 路由 meta 增加版本/权限要求，导航守卫拦截直接访问。
- 侧边栏从路由和权限生成，不再维护一份静态菜单。

### 6. 剪辑工作台仍是半联调状态

已修复固定 `0` 工程 ID 和部分接口契约，但仍有联调缺口：

- `openProject(row)` 只切模式，没有加载工程详情和分镜。
- 保存时将 `config` 直接传对象，后端模型字段是否需要 JSON 字符串需确认。
- 一键成片、批量混剪当前只是创建工程，不等同于真实生成任务。
- 页面里仍保留大量 fallback 素材、模板、最近工程。

建议：

- 打开工程时调用 `getClipProjectDetail(id)`，回填 `config/shots/globalConfig`。
- 明确“保存工程”和“提交生成/导出任务”的接口边界。
- 后端补充或确认工程生成任务接口，前端不要只用 `createClipProject` 代替生成。

### 7. 中文源码存在明显乱码

多个新文件和旧文件中文注释/文案在源码中呈现乱码。虽然当前构建可通过，但长期会影响维护、搜索和文案修改。

建议：

- 统一源码文件为 UTF-8。
- 对新增页面做一次编码清理，尤其是路由标题、菜单、按钮文案、错误提示。
- 清理前先确认编辑器和构建链不会再次按错误编码写入。

### 8. 生产环境直连 IP 触发 CORS 风险

`admin/.env.production` 配置为 `http://123.57.68.51:8080/api`。本次预览环境从 `127.0.0.1:4173` 请求该地址时大量 CORS 失败。

建议：

- 生产前确认正式域名、HTTPS、CORS 白名单。
- 开发/预览环境用 Vite proxy 或同源网关，不要靠页面直连裸 IP。

## 建议验收清单

- 关闭 fallback，接真实测试后端。
- 覆盖新增页面的列表、分页、搜索、详情、创建、编辑、删除。
- 覆盖账号下拉：修改密码、卡密激活、权益信息、版本切换、退出登录。
- 覆盖基础版和连锁版两个账号：菜单展示、直接访问路由、接口权限都要验证。
- 覆盖剪辑工作台：新建、保存、继续编辑、保存为模板、导出任务、任务中心回显。
