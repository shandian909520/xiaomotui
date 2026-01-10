# FeedbackHelper 使用文档

## 概述

FeedbackHelper 是一个综合性的用户反馈工具类，提供视觉、听觉和触觉的成功反馈机制，优化用户体验。

## 功能特性

### 1. 多模态反馈
- ✅ **视觉反馈**: Toast提示、图标显示
- ✅ **触觉反馈**: 震动模式（成功/警告/错误）
- ✅ **听觉反馈**: 声音提示（H5平台）

### 2. 跨平台支持
- 微信小程序
- H5网页
- APP原生
- 支付宝小程序

### 3. 便捷方法
预置常用场景的快捷方法，减少重复代码

## 导入方式

```javascript
import FeedbackHelper from '@/utils/feedbackHelper.js'
```

## API 文档

### 基础方法

#### `success(message, options)`
显示成功反馈

**参数：**
- `message` (String): 成功消息，默认 '操作成功'
- `options` (Object): 可选配置
  - `vibrate` (Boolean): 是否震动，默认 `true`
  - `sound` (Boolean): 是否播放声音，默认 `false`
  - `duration` (Number): Toast显示时长，默认 `2000ms`
  - `icon` (String): 图标类型，默认 `'success'`

**示例：**
```javascript
// 基础用法
FeedbackHelper.success('保存成功')

// 自定义配置
FeedbackHelper.success('操作完成', {
  vibrate: true,
  duration: 1500,
  icon: 'success'
})
```

#### `warning(message, options)`
显示警告反馈

**参数：**
- `message` (String): 警告消息，默认 '请注意'
- `options` (Object): 可选配置
  - `vibrate` (Boolean): 是否震动，默认 `true`
  - `duration` (Number): 显示时长，默认 `2500ms`

**示例：**
```javascript
FeedbackHelper.warning('请填写完整信息', { vibrate: true })
```

#### `error(message, options)`
显示错误反馈

**参数：**
- `message` (String): 错误消息，默认 '操作失败'
- `options` (Object): 可选配置
  - `vibrate` (Boolean): 是否震动，默认 `true`
  - `duration` (Number): 显示时长，默认 `3000ms`

**示例：**
```javascript
FeedbackHelper.error('提交失败，请重试', {
  vibrate: true,
  duration: 3000
})
```

#### `loading(message)`
显示加载反馈

**参数：**
- `message` (String): 加载消息，默认 '加载中...'

**示例：**
```javascript
FeedbackHelper.loading('正在提交...')

// 完成后隐藏
FeedbackHelper.hideLoading()
```

#### `confirm(title, content, options)`
显示确认对话框

**参数：**
- `title` (String): 标题，默认 '提示'
- `content` (String): 内容
- `options` (Object): 可选配置
  - `confirmText` (String): 确认按钮文字，默认 '确定'
  - `cancelText` (String): 取消按钮文字，默认 '取消'
  - `confirmColor` (String): 确认按钮颜色，默认 '#007AFF'
  - `cancelColor` (String): 取消按钮颜色，默认 '#666666'

**返回值：** Promise<Boolean>

**示例：**
```javascript
const confirmed = await FeedbackHelper.confirm(
  '确认删除',
  '删除后无法恢复，确定要删除吗？',
  {
    confirmText: '删除',
    confirmColor: '#FF3B30'
  }
)

if (confirmed) {
  // 执行删除操作
}
```

### 震动反馈

#### `vibrate(type)`
触发震动反馈

**参数：**
- `type` (String): 反馈类型
  - `'success'`: 短震一次 (50ms)
  - `'warning'`: 两次短震 (50-100-50ms)
  - `'error'`: 强震 (100-50-100ms)

**示例：**
```javascript
FeedbackHelper.vibrate('success')
```

### 导航辅助方法

#### `successAndNavigate(message, url, delay)`
成功反馈后自动跳转

**参数：**
- `message` (String): 成功消息
- `url` (String): 跳转URL
- `delay` (Number): 延迟时长（毫秒），默认 `1500ms`

