# 端到端完整业务流程测试指南

## 测试目标

本测试旨在验证小魔推系统的完整业务流程，从用户注册到内容发布的全链路功能。

## 测试场景覆盖

### 1. 核心业务流程（7个场景）
1. **用户注册和登录** - 微信登录、JWT令牌生成
2. **商家入驻审核** - 商家信息提交、审核流程
3. **设备绑定配置** - NFC设备创建、WiFi配置
4. **NFC触发生成内容** - 设备触发、任务创建
5. **内容预览编辑** - 任务查询、内容编辑
6. **多平台发布** - 抖音、微信、快手发布
7. **数据统计查看** - 触发统计、发布统计

### 2. 异常流程测试（3个场景）
8. **网络中断恢复** - 任务超时、重试机制
9. **服务失败重试** - 重试策略、最大重试次数
10. **数据回滚验证** - 事务回滚、数据一致性

### 3. 多场景业务测试（2个场景）
11. **优惠券发放流程** - 优惠券创建、用户领取
12. **WiFi连接流程** - WiFi设备配置、连接触发

## 数据一致性验证

### 数据库验证
- users表：用户数据完整性
- merchants表：商家状态流转
- nfc_devices表：设备配置完整性
- content_tasks表：任务状态变更
- device_triggers表：触发记录准确性
- coupons表、coupon_users表：优惠券数据

### 缓存验证
- 设备配置缓存
- 用户会话缓存
- 任务状态缓存

### 任务状态验证
- PENDING -> PROCESSING -> COMPLETED
- PENDING -> PROCESSING -> FAILED -> PENDING (重试)

## 性能指标

### 响应时间要求
- NFC触发响应：< 1秒
- 内容生成任务创建：< 500ms
- 任务状态查询：< 200ms
- 多平台发布：< 3秒

### 并发性能
- 支持100+设备同时触发
- 支持1000+用户并发访问

## 测试执行步骤

### 方式一：使用现有测试运行器

```bash
# Windows
cd D:\xiaomotui\api\tests\e2e
php full_flow.php

# Linux/Mac
cd /path/to/xiaomotui/api/tests/e2e
php full_flow.php
```

### 方式二：手动测试步骤

#### 步骤1：准备测试环境

```bash
# 1. 确保数据库运行正常
php think migrate:status

# 2. 清理测试数据（可选）
php think db:seed --class=TestDataCleanup

# 3. 检查配置文件
cat api/.env | grep -E "DB_|CACHE_|QUEUE_"
```

#### 步骤2：执行用户注册登录测试

```bash
# 测试微信登录
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "code": "test_code_13800138000"
  }'

# 预期响应
# {
#   "code": 200,
#   "message": "登录成功",
#   "data": {
#     "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
#     "expires_in": 86400,
#     "user": {
#       "id": 1,
#       "nickname": "测试用户",
#       ...
#     }
#   }
# }
```

#### 步骤3：执行商家入驻测试

```bash
# 创建商家
curl -X POST http://localhost/api/merchant/apply \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "测试餐厅",
    "category": "餐饮",
    "address": "北京市朝阳区测试路123号",
    "phone": "13800138001",
    "description": "这是一家测试餐厅"
  }'

# 审核商家（管理员操作）
curl -X PUT http://localhost/api/admin/merchant/1/approve \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": 1,
    "remark": "审核通过"
  }'
```

#### 步骤4：执行设备绑定测试

```bash
# 创建NFC设备
curl -X POST http://localhost/api/merchant/device \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC_TEST_001",
    "device_name": "门口推广设备",
    "type": "CARD",
    "trigger_mode": "VIDEO",
    "location": "店铺门口"
  }'

# 配置WiFi
curl -X PUT http://localhost/api/merchant/device/1/wifi \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "wifi_ssid": "Test_WiFi",
    "wifi_password": "test123456"
  }'
```

#### 步骤5：执行NFC触发测试

```bash
# 模拟NFC触发
curl -X POST http://localhost/api/nfc/trigger \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC_TEST_001",
    "user_location": {
      "latitude": 39.9042,
      "longitude": 116.4074
    }
  }'

# 预期响应
# {
#   "code": 200,
#   "message": "设备触发成功",
#   "data": {
#     "trigger_id": 1,
#     "action": "generate_content",
#     "content_task_id": 1,
#     "message": "内容生成任务已创建"
#   }
# }
```

#### 步骤6：执行内容生成和编辑测试

```bash
# 查询任务状态
curl -X GET "http://localhost/api/content/task/1/status" \
  -H "Authorization: Bearer {token}"

# 编辑内容
curl -X PUT http://localhost/api/content/task/1 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "【修改后】精彩门店推广视频",
    "description": "欢迎来到我们的店铺"
  }'
```

#### 步骤7：执行多平台发布测试

```bash
# 发布到抖音
curl -X POST http://localhost/api/content/task/1/publish \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "platforms": ["DOUYIN", "WECHAT", "KUAISHOU"],
    "scheduled_time": null
  }'
```

#### 步骤8：执行数据统计测试

```bash
# 查看设备统计
curl -X GET "http://localhost/api/nfc/stats" \
  -H "Authorization: Bearer {token}"

# 查看内容统计
curl -X GET "http://localhost/api/content/stats?start_date=2025-09-01&end_date=2025-10-01" \
  -H "Authorization: Bearer {token}"
```

