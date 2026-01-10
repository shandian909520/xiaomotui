# 桌号绑定服务使用文档

## 概述

TableService（桌号绑定服务）提供餐饮场景中的桌号绑定、用餐会话管理和服务呼叫功能。

## 功能模块

### 1. 桌号绑定管理
- NFC设备与桌号的绑定关系管理
- 用户扫码/碰一碰后自动绑定到桌号
- 支持多用户同桌（拼桌场景）
- 桌号状态管理（空闲/使用中/清理中）

### 2. 用餐会话管理
- 创建用餐会话（用户进店-离店的完整周期）
- 会话状态跟踪（进行中/已完成/已取消）
- 多用户关联到同一会话
- 会话结束后自动清理

### 3. 服务呼叫功能
- 呼叫服务员（点餐、加水、结账等）
- 呼叫优先级管理（低/普通/高/紧急）
- 呼叫状态跟踪（待处理/处理中/已完成/已取消）
- 服务响应时间统计

### 4. 数据统计分析
- 桌台使用率统计
- 服务呼叫统计
- 平均响应时间统计
- 翻台率计算

## 数据库表结构

### 桌台表 (tables)
```sql
CREATE TABLE `xmt_tables` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_number` varchar(20) NOT NULL COMMENT '桌号',
  `capacity` tinyint(3) NOT NULL DEFAULT '4' COMMENT '容纳人数',
  `area` varchar(50) DEFAULT NULL COMMENT '区域',
  `qr_code` varchar(255) DEFAULT NULL COMMENT '二维码',
  `status` enum('AVAILABLE','OCCUPIED','CLEANING') DEFAULT 'AVAILABLE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_merchant_table` (`merchant_id`, `table_number`)
);
```

### 用餐会话表 (dining_sessions)
```sql
CREATE TABLE `xmt_dining_sessions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL,
  `table_id` int(11) unsigned NOT NULL,
  `device_id` int(11) unsigned DEFAULT NULL,
  `session_code` varchar(32) NOT NULL COMMENT '会话编码',
  `status` enum('ACTIVE','COMPLETED','CANCELLED') DEFAULT 'ACTIVE',
  `guest_count` tinyint(3) DEFAULT '1' COMMENT '用餐人数',
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT '用餐时长(分钟)',
  PRIMARY KEY (`id`)
);
```

