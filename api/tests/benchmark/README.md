# 性能基准测试文档

## 概述

本性能基准测试套件用于验证小磨推（XiaoMoTui）API系统是否满足所有性能要求。测试覆盖以下关键性能指标：

- **NFC响应时间**: < 1秒
- **AI内容生成时间**: < 30秒
- **视频处理时间**: < 60秒
- **并发设备支持**: 1000+
- **API响应时间**: < 500ms
- **数据库查询时间**: < 100ms

## 目录结构

```
tests/benchmark/
├── config.php                  # 测试配置文件
├── PerformanceBenchmark.php    # 性能测试核心类
├── performance.php             # 主测试入口
├── run_benchmark.sh            # Linux/Mac执行脚本
├── run_benchmark.bat           # Windows执行脚本
├── README.md                   # 本文档
├── reports/                    # 测试报告目录
└── logs/                       # 测试日志目录
```

## 快速开始

### 环境要求

- PHP 8.0+
- MySQL 5.7+ / 8.0+
- cURL扩展
- PDO扩展
- 至少512MB可用内存

### 基本使用

#### Windows系统

```bash
# 完整测试
run_benchmark.bat

# 快速测试
run_benchmark.bat --quick

# 跳过数据库测试
run_benchmark.bat --skip-db
```

#### Linux/Mac系统

```bash
# 添加执行权限
chmod +x run_benchmark.sh

# 完整测试
./run_benchmark.sh

# 快速测试
./run_benchmark.sh --quick

# 跳过数据库测试
./run_benchmark.sh --skip-db
```

#### 直接使用PHP

```bash
# 完整测试
php performance.php

# 快速测试
php performance.php --quick

# 组合选项
php performance.php --quick --skip-db --skip-memory
```

## 命令行选项

| 选项 | 说明 |
|------|------|
| `--quick` | 快速测试模式（减少迭代次数） |
| `--skip-login` | 跳过登录（仅测试公开接口） |
| `--skip-db` | 跳过数据库性能测试 |
| `--skip-memory` | 跳过内存测试 |
| `--skip-concurrent` | 跳过并发测试 |
| `--help` | 显示帮助信息 |

## 测试场景

### 1. API响应时间测试

测试以下端点的响应时间：

- **NFC触发** (`POST /api/nfc/trigger`)
  - 目标: < 1000ms
  - 迭代次数: 50次
  - 测量指标: 平均时间、最小/最大时间、95百分位

- **用户登录** (`POST /api/auth/login`)
  - 目标: < 500ms
  - 迭代次数: 50次

- **内容生成** (`POST /api/content/generate`)
  - 目标: < 30000ms (30秒)
  - 迭代次数: 50次

- **内容模板列表** (`GET /api/content/templates`)
  - 目标: < 500ms
  - 迭代次数: 50次

### 2. 并发负载测试

测试不同并发级别下的系统表现：

| 级别 | 并发用户数 | 说明 |
|------|-----------|------|
| light | 10 | 轻负载 |
| normal | 100 | 正常负载 |
| medium | 500 | 中等负载 |
| high | 1000 | 高负载（目标） |
| stress | 2000 | 压力测试 |

**测量指标**:
- 成功率
- 平均响应时间
- 每秒请求数（RPS）
- 95百分位响应时间

**通过标准**:
- 1000并发时成功率 ≥ 95%
- 2000并发以下成功率 ≥ 99%

### 3. 内存使用测试

测试场景：

1. **空闲状态**: 测量基础内存使用
2. **单个请求**: 测量单次请求的内存增长
3. **批量请求**: 测量100次请求的平均内存使用
4. **内存泄漏检测**: 多次迭代检测内存增长趋势

**通过标准**:
- 峰值内存使用 ≤ 256MB
- 每请求平均内存 ≤ 5MB
- 无明显内存泄漏（每10次请求增长 < 100KB）

### 4. 数据库性能测试

测试查询类型：

- **简单查询**: `SELECT * FROM users WHERE id = ?`
- **JOIN查询**: `SELECT u.*, m.* FROM users u LEFT JOIN merchants m ...`
- **计数查询**: `SELECT COUNT(*) FROM nfc_devices`
- **WHERE条件查询**: `SELECT * FROM nfc_devices WHERE status = ? AND ...`
- **排序查询**: `SELECT * FROM content_tasks ORDER BY created_at DESC LIMIT 10`

**通过标准**:
- 平均查询时间 ≤ 100ms
- 复杂JOIN查询 ≤ 150ms

## 配置说明

### config.php 主要配置项

```php
[
    // 基础配置
    'base_url' => 'http://localhost:8000',
    'environment' => 'development',

    // 性能目标
    'performance_targets' => [
        'nfc_response_time' => 1000,      // NFC响应时间(ms)
        'ai_generation_time' => 30000,    // AI生成时间(ms)
        'api_response_time' => 500,       // API响应时间(ms)
        'concurrent_devices' => 1000,     // 并发设备数
        'success_rate' => 99.0,           // 成功率(%)
    ],

    // 并发测试级别
    'load_test_levels' => [
        'light' => 10,
        'normal' => 100,
        'medium' => 500,
        'high' => 1000,
        'stress' => 2000,
    ],

    // 测试数据
    'test_data' => [
        'login' => [
            'username' => '13800138000',
            'password' => 'test123456',
        ],
        'nfc_trigger' => [
            'device_code' => 'TEST_DEVICE_001',
            'trigger_type' => 'tap',
        ],
    ],
]
```