### 方式三：使用Postman Collection

导入 `api/docs/postman/E2E_Full_Flow.postman_collection.json` 到Postman，按顺序执行所有请求。

## 数据验证

### SQL验证脚本

```sql
-- 验证用户数据
SELECT * FROM users WHERE phone = '13800138000';

-- 验证商家数据
SELECT * FROM merchants WHERE name LIKE '%测试%' ORDER BY create_time DESC LIMIT 5;

-- 验证设备数据
SELECT * FROM nfc_devices WHERE device_code LIKE 'NFC_TEST%';

-- 验证触发记录
SELECT
    dt.id,
    dt.device_code,
    dt.trigger_mode,
    dt.action,
    dt.response_time,
    dt.create_time
FROM device_triggers dt
WHERE dt.device_code LIKE 'NFC_TEST%'
ORDER BY dt.create_time DESC
LIMIT 10;

-- 验证内容任务
SELECT
    ct.id,
    ct.title,
    ct.type,
    ct.status,
    ct.publish_status,
    ct.create_time
FROM content_tasks ct
WHERE ct.merchant_id IN (SELECT id FROM merchants WHERE name LIKE '%测试%')
ORDER BY ct.create_time DESC
LIMIT 10;

-- 验证优惠券数据
SELECT
    c.id,
    c.title,
    c.total_count,
    c.received_count,
    c.status
FROM coupons c
WHERE c.merchant_id IN (SELECT id FROM merchants WHERE name LIKE '%测试%')
ORDER BY c.create_time DESC;

-- 验证数据一致性
SELECT
    '设备触发' AS type,
    COUNT(*) AS total,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS success_count,
    AVG(response_time) AS avg_response_time
FROM device_triggers
WHERE create_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)

UNION ALL

SELECT
    '内容任务' AS type,
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS success_count,
    NULL AS avg_response_time
FROM content_tasks
WHERE create_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

## 测试报告

测试完成后，会生成以下报告文件：

### 报告文件位置
- `tests/logs/e2e_test_report_{timestamp}.json` - 详细JSON报告
- `tests/e2e/reports/full_flow_{timestamp}.html` - HTML可视化报告
- `tests/logs/performance_metrics.log` - 性能指标日志

### 报告内容包括
1. **测试摘要**
   - 总场景数
   - 通过场景数
   - 失败场景数
   - 成功率

2. **场景详情**
   - 每个场景的执行结果
   - 执行时间
   - 错误信息（如有）
   - 详细数据

3. **性能指标**
   - 各场景响应时间
   - 数据库查询性能
   - 缓存命中率

4. **数据一致性**
   - 数据库验证结果
   - 缓存验证结果
   - 状态流转验证

## 常见问题

### Q1: 数据库连接失败
**解决方案：**
```bash
# 检查数据库配置
cat api/.env | grep DB_

# 测试数据库连接
php api/database/test_connection.php

# 确保MySQL服务运行
# Windows: services.msc 查看MySQL服务
# Linux: systemctl status mysql
```

### Q2: JWT令牌验证失败
**解决方案：**
```bash
# 检查JWT配置
cat api/.env | grep JWT_

# 确保secret_key已配置
# JWT_SECRET_KEY = your_secret_key_here
```

### Q3: 任务生成失败
**解决方案：**
```bash
# 检查队列配置
php think queue:status

# 启动队列消费者
php think queue:listen
```

### Q4: 缓存问题
**解决方案：**
```bash
# 清理缓存
php think clear --cache

# 或者重启Redis
# Windows: net stop Redis & net start Redis
# Linux: systemctl restart redis
```

## 性能优化建议

### 1. 数据库优化
- 为常用查询字段添加索引
- 使用缓存减少数据库查询
- 定期清理历史数据

### 2. 缓存优化
- 合理设置缓存过期时间
- 使用缓存预热
- 实现缓存降级策略

### 3. 队列优化
- 使用异步队列处理耗时任务
- 合理设置队列优先级
- 监控队列堆积情况

## 测试最佳实践

### 1. 测试数据隔离
- 使用专门的测试数据库
- 测试后自动清理数据
- 避免影响生产数据

### 2. 测试环境管理
- 维护独立的测试环境
- 配置文件分离（.env.testing）
- 版本控制测试脚本

### 3. 持续集成
- 集成到CI/CD流程
- 自动化测试执行
- 测试报告自动生成

### 4. 监控和告警
- 监控测试执行状态
- 失败时发送告警
- 保留历史测试记录

## 扩展测试场景

### 压力测试
```bash
# 使用Apache Bench进行压力测试
ab -n 1000 -c 100 http://localhost/api/nfc/trigger

# 使用JMeter进行复杂场景测试
jmeter -n -t E2E_Stress_Test.jmx -l results.jtl
```

### 安全测试
```bash
# SQL注入测试
sqlmap -u "http://localhost/api/content/task/1/status" --cookie="token={jwt_token}"

# XSS测试
# 在各个输入字段测试XSS payload
```

### 兼容性测试
- 不同浏览器测试
- 不同设备测试
- 不同网络环境测试

## 总结

完成以上测试后，可以全面验证系统的：
- ✅ 功能完整性
- ✅ 数据一致性
- ✅ 异常处理能力
- ✅ 性能表现
- ✅ 用户体验

建议定期执行端到端测试，确保系统稳定可靠。
