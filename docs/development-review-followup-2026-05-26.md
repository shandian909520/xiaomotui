# 8 项问题复检反馈 - 2026-05-26

## 复检结论

本次复检基于当前工作区代码、`npm run build`、以及本地预览 `http://127.0.0.1:4173/` 的关键页面抽检。

整体结论：8 项问题中，4 项已经基本闭环，2 项部分闭环，2 项仍需继续处理。构建通过，无编译阻塞；仍有大量后端 API/CORS 错误，真实联调质量不能只靠页面能打开判断。

## 本次顺手修复

- `admin/src/layout/NotificationBell.vue`
  - 生产环境 API 失败时不再展示通知 fallback 假数据。
  - 生产环境未读数失败时回落为 0。
- `admin/src/layout/TaskCenterPanel.vue`
  - 生产环境 API 失败时不再展示任务 fallback 假数据。
  - 生产环境任务统计失败时回落为 0。
- `admin/src/router/index.js`
  - 给 `/chain` 和 `/chain/employees` 增加 `requiredVersion: 'chain'`。
  - 路由守卫会拦截非连锁版用户直接访问 `/chain/employees`，并返回 `/home`。

## 逐项复检

### 1. API 响应解包规则不统一

状态：部分完成。

已看到新增 `admin/src/utils/responseHelper.js`，并且以下页面已经使用 `normalizePagination` / `normalizeListPayload`：

- `admin/src/views/library/VideoLibrary.vue`
- `admin/src/views/library/GraphicLibrary.vue`
- `admin/src/views/library/TopicLibrary.vue`
- `admin/src/views/activity/SceneConfigMatrix.vue`
- `admin/src/views/activity/redpackets.vue`
- `admin/src/views/chain/EmployeeManagement.vue`
- `admin/src/views/monitor/topics.vue`
- `admin/src/views/stores/index.vue`
- `admin/src/views/design/materials.vue`

仍需处理：

- 旧页面仍有 `res.data?.list` / `res.data || res` 写法，例如 `admin/src/views/library/videos.vue`、`admin/src/views/library/images.vue`、`admin/src/views/library/topics.vue`、`admin/src/views/activity/scenes.vue`。
- 如果这些旧页面已不再路由使用，可以删除或标记废弃；如果还可能被引用，需要同步改造。

### 2. 内容库前后端路由未对齐

状态：基本完成。

前端 `admin/src/api/index.js` 与后端 `api/route/app.php` 当前已能对齐到：

- video list/create/detail/update/delete/add-local/import
- graphic list/create/detail/update/delete/add-content
- image list/create/detail/update/delete/add
- text list/create/detail/update/delete/add
- topic list/create/detail/add/rename/delete

注意点：

- image/text 当前仍使用 `/detail/:id`、`/update/:id`、`/delete/:id` 风格，后端也按这个风格补了路由，所以是对齐的。
- 后续建议统一 REST 风格，但这不是当前阻塞。

### 3. fallback 假数据掩盖联调失败

状态：部分完成。

较大的新增页面多数已加 `import.meta.env.DEV` 判断，生产环境显示空态。此次又补了顶部通知和任务中心。

仍需处理：

- 旧页面仍无 DEV 限制，例如 `library/videos.vue`、`library/images.vue`、`library/topics.vue`、`activity/scenes.vue`、`tasks/index.vue`、`video/project.vue`、`ai/staff.vue` 等。
- `request.js` 里仍有“各页面有 fallback 机制”的注释和静默思路，建议统一改成：开发可 fallback，生产只显示错误态/空态。

### 4. 顶部“权益信息”入口没有真正展示面板

状态：完成。

`Header.vue` 已引入 `BenefitsPanel.vue`，点击账号下拉的“权益信息”能打开 `el-dialog`。浏览器抽检确认对话框出现。

### 5. 版本/权限体系不完整

状态：部分完成。

已完成：

- 侧边栏按 `localStorage.user.version === 'chain'` 展示员工管理。
- 本次补充 `/chain` 路由级拦截，基础版直接输入 `/chain/employees` 会被打回 `/home`。

仍需处理：

- 其他版本差异功能还没有系统化权限模型。
- 当前仍依赖 localStorage，最终应由后端返回版本和权限列表，前端路由 meta 和菜单都从权限生成。

### 6. 剪辑工作台半联调状态

状态：基本完成，但仍需真实联调确认。

已看到：

- `openProject(row)` 会调用 `getClipProjectDetail(row.id)` 回填 `config`。
- 详情未带分镜时，会再调用 `getClipShots(row.id)`。
- 保存为模板/导出会先确保工程已保存，不再传固定 0。
- `mode` 已做 `oneClick -> auto` 归一。

仍需确认：

- `createClipProject` 是否只是保存工程，还是会触发真实生成任务，需要后端明确。
- 导出任务是否能在任务中心真实回显，需要接真实测试后端验。

### 7. 中文源码乱码

状态：误报为主，仍有控制台显示问题。

用 `rg` 和浏览器实际渲染检查，源码中文和页面中文大部分是正常的；PowerShell `Get-Content` 显示乱码主要是本机控制台编码问题，不应作为代码缺陷直接判定。

仍建议：

- 确认编辑器统一 UTF-8。
- CI 中可增加简单编码检查，避免后续真的写入乱码。

### 8. 生产环境直连 IP / CORS 风险

状态：未完成。

`admin/.env.production` 仍是：

```env
VITE_API_BASE_URL=http://123.57.68.51:8080/api
```

浏览器抽检时控制台仍有大量 API/CORS 错误。这个问题不能靠前端 fallback 或空态解决。

建议：

- 生产使用正式 HTTPS 域名。
- 后端配置正式域名 CORS 白名单。
- 预览/测试环境使用同源网关或 Vite proxy。

## 验证记录

- `cd admin && npm run build`：通过。
- 浏览器抽检：
  - `/home` 可打开。
  - `/library/images` 可打开，显示图文库/图片库/文案库结构，接口失败时为空态。
  - `/video/edit` 可打开，剪辑模式选择页正常。
  - 账号下拉“权益信息”可打开弹窗。
  - 基础版直接访问 `/chain/employees` 会被拦截回 `/home`。
  - 连锁版访问 `/chain/employees` 可进入页面。

## 需要开发继续处理的事项

1. 清理或改造仍在使用旧解包/fallback 写法的旧页面。
2. 全面关闭生产 fallback，不允许真实用户看到示例业务数据。
3. 把版本/权限从 localStorage 判断升级为后端权限列表 + 路由 meta 守卫。
4. 替换生产环境直连 IP，完成 HTTPS 域名和 CORS 配置。
5. 用真实测试后端跑一次端到端验收：内容库 CRUD、剪辑工程保存/继续编辑/导出、任务中心回显、账号权益。
