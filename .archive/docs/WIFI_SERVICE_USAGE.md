# WiFi连接服务使用说明

## 概述

WiFi连接服务(`WifiService`)是小摸推NFC系统的核心服务之一，专门处理NFC设备触发的WiFi自动连接功能。该服务支持多平台(iOS/Android/微信小程序)，提供了完整的WiFi配置生成、验证、连接追踪和统计功能。

## 功能特性

### 1. 多平台支持
- **iOS**: 生成mobileconfig配置描述文件，用户下载后可自动安装WiFi配置
- **Android**: 生成标准WiFi URI格式和二维码，支持相机扫描连接
- **微信小程序**: 返回完整的配置信息，包含二维码、手动连接指南等

### 2. WiFi配置验证
- SSID格式验证(1-32字符，不含控制字符)
- 密码强度验证(WPA: 8-63字符，WEP: 5/10/13/26字符)
- 加密类型自动检测和验证
- 配置有效性检查

### 3. 安全特性
- 访问频率限制(防止暴力破解)
- 密码加密传输
- IP和User-Agent记录
- 异常访问检测

### 4. 连接追踪
- 连接请求记录
- 用户反馈收集
- 连接成功率统计
- 平台分布分析

### 5. 性能优化
- 配置缓存(10分钟)
- 连接记录缓存(1小时)
- 二维码缓存
- 批量操作支持

## 快速开始

### 基本使用

```php
use app\service\WifiService;
use app\model\NfcDevice;
use app\model\User;

// 创建服务实例
$wifiService = new WifiService();

// 获取设备和用户
$device = NfcDevice::find(1);
$user = User::find(1);

// 生成微信小程序格式的WiFi配置
$config = $wifiService->generateWifiConfig(
    $device,
    WifiService::PLATFORM_WECHAT,
    $user
);

// 返回给前端
return json($config);
```

### 平台特定配置

#### iOS配置
```php
// 生成iOS mobileconfig配置文件
$iosConfig = $wifiService->generateWifiConfig(
    $device,
    WifiService::PLATFORM_IOS,
    $user
);

// 返回的配置包含:
// - config_url: mobileconfig文件下载链接
// - download_url: 下载地址
// - install_guide: 安装步骤
// - notes: 注意事项
```

#### Android配置
```php
// 生成Android WiFi URI和二维码
$androidConfig = $wifiService->generateWifiConfig(
    $device,
    WifiService::PLATFORM_ANDROID,
    $user
);

// 返回的配置包含:
// - uri: WiFi连接URI
// - qr_code_url: 二维码图片链接
// - qr_code_data: 二维码数据
// - install_guide: 连接步骤
```

#### 微信小程序配置
```php
// 生成微信小程序格式
$wechatConfig = $wifiService->generateWifiConfig(
    $device,
    WifiService::PLATFORM_WECHAT,
    $user
);

// 返回的配置包含:
// - ssid: WiFi名称
// - password: WiFi密码(可脱敏)
// - encryption: 加密类型
// - qr_code_url: 二维码链接
// - connection_guide: 连接指南
// - network_info: 网络信息
// - tips: 提示信息
```

## WiFi配置验证

### 验证配置有效性

```php
// 验证WiFi配置
$result = $wifiService->validateWifiConfig(
    'TestWiFi',           // SSID
    'password123',        // 密码
    WifiService::ENCRYPTION_WPA2  // 加密类型(可选)
);

if ($result['valid']) {
    echo "配置有效";
} else {
    echo "配置错误: " . implode(', ', $result['errors']);
}

// 检查警告
if (!empty($result['warnings'])) {
    echo "警告: " . implode(', ', $result['warnings']);
}
```

### 支持的加密类型

```php
WifiService::ENCRYPTION_NONE   // 开放网络(无密码)
WifiService::ENCRYPTION_WEP    // WEP加密
WifiService::ENCRYPTION_WPA    // WPA加密
WifiService::ENCRYPTION_WPA2   // WPA2加密(默认)
WifiService::ENCRYPTION_WPA3   // WPA3加密
```

## 连接追踪和统计

### 记录连接反馈

```php
// 记录成功连接
$wifiService->recordConnectionFeedback(
    $userId,
    $deviceId,
    true,           // 是否成功
    '连接成功'      // 消息
);

// 记录失败连接
$wifiService->recordConnectionFeedback(
    $userId,
    $deviceId,
    false,
    '密码错误'
);
```

### 获取连接统计

```php
// 获取今天的统计
$stats = $wifiService->getConnectionStats($deviceId);

// 获取指定日期的统计
$stats = $wifiService->getConnectionStats($deviceId, '2025-09-30');

// 统计数据包含:
// - total_requests: 总请求数
// - success_count: 成功连接数
// - failure_count: 失败连接数
// - success_rate: 成功率
// - platforms: 平台分布
```

### 获取商家统计

```php
// 获取商家的WiFi服务统计
$merchantStats = $wifiService->getWifiServiceStats(
    $merchantId,
    '2025-09-01',  // 开始日期
    '2025-09-30'   // 结束日期
);
```

## 在NFC触发中使用

WiFi服务已经集成到NFC服务中，当设备触发模式为`WIFI`时自动调用：

