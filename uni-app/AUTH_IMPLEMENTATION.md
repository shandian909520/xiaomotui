# 多平台授权登录功能实现文档

## 概述

实现了支持微信小程序、支付宝小程序和H5的多平台授权登录功能，使用Pinia进行状态管理，支持Token自动管理和用户信息持久化。

## 功能特性

### 1. 多平台支持

- **微信小程序**: 使用 `uni.login()` 获取微信授权code
- **支付宝小程序**: 使用 `uni.login()` 获取支付宝授权code
- **H5**: 支持手机号+验证码登录方式

### 2. 状态管理

使用Pinia进行全局状态管理，包括：
- Token管理（存储、刷新、过期检查）
- 用户信息管理
- 登录状态管理
- 平台标识管理

### 3. 条件编译

使用uni-app的条件编译功能，针对不同平台编译不同的登录逻辑：

```javascript
// #ifdef MP-WEIXIN
// 微信小程序专属代码
// #endif

// #ifdef MP-ALIPAY
// 支付宝小程序专属代码
// #endif

// #ifdef H5
// H5专属代码
// #endif
```

### 4. Token管理

- 自动存储Token到本地存储
- Token过期时间管理
- Token有效性检查（提前5分钟判定为过期）
- 自动同步Token到请求实例

### 5. 用户体验

- 美观的渐变背景和动画效果
- 加载状态提示
- 错误信息展示
- 隐私协议勾选
- 登录成功后自动跳转

## 文件结构

```
uni-app/
├── stores/
│   ├── index.js              # Pinia配置和持久化插件
│   └── user.js               # 用户状态管理Store
├── utils/
│   └── auth.js               # 认证工具函数
├── pages/
│   └── auth/
│       └── index.vue         # 登录页面组件
├── api/
│   ├── request.js            # 统一请求封装（已存在）
│   └── modules/
│       └── auth.js           # 认证API模块（已存在）
├── main.js                   # 应用入口（已更新）
├── App.vue                   # 应用根组件（已更新）
└── pages.json                # 页面配置（已更新）
```

## 核心代码说明

### 1. Pinia Store (stores/user.js)

**状态定义**:
```javascript
state: () => ({
  token: '',              // 用户Token
  tokenExpires: 0,        // Token过期时间
  userInfo: {...},        // 用户信息
  isLoggedIn: false,      // 是否已登录
  platform: ''            // 登录平台
})
```

**关键方法**:
- `setToken(token, expiresIn)` - 设置Token
- `clearToken()` - 清除Token
- `setUserInfo(userInfo)` - 设置用户信息
- `wechatLogin(extraData)` - 微信登录
- `alipayLogin(extraData)` - 支付宝登录
- `phoneLogin(phone, code)` - 手机号登录
- `logout()` - 退出登录
- `checkLoginStatus()` - 检查登录状态
- `initUserState()` - 初始化用户状态

**计算属性**:
- `isMerchant` - 是否是商户
- `isAdmin` - 是否是管理员
- `isTokenValid` - Token是否有效
- `displayName` - 用户显示名称

### 2. 认证工具函数 (utils/auth.js)

提供便捷的认证相关工具函数：

```javascript
// 检查是否已登录
isLoggedIn()

// 检查Token是否有效
isTokenValid()

// 跳转到登录页
navigateToLogin(redirect)

// 检查登录，未登录则跳转
requireLogin(redirect)

// 检查用户角色
hasRole(roles)
requireRole(roles, message)

// 获取用户信息
getUserInfo()
getUserId()
getNickname()
getAvatar()

// 退出登录
logout()

// 登录成功处理
handleLoginSuccess(redirect)

// 平台相关
getCurrentPlatform()
getPlatformName()
isSupportPlatform(platform)
```

### 3. 登录页面 (pages/auth/index.vue)

**功能特性**:
- 响应式设计，支持多种屏幕尺寸
- 美观的UI界面（渐变背景、动画效果）
- 隐私协议勾选验证
- 加载状态显示
- 错误信息展示
- 支持重定向到登录前页面

**主要方法**:
- `handleWechatLogin()` - 微信登录处理
- `handleAlipayLogin()` - 支付宝登录处理
- `handlePhoneLogin()` - 手机号登录处理
- `handleSendCode()` - 发送验证码
- `handleGetUserInfo()` - 获取微信用户信息
- `handleGetPhoneNumber()` - 获取微信手机号

## 使用指南

