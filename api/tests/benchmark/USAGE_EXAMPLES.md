# 性能基准测试使用示例

## 快速开始示例

### 示例1: 第一次运行测试

```bash
# 1. 进入测试目录
cd D:\xiaomotui\api\tests\benchmark

# 2. 运行基础功能检查
php test_basic.php

# 3. 执行快速测试（推荐首次使用）
php performance.php --quick

# 预期输出:
# ================================================================================
# 开始执行性能基准测试
# ================================================================================
# [步骤 1/5] 用户登录认证...
# [步骤 2/5] 测试API响应时间...
# [步骤 3/5] 测试并发负载...
# [步骤 4/5] 测试内存使用...
# [步骤 5/5] 测试数据库性能...
# [完成] 生成性能报告...
```

### 示例2: 完整性能测试

```bash
# Windows
run_benchmark.bat

# Linux/Mac
chmod +x run_benchmark.sh
./run_benchmark.sh

# 或直接使用PHP
php performance.php
```

**预期结果**:
- 测试时长: 约2-5分钟
- 生成报告: `reports/report_YYYYMMDD_HHMMSS.json`
- 控制台输出完整报告

### 示例3: 仅测试API响应时间（不需要数据库）

```bash
php performance.php --skip-db --skip-memory --skip-concurrent
```

**适用场景**:
- 快速验证API是否正常
- 开发环境测试
- CI/CD流程

### 示例4: 测试公开API（无需登录）

```bash
php performance.php --skip-login
```

**测试内容**:
- NFC触发端点
- 其他公开API
- 跳过需要认证的接口

**适用场景**:
- 没有测试账号
- 仅验证公开接口性能

## 详细使用场景

### 场景A: 开发阶段快速验证

**需求**: 修改代码后快速检查性能是否受影响

```bash
# 快速测试模式（约1分钟）
php performance.php --quick

# 或者更快（仅测试API）
php performance.php --quick --skip-db --skip-memory --skip-concurrent
```

**优点**:
- 快速反馈（< 1分钟）
- 减少迭代次数
- 适合频繁测试

### 场景B: 发布前完整测试

**需求**: 发布新版本前验证所有性能指标

```bash
# Windows
run_benchmark.bat > test_results.log 2>&1

# Linux/Mac
./run_benchmark.sh | tee test_results.log
```

**步骤**:
1. 确保API服务运行
2. 确保数据库可访问
3. 检查测试账号可用
4. 运行完整测试
5. 查看报告确认所有指标通过

### 场景C: 性能回归测试

**需求**: 对比不同版本的性能表现

```bash
# 1. 测试版本A
git checkout version-a
php performance.php
cp reports/report_*.json reports/version_a.json

# 2. 测试版本B
git checkout version-b
php performance.php
cp reports/report_*.json reports/version_b.json

# 3. 对比两个JSON报告
# 可以使用JSON diff工具或编写脚本对比
```

### 场景D: 压力测试（找出性能极限）

**需求**: 测试系统的最大承载能力

```bash
# 编辑 config.php，增加并发级别
# 'load_test_levels' => [
#     'extreme' => 5000,
#     'maximum' => 10000,
# ]

php performance.php --skip-memory --skip-db
```

**注意**:
- 可能需要增加PHP内存限制
- 可能需要更长的超时时间
- 建议在测试环境执行

### 场景E: 数据库性能优化验证

**需求**: 添加索引后验证性能提升

```bash
# 1. 添加索引前测试
php performance.php --skip-concurrent --skip-memory

# 2. 添加索引
# ALTER TABLE xmt_users ADD INDEX idx_phone (phone);

# 3. 添加索引后测试
php performance.php --skip-concurrent --skip-memory

# 4. 对比查询时间
```

## 配置自定义示例

### 自定义1: 添加新的测试端点

编辑 `config.php`:

```php
'endpoints' => [
    // ... 现有端点 ...

    // 添加新端点
    'my_custom_api' => [
        'url' => '/api/my/custom',
        'method' => 'POST',
        'auth_required' => true,
        'target_time' => 200, // 目标响应时间200ms
    ],
],

// 添加测试数据
'test_data' => [
    // ... 现有数据 ...

    'my_custom_api' => [
        'param1' => 'value1',
        'param2' => 'value2',
    ],
],
```

### 自定义2: 修改性能目标

编辑 `config.php`:

```php
'performance_targets' => [
    'nfc_response_time' => 500,      // 从1000ms改为500ms
    'api_response_time' => 200,      // 从500ms改为200ms
    'concurrent_devices' => 2000,    // 从1000改为2000
    'success_rate' => 99.9,          // 从99.0改为99.9
],
```

### 自定义3: 修改并发测试级别

编辑 `config.php`:

```php
'load_test_levels' => [
    'light' => 50,       // 原来10
    'normal' => 200,     // 原来100
    'medium' => 1000,    // 原来500
    'high' => 2000,      // 原来1000
    'extreme' => 5000,   // 新增级别
],
```

### 自定义4: 添加数据库测试查询

编辑 `config.php`:

```php
'database_tests' => [
    'queries' => [
        // ... 现有查询 ...

        // 添加自定义查询
        'my_complex_query' => '
            SELECT
                u.id,
                u.username,
                COUNT(ct.id) as task_count
            FROM xmt_users u
            LEFT JOIN xmt_content_tasks ct ON u.id = ct.user_id
            WHERE u.status = 1
            GROUP BY u.id
            LIMIT 10
        ',
    ],
],
```

## 输出解读示例

### 示例输出1: 全部通过