**示例：**
```javascript
FeedbackHelper.successAndNavigate(
  '任务创建成功',
  '/pages/nfc/trigger',
  1500
)
```

#### `successAndBack(message, delay, delta)`
成功反馈后返回上一页

**参数：**
- `message` (String): 成功消息
- `delay` (Number): 延迟时长（毫秒），默认 `1500ms`
- `delta` (Number): 返回层数，默认 `1`

**示例：**
```javascript
FeedbackHelper.successAndBack('保存成功', 1500, 1)
```

#### `successAndRefresh(message, callback, delay)`
成功反馈后刷新页面

**参数：**
- `message` (String): 成功消息
- `callback` (Function): 刷新回调函数
- `delay` (Number): 延迟时长（毫秒），默认 `1000ms`

**示例：**
```javascript
FeedbackHelper.successAndRefresh('更新成功', () => {
  this.loadData()
}, 1000)
```

### 便捷方法

以下是预置的便捷方法，内置最佳实践的反馈配置：

#### `saveSuccess()`
保存成功反馈
```javascript
FeedbackHelper.saveSuccess()
// 等同于: success('保存成功', { vibrate: true })
```

#### `deleteSuccess()`
删除成功反馈
```javascript
FeedbackHelper.deleteSuccess()
// 等同于: success('删除成功', { vibrate: true })
```

#### `copySuccess(content)`
复制成功反馈（自动复制到剪贴板）
```javascript
FeedbackHelper.copySuccess('13800138000')
// 自动复制并显示提示
```

#### `submitSuccess()`
提交成功反馈
```javascript
FeedbackHelper.submitSuccess()
```

#### `publishSuccess()`
发布成功反馈
```javascript
FeedbackHelper.publishSuccess()
```

#### `bindSuccess()`
绑定成功反馈
```javascript
FeedbackHelper.bindSuccess()
```

#### `cancelSuccess()`
取消成功反馈（不震动）
```javascript
FeedbackHelper.cancelSuccess()
// 等同于: success('已取消', { vibrate: false, icon: 'none' })
```

#### `shareSuccess()`
分享成功反馈
```javascript
FeedbackHelper.shareSuccess()
```

## 使用场景示例

### 场景1: 表单提交

```javascript
// 校验失败
if (!this.form.name) {
  FeedbackHelper.warning('请输入姓名', { vibrate: true })
  return
}

// 提交中
FeedbackHelper.loading('正在提交...')

try {
  await api.submit(this.form)
  FeedbackHelper.hideLoading()

  // 提交成功并返回
  FeedbackHelper.successAndBack('提交成功', 1500)
} catch (error) {
  FeedbackHelper.hideLoading()
  FeedbackHelper.error(error.message || '提交失败', {
    vibrate: true,
    duration: 3000
  })
}
```

### 场景2: 删除确认

```javascript
async deleteItem(item) {
  const confirmed = await FeedbackHelper.confirm(
    '确认删除',
    `确定要删除"${item.name}"吗？`,
    {
      confirmText: '删除',
      confirmColor: '#FF3B30'
    }
  )

  if (confirmed) {
    try {
      await api.delete(item.id)
      FeedbackHelper.deleteSuccess()
      this.loadList()
    } catch (error) {
      FeedbackHelper.error('删除失败')
    }
  }
}
```

### 场景3: 数据保存

```javascript
async saveData() {
  if (!this.validate()) {
    FeedbackHelper.warning('请填写完整信息', { vibrate: true })
    return
  }

  FeedbackHelper.loading('保存中...')

  try {
    await api.save(this.data)
    FeedbackHelper.hideLoading()
    FeedbackHelper.saveSuccess()
  } catch (error) {
    FeedbackHelper.hideLoading()
    FeedbackHelper.error('保存失败')
  }
}
```

### 场景4: 复制分享

