# AuthController API 文档

## 概述

AuthController 是小魔推碰一碰平台的认证控制器，专门为微信小程序设计，实现了完整的微信用户认证、JWT令牌管理和用户信息管理功能。

## 接口列表

### 1. 微信小程序登录

**接口地址：** `POST /api/auth/login`

**请求参数：**
```json
{
    "code": "wx_code_123",          // 微信临时code (必填)
    "encrypted_data": "",           // 加密数据 (可选，用于获取完整用户信息)
    "iv": ""                        // 初始向量 (可选，配合encrypted_data使用)
}
```

**响应示例：**
```json
{
    "code": 200,
    "message": "登录成功",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "expires_in": 86400,
        "user": {
            "id": 123,
            "openid": "wx_openid_123",
            "nickname": "微信用户",
            "avatar": "https://...",
            "gender": 0,
            "member_level": "BASIC",
            "points": 0
        }
    },
    "timestamp": 1640995200
}
```

### 2. 刷新令牌

**接口地址：** `POST /api/auth/refresh`

**请求参数：**
```json
{
    "refresh_token": "refresh_token_string"  // 刷新令牌 (必填)
}
```

**响应示例：**
```json
{
    "code": 200,
    "message": "刷新成功",
    "data": {
        "token": "new_access_token",
        "expires_in": 86400
    },
    "timestamp": 1640995200
}
```

### 3. 用户登出

**接口地址：** `POST /api/auth/logout`

**请求头：** `Authorization: Bearer {token}`

**响应示例：**
```json
{
    "code": 200,
    "message": "登出成功",
    "data": null,
    "timestamp": 1640995200
}
```

### 4. 获取用户信息

**接口地址：** `GET /api/auth/info`

**请求头：** `Authorization: Bearer {token}`

**响应示例：**
```json
{
    "code": 200,
    "message": "获取成功",
    "data": {
        "id": 123,
        "openid": "wx_openid_123",
        "nickname": "用户昵称",
        "avatar": "https://...",
        "gender": 1,
        "member_level": "VIP",
        "points": 150,
        "phone": "13800138000",
        "status": 1,
        "last_login_time": "2024-01-01 12:00:00",
        "create_time": "2024-01-01 10:00:00"
    },
    "timestamp": 1640995200
}
```

### 5. 更新用户信息

**接口地址：** `POST /api/auth/update`

**请求头：** `Authorization: Bearer {token}`

**请求参数：**
```json
{
    "nickname": "新昵称",        // 可选
    "phone": "13800138000",     // 可选，必须是有效手机号
    "avatar": "https://..."     // 可选，必须是有效URL
}
```

**响应示例：**
```json
{
    "code": 200,
    "message": "更新成功",
    "data": {
        // 更新后的用户信息
    },
    "timestamp": 1640995200
}
```

### 6. 绑定手机号

**接口地址：** `POST /api/auth/bind-phone`

**请求头：** `Authorization: Bearer {token}`

**请求参数：**
```json
{
    "phone": "13800138000",     // 手机号 (必填)
    "code": "123456"            // 验证码 (必填)
}
```

**响应示例：**
```json
{
    "code": 200,
    "message": "绑定成功",
    "data": {
        // 绑定后的用户信息
    },
    "timestamp": 1640995200
}
```

### 7. 用户注册 (预留接口)

**接口地址：** `POST /api/auth/register`

**说明：** 微信小程序不需要单独注册，登录时会自动创建用户账号。此接口预留作为系统扩展使用。

## JWT令牌格式

根据设计文档，JWT令牌包含以下载荷信息：

```json
{
    "iss": "xiaomotui",           // 签发者
    "aud": "miniprogram",         // 接收者
    "iat": 1640995200,            // 签发时间
    "exp": 1641081600,            // 过期时间(24小时)
    "sub": 123,                   // 用户ID
    "openid": "wx_openid_123",    // 微信openid
    "role": "user",               // 用户角色 user/merchant/admin
    "merchant_id": 123            // 商家ID(可选)
}
```

## 错误码说明

| 错误码 | 说明 | HTTP状态码 |
|--------|------|------------|
| 200 | 请求成功 | 200 |
| 400 | 请求参数错误 | 400 |
| 401 | 未授权/令牌无效 | 401 |
| 403 | 权限不足 | 403 |
| 422 | 数据验证失败 | 422 |
| 500 | 服务器内部错误 | 500 |

## 使用说明

1. **首次登录：** 小程序调用 `wx.login()` 获取code，然后调用 `/api/auth/login` 接口
2. **获取用户信息：** 如需完整用户信息，在登录时传入 `encrypted_data` 和 `iv`
3. **token使用：** 在需要认证的接口请求头中添加 `Authorization: Bearer {token}`
4. **token刷新：** token过期后使用 `/api/auth/refresh` 接口刷新
5. **角色权限：** 用户角色决定可访问的API接口范围

## 安全特性

1. **JWT签名验证：** 所有token都经过HMAC-SHA256签名
2. **token黑名单：** 登出后的token会加入黑名单
3. **权限控制：** 基于角色的访问控制(RBAC)
4. **请求验证：** 所有输入参数都经过严格验证
5. **错误处理：** 统一的错误响应格式

## 依赖服务

- **WechatService：** 微信API调用服务
- **JwtUtil：** JWT令牌工具类
- **WechatAuth验证器：** 请求参数验证
- **JwtAuth中间件：** JWT认证中间件