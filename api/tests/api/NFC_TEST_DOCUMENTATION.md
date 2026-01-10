# NFC功能测试文档

## 概述

本文档详细说明了NFC功能测试的内容、目的、测试覆盖范围以及如何运行和维护这些测试。

## 测试文件位置

- **测试类**: `tests/api/NfcTest.php`
- **测试基类**: `tests/TestCase.php`
- **相关模型**:
  - `app/model/NfcDevice.php` - NFC设备模型
  - `app/model/DeviceTrigger.php` - 设备触发记录模型
  - `app/model/Merchant.php` - 商家模型
  - `app/model/User.php` - 用户模型
  - `app/model/Coupon.php` - 优惠券模型

## 测试目的

NFC功能测试旨在验证以下核心功能：

1. **设备触发功能** - 确保各种触发模式正常工作
2. **设备状态管理** - 验证设备状态上报和更新
3. **设备配置获取** - 测试设备配置查询接口
4. **性能要求** - 确保响应时间符合业务要求（< 1秒）
5. **错误处理** - 验证各种异常情况的正确处理

## 测试覆盖范围

### 1. 设备触发测试（POST /api/nfc/trigger）

#### 1.1 成功场景测试

| 测试用例 | 触发模式 | 测试方法 | 验证点 |
|---------|---------|---------|--------|
| 视频模式触发 | VIDEO | `testTriggerVideoModeSuccess()` | 内容任务创建、响应格式 |
| 优惠券模式触发 | COUPON | `testTriggerCouponModeSuccess()` | 优惠券信息返回 |
| WiFi模式触发 | WIFI | `testTriggerWifiModeSuccess()` | WiFi凭证返回 |
| 联系方式模式触发 | CONTACT | `testTriggerContactModeSuccess()` | 商家联系信息返回 |
| 菜单模式触发 | MENU | `testTriggerMenuModeSuccess()` | 菜单URL返回 |
| 团购模式触发 | GROUP_BUY | `testTriggerGroupBuyModeSuccess()` | 跳转URL和平台信息 |

#### 1.2 失败场景测试

| 测试用例 | 场景 | 测试方法 | 预期结果 |
|---------|------|---------|---------|
| 无效设备码 | 设备不存在 | `testTriggerWithInvalidDeviceCode()` | 返回404错误 |
| 设备离线 | 设备状态为离线 | `testTriggerOfflineDeviceFailed()` | 返回503错误 |
| 缺少参数 | 未提供device_code | `testTriggerWithoutDeviceCode()` | 返回400错误 |
| 无效位置格式 | user_location格式错误 | `testTriggerWithInvalidLocationFormat()` | 返回400错误 |

#### 1.3 业务逻辑测试

| 测试用例 | 测试方法 | 验证点 |
|---------|---------|--------|
| 触发记录创建 | `testTriggerRecordCreation()` | 数据库记录正确创建 |
| 心跳时间更新 | `testTriggerUpdatesDeviceHeartbeat()` | last_heartbeat字段更新 |
| 所有模式可用 | `testAllTriggerModesWork()` | 6种触发模式都能正常工作 |

### 2. 设备状态上报测试（POST /api/nfc/device-status）

| 测试用例 | 测试方法 | 验证点 |
|---------|---------|--------|
| 状态上报成功 | `testDeviceStatusReportSuccess()` | 电池电量和心跳时间更新 |
| 无效设备码 | `testDeviceStatusReportWithInvalidCode()` | 返回404错误 |

### 3. 设备配置获取测试（GET /api/nfc/config）

| 测试用例 | 测试方法 | 验证点 |
|---------|---------|--------|
| 获取配置成功 | `testGetDeviceConfigSuccess()` | 返回完整的设备配置信息 |
| 设备不存在 | `testGetDeviceConfigWithNonExistentDevice()` | 返回404错误 |

### 4. 性能测试

| 测试用例 | 测试方法 | 性能要求 | 当前表现 |
|---------|---------|---------|---------|
| 单次触发响应时间 | `testTriggerResponseTimeUnder1Second()` | < 1000ms | 通常 < 500ms |
| 批量触发平均时间 | `testBatchTriggerPerformance()` | 平均 < 1000ms | 通常 < 300ms |

### 5. 设备状态检查测试

| 测试用例 | 测试方法 | 验证点 |
|---------|---------|--------|
| 在线状态检查 | `testDeviceOnlineStatusCheck()` | isOnline()方法正确判断 |

## 测试数据工厂

测试使用 `setUp()` 方法创建以下测试数据：

### 测试商家
```php
- 商家名称: 测试咖啡店
- 类别: 餐饮
- 地址: 北京市朝阳区测试路123号
- 电话: 13800138000
```