```php
// NfcService中的使用示例
protected function handleWifiTrigger(NfcDevice $device, User $user): array
{
    $wifiService = new WifiService();

    // 生成WiFi配置
    $wifiConfig = $wifiService->generateWifiConfig(
        $device,
        WifiService::PLATFORM_WECHAT,
        $user
    );

    // 添加额外信息
    $wifiConfig['type'] = 'wifi';
    $wifiConfig['redirect_url'] = $device->redirect_url;

    return $wifiConfig;
}
```

## 配置选项

WiFi服务的配置文件位于 `config/wifi.php`：

### 主要配置项

```php
return [
    // 是否启用WiFi服务
    'enabled' => true,

    // 是否显示完整密码
    'show_password' => false,

    // 访问频率限制
    'rate_limit' => [
        'enabled' => true,
        'window' => 60,        // 时间窗口(秒)
        'max_attempts' => 10,  // 最大请求次数
    ],

    // 二维码配置
    'qrcode' => [
        'size' => 300,                    // 尺寸
        'error_correction' => 'M',        // 纠错级别
        'storage_path' => 'uploads/wifi/qrcode/',
    ],

    // iOS配置
    'ios' => [
        'storage_path' => 'uploads/wifi/mobileconfig/',
        'expiry' => 2592000,  // 30天
    ],

    // 安全配置
    'security' => [
        'encrypt_password' => true,
        'log_ip' => true,
        'anomaly_detection' => true,
    ],
];
```

## 错误处理

### 常见异常

```php
try {
    $config = $wifiService->generateWifiConfig($device, $platform, $user);
} catch (\think\exception\ValidateException $e) {
    // 验证错误
    switch ($e->getMessage()) {
        case '设备未配置WiFi信息':
            // 处理未配置WiFi的情况
            break;
        case 'WiFi名称长度必须在1-32个字符之间':
            // 处理SSID格式错误
            break;
        case 'WPA密码长度必须在8-63个字符之间':
            // 处理密码格式错误
            break;
        case '访问过于频繁，请稍后再试':
            // 处理访问频率限制
            break;
        case '不支持的平台类型':
            // 处理平台类型错误
            break;
    }
}
```

## 高级用法

### 清除缓存

```php
// 清除设备的WiFi配置缓存
$wifiService->clearWifiConfigCache($deviceId);
```

### 自定义二维码生成

如果需要使用特定的二维码生成库，可以继承`WifiService`并重写`generateQRCode`方法：

```php
class CustomWifiService extends WifiService
{
    protected function generateQRCode(string $data, int $deviceId): string
    {
        // 使用自定义的二维码生成逻辑
        // 例如使用 endroid/qr-code
        $qrCode = QrCode::create($data)
            ->setSize(300)
            ->setMargin(10);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        // 保存并返回URL
        $filename = 'qr_' . $deviceId . '_' . time() . '.png';
        $result->saveToFile(public_path('uploads/wifi/qrcode/' . $filename));

        return config('app.domain') . '/uploads/wifi/qrcode/' . $filename;
    }
}
```

## 最佳实践

### 1. 安全性
- 生产环境中启用`encrypt_password`配置
- 定期检查访问日志，识别异常访问
- 使用强密码策略(WPA2/WPA3 + 12位以上密码)
- 避免在日志中记录完整密码

### 2. 性能优化
- 启用配置缓存减少数据库查询
- 预生成二维码并缓存
- 使用CDN加速静态资源(二维码、配置文件)
- 批量操作时使用异步处理

### 3. 用户体验
- 提供清晰的连接指南
- 根据用户设备类型返回对应格式
- 收集用户反馈优化连接成功率
- 提供多种连接方式(二维码/手动)

### 4. 监控和维护
- 定期检查连接成功率
- 监控访问频率异常
- 清理过期的配置文件和二维码
- 记录详细的操作日志

## 测试

运行测试文件验证功能：

```bash
php test_wifi_service.php
```

测试内容包括：
1. WiFi配置验证
2. 多平台配置生成
3. 连接反馈记录
4. 访问频率限制
5. 连接统计功能

## 注意事项

1. **iOS配置文件**:
   - 需要HTTPS环境才能正常下载和安装
   - 用户首次安装需要输入设备密码确认
   - iOS 11及以上版本支持

2. **Android二维码**:
   - Android 10及以上版本原生支持
   - 部分设备可能需要第三方扫描应用
   - 确保二维码清晰可识别

3. **微信小程序**:
   - 小程序无法直接连接WiFi
   - 提供二维码和手动连接两种方式
   - 注意密码脱敏显示

4. **访问频率限制**:
   - 默认1分钟内最多10次请求
   - 可通过配置文件调整限制策略
   - 管理员操作可考虑豁免限制

5. **文件清理**:
   - 定期清理过期的mobileconfig文件
   - 定期清理缓存的二维码图片
   - 可以设置定时任务自动清理

## 相关文档

- [NFC设备管理](./DATABASE_SETUP.md)
- [缓存服务说明](./app/service/CacheService.php)
- [WiFi配置文件](./config/wifi.php)
- [项目数据库设计](../database/README.md)

## 技术支持

如有问题或建议，请参考：
- 项目文档: `docs/`
- 数据库设计: `database/README.md`
- API示例: `API_EXAMPLES.md`