### 会话用户关联表 (session_users)
```sql
CREATE TABLE `xmt_session_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `is_host` tinyint(1) DEFAULT '0' COMMENT '是否为主用户',
  `join_time` datetime NOT NULL,
  `leave_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### 服务呼叫表 (service_calls)
```sql
CREATE TABLE `xmt_service_calls` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `merchant_id` int(11) unsigned NOT NULL,
  `table_id` int(11) unsigned NOT NULL,
  `call_type` enum('ORDER','WATER','BILL','OTHER') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `priority` enum('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL',
  `status` enum('PENDING','PROCESSING','COMPLETED','CANCELLED') DEFAULT 'PENDING',
  `staff_id` int(11) unsigned DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL COMMENT '响应时间(秒)',
  `complete_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

## API使用示例

### 1. NFC设备触发桌号绑定

```php
use app\service\TableService;

$tableService = new TableService();

// 用户扫码/碰一碰NFC设备
$result = $tableService->bindTableByDevice('NFC001', $userId);

// 返回结果
// type: 'new_session' | 'existing_session' | 'join_session'
// session_id: 会话ID
// session_code: 会话编码
// table_number: 桌号
// is_host: 是否为主用户
// guest_count: 用餐人数
// start_time: 开始时间
// message: 提示消息
```

### 2. 创建服务呼叫

```php
// 用户呼叫服务
$result = $tableService->createServiceCall(
    $sessionId,
    $userId,
    ServiceCall::TYPE_WATER,  // 呼叫类型：ORDER/WATER/BILL/OTHER
    '请帮忙加一壶热水',        // 描述（可选）
    ServiceCall::PRIORITY_NORMAL  // 优先级：LOW/NORMAL/HIGH/URGENT
);

// 返回结果
// call_id: 呼叫ID
// call_type: 呼叫类型
// status: 状态
// message: 提示消息
```

### 3. 处理服务呼叫（商家端）

```php
// 获取待处理的呼叫列表
$pendingCalls = $tableService->getMerchantPendingCalls($merchantId);

// 开始处理呼叫
$result = $tableService->processServiceCall($callId, $staffId);

// 完成处理
$result = $tableService->completeServiceCall($callId);
```

### 4. 获取会话详情

```php
$detail = $tableService->getSessionDetail($sessionId);

// 返回结果包含：
// - 会话基本信息
// - 桌台信息
// - 用户列表
// - 服务呼叫记录
```

### 5. 用户离开会话

```php
$result = $tableService->leaveSession($sessionId, $userId);

// 如果是主用户离开且桌上还有其他用户，会自动转移主用户权限
// 如果是最后一个用户离开，会自动结束会话
```

### 6. 结束用餐会话

```php
// 主用户结束会话
$result = $tableService->endDiningSession($sessionId, $userId);

// 或商家端强制结束
$result = $tableService->endDiningSession($sessionId);

// 会话结束后：
// - 所有用户标记为离开
// - 待处理的服务呼叫自动取消
// - 桌台状态设为清理中
```

### 7. 清理桌台

```php
// 清理完成后，将桌台设为可用状态
$result = $tableService->cleanTable($tableId);
```

### 8. 桌台使用统计

```php
$stats = $tableService->getTableUsageStats(
    $merchantId,
    '2025-01-01',  // 开始日期
    '2025-01-31'   // 结束日期
);

// 返回结果：
// - total_tables: 总桌台数
// - occupied_tables: 使用中桌台
// - usage_rate: 使用率
// - total_sessions: 总会话数
// - turnover_rate: 翻台率
// - avg_duration: 平均用餐时长
// - avg_guests: 平均用餐人数
```

### 9. 服务呼叫统计

```php
$stats = $tableService->getServiceCallStats(
    $merchantId,
    '2025-01-01',
    '2025-01-31'
);

// 返回结果：
// - total_calls: 总呼叫数
// - completed_calls: 已完成数
// - completion_rate: 完成率
// - avg_response_time: 平均响应时间
// - call_type_stats: 按类型统计
// - priority_stats: 按优先级统计
```

## 使用场景

### 场景1：用户进店扫码用餐

```php
// 1. 用户扫码NFC设备
$bindResult = $tableService->bindTableByDevice($deviceCode, $userId);

// 2. 用户呼叫服务
$callResult = $tableService->createServiceCall(
    $bindResult['session_id'],
    $userId,
    ServiceCall::TYPE_ORDER,
    '请帮忙点餐'
);

// 3. 服务员处理呼叫
$processResult = $tableService->processServiceCall($callResult['call_id'], $staffId);
$completeResult = $tableService->completeServiceCall($callResult['call_id']);

// 4. 用户结束用餐
$endResult = $tableService->endDiningSession($bindResult['session_id'], $userId);

// 5. 服务员清理桌台
$cleanResult = $tableService->cleanTable($tableId);
```

### 场景2：多人拼桌

```php
// 第一个用户扫码
$user1Result = $tableService->bindTableByDevice($deviceCode, $user1Id);
// type: 'new_session', is_host: true

// 第二个用户扫码同一桌台
$user2Result = $tableService->bindTableByDevice($deviceCode, $user2Id);
// type: 'join_session', is_host: false

// 第三个用户扫码同一桌台
$user3Result = $tableService->bindTableByDevice($deviceCode, $user3Id);
// type: 'join_session', is_host: false

// 获取会话详情
$detail = $tableService->getSessionDetail($user1Result['session_id']);
// guest_count: 3
// users: [user1(主用户), user2, user3]
```

### 场景3：主用户中途离开

```php
// 主用户离开
$leaveResult = $tableService->leaveSession($sessionId, $hostUserId);
// 系统自动将第一个其他用户设为新主用户

// 查看会话详情
$detail = $tableService->getSessionDetail($sessionId);
// 新的主用户标记为 is_host: true
```

## 模型方法

### Table模型

```php
// 状态检查
$table->isAvailable()  // 是否空闲
$table->isOccupied()   // 是否使用中
$table->isCleaning()   // 是否清理中

// 状态设置
$table->setOccupied()   // 设为使用中
$table->setAvailable()  // 设为空闲
$table->setCleaning()   // 设为清理中

// 静态方法
Table::findByTableNumber($merchantId, $tableNumber)  // 根据桌号查找
Table::getAvailableTables($merchantId, $area)        // 获取可用桌台
Table::getOccupiedTables($merchantId, $area)         // 获取占用桌台
```

### DiningSession模型

```php
// 状态检查
$session->isActive()     // 是否进行中
$session->isCompleted()  // 是否已完成
$session->isCancelled()  // 是否已取消

// 会话操作
$session->complete()                    // 完成会话
$session->cancel()                      // 取消会话
$session->updateGuestCount($count)      // 更新用餐人数

// 静态方法
DiningSession::generateSessionCode()                    // 生成会话编码
DiningSession::findBySessionCode($code)                 // 根据编码查找
DiningSession::getCurrentSessionByTableId($tableId)     // 获取桌台当前会话
DiningSession::getActiveSessions($merchantId)           // 获取活动会话
```

### SessionUser模型

```php
// 状态检查
$sessionUser->isHost()        // 是否主用户
$sessionUser->isInSession()   // 是否还在会话中

// 用户操作
$sessionUser->setAsHost()           // 设为主用户
$sessionUser->leave()               // 离开会话
$sessionUser->getStayDuration()     // 获取停留时长

// 静态方法
SessionUser::findBySessionAndUser($sessionId, $userId)  // 查找关联记录
SessionUser::getUsersBySessionId($sessionId)            // 获取会话所有用户
SessionUser::getHostBySessionId($sessionId)             // 获取主用户
```

### ServiceCall模型

```php
// 状态检查
$call->isPending()      // 是否待处理
$call->isProcessing()   // 是否处理中
$call->isCompleted()    // 是否已完成
$call->isCancelled()    // 是否已取消

// 呼叫操作
$call->startProcessing($staffId)    // 开始处理
$call->complete()                   // 完成处理
$call->cancel()                     // 取消呼叫
$call->getProcessingDuration()      // 获取处理时长

// 静态方法
ServiceCall::getPendingCalls($merchantId)           // 获取待处理呼叫
ServiceCall::getProcessingCalls($merchantId)        // 获取处理中呼叫
ServiceCall::getCallsBySessionId($sessionId)        // 获取会话呼叫记录
ServiceCall::getCallsByTableId($tableId)            // 获取桌台呼叫记录
```

## 常量定义

### 桌台状态
```php
Table::STATUS_AVAILABLE  // 空闲
Table::STATUS_OCCUPIED   // 使用中
Table::STATUS_CLEANING   // 清理中
```

### 会话状态
```php
DiningSession::STATUS_ACTIVE      // 进行中
DiningSession::STATUS_COMPLETED   // 已完成
DiningSession::STATUS_CANCELLED   // 已取消
```

### 呼叫类型
```php
ServiceCall::TYPE_ORDER   // 点餐
ServiceCall::TYPE_WATER   // 加水
ServiceCall::TYPE_BILL    // 结账
ServiceCall::TYPE_OTHER   // 其他
```

### 呼叫优先级
```php
ServiceCall::PRIORITY_LOW      // 低
ServiceCall::PRIORITY_NORMAL   // 普通
ServiceCall::PRIORITY_HIGH     // 高
ServiceCall::PRIORITY_URGENT   // 紧急
```

### 呼叫状态
```php
ServiceCall::STATUS_PENDING      // 待处理
ServiceCall::STATUS_PROCESSING   // 处理中
ServiceCall::STATUS_COMPLETED    // 已完成
ServiceCall::STATUS_CANCELLED    // 已取消
```

## 注意事项

1. **设备绑定**：使用前需要在NFC设备模型中添加 `table_id` 字段，并将设备与桌台关联

2. **权限控制**：
   - 只有主用户可以结束会话
   - 主用户离开时自动转移权限

3. **会话清理**：
   - 会话结束后桌台自动设为清理中
   - 需要手动调用 `cleanTable()` 才能设为可用

4. **通知推送**：
   - 服务呼叫时会调用通知方法
   - 需要集成 NotificationService 或 WebSocket 实现实时推送

5. **数据统计**：
   - 统计数据实时计算，大数据量时建议使用缓存
   - 可以考虑使用定时任务预先计算统计数据

6. **错误处理**：
   - 所有方法都会抛出异常，需要使用 try-catch 捕获
   - 详细错误信息会记录到日志中

## 测试

运行测试文件：
```bash
php test_table_service.php
```

## 依赖

- ThinkPHP 8.0
- 数据库迁移文件已创建
- 依赖模型：Table, DiningSession, SessionUser, ServiceCall, User, NfcDevice

## 更新日志

- 2025-10-01: 初始版本，实现所有核心功能