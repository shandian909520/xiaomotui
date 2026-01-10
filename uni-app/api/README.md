# 小魔推 API 请求封装文档

## 概述

小魔推 API 请求封装是一个跨平台的网络请求库，支持微信小程序、H5、支付宝小程序、APP等多端统一使用。提供了完善的Token管理、错误处理、请求重试等功能。

## 快速开始

### 1. 基础使用

```javascript
import api from '@/api'

// 登录
const loginRes = await api.auth.login(code)

// NFC触发
const triggerRes = await api.nfc.trigger(deviceCode)

// 创建内容生成任务
const taskRes = await api.content.createTask({
  type: 'VIDEO',
  templateId: 1
})

// 发布内容
const publishRes = await api.publish.publishNow(taskId, ['douyin'])
```

### 2. 配置说明

配置文件位置：`config/api.js`

```javascript
{
  baseUrl: 'http://localhost:8000',  // API基础地址
  timeout: 30000,                     // 请求超时时间（毫秒）
  showLoading: true,                  // 是否显示loading
  enableRetry: true,                  // 是否启用重试
  retryCount: 2,                      // 重试次数
  tokenKey: 'xiaomotui_token',        // Token存储key
  autoRefreshToken: true              // Token过期自动刷新
}
```

## API模块

### 认证模块（auth）

#### 登录相关

```javascript
// 微信小程序登录
await api.auth.wechatLogin()

// 支付宝小程序登录
await api.auth.alipayLogin()

// 手机号登录（H5）
await api.auth.phoneLogin('13800138000', '123456')

// 发送验证码
await api.auth.sendSmsCode('13800138000')
```

#### 用户信息

```javascript
// 获取用户信息
const userInfo = await api.auth.getUserInfo()

// 更新用户信息
await api.auth.updateUserInfo({
  nickname: '新昵称',
  avatar: 'https://...'
})

// 绑定手机号
await api.auth.bindPhone('13800138000', '123456')
```

#### Token管理

```javascript
// 刷新Token
await api.auth.refreshToken()

// 退出登录
await api.auth.logout()

// 检查登录状态
const isLogin = api.auth.checkLoginStatus()
```

### NFC模块（nfc）

#### 设备触发

```javascript
// NFC触发
const res = await api.nfc.trigger('DEVICE_001', {
  scene: 'restaurant',
  location: '北京市朝阳区'
})

// 扫码触发（降级方案）
const deviceCode = await api.nfc.scanQRCode()
```

#### 设备管理

```javascript
// 获取设备列表
const devices = await api.nfc.getDeviceList({
  page: 1,
  page_size: 10
})

// 获取设备详情
const device = await api.nfc.getDeviceDetail('DEVICE_001')

// 获取设备配置
const config = await api.nfc.getDeviceConfig('DEVICE_001')

// 绑定设备
await api.nfc.bindDevice('DEVICE_001', '设备名称', {
  mode: 'auto',
  template_id: 1
})

// 解绑设备
await api.nfc.unbindDevice('DEVICE_001')

// 更新设备配置
await api.nfc.updateDeviceConfig('DEVICE_001', {
  mode: 'manual'
})
```

#### 设备状态

```javascript
// 上报设备状态
await api.nfc.reportDeviceStatus('DEVICE_001', {
  battery: 85,
  signal: 4,
  temperature: 25
})

// 获取触发记录
const triggers = await api.nfc.getDeviceTriggers('DEVICE_001', {
  page: 1
})

// 获取统计数据
const stats = await api.nfc.getDeviceStats('DEVICE_001', '2024-01-01', '2024-01-31')
```

#### NFC功能（微信小程序）

```javascript
// 初始化NFC
await api.nfc.initNFC()

// 监听NFC消息
api.nfc.listenNFC((res) => {
  console.log('收到NFC消息', res)
})

// 停止NFC
await api.nfc.stopNFC()

// 检查NFC是否可用
const isAvailable = await api.nfc.checkNFCAvailable()
```

### 内容生成模块（content）

#### 任务管理

```javascript
// 创建生成任务
const task = await api.content.createTask({
  type: 'VIDEO',              // 类型：TEXT/VIDEO/IMAGE
  templateId: 1,              // 模板ID
  deviceCode: 'DEVICE_001',   // 设备码
  scene: {                    // 场景信息
    type: 'restaurant',
    name: '美味餐厅'
  },
  style: 'modern',            // 风格
  platform: 'douyin',         // 目标平台
  keywords: ['美食', '探店']  // 关键词
})

// 查询任务状态
const status = await api.content.getTaskStatus(taskId)

// 获取任务详情
const detail = await api.content.getTaskDetail(taskId)

// 获取任务列表
const tasks = await api.content.getTaskList({
  page: 1,
  pageSize: 10,
  type: 'VIDEO',
  status: 'COMPLETED'
})

// 取消任务
await api.content.cancelTask(taskId)

// 删除任务
await api.content.deleteTask(taskId)

// 重新生成
await api.content.regenerate(taskId, {
  style: 'classic'
})
```

