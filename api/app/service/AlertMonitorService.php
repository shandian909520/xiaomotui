<?php
declare (strict_types = 1);

namespace app\service;

use app\model\DeviceAlert;
use app\model\NfcDevice;
use app\service\AlertService;
use app\service\NotificationService;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Config;

/**
 * 告警监控定时任务服务
 */
class AlertMonitorService
{
    /**
     * 告警服务实例
     */
    protected AlertService $alertService;

    /**
     * 通知服务实例
     */
    protected NotificationService $notificationService;

    /**
     * 监控配置
     */
    protected array $config;

    public function __construct()
    {
        $this->alertService = new AlertService();
        $this->notificationService = new NotificationService();
        $this->config = Config::get('alert.monitor', [
            'enabled' => true,
            'check_interval' => 300,      // 5分钟检查一次
            'batch_size' => 100,          // 每批处理100个设备
            'max_execution_time' => 1800, // 最大执行时间30分钟
            'retry_times' => 3,           // 失败重试次数
            'retry_delay' => 60           // 重试间隔60秒
        ]);
    }

    /**
     * 执行告警监控任务
     *
     * @return array
     */
    public function runMonitorTask(): array
    {
        if (!$this->config['enabled']) {
            Log::info('告警监控任务已禁用');
            return ['status' => 'disabled', 'message' => '告警监控任务已禁用'];
        }

        $startTime = microtime(true);
        $taskId = uniqid('alert_monitor_');

        Log::info('开始执行告警监控任务', ['task_id' => $taskId]);

        try {
            // 检查是否有其他监控任务正在运行
            if ($this->isMonitorTaskRunning()) {
                Log::warning('告警监控任务已在运行中', ['task_id' => $taskId]);
                return ['status' => 'running', 'message' => '告警监控任务已在运行中'];
            }

            // 设置任务运行标志
            $this->setMonitorTaskRunning($taskId);

            // 设置最大执行时间
            set_time_limit($this->config['max_execution_time']);

            // 执行监控检查
            $result = $this->performMonitorChecks($taskId);

            // 清除任务运行标志
            $this->clearMonitorTaskRunning();

            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('告警监控任务执行完成', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'result' => $result
            ]);

            $result['execution_time'] = $executionTime;
            $result['task_id'] = $taskId;

            return $result;

        } catch (\Exception $e) {
            $this->clearMonitorTaskRunning();
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('告警监控任务执行失败', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => '告警监控任务执行失败：' . $e->getMessage(),
                'execution_time' => $executionTime,
                'task_id' => $taskId
            ];
        }
    }

    /**
     * 执行监控检查
     *
     * @param string $taskId
     * @return array
     */
    protected function performMonitorChecks(string $taskId): array
    {
        $stats = [
            'devices_checked' => 0,
            'alerts_generated' => 0,
            'notifications_sent' => 0,
            'errors' => 0,
            'merchants' => []
        ];

        // 获取所有需要监控的商家
        $merchants = $this->getMonitoredMerchants();

        foreach ($merchants as $merchantId) {
            try {
                $merchantStats = $this->checkMerchantDevices($merchantId, $taskId);
                $stats['devices_checked'] += $merchantStats['devices_checked'];
                $stats['alerts_generated'] += $merchantStats['alerts_generated'];
                $stats['notifications_sent'] += $merchantStats['notifications_sent'];
                $stats['merchants'][$merchantId] = $merchantStats;

            } catch (\Exception $e) {
                $stats['errors']++;
                $stats['merchants'][$merchantId] = [
                    'error' => $e->getMessage(),
                    'devices_checked' => 0,
                    'alerts_generated' => 0,
                    'notifications_sent' => 0
                ];

                Log::error('商家设备告警检查失败', [
                    'task_id' => $taskId,
                    'merchant_id' => $merchantId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'status' => 'completed',
            'message' => '告警监控任务执行完成',
            'stats' => $stats
        ];
    }

    /**
     * 检查商家设备
     *
     * @param int $merchantId
     * @param string $taskId
     * @return array
     */
    protected function checkMerchantDevices(int $merchantId, string $taskId): array
    {
        $stats = [
            'devices_checked' => 0,
            'alerts_generated' => 0,
            'notifications_sent' => 0,
            'device_details' => []
        ];

        // 分批处理设备
        $offset = 0;
        $batchSize = $this->config['batch_size'];

        do {
            $devices = NfcDevice::where('merchant_id', $merchantId)
                ->limit($batchSize)
                ->offset($offset)
                ->select();

            if ($devices->isEmpty()) {
                break;
            }

            foreach ($devices as $device) {
                try {
                    $deviceStats = $this->checkSingleDevice($device, $taskId);
                    $stats['devices_checked']++;
                    $stats['alerts_generated'] += $deviceStats['alerts_generated'];
                    $stats['notifications_sent'] += $deviceStats['notifications_sent'];
                    $stats['device_details'][$device->id] = $deviceStats;

                } catch (\Exception $e) {
                    $stats['device_details'][$device->id] = [
                        'error' => $e->getMessage(),
                        'alerts_generated' => 0,
                        'notifications_sent' => 0
                    ];

                    Log::error('单个设备告警检查失败', [
                        'task_id' => $taskId,
                        'device_id' => $device->id,
                        'device_code' => $device->device_code,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $offset += $batchSize;

        } while (count($devices) == $batchSize);

        return $stats;
    }

    /**
     * 检查单个设备
     *
     * @param NfcDevice $device
     * @param string $taskId
     * @return array
     */
    protected function checkSingleDevice(NfcDevice $device, string $taskId): array
    {
        $stats = [
            'alerts_generated' => 0,
            'notifications_sent' => 0,
            'alerts' => []
        ];

        $alerts = [];

        // 检测离线告警
        $offlineAlert = $this->alertService->checkOfflineAlert($device);
        if ($offlineAlert) {
            $alerts[] = $offlineAlert;
        }

        // 检测电量告警
        $batteryAlert = $this->alertService->checkLowBatteryAlert($device);
        if ($batteryAlert) {
            $alerts[] = $batteryAlert;
        }

        // 检测心跳告警
        $heartbeatAlert = $this->alertService->checkHeartbeatAlert($device);
        if ($heartbeatAlert) {
            $alerts[] = $heartbeatAlert;
        }

        // 检测信号强度（如果有相关数据）
        $deviceData = $this->getDeviceExtraData($device);
        if (isset($deviceData['signal_strength'])) {
            $signalAlert = $this->alertService->checkSignalWeakAlert($device, $deviceData['signal_strength']);
            if ($signalAlert) {
                $alerts[] = $signalAlert;
            }
        }

        // 检测温度（如果有相关数据）
        if (isset($deviceData['temperature'])) {
            $temperatureAlert = $this->alertService->checkTemperatureAlert($device, $deviceData['temperature']);
            if ($temperatureAlert) {
                $alerts[] = $temperatureAlert;
            }
        }

        // 检测触发失败
        $failureCount = $this->getRecentTriggerFailures($device->id);
        if ($failureCount > 0) {
            $triggerAlert = $this->alertService->checkTriggerFailedAlert($device->id, $failureCount);
            if ($triggerAlert) {
                $alerts[] = $triggerAlert;
            }
        }

        $stats['alerts_generated'] = count($alerts);

        // 发送通知
        foreach ($alerts as $alert) {
            try {
                if ($alert->needsNotification()) {
                    $sent = $this->notificationService->sendAlert($alert);
                    if ($sent) {
                        $stats['notifications_sent']++;
                    }
                }

                $stats['alerts'][] = [
                    'id' => $alert->id,
                    'type' => $alert->alert_type,
                    'level' => $alert->alert_level,
                    'title' => $alert->alert_title,
                    'notification_sent' => $alert->notification_sent
                ];

            } catch (\Exception $e) {
                Log::error('发送告警通知失败', [
                    'task_id' => $taskId,
                    'alert_id' => $alert->id,
                    'device_id' => $device->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * 执行告警清理任务
     *
     * @return array
     */
    public function runCleanupTask(): array
    {
        $startTime = microtime(true);
        $taskId = uniqid('alert_cleanup_');

        Log::info('开始执行告警清理任务', ['task_id' => $taskId]);

        try {
            $stats = [
                'resolved_alerts_cleaned' => 0,
                'old_alerts_archived' => 0,
                'notifications_cleaned' => 0
            ];

            // 清理已解决的告警（保留30天）
            $resolvedAlerts = DeviceAlert::where('status', 'resolved')
                ->where('resolve_time', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->select();

            foreach ($resolvedAlerts as $alert) {
                $alert->delete();
                $stats['resolved_alerts_cleaned']++;
            }

            // 归档旧告警（保留90天）
            $oldAlerts = DeviceAlert::where('create_time', '<', date('Y-m-d H:i:s', strtotime('-90 days')))
                ->select();

            foreach ($oldAlerts as $alert) {
                // 可以将数据移动到归档表或导出到文件
                $alert->delete();
                $stats['old_alerts_archived']++;
            }

            // 清理过期的系统通知
            $stats['notifications_cleaned'] = $this->cleanupOldNotifications();

            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('告警清理任务执行完成', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'stats' => $stats
            ]);

            return [
                'status' => 'completed',
                'message' => '告警清理任务执行完成',
                'stats' => $stats,
                'execution_time' => $executionTime,
                'task_id' => $taskId
            ];

        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('告警清理任务执行失败', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => '告警清理任务执行失败：' . $e->getMessage(),
                'execution_time' => $executionTime,
                'task_id' => $taskId
            ];
        }
    }

    /**
     * 执行告警统计任务
     *
     * @return array
     */
    public function runStatsTask(): array
    {
        $startTime = microtime(true);
        $taskId = uniqid('alert_stats_');

        Log::info('开始执行告警统计任务', ['task_id' => $taskId]);

        try {
            $stats = [];

            // 获取所有商家的告警统计
            $merchants = $this->getMonitoredMerchants();

            foreach ($merchants as $merchantId) {
                $merchantStats = DeviceAlert::getAlertStats($merchantId);
                $merchantStats['unresolved_count'] = DeviceAlert::getUnresolvedCount($merchantId);
                $stats[$merchantId] = $merchantStats;
            }

            // 全局统计
            $globalStats = [
                'total_merchants' => count($merchants),
                'total_alerts' => DeviceAlert::count(),
                'total_unresolved' => DeviceAlert::getUnresolvedCount(),
                'alert_rate_today' => $this->getTodayAlertRate()
            ];

            // 缓存统计数据
            Cache::set('alert_global_stats', $globalStats, 3600); // 缓存1小时
            Cache::set('alert_merchant_stats', $stats, 3600);

            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('告警统计任务执行完成', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'global_stats' => $globalStats
            ]);

            return [
                'status' => 'completed',
                'message' => '告警统计任务执行完成',
                'global_stats' => $globalStats,
                'merchant_stats' => $stats,
                'execution_time' => $executionTime,
                'task_id' => $taskId
            ];

        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('告警统计任务执行失败', [
                'task_id' => $taskId,
                'execution_time' => $executionTime,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => '告警统计任务执行失败：' . $e->getMessage(),
                'execution_time' => $executionTime,
                'task_id' => $taskId
            ];
        }
    }

    /**
     * 获取需要监控的商家列表
     *
     * @return array
     */
    protected function getMonitoredMerchants(): array
    {
        // 可以从配置或数据库获取需要监控的商家列表
        // 这里简单获取所有有设备的商家
        return NfcDevice::distinct('merchant_id')
            ->column('merchant_id');
    }

    /**
     * 获取设备扩展数据
     *
     * @param NfcDevice $device
     * @return array
     */
    protected function getDeviceExtraData(NfcDevice $device): array
    {
        // 这里可以从缓存、外部API等获取设备的额外数据
        // 如信号强度、温度等
        $cacheKey = "device_extra_data:{$device->id}";
        return Cache::get($cacheKey, []);
    }

    /**
     * 获取近期触发失败次数
     *
     * @param int $deviceId
     * @return int
     */
    protected function getRecentTriggerFailures(int $deviceId): int
    {
        $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        return \app\model\DeviceTrigger::where('device_id', $deviceId)
            ->where('success', 0)
            ->where('create_time', '>=', $startTime)
            ->count();
    }

    /**
     * 检查是否有监控任务正在运行
     *
     * @return bool
     */
    protected function isMonitorTaskRunning(): bool
    {
        return Cache::has('alert_monitor_task_running');
    }

    /**
     * 设置监控任务运行标志
     *
     * @param string $taskId
     */
    protected function setMonitorTaskRunning(string $taskId): void
    {
        Cache::set('alert_monitor_task_running', [
            'task_id' => $taskId,
            'start_time' => time(),
            'pid' => getmypid()
        ], $this->config['max_execution_time']);
    }

    /**
     * 清除监控任务运行标志
     */
    protected function clearMonitorTaskRunning(): void
    {
        Cache::delete('alert_monitor_task_running');
    }

    /**
     * 清理过期的系统通知
     *
     * @return int
     */
    protected function cleanupOldNotifications(): int
    {
        $cleaned = 0;
        $merchants = $this->getMonitoredMerchants();

        foreach ($merchants as $merchantId) {
            $cacheKey = "system_notification:merchant_{$merchantId}";
            $notifications = Cache::get($cacheKey, []);

            if (empty($notifications)) {
                continue;
            }

            // 删除30天前的通知
            $cutoffTime = strtotime('-30 days');
            $filteredNotifications = array_filter($notifications, function($notification) use ($cutoffTime) {
                return strtotime($notification['create_time']) > $cutoffTime;
            });

            $removedCount = count($notifications) - count($filteredNotifications);
            if ($removedCount > 0) {
                Cache::set($cacheKey, array_values($filteredNotifications), 7 * 24 * 3600);
                $cleaned += $removedCount;
            }
        }

        return $cleaned;
    }

    /**
     * 获取今日告警率
     *
     * @return float
     */
    protected function getTodayAlertRate(): float
    {
        $today = date('Y-m-d');
        $todayAlerts = DeviceAlert::where('create_time', 'like', $today . '%')->count();
        $totalDevices = NfcDevice::count();

        return $totalDevices > 0 ? round($todayAlerts / $totalDevices * 100, 2) : 0;
    }

    /**
     * 获取监控任务状态
     *
     * @return array
     */
    public function getMonitorStatus(): array
    {
        $runningTask = Cache::get('alert_monitor_task_running');

        if ($runningTask) {
            $runningTime = time() - $runningTask['start_time'];
            return [
                'status' => 'running',
                'task_id' => $runningTask['task_id'],
                'start_time' => date('Y-m-d H:i:s', $runningTask['start_time']),
                'running_time' => $runningTime,
                'pid' => $runningTask['pid']
            ];
        }

        return [
            'status' => 'idle',
            'last_run' => Cache::get('alert_monitor_last_run'),
            'next_run' => $this->getNextRunTime()
        ];
    }

    /**
     * 获取下次运行时间
     *
     * @return string
     */
    protected function getNextRunTime(): string
    {
        $lastRun = Cache::get('alert_monitor_last_run');
        if ($lastRun) {
            $nextRun = strtotime($lastRun) + $this->config['check_interval'];
            return date('Y-m-d H:i:s', $nextRun);
        }

        return '未知';
    }

    /**
     * 更新最后运行时间
     */
    public function updateLastRunTime(): void
    {
        Cache::set('alert_monitor_last_run', date('Y-m-d H:i:s'), 7 * 24 * 3600);
    }
}