# 微信登录前端测试指南

## 测试环境准备

### 1. 安装依赖
```bash
cd D:\xiaomotui\uni-app
npm install
```

### 2. 配置后端 API 地址
编辑 `src/config/env.js`，确保 API 地址正确：
```javascript
const config = {
  development: {
    baseURL: 'http://localhost:8000'
  },
  production: {
    baseURL: 'https://api.xiaomotui.com'
  }
}
```

### 3. 配置微信小程序 AppID
编辑 `manifest.json`，填入测试 AppID：
```json
{
  "mp-weixin": {
    "appid": "你的测试AppID"
  }
}
```

## 测试步骤

### 测试 1：微信开发者工具测试

#### 步骤 1：启动项目
1. 打开微信开发者工具
2. 导入项目：选择 `D:\xiaomotui\uni-app` 目录
3. 选择"小程序"项目类型
4. 填入 AppID（使用测试号）

#### 步骤 2：测试登录流程
1. 项目启动后，自动进入登录页面 `/pages/auth/index`
2. 查看页面显示：
   - ✓ Logo 和标题正常显示
   - ✓ "微信一键登录"按钮显示
   - ✓ 隐私协议复选框显示

3. 勾选隐私协议
4. 点击"微信一键登录"按钮

#### 预期结果
- 按钮显示"登录中..."
- 控制台输出：
  ```
  [LOG] 微信登录开始
  [LOG] 获取到 code: 071xxx...
  [LOG] 调用后端登录接口
  [LOG] 登录成功
  ```
- 显示"登录成功"提示
- 1.5秒后自动跳转到首页

#### 步骤 3：验证登录状态
1. 在首页查看用户信息
2. 关闭小程序
3. 重新打开小程序

**预期结果**：
- 自动识别已登录状态
- 不需要重新登录
- 直接进入首页

### 测试 2：Storage 数据验证

#### 在微信开发者工具中查看 Storage
1. 点击"调试器"
2. 选择"Storage"标签
3. 查看以下数据：

**应该存在的数据**：
```
xiaomotui_token: "eyJ0eXAiOiJKV1QiLCJhbGc..."
xiaomotui_token_expires: 1234567890000
xiaomotui_user_info: {
  id: 1,
  openid: "oXXXX...",
  nickname: "微信用户",
  avatar: "",
  phone: "",
  member_level: "BASIC",
  role: "user"
}
xiaomotui_platform: "wechat"
```

### 测试 3：网络请求验证

#### 在微信开发者工具中查看网络请求
1. 点击"调试器"
2. 选择"Network"标签
3. 点击登录按钮
4. 查看请求详情

**登录请求**：
```
URL: http://localhost:8000/api/auth/login
Method: POST
Request Headers:
  Content-Type: application/json
Request Body:
  {
    "code": "071xxx..."
  }
Response:
  {
    "code": 200,
    "message": "登录成功",
    "data": {
      "token": "eyJ0eXAi...",
      "expires_in": 86400,
      "user": {
        "id": 1,
        "openid": "oXXXX...",
        "nickname": "微信用户",
        ...
      }
    }
  }
```

### 测试 4：错误处理测试

#### 测试 4.1：未勾选隐私协议
1. 不勾选隐私协议
2. 点击登录按钮

**预期结果**：
- 显示提示："请先阅读并同意隐私政策和用户协议"
- 不发起网络请求

#### 测试 4.2：网络错误
1. 关闭后端服务
2. 勾选隐私协议
3. 点击登录按钮

**预期结果**：
- 显示"登录失败，请重试"
- 按钮恢复可点击状态

#### 测试 4.3：后端返回错误
1. 修改后端代码，让其返回错误
2. 尝试登录

**预期结果**：
- 显示后端返回的错误信息
- 按钮恢复可点击状态

### 测试 5：Pinia Store 测试

#### 在控制台测试 Store
```javascript
// 获取 Store 实例
const userStore = useUserStore()

// 测试登录状态检查
console.log('是否已登录:', userStore.checkLoginStatus())

// 测试 Token 有效性
console.log('Token 是否有效:', userStore.isTokenValid)

// 测试用户信息
console.log('用户信息:', userStore.userInfo)

// 测试显示名称
console.log('显示名称:', userStore.displayName)

// 测试是否是商户
console.log('是否是商户:', userStore.isMerchant)
```

