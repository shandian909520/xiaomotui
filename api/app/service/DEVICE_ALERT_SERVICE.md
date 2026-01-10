# 设备异常告警服务文档

## 概述

DeviceAlertService 是小魔推NFC设备管理平台的核心告警服务，负责监控设备状态、检测异常并及时发送告警通知。

## 功能特性

### 1. 多维度设备监控

- **离线检测**: 检测超过阈值时间未上报心跳的设备
- **低电量检测**: 监控设备电量，支持两级告警（警告/严重）
- **信号强度监控**: 检测设备网络信号质量
- **温度异常监控**: 监控设备工作温度
- **设备错误检测**: 识别设备故障和错误状态

### 2. 智能告警管理

- **告警去重**: 防止同一设备同一类型告警频繁发送
- **频率控制**: 可配置不同告警类型的发送间隔
- **告警级别**: 支持低、中、高、严重四个级别
- **批量处理**: 高效处理多设备告警检查

### 3. 多渠道通知

- **系统内通知**: 缓存通知消息，供商家后台查看
- **微信通知**: 通过企业微信机器人发送
- **短信通知**: 支持阿里云、腾讯云等短信服务商
- **邮件通知**: SMTP邮件发送
- **Webhook**: 支持自定义Webhook回调

## 快速开始

### 基本使用

```php
use app\service\DeviceAlertService;

// 创建服务实例
$alertService = new DeviceAlertService();

// 检查离线设备
$offlineDevices = $alertService->checkOffline();

// 检查低电量设备
$lowBatteryDevices = $alertService->checkLowBattery();

// 检查所有设备问题
$issues = $alertService->checkAllDeviceIssues();

// 执行定期检查并发送告警
$result = $alertService->runPeriodicCheck();
```

### 发送告警

```php
// 发送单个告警
$deviceInfo = [
    'device_id' => 1,
    'device_code' => 'NFC001',
    'device_name' => '门店入口',
    'merchant_id' => 1,
    'location' => '一楼大厅',
    'battery_level' => 15,
];

$alertService->sendAlert(
    DeviceAlertService::TYPE_OFFLINE,
    $deviceInfo,
    DeviceAlertService::LEVEL_ERROR,
    '设备已离线超过30分钟'
);
```

### 检查单个设备

```php
// 检查指定设备是否有告警
$result = $alertService->checkDeviceAlert($deviceId);

if ($result['has_alert']) {
    foreach ($result['alerts'] as $alert) {
        echo "告警类型: {$alert['type']}\n";
        echo "告警级别: {$alert['level']}\n";
        echo "告警消息: {$alert['message']}\n";
    }
}
```

## 配置说明

### 配置文件位置

配置文件位于 `config/device_alert.php`

### 主要配置项

#### 1. 检测阈值配置

```php
// 离线阈值（分钟）
'offline_threshold' => 5,

// 电量阈值
'battery' => [
    'low_threshold' => 20,        // 低电量警告
    'critical_threshold' => 10,   // 严重低电量
],
```

#### 2. 告警频率控制

```php
'alert_frequency' => [
    'offline' => 30,         // 离线告警30分钟发送一次
    'low_battery' => 60,     // 低电量告警60分钟发送一次
    'weak_signal' => 120,    // 信号弱告警120分钟发送一次
    'temperature' => 30,     // 温度异常30分钟发送一次
    'error' => 15,           // 设备错误15分钟发送一次
],
```

#### 3. 通知渠道配置

```php
'notification_channels' => [
    'system' => ['enabled' => true],
    'wechat' => [
        'enabled' => true,
        'webhook_url' => 'https://qyapi.weixin.qq.com/...',
    ],
    'sms' => [
        'enabled' => true,
        'provider' => 'aliyun',
        'access_key' => 'your_key',
        'access_secret' => 'your_secret',
    ],
],
```

#### 4. 告警级别映射

```php
'level_mapping' => [
    'info' => [
        'channels' => ['system'],
    ],
    'warning' => [
        'channels' => ['system', 'wechat'],
    ],
    'error' => [
        'channels' => ['system', 'wechat', 'sms'],
    ],
    'critical' => [
        'channels' => ['system', 'wechat', 'sms', 'email'],
    ],
],
```

## API接口说明

### 核心方法

