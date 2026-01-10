# 内容预览页面文档

## 概述

内容预览页面（`pages/content/preview.vue`）是小魔推碰一碰系统中用于展示AI生成内容的核心页面，支持视频、图片、文案等多种内容类型的预览，并提供下载、保存、分享、重新生成和发布等完整功能。

## 功能特性

### 核心功能

1. **多类型内容预览**
   - 视频内容：支持播放控制、进度显示、全屏播放
   - 图片内容：支持高清展示、点击预览、缩放查看
   - 文案内容：支持长文展示、格式保持、一键复制

2. **内容信息展示**
   - 内容标题和描述
   - 生成状态（等待中/生成中/已完成/失败）
   - 生成时间和耗时
   - AI服务商信息
   - 文件大小和时长
   - 内容标签

3. **视频播放功能**
   - 播放/暂停控制
   - 进度条拖动
   - 全屏播放
   - 音量控制
   - 播放速度调节
   - 播放状态监听

4. **下载和保存**
   - 下载到本地存储
   - 保存到系统相册
   - 多平台兼容处理
   - 权限申请引导

5. **分享功能**
   - 微信小程序分享
   - H5网页分享
   - APP原生分享
   - 自定义分享内容

6. **内容操作**
   - 复制营销文案
   - 重新生成内容
   - 立即发布到平台
   - 取消和返回

## 页面路由

### 路由配置

在 `pages.json` 中的配置：

```json
{
  "path": "pages/content/preview",
  "style": {
    "navigationBarTitleText": "内容预览",
    "navigationBarBackgroundColor": "#6366f1",
    "navigationBarTextStyle": "white"
  }
}
```

### 参数传递

页面通过URL参数接收任务ID：

```javascript
// 跳转到预览页面
uni.navigateTo({
  url: `/pages/content/preview?task_id=${taskId}`
})

// 页面接收参数
onLoad(options) {
  this.taskId = options.task_id
}
```

**必传参数：**
- `task_id`: 内容生成任务ID（必需）

## API调用

### 获取内容详情

```javascript
const res = await api.content.getTaskDetail(taskId)
```

**返回数据结构：**

```javascript
{
  id: 123,
  type: 'VIDEO', // VIDEO/IMAGE/TEXT
  status: 'COMPLETED', // PENDING/PROCESSING/COMPLETED/FAILED
  title: '咖啡店探店视频',
  description: '温馨咖啡店环境展示',
  video_url: 'https://example.com/video.mp4',
  poster_url: 'https://example.com/poster.jpg',
  image_url: 'https://example.com/image.jpg',
  text_content: '营销文案内容...',
  file_size: 2048000, // 字节
  duration: 15, // 秒
  ai_provider: '剪映',
  generation_time: 25, // 秒
  create_time: '2024-01-01 12:00:00',
  tags: ['咖啡', '探店', '美食'],
  output_data: {
    // 其他输出数据
  }
}
```

### 保存到相册

```javascript
await api.content.saveToAlbum(fileUrl, 'video') // 或 'image'
```

### 分享内容

```javascript
await api.content.shareContent({
  title: '标题',
  description: '描述',
  url: 'https://...',
  imageUrl: 'https://...'
})
```

### 重新生成

```javascript
const result = await api.content.regenerate(taskId)
// 返回新任务ID
```

### 下载内容

```javascript
await api.content.downloadContent(taskId, savePath)
```

## 平台兼容性

### H5平台

**特殊处理：**

```javascript
// #ifdef H5
// 下载文件
const a = document.createElement('a')
a.href = fileUrl
a.download = 'filename.mp4'
a.click()

// 分享
if (navigator.share) {
  await navigator.share({
    title: '标题',
    url: location.href
  })
}
// #endif
```

**限制：**
- 无法直接保存到相册
- 分享功能依赖浏览器支持
- 下载行为受浏览器策略影响

### 微信小程序

**特殊处理：**