```javascript
// 复制文本
copyText() {
  FeedbackHelper.copySuccess(this.shareUrl)
}

// 分享成功回调
onShareSuccess() {
  FeedbackHelper.shareSuccess()
}
```

### 场景5: 任务创建

```javascript
async createTask() {
  try {
    const res = await api.createTask(this.taskData)

    // 成功后跳转到任务详情
    FeedbackHelper.successAndNavigate(
      '任务创建成功',
      `/pages/task/detail?id=${res.id}`,
      1500
    )
  } catch (error) {
    FeedbackHelper.error('创建失败，请重试')
  }
}
```

## 平台差异说明

### 震动反馈
- **微信小程序/支付宝小程序**: 使用 `uni.vibrateShort()`
- **H5**: 使用 `navigator.vibrate()`
- **APP**: 根据平台自动适配

### 声音反馈
- **H5**: 使用 Web Audio API 生成音效
- **小程序/APP**: 暂不支持声音反馈

### 条件编译
工具内部使用了条件编译，自动适配不同平台：
```javascript
// #ifdef MP-WEIXIN
// 微信小程序专用代码
// #endif

// #ifdef H5
// H5专用代码
// #endif
```

## 最佳实践

### 1. 反馈及时性
操作完成后立即显示反馈，不要延迟：
```javascript
// ✅ 推荐
FeedbackHelper.saveSuccess()

// ❌ 不推荐
setTimeout(() => {
  FeedbackHelper.saveSuccess()
}, 500)
```

### 2. 震动使用
- 成功操作：使用震动增强反馈
- 信息提示：不使用震动
- 错误警告：使用强震动

```javascript
// 成功操作 - 震动
FeedbackHelper.saveSuccess()

// 信息提示 - 不震动
FeedbackHelper.success('查看详情', { vibrate: false })

// 错误警告 - 震动
FeedbackHelper.error('操作失败', { vibrate: true })
```

### 3. 消息文案
- 简洁明了，5-10字为佳
- 说明结果，而非过程
- 避免技术术语

```javascript
// ✅ 推荐
FeedbackHelper.success('保存成功')
FeedbackHelper.error('网络异常，请重试')

// ❌ 不推荐
FeedbackHelper.success('数据已成功写入数据库')
FeedbackHelper.error('Error: Network timeout')
```

### 4. 加载状态管理
记得在操作完成或失败后隐藏加载状态：

```javascript
FeedbackHelper.loading('加载中...')

try {
  await api.getData()
  FeedbackHelper.hideLoading()
  FeedbackHelper.success('加载完成')
} catch (error) {
  FeedbackHelper.hideLoading()  // 确保隐藏
  FeedbackHelper.error('加载失败')
}
```

## 已集成页面

### ✅ 内容生成页面 (content/generate.vue)
- 添加关键词成功
- 表单重置
- 配置校验
- 任务创建成功（带跳转）
- 创建失败错误

### ✅ 设备管理页面 (merchant/devices.vue)
- 表单校验警告
- 设备保存成功
- 设备添加成功
- 设备删除成功
- 查看详情提示

### ✅ NFC触发页面 (nfc/trigger.vue)
- 扫码失败
- 设备码无效
- 设备信息加载
- 触发成功
- 生成完成
- 任务取消
- 复制微信号
- 商家信息不可用

## 注意事项

1. **导入路径**: 确保正确导入 `@/utils/feedbackHelper.js`
2. **加载状态**: 使用 `loading()` 后必须调用 `hideLoading()`
3. **震动权限**: 某些平台可能需要用户授权震动权限
4. **声音反馈**: 仅H5支持，且需要用户交互后才能播放
5. **跳转延迟**: `successAndNavigate` 默认延迟1.5秒，可根据需要调整

## 后续优化计划

- [ ] 添加自定义音效支持
- [ ] 支持更多震动模式
- [ ] 添加动画效果配置
- [ ] 支持国际化
- [ ] 添加埋点统计

## 问题反馈

如遇到问题或有改进建议，请联系开发团队。