#### checkOffline(?int $merchantId = null): array

检测离线设备

**参数:**
- `$merchantId` (可选): 商家ID，为null则检查所有商家

**返回值:**
```php
[
    [
        'device_id' => 1,
        'device_code' => 'NFC001',
        'device_name' => '门店入口',
        'merchant_id' => 1,
        'location' => '一楼大厅',
        'last_heartbeat' => '2025-09-30 10:00:00',
        'offline_duration' => 45,  // 分钟
    ],
    // ...
]
```

#### checkLowBattery(?int $merchantId = null): array

检测低电量设备

**返回值:**
```php
[
    [
        'device_id' => 2,
        'device_code' => 'NFC002',
        'device_name' => '收银台',
        'merchant_id' => 1,
        'battery_level' => 15,
        'alert_level' => 'warning',  // 或 'critical'
    ],
    // ...
]
```

#### checkAllDeviceIssues(?int $merchantId = null): array

检查所有设备问题

**返回值:**
```php
[
    'offline' => [...],        // 离线设备列表
    'low_battery' => [...],    // 低电量设备列表
    'total_issues' => 5,       // 问题总数
]
```

#### sendAlert(string $alertType, array $deviceInfo, string $level, string $message = ''): bool

发送告警通知

**参数:**
- `$alertType`: 告警类型（TYPE_OFFLINE, TYPE_LOW_BATTERY等）
- `$deviceInfo`: 设备信息数组
- `$level`: 告警级别（LEVEL_INFO, LEVEL_WARNING等）
- `$message`: 自定义告警消息（可选）

**返回值:** 发送是否成功

#### runPeriodicCheck(): array

执行定期告警检查

**返回值:**
```php
[
    'status' => 'success',
    'issues_found' => 5,       // 发现的问题数
    'alerts_sent' => 5,        // 成功发送的告警数
    'alerts_failed' => 0,      // 失败的告警数
    'details' => [...],        // 详细信息
]
```

#### checkDeviceAlert(int $deviceId): array

检查单个设备告警

**返回值:**
```php
[
    'has_alert' => true,
    'device_id' => 1,
    'device_name' => '门店入口',
    'alerts' => [
        [
            'type' => 'offline',
            'level' => 'error',
            'message' => '设备已离线',
        ],
    ],
]
```

#### getAlertStats(int $merchantId): array

获取商家的设备告警统计

**返回值:**
```php
[
    'merchant_id' => 1,
    'offline_count' => 2,
    'low_battery_count' => 3,
    'total_issues' => 5,
    'check_time' => '2025-09-30 12:00:00',
]
```

### 辅助方法

#### clearAlertRecord(string $alertType, int $deviceId): bool

清除告警发送记录（用于测试或重置频率控制）

#### getAlertFrequencyConfig(): array

获取告警频率配置

## 常量定义

### 告警级别

```php
const LEVEL_INFO = 'info';        // 信息
const LEVEL_WARNING = 'warning';  // 警告
const LEVEL_ERROR = 'error';      // 错误
const LEVEL_CRITICAL = 'critical'; // 严重
```

### 告警类型

```php
const TYPE_OFFLINE = 'offline';           // 设备离线
const TYPE_LOW_BATTERY = 'low_battery';   // 电量低
const TYPE_WEAK_SIGNAL = 'weak_signal';   // 信号弱
const TYPE_TEMPERATURE = 'temperature';   // 温度异常
const TYPE_ERROR = 'error';               // 设备错误
```

## 定时任务配置

### Linux/Mac Crontab

```bash
# 每5分钟执行一次设备告警检查
*/5 * * * * cd /path/to/project/api && php test_device_alert.php >> logs/device_alert.log 2>&1
```

### Windows 任务计划程序

1. 打开"任务计划程序"
2. 创建基本任务
3. 触发器：每5分钟执行一次
4. 操作：启动程序
   - 程序/脚本：`php.exe`
   - 参数：`D:\xiaomotui\api\test_device_alert.php`
   - 起始于：`D:\xiaomotui\api`

### ThinkPHP 命令行

创建定时任务命令：