```javascript
// #ifdef MP-WEIXIN
// 下载并保存
const downloadRes = await uni.downloadFile({ url: fileUrl })
await uni.saveVideoToPhotosAlbum({
  filePath: downloadRes.tempFilePath
})

// 分享
uni.showShareMenu({
  withShareTicket: true,
  menus: ['shareAppMessage', 'shareTimeline']
})
// #endif
```

**权限申请：**
- `scope.writePhotosAlbum`: 保存到相册权限

### 支付宝小程序

**特殊处理：**

```javascript
// #ifdef MP-ALIPAY
// 类似微信小程序，使用支付宝API
// #endif
```

### APP平台

**特殊处理：**

```javascript
// #ifdef APP-PLUS
// 使用原生下载
plus.downloader.createDownload(url, {
  filename: '_downloads/video.mp4'
})

// 原生分享
plus.share.sendWithSystem({
  type: 'video',
  href: fileUrl
})
// #endif
```

## 使用示例

### 基本流程

```javascript
// 1. 从NFC触发页面跳转
// pages/nfc/trigger.vue
handleViewContent() {
  uni.navigateTo({
    url: `/pages/content/preview?task_id=${this.taskId}`
  })
}

// 2. 预览页面加载内容
// pages/content/preview.vue
async loadContentData() {
  const res = await api.content.getTaskDetail(this.taskId)
  this.contentData = res
}

// 3. 用户操作
// 下载
await this.downloadVideo()

// 保存到相册
await this.saveToAlbum()

// 分享
await this.shareContent()

// 重新生成
const newTask = await api.content.regenerate(this.taskId)
uni.redirectTo({
  url: `/pages/nfc/trigger?task_id=${newTask.task_id}`
})

// 发布
uni.navigateTo({
  url: `/pages/publish/settings?task_id=${this.taskId}`
})
```

### 完整示例代码

参考 `pages/content/preview-demo.vue` 文件查看完整的使用演示。

## 页面状态

### 加载状态

```javascript
data() {
  return {
    isLoading: false,
    loadingText: '加载中...'
  }
}

// 显示加载
this.isLoading = true
this.loadingText = '下载中...'

// 隐藏加载
this.isLoading = false
```

### 视频播放状态

```javascript
data() {
  return {
    currentTime: 0, // 当前播放时间
    duration: 0, // 总时长
    isPlaying: false // 是否正在播放
  }
}

// 监听播放事件
onVideoPlay() {
  this.isPlaying = true
}

onVideoPause() {
  this.isPlaying = false
}

onTimeUpdate(e) {
  this.currentTime = e.detail.currentTime
  this.duration = e.detail.duration
}
```

## 错误处理

### 视频播放错误

```javascript
onVideoError(e) {
  console.error('视频播放错误:', e)
  uni.showToast({
    title: '视频加载失败',
    icon: 'none'
  })
}
```

### 下载失败处理

```javascript
try {
  await this.downloadVideo()
} catch (error) {
  if (error.errMsg && error.errMsg.includes('auth deny')) {
    // 权限被拒绝
    uni.showModal({
      title: '提示',
      content: '需要授权访问相册',
      success: (res) => {
        if (res.confirm) {
          uni.openSetting() // 打开设置页面
        }
      }
    })
  } else {
    uni.showToast({
      title: '下载失败',
      icon: 'none'
    })
  }
}
```

### 网络错误处理

```javascript
async loadContentData() {
  try {
    const res = await api.content.getTaskDetail(this.taskId)
    this.contentData = res
  } catch (error) {
    uni.showModal({
      title: '加载失败',
      content: error.message || '网络异常，请稍后重试',
      confirmText: '返回',
      showCancel: false,
      success: () => {
        uni.navigateBack()
      }
    })
  }
}
```

## 工具函数

### 格式化时长