### 1. 在页面中检查登录状态

**方法一：使用Store**
```javascript
<script setup>
import { useUserStore } from '@/stores/user.js'

const userStore = useUserStore()

// 检查是否已登录
if (userStore.checkLoginStatus()) {
  console.log('已登录')
  console.log('用户信息:', userStore.userInfo)
}
</script>
```

**方法二：使用工具函数**
```javascript
<script setup>
import { requireLogin, getUserInfo } from '@/utils/auth.js'

onMounted(() => {
  // 检查登录，未登录则跳转
  if (!requireLogin()) {
    return
  }

  // 已登录，继续执行
  const userInfo = getUserInfo()
  console.log('用户信息:', userInfo)
})
</script>
```

### 2. 跳转到登录页

```javascript
import { navigateToLogin } from '@/utils/auth.js'

// 直接跳转
navigateToLogin()

// 带重定向地址
navigateToLogin('/pages/user/profile')
```

### 3. 退出登录

```javascript
import { logout } from '@/utils/auth.js'

// 或者使用Store
import { useUserStore } from '@/stores/user.js'
const userStore = useUserStore()

// 退出登录
await logout()
// 或
await userStore.logout()
```

### 4. 获取用户信息

```javascript
import { useUserStore } from '@/stores/user.js'
const userStore = useUserStore()

// 获取用户信息
console.log(userStore.userInfo)

// 获取用户显示名称
console.log(userStore.displayName)

// 检查是否是商户
if (userStore.isMerchant) {
  console.log('商户ID:', userStore.userInfo.merchant_id)
}

// 检查是否是管理员
if (userStore.isAdmin) {
  console.log('管理员用户')
}
```

### 5. 权限检查

```javascript
import { hasRole, requireRole } from '@/utils/auth.js'

// 检查用户角色
if (hasRole('merchant')) {
  console.log('是商户')
}

// 检查多个角色
if (hasRole(['merchant', 'admin'])) {
  console.log('是商户或管理员')
}

// 要求特定角色，无权限则提示并返回
onMounted(() => {
  if (!requireRole('merchant', '仅商户可访问')) {
    return
  }

  // 继续执行商户相关逻辑
})
```

## 测试指南

### 1. 微信小程序测试

1. 使用微信开发者工具打开项目
2. 编译到微信小程序
3. 访问登录页 `/pages/auth/index`
4. 点击"微信一键登录"按钮
5. 检查以下内容：
   - 是否显示loading状态
   - 是否成功调用后端登录接口
   - 是否存储Token到本地
   - 是否跳转到首页
   - Storage中是否保存了用户信息

### 2. 支付宝小程序测试

1. 使用支付宝开发者工具打开项目
2. 编译到支付宝小程序
3. 访问登录页
4. 点击"支付宝一键登录"按钮
5. 验证登录流程

### 3. H5测试

1. 编译到H5
2. 访问登录页
3. 输入手机号
4. 点击获取验证码
5. 输入验证码
6. 点击登录
7. 验证登录流程

### 4. 登录状态检查测试

在任意页面添加以下代码测试：

```javascript
<script setup>
import { onMounted } from 'vue'
import { useUserStore } from '@/stores/user.js'

const userStore = useUserStore()

onMounted(() => {
  console.log('Token:', userStore.token)
  console.log('Token有效性:', userStore.isTokenValid)
  console.log('登录状态:', userStore.isLoggedIn)
  console.log('用户信息:', userStore.userInfo)
  console.log('是否是商户:', userStore.isMerchant)
  console.log('是否是管理员:', userStore.isAdmin)
})
</script>
```

### 5. Token过期测试

1. 登录成功后
2. 手动修改Storage中的`xiaomotui_token_expires`为过期时间
3. 刷新页面
4. 检查是否自动清除Token
5. 访问需要登录的页面
6. 检查是否跳转到登录页

### 6. 重定向测试

1. 未登录状态访问需要登录的页面
2. 应该跳转到登录页并带上redirect参数
3. 登录成功后
4. 应该自动跳转回之前访问的页面

## API接口要求

后端需要提供以下接口：

### 1. 登录接口

**请求**:
```
POST /api/auth/login
Content-Type: application/json

{
  "code": "wx_code_123",      // 微信/支付宝临时code
  "encrypted_data": "",       // 加密数据(可选)
  "iv": ""                    // 初始向量(可选)
}
```