```php
// app/command/DeviceAlertCheck.php
<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\DeviceAlertService;

class DeviceAlertCheck extends Command
{
    protected function configure()
    {
        $this->setName('device:alert:check')
             ->setDescription('检查设备状态并发送告警');
    }

    protected function execute(Input $input, Output $output)
    {
        $alertService = new DeviceAlertService();
        $result = $alertService->runPeriodicCheck();

        $output->writeln('检查完成！');
        $output->writeln('发现问题: ' . $result['issues_found']);
        $output->writeln('发送成功: ' . $result['alerts_sent']);

        return 0;
    }
}
```

然后在 crontab 中执行：

```bash
*/5 * * * * cd /path/to/project/api && php think device:alert:check
```

## 测试

### 运行测试脚本

```bash
cd /path/to/project/api
php test_device_alert.php
```

测试脚本会执行以下测试：

1. 检查离线设备
2. 检查低电量设备
3. 检查所有设备问题
4. 测试告警发送功能
5. 测试单个设备检查
6. 获取告警统计信息
7. 执行定期告警检查
8. 获取告警频率配置

### 测试输出示例

```
================================================================================
  设备告警服务测试
================================================================================

================================================================================
  测试1: 检查离线设备
================================================================================
发现离线设备数量: 2
离线设备详情:
  - 设备: 门店入口 (NFC001)
    位置: 一楼大厅
    离线时长: 45 分钟
    最后心跳: 2025-09-30 10:15:00
✓ 离线设备检查完成
```

## 最佳实践

### 1. 告警级别使用建议

- **INFO**: 设备上线、状态恢复等正常事件
- **WARNING**: 电量偏低、信号较弱等需要关注的问题
- **ERROR**: 设备离线、电量严重不足等需要及时处理的问题
- **CRITICAL**: 多台设备离线、关键设备故障等紧急情况

### 2. 频率控制建议

- 离线告警：30分钟
- 低电量告警：60分钟（电量变化缓慢）
- 信号弱告警：120分钟（网络波动正常）
- 温度异常：30分钟
- 设备错误：15分钟（需要快速响应）

### 3. 通知渠道选择

| 告警级别 | 系统通知 | 微信 | 短信 | 邮件 |
|---------|---------|------|------|------|
| INFO    | ✓       |      |      |      |
| WARNING | ✓       | ✓    |      |      |
| ERROR   | ✓       | ✓    | ✓    |      |
| CRITICAL| ✓       | ✓    | ✓    | ✓    |

### 4. 性能优化

- 批量检查：一次检查多个设备，减少数据库查询
- 缓存使用：利用缓存存储告警发送时间
- 异步处理：使用队列异步发送通知
- 定期清理：及时清理过期的告警记录

### 5. 监控和维护

- 定期查看告警日志
- 监控告警发送成功率
- 根据实际情况调整阈值
- 定期清理历史告警数据

## 故障排查

### 1. 告警未发送

检查项：
- 配置文件中通知渠道是否启用
- 设备信息是否完整
- 告警频率控制是否生效
- 查看日志文件中的错误信息

### 2. 告警频繁发送

解决方案：
- 检查频率控制配置
- 检查缓存服务是否正常
- 调整告警阈值
- 清除告警发送记录缓存

### 3. 离线检测不准确

检查项：
- 离线阈值配置是否合理
- 设备心跳上报是否正常
- 服务器时间是否准确

## 扩展开发

### 添加新的告警类型

1. 在 DeviceAlertService 中定义新常量

```php
const TYPE_CUSTOM = 'custom';
```

2. 在配置文件中添加频率配置

```php
'alert_frequency' => [
    'custom' => 60,
],
```

3. 实现检测方法

```php
public function checkCustomAlert(?int $merchantId = null): array
{
    // 实现检测逻辑
}
```

### 添加新的通知渠道

1. 在 NotificationService 中添加发送方法

```php
protected function sendCustomNotification(DeviceAlert $alert): bool
{
    // 实现发送逻辑
}
```

2. 在配置中添加渠道配置

```php
'notification_channels' => [
    'custom' => [
        'enabled' => true,
        // 其他配置
    ],
],
```

## 版本历史

- **v1.0.0** (2025-09-30)
  - 初始版本
  - 支持离线和低电量检测
  - 实现告警去重和频率控制
  - 支持多渠道通知

## 技术支持

如有问题或建议，请联系技术团队。

## 许可证

本服务为小魔推项目的一部分，遵循项目整体许可协议。
