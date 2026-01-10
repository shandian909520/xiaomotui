# 登录功能测试示例

## 快速测试

### 测试账号
根据系统要求，可以使用以下测试账号：
- 手机号: 13800138000
- 手机号: 13800000000

## 测试场景

### 场景1: 微信小程序登录流程测试

1. **打开登录页**
   ```
   访问: /pages/auth/index
   ```

2. **检查页面显示**
   - [ ] Logo和标题正确显示
   - [ ] 显示"微信一键登录"按钮
   - [ ] 隐私协议复选框显示
   - [ ] 平台信息显示"当前平台：微信小程序"

3. **未勾选隐私协议点击登录**
   - [ ] 应该提示"请先阅读并同意隐私政策和用户协议"
   - [ ] 不触发登录流程

4. **勾选隐私协议后点击登录**
   - [ ] 按钮显示loading状态
   - [ ] 按钮文字变为"登录中..."
   - [ ] 按钮处于禁用状态

5. **登录成功**
   - [ ] 显示"登录成功"toast提示
   - [ ] 1.5秒后自动跳转到首页
   - [ ] Storage中保存了token
   - [ ] Storage中保存了用户信息

6. **验证登录状态**
   打开开发者工具Console，输入：
   ```javascript
   // 查看Storage
   console.log('Token:', uni.getStorageSync('xiaomotui_token'))
   console.log('用户信息:', uni.getStorageSync('xiaomotui_user_info'))
   ```

### 场景2: 登录状态持久化测试

1. **完成登录后刷新页面**
   - [ ] 用户仍然保持登录状态
   - [ ] 不需要重新登录

2. **清除Token后访问受保护页面**
   ```javascript
   // 开发者工具Console执行
   uni.removeStorageSync('xiaomotui_token')
   ```
   - [ ] 访问需要登录的页面时自动跳转到登录页

### 场景3: Token过期测试

1. **修改Token过期时间**
   ```javascript
   // 开发者工具Console执行
   // 设置为已过期
   uni.setStorageSync('xiaomotui_token_expires', Date.now() - 1000)
   ```

2. **刷新页面**
   - [ ] Token应该被自动清除
   - [ ] isLoggedIn状态应该为false

3. **访问需要登录的页面**
   - [ ] 自动跳转到登录页

### 场景4: 重定向功能测试

1. **未登录状态访问个人中心**
   ```
   访问: /pages/user/profile
   ```
   - [ ] 应该跳转到登录页
   - [ ] URL包含redirect参数: `/pages/auth/index?redirect=%2Fpages%2Fuser%2Fprofile`

2. **完成登录**
   - [ ] 自动跳转回个人中心页面
   - [ ] 不需要手动导航

### 场景5: 用户信息显示测试

创建测试页面 `pages/test/auth-test.vue`:

```vue
<template>
  <view class="container">
    <view class="section">
      <text class="title">登录状态测试</text>

      <view class="info-item">
        <text>是否已登录: </text>
        <text :class="userStore.isLoggedIn ? 'text-success' : 'text-danger'">
          {{ userStore.isLoggedIn ? '是' : '否' }}
        </text>
      </view>

      <view class="info-item">
        <text>Token有效性: </text>
        <text :class="userStore.isTokenValid ? 'text-success' : 'text-danger'">
          {{ userStore.isTokenValid ? '有效' : '无效' }}
        </text>
      </view>

      <view class="info-item">
        <text>Token: </text>
        <text class="token-text">{{ userStore.token || '未登录' }}</text>
      </view>
    </view>

    <view class="section">
      <text class="title">用户信息</text>

      <view class="info-item">
        <text>用户ID: </text>
        <text>{{ userStore.userInfo.id || '-' }}</text>
      </view>

      <view class="info-item">
        <text>昵称: </text>
        <text>{{ userStore.displayName }}</text>
      </view>

      <view class="info-item">
        <text>OpenID: </text>
        <text class="token-text">{{ userStore.userInfo.openid || '-' }}</text>
      </view>

      <view class="info-item">
        <text>手机号: </text>
        <text>{{ userStore.userInfo.phone || '未绑定' }}</text>
      </view>

      <view class="info-item">
        <text>用户角色: </text>
        <text>{{ userStore.userInfo.role || '-' }}</text>
      </view>

      <view class="info-item">
        <text>会员等级: </text>
        <text>{{ userStore.userInfo.member_level || '-' }}</text>
      </view>

      <view class="info-item">
        <text>是否是商户: </text>
        <text :class="userStore.isMerchant ? 'text-success' : 'text-muted'">
          {{ userStore.isMerchant ? '是' : '否' }}
        </text>
      </view>

      <view class="info-item">
        <text>商户ID: </text>
        <text>{{ userStore.userInfo.merchant_id || '-' }}</text>
      </view>

      <view class="info-item">
        <text>登录平台: </text>
        <text>{{ platformName }}</text>
      </view>
    </view>

    <view class="section">
      <text class="title">功能测试</text>

      <button class="btn-primary" @tap="handleRefreshUserInfo">
        刷新用户信息
      </button>

      <button class="btn-primary" @tap="handleCheckLogin" style="margin-top: 20rpx;">
        检查登录状态
      </button>

      <button class="btn-primary" @tap="handleToLogin" style="margin-top: 20rpx;">
        跳转登录页
      </button>

      <button class="btn-secondary" @tap="handleLogout" style="margin-top: 20rpx;">
        退出登录
      </button>
    </view>

    <view class="section">
      <text class="title">Storage数据</text>
      <button class="btn-primary" @tap="handleViewStorage">
        查看Storage
      </button>
      <view v-if="storageData" class="storage-data">
        <text>{{ storageData }}</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { useUserStore } from '@/stores/user.js'
import {
  navigateToLogin,
  logout,
  getPlatformName,
  requireLogin
} from '@/utils/auth.js'

const userStore = useUserStore()
const platformName = ref(getPlatformName())
const storageData = ref('')

// 刷新用户信息
async function handleRefreshUserInfo() {
  try {
    await userStore.refreshUserInfo()
    uni.showToast({
      title: '刷新成功',
      icon: 'success'
    })
  } catch (error) {
    console.error('刷新用户信息失败', error)
    uni.showToast({
      title: '刷新失败',
      icon: 'none'
    })
  }
}

// 检查登录状态
function handleCheckLogin() {
  const isLogin = userStore.checkLoginStatus()
  uni.showModal({
    title: '登录状态',
    content: isLogin ? '已登录' : '未登录',
    showCancel: false
  })
}

// 跳转登录页
function handleToLogin() {
  navigateToLogin()
}

// 退出登录
async function handleLogout() {
  uni.showModal({
    title: '提示',
    content: '确定要退出登录吗？',
    success: async (res) => {
      if (res.confirm) {
        await logout()
      }
    }
  })
}

// 查看Storage
function handleViewStorage() {
  const data = {
    token: uni.getStorageSync('xiaomotui_token'),
    tokenExpires: uni.getStorageSync('xiaomotui_token_expires'),
    userInfo: uni.getStorageSync('xiaomotui_user_info'),
    platform: uni.getStorageSync('xiaomotui_platform'),
    userStore: uni.getStorageSync('xiaomotui_user_store')
  }
  storageData.value = JSON.stringify(data, null, 2)
  console.log('Storage数据:', data)
}
</script>

<style lang="scss" scoped>
.container {
  padding: 30rpx;
}

.section {
  background: #ffffff;
  border-radius: 12rpx;
  padding: 30rpx;
  margin-bottom: 30rpx;
}

.title {
  font-size: 32rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 30rpx;
  display: block;
}

.info-item {
  display: flex;
  align-items: center;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
  font-size: 28rpx;

  &:last-child {
    border-bottom: none;
  }

  text:first-child {
    color: #666;
    width: 180rpx;
    flex-shrink: 0;
  }

  text:last-child {
    color: #333;
    flex: 1;
    word-break: break-all;
  }
}

.token-text {
  font-size: 24rpx;
  color: #999;
  font-family: monospace;
}

.storage-data {
  margin-top: 20rpx;
  padding: 20rpx;
  background: #f5f5f5;
  border-radius: 8rpx;
  font-size: 24rpx;
  font-family: monospace;
  color: #333;
  word-break: break-all;
}
</style>
```

### 场景6: API请求携带Token测试

1. **确认Token已正确设置**
   - 登录成功后检查Storage

2. **发起需要认证的API请求**
   ```javascript
   // 在测试页面中执行
   import authApi from '@/api/modules/auth.js'

   // 获取用户信息
   const userInfo = await authApi.getUserInfo()
   console.log('用户信息:', userInfo)
   ```

3. **检查请求头**
   - 打开网络面板
   - 查看请求头是否包含: `Authorization: Bearer {token}`
   - 查看请求头是否包含: `X-Platform: wechat`

### 场景7: 错误处理测试

1. **网络错误测试**
   - 关闭后端服务
   - 尝试登录
   - [ ] 应该显示网络错误提示

2. **登录失败测试**
   - 使用无效的code
   - [ ] 应该显示登录失败提示
   - [ ] 不保存Token
   - [ ] 不跳转页面

3. **Token过期测试**
   - 使用过期的Token访问API
   - [ ] 应该收到401状态码
   - [ ] 自动清除Token
   - [ ] 跳转到登录页

## 自动化测试脚本

可以在开发者工具Console中运行以下测试脚本：