```javascript
formatDuration(seconds) {
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${mins}:${secs.toString().padStart(2, '0')}`
}
```

### 格式化文件大小

```javascript
formatFileSize(bytes) {
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / 1024 / 1024).toFixed(1) + 'MB'
}
```

### 格式化时间

```javascript
formatTime(timeStr) {
  const date = new Date(timeStr)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return '刚刚'
  if (diff < 3600000) return Math.floor(diff / 60000) + '分钟前'

  if (date.toDateString() === now.toDateString()) {
    return '今天 ' + date.toLocaleTimeString('zh-CN', {
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  return date.toLocaleDateString('zh-CN')
}
```

### 格式化状态

```javascript
formatStatus(status) {
  const statusMap = {
    'PENDING': '等待中',
    'PROCESSING': '生成中',
    'COMPLETED': '已完成',
    'FAILED': '失败'
  }
  return statusMap[status] || status
}
```

## 样式设计

### 主要颜色

```scss
$primary-color: #6366f1;
$primary-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
$success-color: #10b981;
$error-color: #ef4444;
$text-primary: #1f2937;
$text-secondary: #6b7280;
$background: #f8f9fa;
```

### 响应式布局

```scss
// 视频播放器
.video-player {
  width: 100%;
  height: 600rpx; // 固定高度
}

// 操作按钮网格布局
.action-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20rpx;
}
```

### 动画效果

```scss
// 加载动画
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.loading-spinner {
  animation: spin 1s linear infinite;
}
```

## 性能优化

### 图片懒加载

```vue
<image
  :src="imageUrl"
  lazy-load
  mode="aspectFit"
/>
```

### 条件渲染

```vue
<view v-if="contentData.type === 'VIDEO'">
  <!-- 只在视频类型时渲染 -->
</view>
```

### 数据缓存

```javascript
// 缓存内容数据，避免重复请求
if (this.contentData.id === this.taskId) {
  return this.contentData
}
```

## 常见问题

### Q1: 视频无法播放？

**解决方案：**
1. 检查视频URL是否有效
2. 确认视频格式是否支持（推荐MP4）
3. 检查网络连接
4. 查看控制台错误信息

### Q2: 保存到相册失败？

**解决方案：**
1. 检查是否授权相册权限
2. 使用 `uni.openSetting()` 引导用户授权
3. 确认文件格式正确
4. 检查存储空间是否充足

### Q3: 分享功能不生效？

**解决方案：**
1. 微信小程序：配置 `onShareAppMessage` 方法
2. H5：检查浏览器是否支持 Web Share API
3. APP：确认已集成原生分享插件

### Q4: 下载速度慢？

**解决方案：**
1. 使用CDN加速
2. 压缩视频文件大小
3. 提供多清晰度选项
4. 显示下载进度

### Q5: 页面加载失败？

**解决方案：**
1. 检查task_id参数是否正确
2. 确认任务状态是否为COMPLETED
3. 验证API接口是否正常
4. 查看网络请求日志

## 开发建议

### 最佳实践

1. **错误处理**：所有异步操作都应该有try-catch
2. **加载提示**：耗时操作显示loading状态
3. **权限申请**：提前申请必要权限，提供引导
4. **用户反馈**：操作完成后给予明确提示
5. **数据校验**：检查数据完整性和有效性

### 代码规范

1. 使用ES6+语法
2. 合理使用async/await
3. 组件化开发
4. 注释清晰完整
5. 遵循uni-app规范

### 测试要点

1. 多平台兼容性测试
2. 不同内容类型测试
3. 网络异常场景测试
4. 权限拒绝场景测试
5. 边界情况测试

## 更新日志

### v1.0.0 (2024-10-01)

- 初始版本发布
- 支持视频、图片、文案预览
- 实现下载、保存、分享功能
- 多平台兼容处理
- 完整的错误处理机制

## 相关页面

- [NFC触发页面](../nfc/trigger.vue)
- [内容生成页面](./generate.vue)
- [发布设置页面](../publish/settings.vue)

## 联系方式

如有问题或建议，请联系开发团队。

---

**小魔推碰一碰** - 让营销更简单