### 环境变量配置

确保 `.env` 文件中配置了以下信息：

```env
# 应用配置
APP_URL=http://localhost:8000
APP_ENV=development

# 数据库配置
database.hostname=127.0.0.1
database.hostport=3306
database.database=xiaomotui
database.username=root
database.password=your_password
database.prefix=xmt_
```

## 测试报告

### 报告格式

测试完成后会生成以下格式的报告：

#### 控制台输出

```
================================================================================
性能基准测试报告
Performance Benchmark Report
================================================================================

测试日期: 2025-10-01 12:00:00
测试环境: development
基础URL: http://localhost:8000
测试时长: 125.34 秒

[API响应时间测试结果]
--------------------------------------------------------------------------------

✓ nfc_trigger:
  URL: /api/nfc/trigger
  平均响应时间: 245ms
  95百分位: 380ms
  目标时间: 1000ms
  成功率: 100%
  状态: PASS

✓ content_generate:
  URL: /api/content/generate
  平均响应时间: 15200ms
  95百分位: 22500ms
  目标时间: 30000ms
  成功率: 98%
  状态: PASS

[并发负载测试结果]
--------------------------------------------------------------------------------

✓ high - 1000 并发用户:
  成功率: 99.2%
  平均响应时间: 890ms
  每秒请求数(RPS): 1123.45
  状态: PASS

[内存使用测试结果]
--------------------------------------------------------------------------------

✓ 单个请求内存使用: 2.5 MB
✓ 批量请求平均内存: 2.3 MB
✓ 峰值内存使用: 128 MB
✓ 内存泄漏检测: 未检测到

[数据库性能测试结果]
--------------------------------------------------------------------------------

✓ select_simple:
  平均查询时间: 12ms
  目标时间: 100ms
  状态: PASS

================================================================================
总体测试摘要
================================================================================

总测试数: 15
通过: 15
失败: 0
成功率: 100%

=== 总体状态: PASS ===

所有性能要求均已满足！
```

#### JSON报告

报告保存在 `reports/report_YYYYMMDD_HHMMSS.json`：

```json
{
    "timestamp": "2025-10-01 12:00:00",
    "environment": "development",
    "base_url": "http://localhost:8000",
    "duration": 125.34,
    "results": {
        "api_response_time": { ... },
        "concurrent_load": { ... },
        "memory_usage": { ... },
        "database_performance": { ... }
    }
}
```

### 报告存储

- **位置**: `tests/benchmark/reports/`
- **格式**: JSON
- **命名**: `report_YYYYMMDD_HHMMSS.json`
- **保留**: 默认保留30天

## 性能优化建议

### 如果测试失败

#### API响应时间过长

1. 检查数据库索引
2. 启用查询缓存
3. 优化SQL查询
4. 使用Redis缓存

#### 并发测试失败

1. 增加数据库连接池大小
2. 启用Opcache
3. 优化应用代码
4. 使用负载均衡

#### 内存使用过高

1. 减少单次查询返回的数据量
2. 使用分页
3. 及时释放大对象
4. 检查内存泄漏

#### 数据库查询慢

1. 添加合适的索引
2. 优化JOIN查询
3. 使用EXPLAIN分析
4. 考虑读写分离

## 故障排除

### 常见问题

#### 1. 连接超时

**问题**: `Connection timeout`

**解决**:
- 检查 `base_url` 配置
- 确保API服务正在运行
- 增加 `http_client.timeout` 配置

#### 2. 登录失败

**问题**: `Login failed`

**解决**:
- 检查测试账号是否存在
- 验证账号密码是否正确
- 查看数据库用户表

#### 3. 数据库连接失败

**问题**: `Database connection failed`

**解决**:
- 检查 `.env` 数据库配置
- 确保数据库服务运行
- 验证数据库权限

#### 4. 内存不足

**问题**: `Allowed memory size exhausted`

**解决**:
- 增加 PHP `memory_limit`
- 使用 `--quick` 快速模式
- 跳过内存密集型测试

## 持续集成

### Jenkins配置示例

```groovy
stage('Performance Test') {
    steps {
        sh 'cd api/tests/benchmark'
        sh './run_benchmark.sh --quick'
    }
}
```

### GitHub Actions配置示例

```yaml
- name: Run Performance Tests
  run: |
    cd api/tests/benchmark
    php performance.php --quick
```

## 最佳实践

1. **定期执行**: 每次重大更新后运行完整测试
2. **版本对比**: 保存历史报告，对比性能变化
3. **生产环境**: 在类似生产环境中测试
4. **负载测试**: 使用专业工具（如JMeter、Locust）补充测试
5. **监控**: 结合APM工具（如New Relic）持续监控

## 技术支持

如有问题或建议，请联系：

- 项目文档: `D:\xiaomotui\.claude\specs\xiaomotui\`
- 规格说明: 参考项目需求文档

## 更新日志

### v1.0.0 (2025-10-01)

- 初始版本
- 支持API响应时间测试
- 支持并发负载测试
- 支持内存使用测试
- 支持数据库性能测试
- 生成JSON报告
- 跨平台支持（Windows/Linux/Mac）

## 许可证

本测试套件是小磨推项目的一部分。
