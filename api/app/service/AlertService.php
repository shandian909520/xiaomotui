<?php
declare (strict_types = 1);

namespace app\service;

use app\model\DeviceAlert;
use app\model\NfcDevice;
use app\model\DeviceTrigger;
use app\service\CacheService;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Cache;

/**
 * 设备异常告警服务
 */
class AlertService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'alert_';

    /**
     * 告警规则缓存时间(秒)
     */
    const RULE_CACHE_TTL = 1800; // 30分钟

    /**
     * 告警抑制时间(秒)
     */
    const ALERT_SUPPRESS_TIME = 300; // 5分钟内相同告警不重复发送

    /**
     * 通知服务实例
     */
    protected NotificationService $notificationService;

    /**
     * 告警规则服务实例
     */
    protected AlertRuleService $alertRuleService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->alertRuleService = new AlertRuleService();
    }

    /**
     * 检测设备离线告警
     *
     * @param NfcDevice $device
     * @return DeviceAlert|null
     */
    public function checkOfflineAlert(NfcDevice $device): ?DeviceAlert
    {
        if (!$device->isOffline()) {
            return null;
        }

        // 获取离线时长阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_OFFLINE);
        $offlineThreshold = $rule['threshold'] ?? 600; // 默认10分钟

        // 检查设备离线时长
        $lastHeartbeat = strtotime($device->last_heartbeat ?: '1970-01-01');
        $offlineTime = time() - $lastHeartbeat;

        if ($offlineTime < $offlineThreshold) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateOfflineAlertLevel($offlineTime, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_OFFLINE,
            $alertLevel,
            '设备离线告警',
            sprintf('设备 %s (%s) 已离线 %s', $device->device_name, $device->device_code, $this->formatDuration($offlineTime)),
            [
                'offline_time' => $offlineTime,
                'last_heartbeat' => $device->last_heartbeat,
                'device_location' => $device->location,
                'threshold' => $offlineThreshold
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备离线告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测电池电量低告警
     *
     * @param NfcDevice $device
     * @return DeviceAlert|null
     */
    public function checkLowBatteryAlert(NfcDevice $device): ?DeviceAlert
    {
        if ($device->battery_level === null) {
            return null;
        }

        // 获取电量阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_LOW_BATTERY);
        $lowBatteryThreshold = $rule['threshold'] ?? 20; // 默认20%

        if ($device->battery_level > $lowBatteryThreshold) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateBatteryAlertLevel($device->battery_level, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_LOW_BATTERY,
            $alertLevel,
            '设备电量不足告警',
            sprintf('设备 %s (%s) 电量不足，当前电量：%d%%', $device->device_name, $device->device_code, $device->battery_level),
            [
                'battery_level' => $device->battery_level,
                'threshold' => $lowBatteryThreshold,
                'device_location' => $device->location,
                'battery_status' => $device->getBatteryStatusAttr(null, $device->getData())
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备电量不足告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测响应超时告警
     *
     * @param int $deviceId
     * @param int $responseTime
     * @return DeviceAlert|null
     */
    public function checkResponseTimeoutAlert(int $deviceId, int $responseTime): ?DeviceAlert
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            return null;
        }

        // 获取响应时间阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_RESPONSE_TIMEOUT);
        $timeoutThreshold = $rule['threshold'] ?? 5000; // 默认5秒

        if ($responseTime < $timeoutThreshold) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateResponseTimeAlertLevel($responseTime, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_RESPONSE_TIMEOUT,
            $alertLevel,
            '设备响应超时告警',
            sprintf('设备 %s (%s) 响应超时，响应时间：%dms', $device->device_name, $device->device_code, $responseTime),
            [
                'response_time' => $responseTime,
                'threshold' => $timeoutThreshold,
                'device_location' => $device->location
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备响应超时告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测设备故障告警
     *
     * @param NfcDevice $device
     * @param string $errorCode
     * @param string $errorMessage
     * @return DeviceAlert|null
     */
    public function checkDeviceErrorAlert(NfcDevice $device, string $errorCode, string $errorMessage): ?DeviceAlert
    {
        // 获取设备故障规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_DEVICE_ERROR);

        // 根据错误码确定告警级别
        $alertLevel = $this->getErrorAlertLevel($errorCode, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_DEVICE_ERROR,
            $alertLevel,
            '设备故障告警',
            sprintf('设备 %s (%s) 发生故障：%s', $device->device_name, $device->device_code, $errorMessage),
            [
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'device_location' => $device->location,
                'device_status' => $device->status
            ],
            $rule['notification_channels'] ?? ['system', 'wechat']
        );

        $this->logAlert('检测到设备故障告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测信号弱告警
     *
     * @param NfcDevice $device
     * @param int $signalStrength
     * @return DeviceAlert|null
     */
    public function checkSignalWeakAlert(NfcDevice $device, int $signalStrength): ?DeviceAlert
    {
        // 获取信号强度阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_SIGNAL_WEAK);
        $weakSignalThreshold = $rule['threshold'] ?? 30; // 默认30%

        if ($signalStrength >= $weakSignalThreshold) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateSignalAlertLevel($signalStrength, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_SIGNAL_WEAK,
            $alertLevel,
            '设备信号弱告警',
            sprintf('设备 %s (%s) 信号弱，当前信号强度：%d%%', $device->device_name, $device->device_code, $signalStrength),
            [
                'signal_strength' => $signalStrength,
                'threshold' => $weakSignalThreshold,
                'device_location' => $device->location
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备信号弱告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测温度异常告警
     *
     * @param NfcDevice $device
     * @param float $temperature
     * @return DeviceAlert|null
     */
    public function checkTemperatureAlert(NfcDevice $device, float $temperature): ?DeviceAlert
    {
        // 获取温度阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_TEMPERATURE);
        $minTemp = $rule['min_threshold'] ?? -10; // 默认-10°C
        $maxTemp = $rule['max_threshold'] ?? 70;  // 默认70°C

        if ($temperature >= $minTemp && $temperature <= $maxTemp) {
            return null;
        }

        // 确定异常类型和级别
        $alertLevel = DeviceAlert::LEVEL_MEDIUM;
        $temperatureType = 'normal';

        if ($temperature < $minTemp) {
            $temperatureType = 'too_low';
            $alertLevel = $temperature < ($minTemp - 10) ? DeviceAlert::LEVEL_HIGH : DeviceAlert::LEVEL_MEDIUM;
        } elseif ($temperature > $maxTemp) {
            $temperatureType = 'too_high';
            $alertLevel = $temperature > ($maxTemp + 10) ? DeviceAlert::LEVEL_HIGH : DeviceAlert::LEVEL_MEDIUM;
        }

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_TEMPERATURE,
            $alertLevel,
            '设备温度异常告警',
            sprintf('设备 %s (%s) 温度异常，当前温度：%.1f°C', $device->device_name, $device->device_code, $temperature),
            [
                'temperature' => $temperature,
                'min_threshold' => $minTemp,
                'max_threshold' => $maxTemp,
                'temperature_type' => $temperatureType,
                'device_location' => $device->location
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备温度异常告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测心跳异常告警
     *
     * @param NfcDevice $device
     * @return DeviceAlert|null
     */
    public function checkHeartbeatAlert(NfcDevice $device): ?DeviceAlert
    {
        // 获取心跳间隔规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_HEARTBEAT);
        $heartbeatInterval = $rule['threshold'] ?? 300; // 默认5分钟

        if (empty($device->last_heartbeat)) {
            return null;
        }

        $lastHeartbeat = strtotime($device->last_heartbeat);
        $timeSinceHeartbeat = time() - $lastHeartbeat;

        if ($timeSinceHeartbeat < $heartbeatInterval) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateHeartbeatAlertLevel($timeSinceHeartbeat, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_HEARTBEAT,
            $alertLevel,
            '设备心跳异常告警',
            sprintf('设备 %s (%s) 心跳异常，上次心跳时间：%s', $device->device_name, $device->device_code, $device->last_heartbeat),
            [
                'last_heartbeat' => $device->last_heartbeat,
                'time_since_heartbeat' => $timeSinceHeartbeat,
                'threshold' => $heartbeatInterval,
                'device_location' => $device->location
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备心跳异常告警', $device, $alert);
        return $alert;
    }

    /**
     * 检测触发失败告警
     *
     * @param int $deviceId
     * @param int $failureCount
     * @param string $timeRange
     * @return DeviceAlert|null
     */
    public function checkTriggerFailedAlert(int $deviceId, int $failureCount, string $timeRange = '1 hour'): ?DeviceAlert
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            return null;
        }

        // 获取触发失败阈值规则
        $rule = $this->alertRuleService->getRule($device->merchant_id, DeviceAlert::TYPE_TRIGGER_FAILED);
        $failureThreshold = $rule['threshold'] ?? 5; // 默认5次失败

        if ($failureCount < $failureThreshold) {
            return null;
        }

        // 计算告警级别
        $alertLevel = $this->calculateTriggerFailureAlertLevel($failureCount, $rule);

        // 创建告警
        $alert = DeviceAlert::createAlert(
            $device->id,
            $device->device_code,
            $device->merchant_id,
            DeviceAlert::TYPE_TRIGGER_FAILED,
            $alertLevel,
            '设备触发失败告警',
            sprintf('设备 %s (%s) 在过去%s内触发失败%d次', $device->device_name, $device->device_code, $timeRange, $failureCount),
            [
                'failure_count' => $failureCount,
                'time_range' => $timeRange,
                'threshold' => $failureThreshold,
                'device_location' => $device->location
            ],
            $rule['notification_channels'] ?? ['system']
        );

        $this->logAlert('检测到设备触发失败告警', $device, $alert);
        return $alert;
    }

    /**
     * 批量检测设备告警
     *
     * @param int $merchantId 商家ID（可选）
     * @return array
     */
    public function batchCheckAlerts(int $merchantId = null): array
    {
        $startTime = microtime(true);
        $alerts = [];
        $errors = [];

        try {
            // 获取需要检测的设备
            $query = NfcDevice::query();
            if ($merchantId !== null) {
                $query->where('merchant_id', $merchantId);
            }
            $devices = $query->select();

            foreach ($devices as $device) {
                try {
                    // 检测各种告警
                    $deviceAlerts = [];

                    // 离线告警
                    $offlineAlert = $this->checkOfflineAlert($device);
                    if ($offlineAlert) {
                        $deviceAlerts[] = $offlineAlert;
                    }

                    // 电量告警
                    $batteryAlert = $this->checkLowBatteryAlert($device);
                    if ($batteryAlert) {
                        $deviceAlerts[] = $batteryAlert;
                    }

                    // 心跳告警
                    $heartbeatAlert = $this->checkHeartbeatAlert($device);
                    if ($heartbeatAlert) {
                        $deviceAlerts[] = $heartbeatAlert;
                    }

                    // 检查近期触发失败次数
                    $failureCount = $this->getRecentTriggerFailures($device->id);
                    if ($failureCount > 0) {
                        $triggerAlert = $this->checkTriggerFailedAlert($device->id, $failureCount);
                        if ($triggerAlert) {
                            $deviceAlerts[] = $triggerAlert;
                        }
                    }

                    $alerts = array_merge($alerts, $deviceAlerts);

                } catch (\Exception $e) {
                    $errors[] = [
                        'device_id' => $device->id,
                        'device_code' => $device->device_code,
                        'error' => $e->getMessage()
                    ];
                    Log::error('设备告警检测失败', [
                        'device_id' => $device->id,
                        'device_code' => $device->device_code,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 发送通知
            $this->sendPendingNotifications($alerts);

        } catch (\Exception $e) {
            Log::error('批量告警检测失败', ['error' => $e->getMessage()]);
            $errors[] = ['error' => $e->getMessage()];
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('批量告警检测完成', [
            'merchant_id' => $merchantId,
            'devices_count' => count($devices ?? []),
            'alerts_count' => count($alerts),
            'errors_count' => count($errors),
            'execution_time' => $executionTime
        ]);

        return [
            'alerts' => $alerts,
            'errors' => $errors,
            'stats' => [
                'devices_checked' => count($devices ?? []),
                'alerts_generated' => count($alerts),
                'errors_occurred' => count($errors),
                'execution_time' => $executionTime
            ]
        ];
    }

    /**
     * 发送待处理的通知
     *
     * @param array $alerts
     * @return void
     */
    protected function sendPendingNotifications(array $alerts): void
    {
        foreach ($alerts as $alert) {
            if ($alert->needsNotification()) {
                $this->notificationService->sendAlert($alert);
            }
        }
    }

    /**
     * 获取近期触发失败次数
     *
     * @param int $deviceId
     * @param string $timeRange
     * @return int
     */
    protected function getRecentTriggerFailures(int $deviceId, string $timeRange = '1 hour'): int
    {
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $timeRange));

        return DeviceTrigger::where('device_id', $deviceId)
            ->where('success', 0)
            ->where('create_time', '>=', $startTime)
            ->count();
    }

    /**
     * 计算离线告警级别
     *
     * @param int $offlineTime
     * @param array $rule
     * @return string
     */
    protected function calculateOfflineAlertLevel(int $offlineTime, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_LOW => 600,      // 10分钟
            DeviceAlert::LEVEL_MEDIUM => 1800,  // 30分钟
            DeviceAlert::LEVEL_HIGH => 3600,    // 1小时
            DeviceAlert::LEVEL_CRITICAL => 7200 // 2小时
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($offlineTime >= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 计算电量告警级别
     *
     * @param int $batteryLevel
     * @param array $rule
     * @return string
     */
    protected function calculateBatteryAlertLevel(int $batteryLevel, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_CRITICAL => 5,  // 5%
            DeviceAlert::LEVEL_HIGH => 10,     // 10%
            DeviceAlert::LEVEL_MEDIUM => 15,   // 15%
            DeviceAlert::LEVEL_LOW => 20       // 20%
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($batteryLevel <= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 计算响应时间告警级别
     *
     * @param int $responseTime
     * @param array $rule
     * @return string
     */
    protected function calculateResponseTimeAlertLevel(int $responseTime, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_LOW => 5000,      // 5秒
            DeviceAlert::LEVEL_MEDIUM => 10000,  // 10秒
            DeviceAlert::LEVEL_HIGH => 20000,    // 20秒
            DeviceAlert::LEVEL_CRITICAL => 30000 // 30秒
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($responseTime >= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 获取错误告警级别
     *
     * @param string $errorCode
     * @param array $rule
     * @return string
     */
    protected function getErrorAlertLevel(string $errorCode, array $rule): string
    {
        $errorLevels = $rule['error_levels'] ?? [
            'SYSTEM_ERROR' => DeviceAlert::LEVEL_CRITICAL,
            'HARDWARE_ERROR' => DeviceAlert::LEVEL_HIGH,
            'NETWORK_ERROR' => DeviceAlert::LEVEL_MEDIUM,
            'CONFIG_ERROR' => DeviceAlert::LEVEL_MEDIUM,
            'USER_ERROR' => DeviceAlert::LEVEL_LOW
        ];

        return $errorLevels[$errorCode] ?? DeviceAlert::LEVEL_MEDIUM;
    }

    /**
     * 计算信号告警级别
     *
     * @param int $signalStrength
     * @param array $rule
     * @return string
     */
    protected function calculateSignalAlertLevel(int $signalStrength, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_CRITICAL => 10,  // 10%
            DeviceAlert::LEVEL_HIGH => 15,      // 15%
            DeviceAlert::LEVEL_MEDIUM => 20,    // 20%
            DeviceAlert::LEVEL_LOW => 30        // 30%
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($signalStrength <= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 计算心跳告警级别
     *
     * @param int $timeSinceHeartbeat
     * @param array $rule
     * @return string
     */
    protected function calculateHeartbeatAlertLevel(int $timeSinceHeartbeat, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_LOW => 300,      // 5分钟
            DeviceAlert::LEVEL_MEDIUM => 900,   // 15分钟
            DeviceAlert::LEVEL_HIGH => 1800,    // 30分钟
            DeviceAlert::LEVEL_CRITICAL => 3600 // 1小时
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($timeSinceHeartbeat >= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 计算触发失败告警级别
     *
     * @param int $failureCount
     * @param array $rule
     * @return string
     */
    protected function calculateTriggerFailureAlertLevel(int $failureCount, array $rule): string
    {
        $thresholds = $rule['level_thresholds'] ?? [
            DeviceAlert::LEVEL_LOW => 5,        // 5次
            DeviceAlert::LEVEL_MEDIUM => 10,    // 10次
            DeviceAlert::LEVEL_HIGH => 20,      // 20次
            DeviceAlert::LEVEL_CRITICAL => 50   // 50次
        ];

        foreach ([DeviceAlert::LEVEL_CRITICAL, DeviceAlert::LEVEL_HIGH, DeviceAlert::LEVEL_MEDIUM, DeviceAlert::LEVEL_LOW] as $level) {
            if ($failureCount >= ($thresholds[$level] ?? 0)) {
                return $level;
            }
        }

        return DeviceAlert::LEVEL_LOW;
    }

    /**
     * 格式化时长
     *
     * @param int $seconds
     * @return string
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . '分钟';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600) . '小时';
        } else {
            return round($seconds / 86400) . '天';
        }
    }

    /**
     * 记录告警日志
     *
     * @param string $message
     * @param NfcDevice $device
     * @param DeviceAlert $alert
     */
    protected function logAlert(string $message, NfcDevice $device, DeviceAlert $alert): void
    {
        Log::info($message, [
            'alert_id' => $alert->id,
            'device_id' => $device->id,
            'device_code' => $device->device_code,
            'alert_type' => $alert->alert_type,
            'alert_level' => $alert->alert_level,
            'merchant_id' => $device->merchant_id
        ]);
    }
}