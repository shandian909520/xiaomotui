# 商家管理模块API测试执行计划

## 一、测试前准备

### 1.1 环境检查清单

- [ ] PHP 8.1+ 已安装
- [ ] MySQL 8.0 已安装并运行
- [ ] Composer 依赖已安装
- [ ] 数据库已创建并初始化
- [ ] 测试数据已准备
- [ ] API服务可正常启动

### 1.2 数据库准备

#### 创建测试数据库

```sql
-- 创建测试数据库
CREATE DATABASE IF NOT EXISTS xiaomotui_test
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- 使用测试数据库
USE xiaomotui_test;

-- 运行迁移脚本
source database/migrations/*.sql;
```

#### 准备测试数据

```sql
-- 1. 创建测试用户
INSERT INTO users (id, phone, nickname, status, create_time) VALUES
(1, '13800138000', '测试商家用户', 1, NOW());

-- 2. 创建测试商家
INSERT INTO merchants (id, user_id, name, category, address, status, create_time) VALUES
(1, 1, '测试咖啡店', '餐饮', '北京市朝阳区', 1, NOW());

-- 3. 创建测试NFC设备
INSERT INTO nfc_devices (id, merchant_id, device_code, device_name, type, trigger_mode, status, create_time) VALUES
(1, 1, 'NFC001', '1号桌NFC贴片', 'DESK_STAND', 'VIDEO', 1, NOW()),
(2, 1, 'NFC002', '2号桌NFC贴片', 'DESK_STAND', 'COUPON', 1, NOW()),
(3, 1, 'NFC003', '收银台NFC', 'COUNTER_STAND', 'GROUP_BUY', 1, NOW());

-- 4. 创建测试优惠券
INSERT INTO coupons (id, merchant_id, title, description, discount_type, discount_value,
                     total_count, remain_count, start_time, end_time, status, create_time) VALUES
(1, 1, '新人优惠券', '新用户专享优惠', 'PERCENTAGE', 10.00, 1000, 1000,
 NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1, NOW());

-- 5. 创建测试模板
INSERT INTO content_templates (id, merchant_id, name, type, category, style, content, status, create_time) VALUES
(1, NULL, '咖啡店温馨视频', 'VIDEO', '餐饮', '温馨', '模板内容...', 1, NOW()),
(2, 1, '自定义活动模板', 'IMAGE', '餐饮', '时尚', '自定义内容...', 1, NOW());
```

### 1.3 配置文件检查

#### .env 配置

```env
# 应用配置
APP_DEBUG = true
APP_TRACE = false

# 数据库配置
DATABASE_TYPE = mysql
DATABASE_HOSTNAME = 127.0.0.1
DATABASE_DATABASE = xiaomotui_test
DATABASE_USERNAME = root
DATABASE_PASSWORD =
DATABASE_HOSTPORT = 3306
DATABASE_CHARSET = utf8mb4

# JWT配置
JWT_SECRET = your_test_secret_key_here
JWT_ALGORITHM = HS256
JWT_TTL = 7200
JWT_REFRESH_TTL = 604800

# 短信配置（测试环境可使用Mock）
SMS_DRIVER = mock
SMS_ALIYUN_ACCESS_KEY_ID = test
SMS_ALIYUN_ACCESS_KEY_SECRET = test
SMS_ALIYUN_SIGN_NAME = 测试签名
SMS_ALIYUN_TEMPLATE_CODE = test_template
```

### 1.4 启动服务

```bash
# 启动API服务
cd D:\xiaomotui\api
php think run -H localhost -p 8001

# 验证服务启动
curl http://localhost:8001/health/check
```

## 二、测试执行步骤

### 2.1 执行完整测试套件

```bash
# 运行完整测试
php tests/merchant_api_test.php > tests/test_results.log 2>&1

# 查看结果
type tests\test_results.log
```

### 2.2 分模块执行测试

#### 模块1: 认证测试

```bash
# 仅测试认证相关接口
php -r "
require 'tests/merchant_api_test.php';
// 执行认证模块测试
"
```

#### 模块2: 商家信息测试

```bash
# 仅测试商家信息接口
php -r "
// 执行商家信息模块测试
"
```

### 2.3 手动验证测试

#### 使用Postman手动测试