### 测试 6：条件编译测试

#### 测试微信小程序特有功能
1. 查看页面源码，确认以下代码块存在：
```vue
<!-- #ifdef MP-WEIXIN -->
<button @tap="handleWechatLogin">
  微信一键登录
</button>
<!-- #endif -->
```

2. 编译到 H5 平台，确认微信登录按钮不显示

### 测试 7：UI 交互测试

#### 测试加载状态
1. 点击登录按钮
2. 观察按钮状态变化

**预期效果**：
- 按钮文字变为"登录中..."
- 按钮变为禁用状态
- 按钮有 loading 动画

#### 测试动画效果
1. 观察背景装饰圆圈动画
2. 观察按钮点击效果

**预期效果**：
- 圆圈有浮动动画
- 按钮点击有缩放效果

### 测试 8：兼容性测试

#### 测试不同微信版本
1. 在真机上测试（iOS）
2. 在真机上测试（Android）
3. 在模拟器上测试

**检查项**：
- [ ] 页面布局正常
- [ ] 按钮可点击
- [ ] 登录流程正常
- [ ] 跳转正常

## 常见问题排查

### 问题 1：点击登录无反应
**排查步骤**：
1. 检查是否勾选隐私协议
2. 查看控制台是否有错误
3. 检查网络请求是否发出
4. 检查后端服务是否启动

### 问题 2：登录后立即退出
**排查步骤**：
1. 检查 token 是否保存成功
2. 检查 token 过期时间是否正确
3. 查看 Storage 中的数据

### 问题 3：无法获取 code
**排查步骤**：
1. 检查 AppID 是否正确
2. 检查网络连接
3. 查看微信开发者工具控制台错误

### 问题 4：后端返回 401
**排查步骤**：
1. 检查 token 是否正确携带
2. 检查 token 格式（Bearer + 空格 + token）
3. 检查后端中间件配置

## 性能测试

### 测试登录速度
1. 使用 Performance 工具
2. 记录从点击到跳转的时间

**性能指标**：
- 获取 code: < 500ms
- 后端登录: < 1000ms
- 总耗时: < 2000ms

### 测试内存占用
1. 在微信开发者工具中查看内存
2. 多次登录退出
3. 观察内存是否泄漏

## 自动化测试（可选）

### 使用 uni-app 自动化测试
```javascript
describe('微信登录测试', () => {
  it('应该显示登录页面', async () => {
    const page = await program.navigateTo('/pages/auth/index')
    expect(await page.$$('.login-btn')).toHaveLength(1)
  })

  it('应该能够点击登录按钮', async () => {
    const page = await program.currentPage()
    await page.callMethod('handleWechatLogin')
    // 验证结果
  })
})
```

## 测试清单

### 功能测试
- [ ] 登录按钮显示正常
- [ ] 隐私协议勾选功能正常
- [ ] 点击登录能获取 code
- [ ] 能成功调用后端接口
- [ ] 能保存 token 到本地
- [ ] 能保存用户信息到本地
- [ ] 登录成功后能跳转
- [ ] 重新打开能自动登录

### UI 测试
- [ ] 页面布局正常
- [ ] 按钮样式正常
- [ ] 加载状态显示正常
- [ ] 错误提示显示正常
- [ ] 动画效果正常

### 兼容性测试
- [ ] 微信开发者工具正常
- [ ] iOS 真机正常
- [ ] Android 真机正常
- [ ] 不同微信版本正常

### 性能测试
- [ ] 登录速度符合要求
- [ ] 无内存泄漏
- [ ] 无卡顿现象

### 安全测试
- [ ] Token 安全存储
- [ ] 敏感信息不泄漏
- [ ] 网络请求使用 HTTPS（生产环境）

## 测试报告模板

```
测试日期：2025-XX-XX
测试人员：XXX
测试环境：微信开发者工具 / iOS / Android

测试结果：
✓ 功能测试：通过
✓ UI 测试：通过
✓ 兼容性测试：通过
✓ 性能测试：通过
✓ 安全测试：通过

发现问题：
1. 无

建议：
1. 建议添加登录失败重试机制
2. 建议优化登录速度
```

## 总结

前端微信登录功能已完整实现，包括：
- 登录页面 UI
- 微信授权流程
- Token 管理
- 状态管理
- 错误处理

建议按照上述测试步骤进行完整测试，确保功能正常。
