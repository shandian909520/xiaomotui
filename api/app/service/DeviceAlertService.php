<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\Merchant;
use think\facade\Log;

/**
 * 设备异常告警服务
 * 检测设备状态异常并发送告警通知
 */
class DeviceAlertService
{
    /**
     * 设备离线阈值（分钟）
     */
    const OFFLINE_THRESHOLD = 5;

    /**
     * 低电量阈值（百分比）
     */
    const LOW_BATTERY_THRESHOLD = 20;

    /**
     * 极低电量阈值（百分比）
     */
    const CRITICAL_BATTERY_THRESHOLD = 10;

    /**
     * 告警级别
     */
    const LEVEL_INFO = 'info';        // 信息
    const LEVEL_WARNING = 'warning';  // 警告
    const LEVEL_ERROR = 'error';      // 错误
    const LEVEL_CRITICAL = 'critical'; // 严重

    /**
     * 告警类型
     */
    const TYPE_OFFLINE = 'offline';           // 设备离线
    const TYPE_LOW_BATTERY = 'low_battery';   // 电量低
    const TYPE_WEAK_SIGNAL = 'weak_signal';   // 信号弱
    const TYPE_TEMPERATURE = 'temperature';   // 温度异常
    const TYPE_ERROR = 'error';               // 设备错误

    /**
     * 检测离线设备
     *
     * @param int|null $merchantId 商家ID，为null则检测所有商家
     * @return array
     */
    public function checkOffline(?int $merchantId = null): array
    {
        $offlineDevices = [];
        $thresholdTime = date('Y-m-d H:i:s', time() - (self::OFFLINE_THRESHOLD * 60));

        // 查询离线设备
        $query = NfcDevice::where(function($query) use ($thresholdTime) {
            $query->where('status', NfcDevice::STATUS_OFFLINE)
                  ->whereOr(function($q) use ($thresholdTime) {
                      $q->where('status', NfcDevice::STATUS_ONLINE)
                        ->where(function($subQ) use ($thresholdTime) {
                            $subQ->where('last_heartbeat', '<', $thresholdTime)
                                 ->whereOr('last_heartbeat', null);
                        });
                  });
        });

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        $devices = $query->select();

        foreach ($devices as $device) {
            $offlineDevices[] = [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->device_name,
                'merchant_id' => $device->merchant_id,
                'location' => $device->location,
                'last_heartbeat' => $device->last_heartbeat,
                'offline_duration' => $this->calculateOfflineDuration($device->last_heartbeat)
            ];
        }

        return $offlineDevices;
    }

    /**
     * 检测低电量设备
     *
     * @param int|null $merchantId
     * @return array
     */
    public function checkLowBattery(?int $merchantId = null): array
    {
        $lowBatteryDevices = [];

        $query = NfcDevice::where('battery_level', '<=', self::LOW_BATTERY_THRESHOLD)
            ->where('battery_level', '>', 0);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        $devices = $query->select();

        foreach ($devices as $device) {
            $level = $device->battery_level <= self::CRITICAL_BATTERY_THRESHOLD
                ? self::LEVEL_CRITICAL
                : self::LEVEL_WARNING;

            $lowBatteryDevices[] = [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->device_name,
                'merchant_id' => $device->merchant_id,
                'battery_level' => $device->battery_level,
                'alert_level' => $level
            ];
        }

        return $lowBatteryDevices;
    }

    /**
     * 检测所有设备异常
     *
     * @param int|null $merchantId
     * @return array
     */
    public function checkAllDeviceIssues(?int $merchantId = null): array
    {
        $issues = [
            'offline' => $this->checkOffline($merchantId),
            'low_battery' => $this->checkLowBattery($merchantId),
            'total_issues' => 0
        ];

        $issues['total_issues'] = count($issues['offline']) + count($issues['low_battery']);

        return $issues;
    }

