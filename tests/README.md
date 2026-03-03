# 商家管理模块API测试

## 📋 目录

- [概述](#概述)
- [快速开始](#快速开始)
- [测试文档](#测试文档)
- [测试工具](#测试工具)
- [常见问题](#常见问题)
- [贡献指南](#贡献指南)

---

## 概述

本测试套件用于全面测试小磨推商家管理模块的所有API接口，包括：

- ✅ 用户认证与授权
- ✅ 商家信息管理
- ✅ NFC设备管理
- ✅ 团购配置管理
- ✅ 模板管理
- ✅ 优惠券管理
- ✅ 统计数据获取

**测试覆盖范围**:
- 25+ API接口
- 35+ 测试用例
- 功能测试、权限测试、性能测试

---

## 快速开始

### 1. 环境准备

```bash
# 检查PHP版本 (需要 >= 8.1)
php -v

# 检查MySQL服务
mysql --version

# 安装Composer依赖
cd D:\xiaomotui\api
composer install
```

### 2. 数据库初始化

```bash
# 创建测试数据库
mysql -u root -p
CREATE DATABASE xiaomotui_test;

# 导入测试数据
mysql -u root -p xiaomotui_test < tests/test_data.sql
```

### 3. 配置环境

编辑 `api/.env` 文件：

```env
DATABASE_DATABASE = xiaomotui_test
JWT_SECRET = your_test_secret_key
SMS_DRIVER = mock  # 测试环境使用Mock
```

### 4. 启动服务

```bash
# 启动API服务
cd api
php think run -H localhost -p 8001

# 验证服务
curl http://localhost:8001/health/check
```

### 5. 运行测试

```bash
# 运行完整测试套件
php tests/merchant_api_test.php

# 查看帮助
php tests/merchant_api_test.php --help
```

### 6. 查看结果

测试完成后会显示：
- 总测试数
- 通过/失败数量
- 通过率
- 详细错误信息（如有）

---

## 测试文档

### 核心文档

| 文档 | 说明 |
|-----|------|
| [merchant_api_test.php](merchant_api_test.php) | 完整测试脚本 |
| [MERCHANT_API_TEST_REPORT.md](MERCHANT_API_TEST_REPORT.md) | 详细测试报告 |
| [QUICK_START_TEST.md](QUICK_START_TEST.md) | 快速开始指南 |
| [EXECUTION_PLAN.md](EXECUTION_PLAN.md) | 测试执行计划 |

### 测试用例

测试用例分为以下几类：

#### 1. 功能测试 (25个用例)
- 用户登录认证
- 商家信息CRUD
- NFC设备管理
- 团购配置
- 模板管理
- 优惠券管理

#### 2. 权限测试 (3个用例)
- 未登录访问
- 无效token访问
- 跨商家访问控制

#### 3. 数据验证测试 (3个用例)
- 必填字段验证
- 数据格式验证
- 数据长度验证

#### 4. 边界条件测试 (2个用例)
- 分页边界
- 空数据集

#### 5. 性能测试 (2个用例)
- 响应时间测试
- 并发访问测试

---

## 测试工具

### 1. 自动化测试脚本

```bash
# 运行完整测试
php tests/merchant_api_test.php

# 运行指定模块
php tests/merchant_api_test.php --module=auth

# 生成HTML报告
php tests/merchant_api_test.php --report=html

# 导出JSON结果
php tests/merchant_api_test.php --format=json
```

### 2. Postman集合

导入Postman集合进行可视化测试：

```
文件位置: tests/postman/Xiaomotu-API.postman_collection.json
```

### 3. 性能测试工具

#### Apache Bench

```bash
# 测试接口并发性能
ab -n 1000 -c 10 -H "Authorization: Bearer TOKEN" \
   http://localhost:8001/api/merchant/info
```

#### JMeter

导入JMeter测试计划：
```
文件位置: tests/jmeter/Xiaomotu-API-Test.jmx
```

---

## 测试数据

### 测试账号

| 角色 | 手机号 | 验证码 | 说明 |
|-----|-------|--------|-----|
| 商家 | 13800138000 | 123456 | 测试商家账号 |
| 用户 | 13900139000 | 123456 | 普通用户账号 |

### 测试设备

| 设备编码 | 设备名称 | 触发模式 | 状态 |
|---------|---------|---------|------|
| NFC001 | 1号桌NFC贴片 | VIDEO | 在线 |
| NFC002 | 2号桌NFC贴片 | COUPON | 在线 |
| NFC003 | 收银台NFC | GROUP_BUY | 在线 |

---

## 常见问题

### Q1: 如何修改测试手机号？

**A**: 编辑测试脚本中的常量：

```php
// 在 merchant_api_test.php 中
define('TEST_PHONE', '13800138000');
define('TEST_CODE', '123456');
```

### Q2: 测试失败如何调试？

**A**: 查看详细日志：

```bash
# 启用详细日志
php tests/merchant_api_test.php --verbose

# 查看API日志
tail -f api/runtime/log/$(date +%Y%m%d).log
```

### Q3: 如何只测试特定模块？

**A**: 使用模块参数：

```bash
# 只测试认证模块
php tests/merchant_api_test.php --module=auth

# 只测试商家信息
php tests/merchant_api_test.php --module=merchant

# 只测试设备管理
php tests/merchant_api_test.php --module=device
```

### Q4: 如何添加新的测试用例？

**A**: 编辑测试脚本，添加新的测试函数：

```php
function testNewFeature() {
    global $authToken;

    printStep("测试新功能");
    $response = sendRequest('GET', '/api/new-endpoint', null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);
}
```

### Q5: 测试报告在哪里？

**A**: 测试报告保存在 `tests/reports/` 目录：

```
tests/reports/
├── report_20260125.html
├── report_20260125.json
└── report_20260125.md
```

### Q6: 如何处理token过期？

**A**: 测试脚本会自动处理token刷新：

```php
// 如果token过期，自动刷新
if ($response['http_code'] === 401) {
    $newToken = refreshToken($refreshToken);
    $authToken = $newToken;
}
```

---

## 测试最佳实践

### 1. 测试隔离

每个测试用例应该独立，不依赖其他用例的执行顺序。

### 2. 数据清理

测试完成后清理测试数据：

```bash
# 清理测试数据
php tests/cleanup_test_data.php
```

### 3. 持续集成

集成到CI/CD流程：

```yaml
# .github/workflows/api-test.yml
name: API Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run API Tests
        run: php tests/merchant_api_test.php
```

### 4. 测试覆盖率

使用PHPUnit生成覆盖率报告：

```bash
# 生成覆盖率报告
phpunit --coverage-html tests/coverage
```

---

## 测试报告示例

### 测试统计

```
============================================================
  测试总结
============================================================

  总测试数: 35
  通过: 32
  失败: 3
  通过率: 91.43%

  ✓ 测试结果良好
```

### 模块测试结果

| 模块 | 测试数 | 通过 | 失败 | 通过率 |
|-----|-------|------|------|--------|
| 认证 | 5 | 5 | 0 | 100% |
| 商家信息 | 8 | 7 | 1 | 87.5% |
| NFC设备 | 10 | 9 | 1 | 90% |
| 团购管理 | 6 | 6 | 0 | 100% |
| 模板管理 | 8 | 8 | 0 | 100% |
| 优惠券 | 8 | 7 | 1 | 87.5% |

### 性能测试结果

| 接口 | 平均响应时间 | QPS | 是否达标 |
|-----|------------|-----|---------|
| GET /merchant/info | 45ms | 2000 | ✅ |
| GET /nfc/devices | 180ms | 500 | ✅ |
| POST /coupon/create | 120ms | 800 | ✅ |

---

## 贡献指南

### 如何贡献测试用例？

1. Fork项目
2. 创建特性分支 (`git checkout -b test/new-feature`)
3. 添加测试用例
4. 确保测试通过
5. 提交更改 (`git commit -m 'Add new test cases'`)
6. 推送到分支 (`git push origin test/new-feature`)
7. 创建Pull Request

### 测试用例编写规范

```php
/**
 * 测试用例: 简短描述
 *
 * 测试步骤:
 * 1. 步骤一
 * 2. 步骤二
 *
 * 预期结果: 描述期望的结果
 */
function testExample() {
    // Arrange 准备测试数据
    $testData = ['key' => 'value'];

    // Act 执行测试
    $response = sendRequest('POST', '/api/endpoint', $testData);

    // Assert 验证结果
    $this->assertEquals(200, $response['http_code']);
    $this->assertArrayHasKey('data', $response['data']);
}
```

---

## 联系方式

- **测试负责人**: 测试团队
- **邮箱**: test@xiaomotui.com
- **问题反馈**: [GitHub Issues](https://github.com/xiaomotui/issues)

---

## 许可证

Copyright © 2026 小磨推. All rights reserved.

---

**文档版本**: v1.0
**最后更新**: 2026-01-25
**维护团队**: 测试团队
