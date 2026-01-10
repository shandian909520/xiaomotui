# NFC测试环境配置指南

## 前提条件

在运行NFC测试之前，需要确保以下环境已正确配置：

### 1. PHP环境要求

- PHP版本: >= 8.1.0
- 必需的PHP扩展:
  - pdo_mysql
  - mbstring
  - json
  - curl
  - redis (可选，用于缓存测试)

检查PHP版本和扩展：
```bash
php -v
php -m | grep -E "pdo_mysql|mbstring|json|curl|redis"
```

### 2. 数据库配置

#### 2.1 创建测试数据库

```sql
-- 创建测试数据库
CREATE DATABASE IF NOT EXISTS xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建测试用户（可选）
CREATE USER IF NOT EXISTS 'xiaomotui_test'@'localhost' IDENTIFIED BY 'test_password_123';
GRANT ALL PRIVILEGES ON xiaomotui_test.* TO 'xiaomotui_test'@'localhost';
FLUSH PRIVILEGES;
```

#### 2.2 配置测试环境变量

创建或编辑 `.env.testing` 文件：

```bash
cd D:\xiaomotui\api
cp .env.example .env.testing
```

编辑 `.env.testing` 内容：

```ini
# 应用配置
APP_DEBUG = true
APP_TRACE = false

# 数据库配置（测试环境）
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = xiaomotui_test
USERNAME = xiaomotui_test
PASSWORD = test_password_123
HOSTPORT = 3306
CHARSET = utf8mb4
PREFIX =

# Redis配置（测试环境）
[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =
SELECT = 1
TIMEOUT = 0
EXPIRE = 0
PERSISTENT = false
PREFIX = test_

# JWT配置
[JWT]
SECRET_KEY = test_jwt_secret_key_for_testing
EXPIRE_TIME = 86400

# 微信配置（测试环境使用mock数据）
[WECHAT]
APP_ID = test_app_id
APP_SECRET = test_app_secret
```

#### 2.3 运行数据库迁移

```bash
# Windows
cd D:\xiaomotui\api
php think migrate:run

# Linux/Mac
cd /path/to/xiaomotui/api
php think migrate:run
```

### 3. PHPUnit配置

创建或编辑 `phpunit.xml` 文件：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false"
         executionOrder="default"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTodoAnnotatedTests="true"
         failOnRisky="false"
         failOnWarning="false">

    <testsuites>
        <testsuite name="API Tests">
            <directory>tests/api</directory>
        </testsuite>
    </testsuites>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <env name="DATABASE_NAME" value="xiaomotui_test"/>
    </php>

    <coverage>
        <include>
            <directory suffix=".php">app/controller</directory>
            <directory suffix=".php">app/service</directory>
            <directory suffix=".php">app/model</directory>
        </include>
        <exclude>
            <directory>app/common</directory>
            <file>app/BaseController.php</file>
        </exclude>
    </coverage>
</phpunit>
```

### 4. 安装依赖

确保所有Composer依赖已安装：

```bash
cd D:\xiaomotui\api
composer install
```

如果缺少PHPUnit，手动安装：

```bash
composer require --dev phpunit/phpunit ^10.0
```

## 运行测试

### 快速开始

#### 1. 验证数据库连接

```bash
# 测试数据库连接
php -r "
\$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=xiaomotui_test',
    'xiaomotui_test',
    'test_password_123'
);
echo 'Database connection successful!' . PHP_EOL;
"
```

#### 2. 运行单个测试

```bash
# Windows
cd D:\xiaomotui\api
vendor\bin\phpunit tests\api\NfcTest.php --testdox

# Linux/Mac
cd /path/to/xiaomotui/api
./vendor/bin/phpunit tests/api/NfcTest.php --testdox
```

#### 3. 运行特定测试方法

```bash
# Windows
vendor\bin\phpunit tests\api\NfcTest.php --filter testTriggerVideoModeSuccess

# Linux/Mac
./vendor/bin/phpunit tests/api/NfcTest.php --filter testTriggerVideoModeSuccess
```

### 测试选项说明

#### --testdox
以易读的格式显示测试结果：
```bash
vendor\bin\phpunit tests\api\NfcTest.php --testdox
```

#### --verbose
显示详细的测试输出：
```bash
vendor\bin\phpunit tests\api\NfcTest.php --verbose
```

#### --filter
只运行匹配模式的测试：
```bash
vendor\bin\phpunit tests\api\NfcTest.php --filter trigger
```

#### --coverage-html
生成HTML格式的代码覆盖率报告：
```bash
vendor\bin\phpunit tests\api\NfcTest.php --coverage-html coverage
```

## 常见问题解决

### 问题1: 数据库连接失败

**错误信息**:
```
PDOException: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**解决方案**:
1. 检查 `.env.testing` 文件中的数据库配置
2. 确保数据库用户名和密码正确
3. 确保MySQL服务正在运行
4. 测试数据库连接（见上方"验证数据库连接"）

### 问题2: 表不存在

**错误信息**:
```
SQLSTATE[42S02]: Base table or view not found
```

**解决方案**:
1. 运行数据库迁移：
   ```bash
   php think migrate:run
   ```