    /**
     * 发送告警通知（支持去重和频率控制）
     *
     * @param string $alertType 告警类型
     * @param array $deviceInfo 设备信息
     * @param string $level 告警级别
     * @param string $message 告警消息
     * @return bool
     */
    public function sendAlert(string $alertType, array $deviceInfo, string $level = self::LEVEL_WARNING, string $message = ''): bool
    {
        try {
            // 告警去重检查
            if (!$this->shouldSendAlert($alertType, $deviceInfo['device_id'] ?? 0)) {
                Log::info('告警被去重过滤', [
                    'alert_type' => $alertType,
                    'device_id' => $deviceInfo['device_id'] ?? 0
                ]);
                return true; // 返回true表示处理成功（虽然没有实际发送）
            }

            // 获取商家信息
            $merchant = Merchant::find($deviceInfo['merchant_id']);
            if (!$merchant) {
                Log::warning('告警发送失败：商家不存在', ['merchant_id' => $deviceInfo['merchant_id']]);
                return false;
            }

            // 构建告警数据
            $alertData = [
                'alert_type' => $alertType,
                'level' => $level,
                'device_info' => $deviceInfo,
                'merchant_info' => [
                    'id' => $merchant->id,
                    'name' => $merchant->name,
                    'phone' => $merchant->phone
                ],
                'message' => $message ?: $this->generateAlertMessage($alertType, $deviceInfo),
                'time' => date('Y-m-d H:i:s'),
                'suggestions' => $this->generateSuggestions($alertType, $deviceInfo)
            ];

            // 记录告警日志
            Log::warning('设备告警', $alertData);

            // 发送通知（可扩展：短信、邮件、微信通知等）
            $this->sendNotification($merchant, $alertData);

            // 记录告警发送时间（用于频率控制）
            $this->recordAlertSent($alertType, $deviceInfo['device_id'] ?? 0);

            return true;

        } catch (\Exception $e) {
            Log::error('发送告警失败', [
                'alert_type' => $alertType,
                'device_info' => $deviceInfo,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批量发送告警
     *
     * @param array $issues 设备问题列表
     * @return array
     */
    public function sendBatchAlerts(array $issues): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        // 处理离线设备告警
        foreach ($issues['offline'] as $device) {
            $success = $this->sendAlert(
                self::TYPE_OFFLINE,
                $device,
                self::LEVEL_ERROR,
                "设备 {$device['device_name']} 已离线"
            );

            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'device_code' => $device['device_code'],
                'type' => self::TYPE_OFFLINE,
                'success' => $success
            ];
        }

        // 处理低电量设备告警
        foreach ($issues['low_battery'] as $device) {
            $success = $this->sendAlert(
                self::TYPE_LOW_BATTERY,
                $device,
                $device['alert_level'],
                "设备 {$device['device_name']} 电量不足：{$device['battery_level']}%"
            );

            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'device_code' => $device['device_code'],
                'type' => self::TYPE_LOW_BATTERY,
                'success' => $success
            ];
        }

        return $results;
    }

    /**
     * 计算设备离线时长（分钟）
     *
     * @param string|null $lastHeartbeat
     * @return int
     */
    protected function calculateOfflineDuration(?string $lastHeartbeat): int
    {
        if (empty($lastHeartbeat)) {
            return 0;
        }

        $lastTime = strtotime($lastHeartbeat);
        $currentTime = time();
        return (int)(($currentTime - $lastTime) / 60);
    }

    /**
     * 生成告警消息
     *
     * @param string $alertType
     * @param array $deviceInfo
     * @return string
     */
    protected function generateAlertMessage(string $alertType, array $deviceInfo): string
    {
        $deviceName = $deviceInfo['device_name'] ?? '未知设备';
        $location = $deviceInfo['location'] ?? '';

        switch ($alertType) {
            case self::TYPE_OFFLINE:
                $duration = $deviceInfo['offline_duration'] ?? 0;
                return "设备 {$deviceName}（{$location}）已离线 {$duration} 分钟";

            case self::TYPE_LOW_BATTERY:
                $level = $deviceInfo['battery_level'] ?? 0;
                return "设备 {$deviceName}（{$location}）电量不足：{$level}%";

            case self::TYPE_WEAK_SIGNAL:
                return "设备 {$deviceName}（{$location}）信号弱";

            case self::TYPE_TEMPERATURE:
                return "设备 {$deviceName}（{$location}）温度异常";

            case self::TYPE_ERROR:
                return "设备 {$deviceName}（{$location}）出现错误";

            default:
                return "设备 {$deviceName}（{$location}）出现异常";
        }
    }

    /**
     * 生成处理建议
     *
     * @param string $alertType
     * @param array $deviceInfo
     * @return array
     */
    protected function generateSuggestions(string $alertType, array $deviceInfo): array
    {
        switch ($alertType) {
            case self::TYPE_OFFLINE:
                return [
                    '检查设备电源是否正常',
                    '检查网络连接是否正常',
                    '尝试重启设备',
                    '如果问题持续，请联系技术支持'
                ];

            case self::TYPE_LOW_BATTERY:
                $level = $deviceInfo['battery_level'] ?? 0;
                if ($level <= self::CRITICAL_BATTERY_THRESHOLD) {
                    return [
                        '电量严重不足，请立即更换电池',
                        '设备可能即将自动关机'
                    ];
                }
                return [
                    '请及时更换设备电池',
                    '建议准备备用电池'
                ];

            case self::TYPE_WEAK_SIGNAL:
                return [
                    '检查设备位置是否遮挡',
                    '尝试调整设备位置',
                    '检查路由器是否正常工作'
                ];

            case self::TYPE_TEMPERATURE:
                return [
                    '检查设备工作环境温度',
                    '确保设备通风良好',
                    '避免阳光直射或高温环境'
                ];

            case self::TYPE_ERROR:
                return [
                    '查看设备错误代码',
                    '尝试重启设备',
                    '联系技术支持'
                ];

            default:
                return [
                    '请检查设备状态',
                    '如果问题持续，请联系技术支持'
                ];
        }
    }

    /**
     * 发送通知（扩展点）
     *
     * @param Merchant $merchant
     * @param array $alertData
     * @return bool
     */
    protected function sendNotification(Merchant $merchant, array $alertData): bool
    {
        try {
            // 邮件通知
            if (!empty($merchant->email)) {
                $emailService = new \app\service\EmailService();
                $emailService->sendDeviceAlertEmail($merchant->email, [
                    'device_code' => $alertData['device_info']['device_code'] ?? '',
                    'device_name' => $alertData['device_info']['device_name'] ?? '',
                    'alert_type' => $alertData['alert_type'],
                    'alert_level' => $alertData['level'],
                    'alert_message' => $alertData['message'],
                    'trigger_time' => $alertData['time'],
                    'location' => $alertData['device_info']['location'] ?? '',
                    'suggestions' => $alertData['suggestions'] ?? []
                ]);
            }

            // 短信通知（TODO: 集成短信服务）
            // 微信公众号通知（TODO: 集成微信服务）

            // 记录日志
            Log::info('告警通知已发送', [
                'merchant_id' => $merchant->id,
                'merchant_name' => $merchant->name,
                'alert_type' => $alertData['alert_type'],
                'level' => $alertData['level']
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('发送告警通知失败', [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取商家的设备告警统计
     *
     * @param int $merchantId
     * @return array
     */
    public function getAlertStats(int $merchantId): array
    {
        $issues = $this->checkAllDeviceIssues($merchantId);

        return [
            'merchant_id' => $merchantId,
            'offline_count' => count($issues['offline']),
            'low_battery_count' => count($issues['low_battery']),
            'total_issues' => $issues['total_issues'],
            'check_time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 执行定期告警检查
     *
     * @return array
     */
    public function runPeriodicCheck(): array
    {
        Log::info('开始执行定期设备告警检查');

        // 检查所有设备问题
        $issues = $this->checkAllDeviceIssues();

        // 如果没有问题，直接返回
        if ($issues['total_issues'] === 0) {
            Log::info('设备状态正常，无需告警');
            return [
                'status' => 'success',
                'issues_found' => 0,
                'alerts_sent' => 0
            ];
        }

        // 发送批量告警
        $results = $this->sendBatchAlerts($issues);

        Log::info('定期设备告警检查完成', [
            'issues_found' => $issues['total_issues'],
            'alerts_success' => $results['success'],
            'alerts_failed' => $results['failed']
        ]);

        return [
            'status' => 'success',
            'issues_found' => $issues['total_issues'],
            'alerts_sent' => $results['success'],
            'alerts_failed' => $results['failed'],
            'details' => $results['details']
        ];
    }

    /**
     * 检查单个设备是否需要告警
     *
     * @param int $deviceId
     * @return array
     */
    public function checkDeviceAlert(int $deviceId): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            return [
                'has_alert' => false,
                'message' => '设备不存在'
            ];
        }

        $alerts = [];

        // 检查离线
        if ($device->isOffline()) {
            $alerts[] = [
                'type' => self::TYPE_OFFLINE,
                'level' => self::LEVEL_ERROR,
                'message' => '设备已离线'
            ];
        }

        // 检查低电量
        if ($device->isLowBattery()) {
            $level = $device->battery_level <= self::CRITICAL_BATTERY_THRESHOLD
                ? self::LEVEL_CRITICAL
                : self::LEVEL_WARNING;

            $alerts[] = [
                'type' => self::TYPE_LOW_BATTERY,
                'level' => $level,
                'message' => "电量不足：{$device->battery_level}%"
            ];
        }

        return [
            'has_alert' => !empty($alerts),
            'device_id' => $deviceId,
            'device_name' => $device->device_name,
            'alerts' => $alerts
        ];
    }

    /**
     * 检查是否应该发送告警（去重和频率控制）
     *
     * @param string $alertType 告警类型
     * @param int $deviceId 设备ID
     * @return bool
     */
    protected function shouldSendAlert(string $alertType, int $deviceId): bool
    {
        // 获取频率控制配置（分钟）
        $config = config('device_alert.alert_frequency', [
            self::TYPE_OFFLINE => 30,        // 离线告警30分钟发送一次
            self::TYPE_LOW_BATTERY => 60,    // 低电量告警60分钟发送一次
            self::TYPE_WEAK_SIGNAL => 120,   // 信号弱告警120分钟发送一次
            self::TYPE_TEMPERATURE => 30,    // 温度异常告警30分钟发送一次
            self::TYPE_ERROR => 15,          // 错误告警15分钟发送一次
        ]);

        $frequency = $config[$alertType] ?? 30; // 默认30分钟

        // 获取上次发送时间
        $cacheKey = "alert_sent:{$alertType}:{$deviceId}";
        $lastSentTime = \think\facade\Cache::get($cacheKey);

        if ($lastSentTime) {
            $elapsed = time() - $lastSentTime;
            $minInterval = $frequency * 60; // 转换为秒

            if ($elapsed < $minInterval) {
                // 未到发送时间
                return false;
            }
        }

        return true;
    }

    /**
     * 记录告警发送时间
     *
     * @param string $alertType 告警类型
     * @param int $deviceId 设备ID
     * @return bool
     */
    protected function recordAlertSent(string $alertType, int $deviceId): bool
    {
        $cacheKey = "alert_sent:{$alertType}:{$deviceId}";
        // 缓存24小时
        return \think\facade\Cache::set($cacheKey, time(), 86400);
    }

    /**
     * 清除告警发送记录
     *
     * @param string $alertType 告警类型
     * @param int $deviceId 设备ID
     * @return bool
     */
    public function clearAlertRecord(string $alertType, int $deviceId): bool
    {
        $cacheKey = "alert_sent:{$alertType}:{$deviceId}";
        return \think\facade\Cache::delete($cacheKey);
    }

    /**
     * 获取告警频率配置
     *
     * @return array
     */
    public function getAlertFrequencyConfig(): array
    {
        return config('device_alert.alert_frequency', [
            self::TYPE_OFFLINE => 30,
            self::TYPE_LOW_BATTERY => 60,
            self::TYPE_WEAK_SIGNAL => 120,
            self::TYPE_TEMPERATURE => 30,
            self::TYPE_ERROR => 15,
        ]);
    }
}