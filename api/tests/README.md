# 小魔推API测试文档

## 快速开始

- **新手指南**: [QUICKSTART.md](QUICKSTART.md) - 5分钟快速上手
- **配置指南**: [CONFIGURATION.md](CONFIGURATION.md) - 详细配置说明
- **测试框架**: 本文档 - 测试框架和最佳实践

## 目录结构

```
tests/
├── api/                    # API接口测试
│   ├── AuthTest.php        # 认证接口测试
│   ├── NfcTest.php         # NFC接口测试
│   └── ContentTest.php     # 内容接口测试
├── unit/                   # 单元测试（未来扩展）
├── feature/                # 功能测试（未来扩展）
├── benchmark/              # 性能基准测试
├── coverage/               # 测试覆盖率报告
├── logs/                   # 测试日志
├── TestCase.php            # 测试基类
├── README.md               # 测试框架文档（本文档）
├── QUICKSTART.md           # 快速启动指南
├── CONFIGURATION.md        # 配置指南
├── setup_test_env.bat      # Windows环境配置脚本
├── setup_test_env.sh       # Linux/Mac环境配置脚本
├── run_tests.bat           # Windows快速测试脚本
└── run_tests.sh            # Linux/Mac快速测试脚本
```

## 环境要求

- PHP >= 8.1
- PHPUnit ^10.0
- MySQL数据库（测试数据库）
- Redis（可选）

## 测试数据库配置

测试使用独立的数据库，配置在`phpunit.xml`中：

```xml
<env name="DATABASE_NAME" value="xiaomotui_test"/>
```

**重要**: 请确保创建测试数据库：

```sql
CREATE DATABASE IF NOT EXISTS xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 运行测试

### 使用快捷脚本（推荐）

```bash
# Windows
tests\run_tests.bat          # 运行所有API测试
tests\run_tests.bat auth     # 运行认证测试
tests\run_tests.bat coverage # 生成覆盖率报告

# Linux/Mac
./tests/run_tests.sh          # 运行所有API测试
./tests/run_tests.sh auth     # 运行认证测试
./tests/run_tests.sh coverage # 生成覆盖率报告
```

### 使用PHPUnit命令

```bash
# Windows
vendor\bin\phpunit                                    # 运行所有测试
vendor\bin\phpunit --testsuite "API Tests"           # 运行API测试套件
vendor\bin\phpunit tests/api/AuthTest.php            # 运行特定文件
vendor\bin\phpunit --filter testLoginSuccess         # 运行特定方法
vendor\bin\phpunit --testdox                         # 显示测试文档

# Linux/Mac
./vendor/bin/phpunit                                  # 运行所有测试
./vendor/bin/phpunit --testsuite "API Tests"         # 运行API测试套件
./vendor/bin/phpunit tests/api/AuthTest.php          # 运行特定文件
./vendor/bin/phpunit --filter testLoginSuccess       # 运行特定方法
./vendor/bin/phpunit --testdox                       # 显示测试文档
```

## 测试覆盖率

生成测试覆盖率报告：

```bash
vendor\bin\phpunit --coverage-html tests/coverage
```

然后在浏览器中打开 `tests/coverage/index.html` 查看报告。

## 认证接口测试说明

`AuthTest.php` 包含以下测试用例：

### 登录测试
1. **testLoginSuccessWithNewUser** - 测试新用户登录成功
2. **testLoginSuccessWithExistingUser** - 测试已存在用户登录成功
3. **testLoginFailureWithInvalidCode** - 测试无效code登录失败
4. **testLoginFailureWithMissingCode** - 测试缺少code参数登录失败
5. **testLoginWithEncryptedUserInfo** - 测试带加密用户信息的登录

### Token测试
6. **testRefreshTokenSuccess** - 测试Token刷新成功
7. **testRefreshTokenFailureWithInvalidToken** - 测试无效token刷新失败
8. **testRefreshTokenFailureWithExpiredToken** - 测试过期token刷新失败
9. **testTokenPayloadStructure** - 测试Token载荷结构

### 退出登录测试
10. **testLogoutSuccess** - 测试退出登录成功
11. **testLogoutFailureWithoutToken** - 测试未提供token退出失败

### 用户信息测试
12. **testGetUserInfoSuccess** - 测试获取用户信息成功

## 测试基类功能

`TestCase.php` 提供以下辅助方法：

### HTTP请求方法
- `get($uri, $params, $headers)` - GET请求
- `post($uri, $data, $headers)` - POST请求
- `put($uri, $data, $headers)` - PUT请求
- `delete($uri, $params, $headers)` - DELETE请求
- `authGet($uri, $token, $params)` - 带认证的GET请求
- `authPost($uri, $token, $data)` - 带认证的POST请求

### 断言方法
- `assertSuccess($response, $message)` - 断言响应成功
- `assertError($response, $expectedCode, $message)` - 断言响应失败
- `assertHasFields($response, $fields, $message)` - 断言响应包含字段
- `assertDatabaseHas($table, $conditions)` - 断言数据库有记录
- `assertDatabaseMissing($table, $conditions)` - 断言数据库没有记录

### 辅助方法
- `createUser($attributes)` - 创建测试用户
- `generateToken($userId, $openid, $expireTime)` - 生成测试JWT令牌
- `mockWechatSession($code, $sessionInfo)` - Mock微信会话

## 编写测试的最佳实践

### 1. 测试命名规范

测试方法名应清晰描述测试内容：

```php
public function testLoginSuccessWithNewUser(): void
{
    // 测试新用户登录成功
}

public function testLoginFailureWithInvalidCode(): void
{
    // 测试无效code登录失败
}
```

### 2. 使用数据库事务

测试基类默认开启数据库事务，每个测试后自动回滚，保证测试隔离：

```php
protected bool $useTransaction = true;
```

### 3. Mock外部依赖

不要在测试中调用真实的外部API，使用Mock数据：

```php
// Mock微信API响应
$this->mockWechatSession('test_code', [
    'openid' => 'test_openid',
    'session_key' => 'test_session_key',
]);
```

### 4. 清晰的断言消息

提供清晰的断言消息，便于定位失败原因：

```php
$this->assertSuccess($response, '登录应该成功');
$this->assertEquals('BASIC', $user['member_level'], '新用户应为基础会员');
```

### 5. 测试数据准备

在`setUp()`方法中准备测试数据：

```php
protected function setUp(): void
{
    parent::setUp();

    $this->testData = [
        'code' => 'test_wx_code_' . uniqid(),
        'openid' => 'test_openid_' . uniqid(),
    ];
}
```

## 常见问题

### 1. 测试数据库连接失败

检查`phpunit.xml`中的数据库配置是否正确，确保测试数据库已创建。

### 2. 找不到类或方法

运行`composer dump-autoload`重新生成自动加载文件。

### 3. 微信API调用失败

确保在测试环境中使用了Mock数据，不要调用真实的微信API。

### 4. 缓存问题

测试基类默认在每个测试后清理缓存，如果遇到缓存问题，手动清理：

```bash
php think clear
```

## 持续集成

可以将测试集成到CI/CD流程中：

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: vendor/bin/phpunit
```

## 下一步

- 添加更多API接口测试（NFC、内容生成等）
- 添加单元测试（Service、Model等）
- 添加性能测试
- 提高测试覆盖率到80%以上