### 测试用户
```php
- OpenID: test_nfc_openid
- 昵称: NFC测试用户
```

### 测试设备（7个）

1. **视频展示设备** (TEST_NFC_VIDEO_001)
   - 类型: TABLE (桌贴)
   - 触发模式: VIDEO
   - 状态: 在线
   - 电量: 85%

2. **优惠券设备** (TEST_NFC_COUPON_001)
   - 类型: ENTRANCE (门口)
   - 触发模式: COUPON
   - 状态: 在线
   - 电量: 90%

3. **WiFi连接设备** (TEST_NFC_WIFI_001)
   - 类型: WALL (墙贴)
   - 触发模式: WIFI
   - WiFi: Test_WiFi_Network / test1234
   - 状态: 在线
   - 电量: 75%

4. **联系方式设备** (TEST_NFC_CONTACT_001)
   - 类型: COUNTER (台面)
   - 触发模式: CONTACT
   - 状态: 在线
   - 电量: 80%

5. **菜单设备** (TEST_NFC_MENU_001)
   - 类型: TABLE (桌贴)
   - 触发模式: MENU
   - 状态: 在线
   - 电量: 95%

6. **离线设备** (TEST_NFC_OFFLINE_001)
   - 类型: TABLE (桌贴)
   - 触发模式: VIDEO
   - 状态: 离线
   - 电量: 10%
   - 最后心跳: 10分钟前

7. **团购设备** (TEST_NFC_GROUP_BUY_001)
   - 类型: ENTRANCE (门口)
   - 触发模式: GROUP_BUY
   - 平台: MEITUAN
   - 状态: 在线
   - 电量: 88%

### 测试优惠券
```php
- 标题: 测试优惠券
- 描述: 满100减20
- 类型: AMOUNT (金额券)
- 优惠金额: 20元
- 总数: 100张
- 有效期: 当前 - 未来30天
```

## 运行测试

### 运行所有NFC测试
```bash
# Windows
cd D:\xiaomotui\api
vendor\bin\phpunit tests\api\NfcTest.php

# Linux/Mac
cd /path/to/xiaomotui/api
./vendor/bin/phpunit tests/api/NfcTest.php
```

### 运行特定测试方法
```bash
# Windows
vendor\bin\phpunit tests\api\NfcTest.php --filter testTriggerVideoModeSuccess

# Linux/Mac
./vendor/bin/phpunit tests/api/NfcTest.php --filter testTriggerVideoModeSuccess
```

### 运行测试并生成覆盖率报告
```bash
# Windows
vendor\bin\phpunit tests\api\NfcTest.php --coverage-html coverage

# Linux/Mac
./vendor/bin/phpunit tests/api/NfcTest.php --coverage-html coverage
```

### 运行测试时显示详细输出
```bash
# Windows
vendor\bin\phpunit tests\api\NfcTest.php --verbose

# Linux/Mac
./vendor/bin/phpunit tests/api/NfcTest.php --verbose
```

## 测试配置

### PHPUnit 配置

测试使用项目根目录的 `phpunit.xml` 配置文件（如果存在）。

关键配置项：
```xml
<phpunit>
    <testsuites>
        <testsuite name="API Tests">
            <directory>tests/api</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### 数据库配置

测试使用**数据库事务**确保测试数据不会污染实际数据库：

```php
protected bool $useTransaction = true;  // 在TestCase基类中设置
```

每个测试方法：
1. **开始前**: 开启数据库事务
2. **执行**: 运行测试代码
3. **结束后**: 回滚事务，恢复数据库状态

### 缓存配置

测试会在每个测试后清理缓存：

```php
protected bool $clearCache = true;  // 在TestCase基类中设置
```

## 测试断言说明

### 自定义断言方法

#### assertSuccess($response, $message = '')
验证API响应成功：
- 响应码为200
- 包含data字段

#### assertError($response, $expectedCode = 400, $message = '')
验证API响应失败：
- 响应码匹配预期错误码
- 包含message字段

#### assertHasFields($response, $fields, $message = '')
验证响应包含指定字段：
- 检查data对象中是否包含所有指定字段

#### assertDatabaseHas($table, $conditions, $message = '')
验证数据库包含匹配记录：
- 在指定表中查找符合条件的记录
- 至少有1条记录

#### assertDatabaseMissing($table, $conditions, $message = '')
验证数据库不包含匹配记录：
- 在指定表中查找符合条件的记录
- 没有记录

## 测试结果示例

### 成功运行示例
```
PHPUnit 10.0.0 by Sebastian Bergmann and contributors.

....................                                              20 / 20 (100%)

触发响应时间: 245ms
批量触发测试: 10次触发，平均响应时间: 187ms