1. 导入Postman集合（需要创建）
2. 设置环境变量
3. 按顺序执行请求
4. 记录测试结果

#### 使用curl命令测试

```bash
# 创建测试脚本文件 tests/manual_test.sh

#!/bin/bash

BASE_URL="http://localhost:8001"

echo "=== 商家管理API手动测试 ==="

# 1. 登录
echo -e "\n1. 登录测试"
LOGIN_RESPONSE=$(curl -s -X POST $BASE_URL/api/auth/phone-login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138000","code":"123456"}')

echo $LOGIN_RESPONSE | jq .

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.token')
echo "获取的Token: $TOKEN"

# 2. 获取商家信息
echo -e "\n2. 获取商家信息"
curl -s -X GET $BASE_URL/api/merchant/info \
  -H "Authorization: Bearer $TOKEN" | jq .

# 3. 获取设备列表
echo -e "\n3. 获取设备列表"
curl -s -X GET $BASE_URL/api/nfc/devices \
  -H "Authorization: Bearer $TOKEN" | jq .

# 4. 创建优惠券
echo -e "\n4. 创建优惠券"
curl -s -X POST $BASE_URL/api/coupon/create \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "测试优惠券",
    "description": "测试描述",
    "discount_type": "PERCENTAGE",
    "discount_value": 10,
    "total_count": 100
  }' | jq .
```

### 2.4 性能测试

#### 使用Apache Bench

```bash
# 测试商家信息接口并发性能
ab -n 1000 -c 10 -p tests/post_data.json -T application/json \
   -H "Authorization: Bearer YOUR_TOKEN" \
   http://localhost:8001/api/merchant/info

# 测试设备列表接口
ab -n 500 -c 5 \
   -H "Authorization: Bearer YOUR_TOKEN" \
   http://localhost:8001/api/nfc/devices
```

#### 使用JMeter

1. 创建JMeter测试计划
2. 添加HTTP请求采样器
3. 配置线程组（虚拟用户）
4. 运行测试并分析结果

## 三、测试结果记录

### 3.1 测试结果模板

```markdown
# 测试执行结果

**执行时间**: 2026-01-25 10:00:00
**执行人**: 测试工程师
**测试环境**: 开发环境

### 测试统计

| 模块 | 用例数 | 通过 | 失败 | 阻塞 | 通过率 |
|-----|-------|------|------|------|--------|
| 认证 | 5 | 5 | 0 | 0 | 100% |
| 商家信息 | 8 | 7 | 1 | 0 | 87.5% |
| NFC设备 | 10 | 9 | 1 | 0 | 90% |
| 团购管理 | 6 | 5 | 1 | 0 | 83.3% |
| 模板管理 | 8 | 8 | 0 | 0 | 100% |
| 优惠券 | 8 | 7 | 1 | 0 | 87.5% |
| **总计** | **45** | **41** | **4** | **0** | **91.1%** |

### 失败用例详情

#### FC001: 更新商家信息失败

**用例ID**: TC003
**接口**: POST /api/merchant/update
**失败原因**: 字段长度验证错误
**错误信息**: name字段长度超过100字符限制
**严重程度**: 中
**状态**: 待修复

#### FC002: 团购配置保存失败

**用例ID**: TC005
**接口**: PUT /api/nfc/device/:device_id/group-buy
**失败原因**: deal_id字段格式验证错误
**错误信息**: deal_id不能包含特殊字符
**严重程度**: 高
**状态**: 待修复

### 性能测试结果

| 接口 | 平均响应时间 | 90%响应时间 | 最大响应时间 | QPS | 是否达标 |
|-----|------------|------------|------------|-----|---------|
| GET /merchant/info | 45ms | 60ms | 120ms | 2000 | ✅ |
| GET /nfc/devices | 180ms | 250ms | 400ms | 500 | ✅ |
| POST /coupon/create | 120ms | 180ms | 300ms | 800 | ✅ |
| GET /merchant/statistics | 350ms | 500ms | 800ms | 300 | ✅ |

### 问题和风险

#### 问题1: 设备列表查询性能
- **描述**: 设备超过1000条时查询变慢
- **影响**: 用户体验差
- **建议**: 添加索引，使用分页
- **优先级**: 高

#### 问题2: 并发创建优惠券冲突
- **描述**: 高并发时优惠券编号重复
- **影响**: 数据错误
- **建议**: 添加唯一索引，使用分布式锁
- **优先级**: 高

### 测试结论

✅ **通过**: 核心功能正常，可以发布
⚠ **条件通过**: 需要修复高优先级问题
❌ **不通过**: 存在阻塞性问题
```