2. 确认所有迁移文件都已执行：
   ```bash
   php think migrate:status
   ```

### 问题3: Redis连接失败

**错误信息**:
```
Connection refused [tcp://127.0.0.1:6379]
```

**解决方案**:
1. 启动Redis服务：
   ```bash
   # Windows
   redis-server

   # Linux/Mac
   sudo systemctl start redis
   ```
2. 或者在测试中禁用缓存：
   在 `TestCase.php` 中设置：
   ```php
   protected bool $clearCache = false;
   ```

### 问题4: 权限问题

**错误信息**:
```
Access denied for table 'xxx'
```

**解决方案**:
1. 授予测试用户足够的权限：
   ```sql
   GRANT ALL PRIVILEGES ON xiaomotui_test.* TO 'xiaomotui_test'@'localhost';
   FLUSH PRIVILEGES;
   ```

### 问题5: 内存不足

**错误信息**:
```
Allowed memory size of xxx bytes exhausted
```

**解决方案**:
1. 增加PHP内存限制：
   ```ini
   # php.ini
   memory_limit = 256M
   ```
2. 或在运行测试时设置：
   ```bash
   php -d memory_limit=256M vendor/bin/phpunit tests/api/NfcTest.php
   ```

## 测试数据管理

### 自动清理

测试使用数据库事务，每个测试后自动回滚，不会留下测试数据。

### 手动清理

如果需要手动清理测试数据：

```sql
-- 清空测试表（谨慎使用！）
USE xiaomotui_test;
TRUNCATE TABLE nfc_devices;
TRUNCATE TABLE device_triggers;
TRUNCATE TABLE users;
TRUNCATE TABLE merchants;
TRUNCATE TABLE coupons;
TRUNCATE TABLE coupon_users;
```

### 重置测试数据库

如果需要完全重置测试数据库：

```bash
# 删除并重新创建数据库
mysql -u root -p -e "DROP DATABASE IF EXISTS xiaomotui_test; CREATE DATABASE xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 重新运行迁移
php think migrate:run
```

## 持续集成配置

### GitHub Actions示例

创建 `.github/workflows/test.yml`:

```yaml
name: Run Tests

on:
  push:
    branches: [ master, develop ]
  pull_request:
    branches: [ master, develop ]

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
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, pdo_mysql, redis
        coverage: xdebug

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Copy .env.testing
      run: cp .env.testing .env

    - name: Run migrations
      run: php think migrate:run

    - name: Run NFC tests
      run: vendor/bin/phpunit tests/api/NfcTest.php

    - name: Generate coverage report
      run: vendor/bin/phpunit tests/api/NfcTest.php --coverage-clover coverage.xml

    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        files: ./coverage.xml
```

## 性能优化建议

### 1. 使用内存数据库（开发环境）

对于快速测试，可以使用SQLite内存数据库：

`.env.testing`:
```ini
[DATABASE]
TYPE = sqlite
DATABASE = :memory:
```

### 2. 并行测试

使用 ParaTest 进行并行测试：

```bash
composer require --dev brianium/paratest
vendor/bin/paratest tests/api/NfcTest.php
```

### 3. 跳过慢速测试

标记慢速测试：
```php
/**
 * @group slow
 */
public function testSlowOperation(): void
{
    // ...
}
```

运行时排除：
```bash
vendor/bin/phpunit --exclude-group slow
```

## 调试技巧

### 1. 启用详细输出

```bash
vendor\bin\phpunit tests\api\NfcTest.php --verbose --debug
```

### 2. 停止在第一个失败

```bash
vendor\bin\phpunit tests\api\NfcTest.php --stop-on-failure
```

### 3. 打印SQL查询

在 `TestCase.php` 的 `setUp()` 中添加：
```php
\think\facade\Db::listen(function ($sql, $time, $master) {
    echo "[SQL] {$sql} [{$time}ms]" . PHP_EOL;
});
```

### 4. 使用XDebug

配置 `php.ini`:
```ini
[XDebug]
zend_extension=xdebug
xdebug.mode=coverage,debug
xdebug.start_with_request=yes
```

## 验证测试环境

运行以下命令验证环境是否正确配置：

```bash
# 1. 检查PHP版本
php -v

# 2. 检查数据库连接
php -r "\$pdo = new PDO('mysql:host=127.0.0.1;dbname=xiaomotui_test', 'xiaomotui_test', 'test_password_123'); echo 'OK';"

# 3. 检查Redis连接
php -r "\$redis = new Redis(); \$redis->connect('127.0.0.1', 6379); echo 'OK';"

# 4. 运行简单测试
vendor/bin/phpunit --version

# 5. 运行实际测试
vendor/bin/phpunit tests/api/NfcTest.php --testdox
```

## 下一步

环境配置完成后：

1. 阅读 [NFC_TEST_DOCUMENTATION.md](./NFC_TEST_DOCUMENTATION.md) 了解测试详情
2. 运行测试并查看结果
3. 根据需要添加新的测试用例
4. 集成到CI/CD流程

## 获取帮助

如遇到问题：
1. 查看本文档的"常见问题解决"部分
2. 检查测试日志输出
3. 联系技术团队: tech@xiaomotui.com