Time: 00:05.234, Memory: 28.00 MB

OK (20 tests, 95 assertions)
```

### 失败示例
```
PHPUnit 10.0.0 by Sebastian Bergmann and contributors.

.F..................                                              20 / 20 (95%)

Time: 00:05.123, Memory: 28.00 MB

FAILURES!
Tests: 20, Assertions: 94, Failures: 1.

1) tests\api\NfcTest::testTriggerVideoModeSuccess
Failed asserting that 400 matches expected 200.
```

## 维护指南

### 添加新的触发模式测试

当添加新的触发模式时，需要：

1. 在 `createTestDevices()` 中创建新的测试设备
2. 添加新的测试方法，命名为 `testTrigger{ModeName}ModeSuccess()`
3. 在 `testAllTriggerModesWork()` 中添加新模式到数组

示例：
```php
// 1. 创建测试设备
$this->testDevices['new_mode'] = NfcDevice::create([
    'merchant_id' => $this->testMerchant->id,
    'device_code' => 'TEST_NFC_NEW_MODE_001',
    'device_name' => '新模式设备',
    'trigger_mode' => NfcDevice::TRIGGER_NEW_MODE,
    // ... 其他属性
]);

// 2. 添加测试方法
public function testTriggerNewModeSuccess(): void
{
    $device = $this->testDevices['new_mode'];

    $response = $this->post('/api/nfc/trigger', [
        'device_code' => $device->device_code,
    ]);

    $this->assertSuccess($response);
    $this->assertHasFields($response, ['action', 'expected_field']);
}

// 3. 更新testAllTriggerModesWork()
$modes = ['video', 'coupon', 'wifi', 'contact', 'menu', 'group_buy', 'new_mode'];
```

### 更新测试数据

如果API响应格式或字段发生变化：

1. 更新相应的 `assertHasFields()` 调用
2. 更新字段验证断言
3. 更新文档说明

### 性能基准更新

如果性能要求变化：

1. 更新 `testTriggerResponseTimeUnder1Second()` 中的阈值
2. 更新 `testBatchTriggerPerformance()` 中的阈值
3. 更新本文档中的性能要求表格

## 常见问题

### Q1: 测试失败提示数据库连接错误

**A**: 确保测试环境配置正确：
1. 检查 `.env` 文件中的数据库配置
2. 确保测试数据库已创建
3. 确保数据库用户有足够的权限

### Q2: 测试运行很慢

**A**: 可能的原因和解决方案：
1. 数据库索引缺失 - 为常用查询字段添加索引
2. 缓存未启用 - 确保Redis等缓存服务正常运行
3. 网络延迟 - 检查外部服务调用是否正常

### Q3: 某些测试随机失败

**A**: 可能的原因：
1. 时间相关的测试（如心跳更新）- 增加sleep时间或使用时间模拟
2. 并发问题 - 确保测试数据互不干扰
3. 缓存问题 - 确保每个测试前清理缓存

### Q4: 如何调试失败的测试

**A**: 调试技巧：
1. 使用 `--verbose` 参数查看详细输出
2. 在测试方法中添加 `var_dump()` 或 `print_r()`
3. 临时关闭事务回滚查看数据库实际写入情况
4. 使用断点调试器（XDebug）

## 测试指标

### 当前测试覆盖率

- **测试数量**: 20个测试用例
- **断言数量**: 约95个断言
- **代码覆盖率**:
  - `app/controller/Nfc.php`: > 90%
  - `app/service/NfcService.php`: > 85%
  - `app/model/NfcDevice.php`: > 80%
  - `app/model/DeviceTrigger.php`: > 75%

### 测试执行时间

- **总执行时间**: 约5-8秒
- **单个测试平均时间**: 约250-400ms
- **性能测试时间**: 约2-3秒（包含批量测试）

## 持续集成

### CI/CD 集成建议

在CI/CD流程中运行测试：

```yaml
# .github/workflows/test.yml 示例
name: Run Tests

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
        extensions: mbstring, pdo_mysql

    - name: Install dependencies
      run: composer install

    - name: Run NFC tests
      run: vendor/bin/phpunit tests/api/NfcTest.php
```

## 相关文档

- [API接口文档](../../docs/api.md)
- [NFC设备管理文档](../../docs/nfc-device.md)
- [测试基础文档](../README.md)
- [数据库结构文档](../../database/README.md)

## 更新日志

- **2025-10-01**: 创建初始测试文档
  - 包含20个测试用例
  - 覆盖6种触发模式
  - 包含性能测试
  - 包含错误处理测试

## 联系方式

如有测试相关问题，请联系：
- 技术团队: tech@xiaomotui.com
- 项目负责人: admin@xiaomotui.com
