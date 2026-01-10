# API接口测试配置指南

## 测试环境配置

### 1. 数据库配置

#### 创建测试数据库

在运行测试之前，需要创建测试数据库：

```sql
-- 创建测试数据库
CREATE DATABASE IF NOT EXISTS xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 授权（如果需要）
GRANT ALL PRIVILEGES ON xiaomotui_test.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

#### 配置测试环境变量

测试环境使用 `.env.testing` 文件配置。如果你的数据库有密码，需要修改：

```ini
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = xiaomotui_test
USERNAME = root
PASSWORD = your_password_here  # 修改为你的数据库密码
HOSTPORT = 3306
```

#### 运行数据库迁移

在运行测试之前，需要先运行数据库迁移，创建测试表结构：

```bash
# Windows
cd D:\xiaomotui\api
php database\migrate.php

# Linux/Mac
cd /path/to/xiaomotui/api
php database/migrate.php
```

### 2. PHPUnit配置

测试配置文件为 `phpunit.xml`，已包含以下配置：

- **测试套件**: API Tests, Unit Tests, Feature Tests
- **数据库**: 使用独立的测试数据库 `xiaomotui_test`
- **缓存**: 使用数组缓存驱动（不需要Redis）
- **环境变量**: APP_ENV = testing

如果需要修改数据库配置，可以编辑 `phpunit.xml`:

```xml
<php>
    <env name="DATABASE_NAME" value="xiaomotui_test"/>
    <env name="DATABASE_USER" value="root"/>
    <env name="DATABASE_PASSWORD" value="your_password"/>
</php>
```

### 3. 依赖安装

确保已安装测试依赖：

```bash
composer install --dev
```

## 运行测试

### 基本命令

```bash
# 运行所有测试
vendor\bin\phpunit

# 运行特定测试套件
vendor\bin\phpunit --testsuite "API Tests"

# 运行特定测试文件
vendor\bin\phpunit tests/api/AuthTest.php

# 运行特定测试方法
vendor\bin\phpunit --filter testLoginSuccessWithNewUser

# 显示测试详细信息
vendor\bin\phpunit --testdox
```

### 生成测试覆盖率

```bash
# 生成HTML覆盖率报告
vendor\bin\phpunit --coverage-html tests/coverage

# 生成文本覆盖率报告
vendor\bin\phpunit --coverage-text
```

然后在浏览器中打开 `tests/coverage/index.html` 查看详细报告。

### 查看测试日志

测试日志文件位于 `tests/logs/` 目录：

- `testdox.html` - HTML格式的测试文档
- `testdox.txt` - 文本格式的测试文档
- `junit.xml` - JUnit格式的测试报告（用于CI/CD）

## 测试数据管理

### 数据库事务

测试基类默认使用数据库事务，每个测试结束后自动回滚：

```php
protected bool $useTransaction = true;
```

这确保了测试之间的数据隔离，不会相互影响。

### Mock数据

测试使用Mock数据而不是真实的外部API调用：

```php
// Mock微信登录
$this->mockWechatSession('test_code', [
    'openid' => 'test_openid',
    'session_key' => 'test_session_key',
]);
```

### 测试用户创建

使用辅助方法创建测试用户：

```php
$user = $this->createUser([
    'openid' => 'test_openid',
    'nickname' => '测试用户',
    'points' => 100,
]);
```

## 常见问题

### 1. 数据库连接失败

**错误**: `Access denied for user 'root'@'localhost' (using password: NO)`

**解决方案**:
- 在 `.env.testing` 文件中设置正确的数据库密码
- 或者在 `phpunit.xml` 中设置 `DATABASE_PASSWORD` 环境变量

### 2. 测试数据库不存在

**错误**: `Unknown database 'xiaomotui_test'`

**解决方案**:
```sql
CREATE DATABASE xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. 表不存在

**错误**: `Table 'xiaomotui_test.xmt_test_user' doesn't exist`

**解决方案**:
运行数据库迁移：
```bash
php database\migrate.php
```

### 4. 自动加载类失败

**错误**: `Class "tests\TestCase" not found`

**解决方案**:
```bash
composer dump-autoload
```

### 5. PHPUnit版本问题

**错误**: 各种PHPUnit相关错误

**解决方案**:
```bash
# 重新安装PHPUnit
composer remove --dev phpunit/phpunit
composer require --dev phpunit/phpunit:^10.0
```

### 6. 缓存问题

如果遇到缓存相关问题，清理缓存：

```bash
# 清理应用缓存
php think clear

# 清理测试缓存（如果使用Redis）
redis-cli -n 1 FLUSHDB
```

## 测试最佳实践

### 1. 测试隔离

- 每个测试应该独立运行，不依赖其他测试
- 使用 `setUp()` 准备测试数据
- 使用 `tearDown()` 清理资源（自动回滚事务）

### 2. Mock外部依赖

- 不要在测试中调用真实的外部API
- 使用Cache或其他方式Mock数据

### 3. 清晰的断言

- 使用描述性的断言消息
- 每个测试应该测试一个特定功能

### 4. 测试命名

- 测试方法名应清晰描述测试内容
- 格式：`test[功能][场景][预期结果]`
- 例如：`testLoginSuccessWithNewUser`

### 5. 测试覆盖率

- 目标：80%以上的代码覆盖率
- 重点测试核心业务逻辑
- 测试边界条件和异常情况

## 持续集成

### GitHub Actions 示例

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: xiaomotui_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, pdo_mysql
          coverage: xdebug

      - name: Install Dependencies
        run: |
          cd api
          composer install --prefer-dist --no-progress

      - name: Run Database Migrations
        run: |
          cd api
          php database/migrate.php

      - name: Run Tests
        run: |
          cd api
          vendor/bin/phpunit --coverage-text
        env:
          DATABASE_PASSWORD: root
```

## 下一步

1. 配置测试数据库
2. 运行数据库迁移
3. 执行测试验证环境配置
4. 根据需要调整配置
5. 编写更多测试用例
