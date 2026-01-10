# API接口测试快速启动指南

## 快速开始（5分钟）

### 步骤1: 配置数据库密码（如果需要）

如果你的MySQL数据库有密码，编辑 `.env.testing` 文件：

```bash
cd D:\xiaomotui\api
notepad .env.testing
```

修改数据库密码：
```ini
[DATABASE]
PASSWORD = your_password_here
```

### 步骤2: 运行自动配置脚本

**Windows:**
```bash
cd D:\xiaomotui\api
tests\setup_test_env.bat
```

**Linux/Mac:**
```bash
cd /path/to/xiaomotui/api
chmod +x tests/setup_test_env.sh
./tests/setup_test_env.sh
```

脚本会自动：
1. 安装测试依赖
2. 检查数据库连接
3. 创建测试数据库
4. 运行数据库迁移

### 步骤3: 运行测试

```bash
# 运行所有认证测试
vendor\bin\phpunit tests/api/AuthTest.php --testdox
```

## 测试用例说明

### AuthTest - 认证接口测试

共12个测试用例，覆盖以下功能：

#### 登录测试 (5个)
- ✅ 新用户登录成功
- ✅ 已存在用户登录成功
- ✅ 无效code登录失败
- ✅ 缺少code参数登录失败
- ✅ 带加密用户信息登录

#### Token测试 (4个)
- ✅ Token刷新成功
- ✅ 无效token刷新失败
- ✅ 过期token刷新失败
- ✅ Token载荷结构验证

#### 登出测试 (2个)
- ✅ 登出成功
- ✅ 未提供token登出失败

#### 用户信息测试 (1个)
- ✅ 获取用户信息成功

## 预期输出

运行成功后，你会看到类似的输出：

```
PHPUnit 10.5.58 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.9
Configuration: D:\xiaomotui\api\phpunit.xml

Auth (tests\api\Auth)
 ✔ Login success with new user
 ✔ Login success with existing user
 ✔ Login failure with invalid code
 ✔ Login failure with missing code
 ✔ Login with encrypted user info
 ✔ Refresh token success
 ✔ Refresh token failure with invalid token
 ✔ Refresh token failure with expired token
 ✔ Logout success
 ✔ Logout failure without token
 ✔ Get user info success
 ✔ Token payload structure

Time: 00:01.234, Memory: 16.00 MB

OK (12 tests, 45 assertions)
```

## 常用命令

```bash
# 运行所有测试
vendor\bin\phpunit

# 只运行认证测试
vendor\bin\phpunit tests/api/AuthTest.php

# 显示测试详细信息
vendor\bin\phpunit --testdox

# 运行特定测试
vendor\bin\phpunit --filter testLoginSuccessWithNewUser

# 生成覆盖率报告
vendor\bin\phpunit --coverage-html tests/coverage

# 显示详细错误信息
vendor\bin\phpunit --verbose
```

## 测试数据

测试使用Mock数据，不会调用真实的微信API：

- **测试用户**: 自动创建，测试后自动清理
- **微信API**: 使用缓存Mock数据
- **JWT Token**: 使用测试密钥生成
- **数据库**: 使用事务，测试后自动回滚

## 验证测试覆盖

测试验证以下功能：

1. **登录流程**
   - 微信code换取session
   - 创建/更新用户
   - 生成JWT token
   - 解密用户信息

2. **Token管理**
   - Token生成
   - Token刷新
   - Token验证
   - Token载荷结构

3. **会话管理**
   - 用户登出
   - Token黑名单
   - 会话清理

4. **用户信息**
   - 获取用户信息
   - 更新用户信息
   - 数据隐藏

5. **错误处理**
   - 参数验证
   - 无效token
   - 过期token
   - 数据库错误

## 故障排查

### 问题1: 数据库连接失败

```
PDOException: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**解决方案**:
在 `.env.testing` 中设置正确的数据库密码

### 问题2: 测试数据库不存在

```
SQLSTATE[HY000] [1049] Unknown database 'xiaomotui_test'
```

**解决方案**:
```sql
CREATE DATABASE xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 问题3: 表不存在

```
SQLSTATE[42S02]: Table 'xiaomotui_test.xmt_test_user' doesn't exist
```

**解决方案**:
```bash
php database\migrate.php
```

### 问题4: 类找不到

```
Class "tests\TestCase" not found
```

**解决方案**:
```bash
composer dump-autoload
```

## 下一步

1. ✅ 运行测试验证环境配置
2. 📖 阅读 [CONFIGURATION.md](CONFIGURATION.md) 了解详细配置
3. 📖 阅读 [README.md](README.md) 了解测试框架
4. 🔧 根据需要调整测试配置
5. ✍️ 编写更多测试用例

## 需要帮助？

- 查看详细配置: [CONFIGURATION.md](CONFIGURATION.md)
- 查看测试文档: [README.md](README.md)
- 查看测试示例: [AuthTest.php](api/AuthTest.php)

---

**提示**: 第一次运行测试可能需要几秒钟来初始化数据库和加载依赖。后续运行会更快。