```javascript
// 测试脚本
async function testAuth() {
  console.log('=== 开始测试登录功能 ===')

  // 1. 检查Storage
  console.log('\n1. 检查Storage')
  const token = uni.getStorageSync('xiaomotui_token')
  const userInfo = uni.getStorageSync('xiaomotui_user_info')
  console.log('Token:', token ? '存在' : '不存在')
  console.log('用户信息:', userInfo ? '存在' : '不存在')

  // 2. 检查Store
  console.log('\n2. 检查Pinia Store')
  const { useUserStore } = await import('@/stores/user.js')
  const userStore = useUserStore()
  console.log('isLoggedIn:', userStore.isLoggedIn)
  console.log('isTokenValid:', userStore.isTokenValid)
  console.log('用户ID:', userStore.userInfo.id)
  console.log('用户昵称:', userStore.displayName)

  // 3. 测试工具函数
  console.log('\n3. 测试工具函数')
  const auth = await import('@/utils/auth.js')
  console.log('isLoggedIn():', auth.isLoggedIn())
  console.log('isTokenValid():', auth.isTokenValid())
  console.log('getUserId():', auth.getUserId())
  console.log('getNickname():', auth.getNickname())
  console.log('getCurrentPlatform():', auth.getCurrentPlatform())

  console.log('\n=== 测试完成 ===')
}

// 运行测试
testAuth()
```

## 性能测试

### 1. 登录速度测试

```javascript
async function testLoginSpeed() {
  const startTime = Date.now()

  // 执行登录
  await userStore.wechatLogin()

  const endTime = Date.now()
  const duration = endTime - startTime

  console.log(`登录耗时: ${duration}ms`)

  // 期望: 登录时间应该在3秒以内（包含网络请求）
}
```

### 2. Store初始化速度测试

```javascript
function testStoreInitSpeed() {
  const startTime = performance.now()

  // 初始化用户状态
  userStore.initUserState()

  const endTime = performance.now()
  const duration = endTime - startTime

  console.log(`Store初始化耗时: ${duration.toFixed(2)}ms`)

  // 期望: 初始化时间应该在100ms以内
}
```

## 测试清单

### 功能测试
- [ ] 微信小程序登录
- [ ] 支付宝小程序登录（如果支持）
- [ ] H5手机号登录（如果支持）
- [ ] 隐私协议勾选验证
- [ ] 登录loading状态
- [ ] 登录成功提示
- [ ] 登录失败提示
- [ ] 登录后自动跳转

### Token管理测试
- [ ] Token存储到Storage
- [ ] Token自动添加到请求头
- [ ] Token过期检查
- [ ] Token过期自动清除
- [ ] Token刷新（如果支持）

### 用户信息测试
- [ ] 用户信息存储
- [ ] 用户信息持久化
- [ ] 用户信息刷新
- [ ] 用户信息显示

### 权限测试
- [ ] 角色判断（user/merchant/admin）
- [ ] 商户身份判断
- [ ] 管理员身份判断
- [ ] 权限不足提示

### 导航测试
- [ ] 未登录跳转登录页
- [ ] 登录后重定向
- [ ] 退出登录跳转
- [ ] 带参数重定向

### UI测试
- [ ] 页面布局正确
- [ ] 按钮样式正常
- [ ] Loading动画显示
- [ ] 错误提示显示
- [ ] 响应式布局适配

### 兼容性测试
- [ ] 微信小程序
- [ ] 支付宝小程序
- [ ] H5浏览器
- [ ] 不同手机型号
- [ ] 不同系统版本

## 问题排查

### 问题1: 登录后仍然显示未登录

**可能原因**:
1. Token未正确存储
2. Store未初始化
3. Token格式错误

**排查步骤**:
```javascript
// 1. 检查Storage
console.log('Token:', uni.getStorageSync('xiaomotui_token'))

// 2. 检查Store
const userStore = useUserStore()
console.log('Store Token:', userStore.token)
console.log('Store isLoggedIn:', userStore.isLoggedIn)

// 3. 检查Token格式
const token = userStore.token
console.log('Token长度:', token.length)
console.log('Token开头:', token.substring(0, 20))
```

### 问题2: 请求未携带Token

**可能原因**:
1. Token未设置到request实例
2. 请求拦截器未正确配置

**排查步骤**:
```javascript
// 检查request实例的token
import request from '@/api/request.js'
console.log('Request Token:', request.getToken())
```

### 问题3: 条件编译不生效

**可能原因**:
1. 条件编译标记错误
2. 平台编译配置错误

**检查方法**:
查看编译后的代码，确认条件编译的代码块是否正确包含/排除。

## 测试报告模板

```
测试时间: 2025-10-01
测试人员: [姓名]
测试平台: [微信小程序/支付宝小程序/H5]
测试环境: [开发/测试/生产]

功能测试结果:
✅ 微信小程序登录: 通过
✅ Token管理: 通过
✅ 用户信息管理: 通过
✅ 权限验证: 通过
✅ 页面跳转: 通过

发现问题:
1. [问题描述]
   - 严重程度: [高/中/低]
   - 复现步骤: [步骤]
   - 解决方案: [方案]

总体评价:
[整体测试情况说明]
```