#### 模板管理

```javascript
// 获取模板列表
const templates = await api.content.getTemplateList({
  category: 'restaurant',
  type: 'VIDEO',
  page: 1
})

// 获取模板详情
const template = await api.content.getTemplateDetail(templateId)

// 创建自定义模板
await api.content.createTemplate({
  name: '我的模板',
  type: 'VIDEO',
  category: 'restaurant',
  content: '模板内容',
  config: {}
})

// 更新模板
await api.content.updateTemplate(templateId, {
  name: '新名称'
})

// 删除模板
await api.content.deleteTemplate(templateId)

// 预览模板
await api.content.previewTemplate(templateId, {
  scene: {}
})
```

#### 内容操作

```javascript
// 下载内容
await api.content.downloadContent(taskId, '/path/to/save')

// 保存到相册
await api.content.saveToAlbum(url, 'video')

// 分享内容
await api.content.shareContent({
  title: '标题',
  path: '/pages/detail',
  imageUrl: 'https://...'
})

// 获取统计
const stats = await api.content.getContentStats({
  startDate: '2024-01-01',
  endDate: '2024-01-31'
})

// 批量生成
await api.content.batchGenerate([
  { type: 'VIDEO', templateId: 1 },
  { type: 'TEXT', templateId: 2 }
])

// 获取AI推荐
const recommendation = await api.content.getAIRecommendation({
  type: 'restaurant',
  name: '餐厅名称'
})
```

### 发布模块（publish）

#### 发布任务

```javascript
// 创建发布任务
const task = await api.publish.createPublishTask({
  contentTaskId: 123,
  platforms: ['douyin', 'xiaohongshu'],
  scheduledTime: '2024-01-01 12:00:00',
  title: '视频标题',
  description: '视频描述',
  tags: ['美食', '探店'],
  cover: 'https://...'
})

// 立即发布
await api.publish.publishNow(contentTaskId, ['douyin'], {
  title: '标题',
  description: '描述'
})

// 获取发布任务列表
const tasks = await api.publish.getPublishTasks({
  page: 1,
  status: 'SUCCESS',
  platform: 'douyin'
})

// 获取任务详情
const detail = await api.publish.getPublishTaskDetail(taskId)

// 取消任务
await api.publish.cancelPublishTask(taskId)

// 删除任务
await api.publish.deletePublishTask(taskId)

// 重新发布
await api.publish.republish(taskId)
```

#### 平台账号管理

```javascript
// 获取平台账号列表
const accounts = await api.publish.getPlatformAccounts()

// 获取账号详情
const account = await api.publish.getPlatformAccountDetail(accountId)

// 添加平台账号
await api.publish.addPlatformAccount('douyin', {
  name: '账号名称',
  access_token: 'token'
})

// 更新账号
await api.publish.updatePlatformAccount(accountId, {
  name: '新名称'
})

// 删除账号
await api.publish.deletePlatformAccount(accountId)

// 刷新Token
await api.publish.refreshPlatformToken(accountId)
```

#### 平台授权

```javascript
// 获取授权URL
const res = await api.publish.getPlatformAuthUrl('douyin')

// 处理授权回调
await api.publish.handleAuthCallback('douyin', code)

// 抖音授权
await api.publish.authDouyin()

// 小红书授权
await api.publish.authXiaohongshu()

// 视频号授权
await api.publish.authChannels()
```

#### 发布辅助

```javascript
// 获取平台规则
const rules = await api.publish.getPlatformRules('douyin')

// 检查内容是否符合规则
const checkRes = await api.publish.checkContentRules('douyin', contentTaskId)

// 批量发布
await api.publish.batchPublish([
  { content_task_id: 1, platforms: ['douyin'] },
  { content_task_id: 2, platforms: ['xiaohongshu'] }
])

// 获取发布预览
const preview = await api.publish.getPublishPreview({
  content_task_id: 123,
  platform: 'douyin'
})

// 获取热门标签
const tags = await api.publish.getHotTags('douyin', 'food')

// 获取最佳发布时间
const bestTime = await api.publish.getBestPublishTime('douyin')

// 获取发布统计
const stats = await api.publish.getPublishStats({
  startDate: '2024-01-01',
  endDate: '2024-01-31'
})
```

## 高级功能

### 自定义请求

```javascript
import { request } from '@/api'

// GET请求
const res = await request.get('/api/custom/endpoint', {
  param1: 'value1'
})

// POST请求
const res = await request.post('/api/custom/endpoint', {
  data1: 'value1'
})

// PUT请求
const res = await request.put('/api/custom/endpoint', {
  data1: 'value1'
})

// DELETE请求
const res = await request.delete('/api/custom/endpoint')

// 上传文件
const res = await request.upload('/api/upload', filePath, {
  extra: 'data'
})

// 下载文件
const tempPath = await request.download('/api/download/file')
```