```
================================================================================
性能基准测试报告
================================================================================

[API响应时间测试结果]
--------------------------------------------------------------------------------

✓ nfc_trigger:
  URL: /api/nfc/trigger
  平均响应时间: 245ms        👈 优秀！远低于1000ms目标
  95百分位: 380ms
  目标时间: 1000ms
  成功率: 100%
  状态: PASS                  👈 通过

✓ auth_login:
  平均响应时间: 156ms        👈 优秀！低于500ms目标
  状态: PASS

[并发负载测试结果]
--------------------------------------------------------------------------------

✓ high - 1000 并发用户:
  成功率: 99.2%              👈 优秀！超过95%目标
  平均响应时间: 890ms
  每秒请求数(RPS): 1123.45   👈 高吞吐量
  状态: PASS

[总体测试摘要]
总测试数: 15
通过: 15                     👈 100%通过率
失败: 0
成功率: 100%

=== 总体状态: PASS ===       👈 所有要求满足
```

### 示例输出2: 部分失败

```
[API响应时间测试结果]

✗ content_generate:
  平均响应时间: 35200ms      👈 警告！超过30000ms目标
  95百分位: 45000ms
  目标时间: 30000ms
  成功率: 98%
  状态: FAIL                  👈 未通过

[并发负载测试结果]

✗ high - 1000 并发用户:
  成功率: 92.5%              👈 警告！低于95%目标
  平均响应时间: 1250ms
  状态: FAIL

[总体测试摘要]
通过: 13
失败: 2                      👈 2项未通过
成功率: 86.67%

=== 总体状态: FAIL ===       👈 需要优化

⚠️ 建议:
1. 优化AI内容生成逻辑
2. 增加数据库连接池
3. 启用缓存机制
```

### 示例输出3: 内存警告

```
[内存使用测试结果]

✓ 单个请求内存使用: 2.5 MB
✓ 峰值内存使用: 128 MB

⚠ 内存泄漏检测: 可能存在      👈 警告！检测到潜在泄漏
  总增长: 15 MB
  每10次请求增长: 150 KB       👈 超过100KB阈值

总体状态: WARNING

⚠️ 建议:
1. 检查是否有未释放的对象
2. 使用Xdebug profiler分析
3. 及时调用gc_collect_cycles()
```

## 常见问题解决示例

### 问题1: 登录失败

**错误信息**:
```
[步骤 1/5] 用户登录认证...
✗ 登录失败，将跳过需要认证的API测试
```

**解决方案**:
```bash
# 方案1: 使用正确的测试账号
# 编辑 config.php
'test_data' => [
    'login' => [
        'username' => '13800138000',  # 确认账号存在
        'password' => 'test123456',   # 确认密码正确
    ],
],

# 方案2: 跳过需要认证的测试
php performance.php --skip-login
```

### 问题2: 数据库连接失败

**错误信息**:
```
数据库连接失败: Access denied for user 'root'@'localhost'
```

**解决方案**:
```bash
# 1. 检查环境变量
cat .env | grep database

# 2. 测试数据库连接
php -r "new PDO('mysql:host=127.0.0.1;dbname=xiaomotui', 'root', 'password');"

# 3. 或跳过数据库测试
php performance.php --skip-db
```

### 问题3: API服务未运行

**错误信息**:
```
Failed to connect to localhost port 8000: Connection refused
```

**解决方案**:
```bash
# 1. 启动API服务
cd D:\xiaomotui\api
php think run -p 8000

# 2. 或修改config.php中的base_url
'base_url' => 'http://your-api-server:port',
```

### 问题4: 内存不足

**错误信息**:
```
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

**解决方案**:
```bash
# 方案1: 增加PHP内存限制
php -d memory_limit=512M performance.php

# 方案2: 使用快速模式
php performance.php --quick

# 方案3: 跳过内存密集型测试
php performance.php --skip-concurrent
```

## CI/CD集成示例

### GitHub Actions

```yaml
name: Performance Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  performance:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: pdo, pdo_mysql, curl

    - name: Start Services
      run: |
        # 启动API服务
        cd api
        php think run -p 8000 &
        sleep 5

    - name: Run Performance Tests
      run: |
        cd api/tests/benchmark
        php performance.php --quick

    - name: Upload Report
      uses: actions/upload-artifact@v2
      with:
        name: performance-report
        path: api/tests/benchmark/reports/*.json
```

### Jenkins Pipeline

```groovy
pipeline {
    agent any

    stages {
        stage('Setup') {
            steps {
                sh 'cd api && composer install'
            }
        }

        stage('Start Services') {
            steps {
                sh 'cd api && php think run -p 8000 &'
                sh 'sleep 5'
            }
        }

        stage('Performance Test') {
            steps {
                sh 'cd api/tests/benchmark && ./run_benchmark.sh --quick'
            }
        }

        stage('Archive Report') {
            steps {
                archiveArtifacts artifacts: 'api/tests/benchmark/reports/*.json'
            }
        }
    }
}
```

## 最佳实践总结

### ✅ DO - 推荐做法

1. **定期执行**: 每次重要更新后运行测试
2. **保存报告**: 保留历史报告进行对比
3. **CI集成**: 集成到持续集成流程
4. **监控趋势**: 跟踪性能变化趋势
5. **真实环境**: 在类似生产环境测试

### ❌ DON'T - 避免做法

1. **生产测试**: 不要在生产环境运行压力测试
2. **忽略警告**: 不要忽略内存泄漏警告
3. **过度优化**: 不要过度追求不必要的性能
4. **单次测试**: 不要依赖单次测试结果
5. **无监控**: 不要测试后不监控生产环境

---

**文档版本**: v1.0.0
**最后更新**: 2025-10-01
