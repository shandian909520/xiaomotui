# 内容生成功能测试文档

## 概述

本文档说明内容生成功能的测试覆盖范围、测试策略和如何运行测试。

## 测试文件

- **ContentTest.php** - 内容生成API主测试类
- **ContentTestFactory.php** - 测试数据工厂类
- **TestCase.php** - 测试基类，提供通用测试方法

## 测试覆盖范围

### 1. 内容生成接口 (POST /api/content/generate)

#### 测试用例：

- ✅ testCreateVideoGenerationTask - 创建视频生成任务（成功）
- ✅ testCreateTextGenerationTask - 创建图文生成任务（成功）
- ✅ testCreateTaskWithInvalidTemplateId - 使用无效模板ID（失败，404）
- ✅ testCreateTaskWithoutAuth - 未登录用户创建任务（失败，401）
- ✅ testCreateTaskWithMismatchedTemplateType - 模板类型不匹配（失败，400）

#### 测试验证点：

- 请求参数验证（商家ID、设备ID、模板ID、类型、输入数据）
- JWT认证验证
- 模板存在性验证
- 模板类型匹配验证
- 任务创建成功后的数据库记录
- 返回的任务ID、状态和类型

### 2. 任务状态查询接口 (GET /api/content/task/:id/status)

#### 测试用例：

- ✅ testQueryTaskStatusPending - 查询PENDING状态任务
- ✅ testQueryTaskStatusProcessing - 查询PROCESSING状态任务
- ✅ testQueryTaskStatusCompleted - 查询COMPLETED状态任务
- ✅ testQueryTaskStatusFailed - 查询FAILED状态任务
- ✅ testQueryNonExistentTask - 查询不存在的任务（失败，404）
- ✅ testQueryOtherUserTask - 查询其他用户的任务（失败，403）

#### 测试验证点：

- 不同状态的任务返回正确的status字段
- PENDING状态：progress为0
- PROCESSING状态：progress为50
- COMPLETED状态：progress为100，包含result和generation_time
- FAILED状态：包含error_message
- 用户权限验证
- 任务存在性验证

### 3. 模板列表接口 (GET /api/content/templates)

#### 测试用例：

- ✅ testGetTemplateListWithDefaults - 获取默认模板列表
- ✅ testGetTemplateListByCategory - 按分类筛选模板
- ✅ testGetTemplateListWithPagination - 分页功能测试
- ✅ testGetTemplateListByType - 按类型筛选模板

#### 测试验证点：

- 模板列表返回格式正确
- 分类筛选功能正确
- 类型筛选功能正确
- 分页参数（page、limit）正确工作
- 返回的分页元数据（total、page、limit、pages）

## 测试数据工厂

`ContentTestFactory` 类提供以下工厂方法：

- `createUser()` - 创建测试用户
- `createMerchant()` - 创建测试商家
- `createDevice()` - 创建测试NFC设备
- `createTemplate()` - 创建内容模板
- `createTask()` - 创建内容任务
- `mockWenxinResponse()` - Mock文心一言服务响应
- `mockJianyingResponse()` - Mock剪映服务响应

## Mock服务

测试中对以下AI服务进行了Mock：

1. **WenxinService** - 百度文心一言服务
   - Mock成功响应：返回生成的文案、token数、耗时
   - Mock失败响应：抛出异常

2. **JianyingVideoService** - 剪映视频服务
   - Mock PENDING状态：返回task_id、estimated_time
   - Mock COMPLETED状态：返回视频URL、封面URL、时长
   - Mock FAILED状态：返回错误信息

## 运行测试

### 运行所有测试

```bash
cd D:\xiaomotui\api
vendor/bin/phpunit tests/api/ContentTest.php
```

### 运行单个测试方法

```bash
vendor/bin/phpunit tests/api/ContentTest.php --filter testCreateVideoGenerationTask
```

### 运行测试并生成覆盖率报告

```bash
vendor/bin/phpunit tests/api/ContentTest.php --coverage-html tests/coverage
```

## 测试环境配置

测试使用独立的测试数据库，配置在 `phpunit.xml` 中：

```xml
<env name="DATABASE_NAME" value="xiaomotui_test"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="QUEUE_DRIVER" value="sync"/>
```

每个测试方法执行前后会：
- setUp(): 开启数据库事务、创建测试数据
- tearDown(): 回滚数据库事务、清理缓存

这确保测试之间互不影响，数据隔离。

## 测试数据准备

每个测试用例在 `setUp()` 方法中自动创建：
- 1个测试用户
- 1个测试商家
- 1个测试NFC设备
- 1个测试模板（VIDEO类型）
- 1个JWT token

测试方法可以直接使用这些数据：

```php
$this->user       // 测试用户
$this->merchant   // 测试商家
$this->device     // 测试设备
$this->template   // 测试模板
$this->token      // JWT token
```

## 断言辅助方法

TestCase基类提供了便捷的断言方法：

- `assertSuccess($response)` - 断言响应成功（200）
- `assertError($response, $code)` - 断言响应错误
- `assertHasFields($response, $fields)` - 断言响应包含特定字段
- `assertDatabaseHas($table, $conditions)` - 断言数据库有记录
- `assertDatabaseMissing($table, $conditions)` - 断言数据库没有记录

## 测试覆盖率目标

- 接口覆盖率：100%（所有API接口都有测试）
- 代码覆盖率：80%以上
- 场景覆盖率：包括正常流程和异常流程

## 注意事项

1. **数据库事务**：所有测试在事务中执行，测试结束后自动回滚
2. **Mock服务**：AI服务已被Mock，不会真实调用外部API
3. **缓存隔离**：使用array缓存驱动，测试间相互隔离
4. **测试数据**：使用工厂类创建，保证数据一致性

## 测试结果示例

```
PHPUnit 10.0.0 by Sebastian Bergmann and contributors.

...............                                              15 / 15 (100%)

Time: 00:02.345, Memory: 24.00 MB

OK (15 tests, 87 assertions)
```

## 持续集成

测试应该集成到CI/CD流程中，每次代码提交都自动运行。

## 未来改进

- [ ] 添加性能测试（负载测试）
- [ ] 添加集成测试（真实AI服务调用）
- [ ] 增加边界条件测试
- [ ] 添加并发测试