**响应**:
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "jwt_token_string",
    "expires_in": 86400,
    "user": {
      "id": 123,
      "openid": "wx_openid_123",
      "nickname": "用户昵称",
      "avatar": "头像URL",
      "phone": "13800138000",
      "member_level": "BASIC",
      "role": "user",
      "merchant_id": null
    }
  }
}
```

### 2. 手机号登录接口（H5）

**请求**:
```
POST /api/auth/phone-login
Content-Type: application/json

{
  "phone": "13800138000",
  "code": "123456"
}
```

**响应**: 同登录接口

### 3. 发送验证码接口

**请求**:
```
POST /api/auth/send-code
Content-Type: application/json

{
  "phone": "13800138000"
}
```

**响应**:
```json
{
  "code": 200,
  "message": "验证码已发送"
}
```

### 4. 获取用户信息接口

**请求**:
```
GET /api/auth/user
Authorization: Bearer {token}
```

**响应**:
```json
{
  "code": 200,
  "data": {
    "id": 123,
    "openid": "wx_openid_123",
    "nickname": "用户昵称",
    "avatar": "头像URL",
    "phone": "13800138000",
    "member_level": "BASIC",
    "role": "user",
    "merchant_id": null
  }
}
```

### 5. 退出登录接口

**请求**:
```
POST /api/auth/logout
Authorization: Bearer {token}
```

**响应**:
```json
{
  "code": 200,
  "message": "退出成功"
}
```

## 配置说明

### 1. API配置 (config/api.js)

已配置的重要参数：
- `tokenKey`: 'xiaomotui_token' - Token存储的key
- `tokenExpiredCode`: 401 - Token过期的状态码
- `needLoginCode`: 403 - 需要登录的状态码
- `autoRefreshToken`: true - 自动刷新Token

### 2. 页面配置 (pages.json)

登录页配置：
```json
{
  "path": "pages/auth/index",
  "style": {
    "navigationBarTitleText": "登录",
    "navigationBarBackgroundColor": "#6366f1",
    "navigationBarTextStyle": "white",
    "navigationStyle": "custom"
  }
}
```

## 注意事项

1. **隐私协议**: 用户必须勾选隐私协议才能登录
2. **Token管理**: Token会自动存储到本地，刷新页面后自动恢复
3. **Token过期**: Token过期前5分钟会被判定为无效，需要重新登录
4. **平台标识**: 请求头中会自动添加`X-Platform`标识当前平台
5. **重定向**: 未登录访问受保护页面会自动跳转登录，登录后返回原页面
6. **持久化**: 用户信息使用Pinia持久化插件自动同步到本地存储

## 依赖安装

需要安装以下npm包：

```bash
npm install pinia
npm install pinia-plugin-persistedstate
```

## 常见问题

### 1. Pinia store未初始化

确保在`main.js`中已经注册Pinia：
```javascript
import pinia from './stores/index.js'
app.use(pinia)
```

### 2. Token未自动添加到请求头

检查`request.js`中是否正确获取Token：
```javascript
getHeaders(customHeader = {}) {
  const token = this.getToken()
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }
}
```

### 3. 登录后未跳转

检查`handleLoginSuccess`函数中的跳转逻辑，确保redirect参数正确。

### 4. 条件编译不生效

检查条件编译标记是否正确：
- 微信小程序: `#ifdef MP-WEIXIN`
- 支付宝小程序: `#ifdef MP-ALIPAY`
- H5: `#ifdef H5`

## 扩展功能建议

1. **第三方登录**: 可以扩展支持QQ、微博等第三方登录
2. **生物识别**: 支持指纹、面容ID等生物识别登录
3. **记住密码**: H5支持记住密码功能
4. **多账号切换**: 支持多账号快速切换
5. **登录历史**: 记录登录历史和设备信息

## 版本信息

- 实现日期: 2025-10-01
- uni-app版本: 3.x
- Vue版本: 3.x
- Pinia版本: 2.x

## 总结

本次实现了完整的多平台授权登录功能，包括：
- ✅ 微信小程序登录
- ✅ 支付宝小程序登录
- ✅ H5手机号登录
- ✅ Pinia状态管理
- ✅ Token自动管理
- ✅ 用户信息持久化
- ✅ 条件编译支持
- ✅ 美观的UI界面
- ✅ 完善的错误处理
- ✅ 登录状态检查
- ✅ 权限验证功能

所有代码已经过优化，遵循uni-app和Vue 3最佳实践，具有良好的可维护性和扩展性。