### Token管理

```javascript
// 获取Token
const token = request.getToken()

// 设置Token
request.setToken('new_token')

// 清除Token
request.clearToken()
```

### 请求配置

```javascript
// 单个请求自定义配置
await request.get('/api/endpoint', data, {
  timeout: 60000,           // 超时时间
  showLoading: false,       // 不显示loading
  loadingText: '请稍候...',  // loading文本
  enableRetry: false,       // 禁用重试
  retryCount: 3,           // 重试次数
  retryDelay: 2000,        // 重试延迟
  header: {                // 自定义请求头
    'Custom-Header': 'value'
  }
})
```

## 错误处理

### 错误类型

1. **网络错误**：无网络、请求超时
2. **HTTP错误**：404、500等HTTP状态码错误
3. **业务错误**：接口返回的业务错误码
4. **Token错误**：401、403等认证错误

### 错误处理示例

```javascript
try {
  const res = await api.auth.login(code)
  // 处理成功
} catch (error) {
  // 错误已经在底层统一处理并显示提示
  // 这里可以做额外的业务处理
  console.error('登录失败', error)

  if (error.code === 401) {
    // Token过期，跳转登录
  } else if (error.code === 500) {
    // 服务器错误
  }
}
```

## 平台差异处理

### 条件编译

请求封装内部已经处理了平台差异，自动添加平台标识。

```javascript
// 微信小程序特有功能
// #ifdef MP-WEIXIN
await api.nfc.initNFC()
// #endif

// H5特有功能
// #ifdef H5
await api.auth.phoneLogin(phone, code)
// #endif

// 跨平台通用
await api.auth.getUserInfo()
```

### 平台标识

所有请求自动携带 `X-Platform` 请求头：
- `wechat`：微信小程序
- `alipay`：支付宝小程序
- `h5`：H5网页
- `app`：APP应用

## 最佳实践

### 1. 统一错误处理

```javascript
// 在页面中统一捕获错误
export default {
  methods: {
    async loadData() {
      try {
        const res = await api.content.getTaskList()
        this.list = res.list
      } catch (e) {
        // 错误已经由底层统一处理
        // 这里只需要做业务相关的处理
      }
    }
  }
}
```

### 2. 加载状态管理

```javascript
export default {
  data() {
    return {
      loading: false
    }
  },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const res = await api.content.getTaskList()
        this.list = res.list
      } finally {
        this.loading = false
      }
    }
  }
}
```

### 3. 轮询任务状态

```javascript
export default {
  methods: {
    async pollTaskStatus(taskId) {
      const timer = setInterval(async () => {
        try {
          const res = await api.content.getTaskStatus(taskId)

          if (res.status === 'COMPLETED') {
            clearInterval(timer)
            // 任务完成处理
          } else if (res.status === 'FAILED') {
            clearInterval(timer)
            // 任务失败处理
          }
        } catch (e) {
          clearInterval(timer)
        }
      }, 2000)
    }
  }
}
```

### 4. 并发请求

```javascript
export default {
  async onLoad() {
    try {
      // 并发请求多个接口
      const [userInfo, deviceList, taskList] = await Promise.all([
        api.auth.getUserInfo(),
        api.nfc.getDeviceList(),
        api.content.getTaskList()
      ])

      this.userInfo = userInfo
      this.deviceList = deviceList
      this.taskList = taskList
    } catch (e) {
      console.error('加载失败', e)
    }
  }
}
```

## 常见问题

### 1. Token过期怎么处理？

答：Token过期会自动刷新，无需手动处理。如果刷新失败会自动跳转到登录页。

### 2. 如何取消loading提示？

答：在单个请求中设置 `showLoading: false`

```javascript
await api.auth.getUserInfo({}, { showLoading: false })
```

### 3. 如何修改超时时间？

答：可以在配置文件中全局修改，也可以在单个请求中设置

```javascript
await api.content.getTaskStatus(taskId, {
  timeout: 60000
})
```

### 4. 如何处理文件上传？

答：使用 `request.upload` 方法

```javascript
const res = await request.upload('/api/upload', filePath, {
  type: 'image'
})
```

### 5. 如何在H5中使用？

答：H5环境会自动处理，部分平台特有功能（如NFC）在H5中不可用，已提供降级方案（扫码）。

## 注意事项

1. 所有API调用都返回Promise，建议使用async/await
2. 错误已经在底层统一处理，try-catch主要用于业务逻辑处理
3. Token会自动管理，无需手动设置
4. 跨平台使用时注意条件编译
5. 文件操作需要用户授权相册/存储权限

## 更新日志

### v1.0.0 (2024-01-01)
- 初始版本发布
- 支持认证、NFC、内容生成、发布四大模块
- 完整的Token管理和错误处理
- 支持微信小程序、H5、支付宝小程序、APP多端