### 3.2 缺陷报告模板

```markdown
# 缺陷报告

## 基本信息

- **缺陷ID**: BUG-001
- **标题**: 更新商家信息时字段长度验证错误
- **严重程度**: 中
- **优先级**: P1
- **状态**: Open
- **报告人**: 测试工程师
- **报告时间**: 2026-01-25 10:30:00
- ** assigned_to**: 开发工程师

## 环境信息

- **测试环境**: 开发环境
- **API版本**: v1.0.0
- **数据库**: MySQL 8.0

## 复现步骤

1. 登录系统获取token
2. 调用 POST /api/merchant/update
3. 提交name字段超过100字符的数据
4. 观察返回结果

## 期望结果

返回明确的验证错误提示，说明字段长度限制

## 实际结果

返回500服务器错误

## 错误日志

```
[2026-01-25 10:30:00] ERROR: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' at row 1
```

## 附件

- 请求截图
- 响应数据
- 日志文件

## 备注

需要添加字段长度验证，并在数据库层面也设置正确的字段长度
```

## 四、测试报告生成

### 4.1 自动生成报告

```bash
# 运行测试并生成HTML报告
php tests/merchant_api_test.php --report=html --output=tests/reports/report_20260125.html

# 生成JSON格式报告
php tests/merchant_api_test.php --report=json --output=tests/reports/report_20260125.json

# 生成Markdown格式报告
php tests/merchant_api_test.php --report=md --output=tests/reports/report_20260125.md
```

### 4.2 报告内容结构

```html
<!DOCTYPE html>
<html>
<head>
    <title>商家管理API测试报告</title>
    <style>
        /* 报告样式 */
    </style>
</head>
<body>
    <h1>商家管理API测试报告</h1>

    <div id="summary">
        <h2>测试概要</h2>
        <!-- 测试统计图表 -->
    </div>

    <div id="test-cases">
        <h2>测试用例详情</h2>
        <!-- 每个测试用例的执行结果 -->
    </div>

    <div id="defects">
        <h2>缺陷列表</h2>
        <!-- 发现的缺陷 -->
    </div>

    <div id="performance">
        <h2>性能测试结果</h2>
        <!-- 性能数据和图表 -->
    </div>

    <div id="conclusion">
        <h2>测试结论</h2>
        <!-- 总体评价和建议 -->
    </div>
</body>
</html>
```

## 五、后续行动

### 5.1 立即行动（今天）

1. **运行测试** - 执行完整测试套件
2. **记录结果** - 记录所有失败用例
3. **分析原因** - 分析失败原因
4. **创建缺陷** - 为每个问题创建缺陷报告

### 5.2 短期行动（本周）

1. **修复Bug** - 修复高优先级问题
2. **回归测试** - 验证修复结果
3. **补充测试** - 添加遗漏的测试用例
4. **更新文档** - 更新API文档

### 5.3 中期行动（本月）

1. **性能优化** - 优化慢查询
2. **压力测试** - 进行压力测试
3. **安全测试** - 进行安全扫描
4. **用户验收** - 组织用户验收测试

### 5.4 长期行动（持续）

1. **自动化测试** - 集成到CI/CD
2. **监控告警** - 建立监控体系
3. **持续优化** - 持续改进
4. **知识积累** - 建立测试知识库

## 六、参考资料

### 6.1 相关文档

- [API接口文档](./docs/api.md)
- [数据库设计文档](./docs/database.md)
- [测试用例文档](./docs/test-cases.md)

### 6.2 工具文档

- [ThinkPHP 8.0 文档](https://www.thinkphp.cn/)
- [PHPUnit 文档](https://phpunit.de/)
- [Postman 使用指南](https://learning.postman.com/)

### 6.3 测试规范

- [API测试规范](./docs/testing-standards.md)
- [缺陷管理规范](./docs/bug-management.md)
- [代码规范](./docs/coding-standards.md)

---

**文档版本**: v1.0
**创建日期**: 2026-01-25
**最后更新**: 2026-01-25
**维护人**: 测试团队
