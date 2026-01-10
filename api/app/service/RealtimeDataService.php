<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\DeviceTrigger;
use app\model\ContentTask;
use app\model\User;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;

/**
 * 实时数据服务类
 * 用于实时采集、聚合和展示各种业务指标数据
 */
class RealtimeDataService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'realtime:';

    /**
     * 缓存时间常量(秒)
     */
    const CACHE_TTL_REALTIME = 60;      // 实时数据：1分钟
    const CACHE_TTL_HOUR = 300;         // 小时数据：5分钟
    const CACHE_TTL_DAY = 1800;         // 天数据：30分钟
    const CACHE_TTL_WEEK = 3600;        // 周数据：1小时
    const CACHE_TTL_MONTH = 7200;       // 月数据：2小时

    /**
     * 时间维度常量
     */
    const DIMENSION_REALTIME = 'realtime';  // 实时
    const DIMENSION_HOUR = 'hour';          // 小时
    const DIMENSION_DAY = 'day';            // 天
    const DIMENSION_WEEK = 'week';          // 周
    const DIMENSION_MONTH = 'month';        // 月

    /**
     * 获取实时指标数据
     *
     * @param int|null $merchantId 商家ID，null表示系统级
     * @param bool $useCache 是否使用缓存
     * @return array
     */
    public function getRealTimeMetrics(?int $merchantId = null, bool $useCache = true): array
    {
        $cacheKey = $this->getCacheKey('metrics', $merchantId);

        // 尝试从缓存获取
        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                Log::debug('实时指标缓存命中', ['merchant_id' => $merchantId]);
                return $cached;
            }
        }

        // 实时计算指标
        $startTime = microtime(true);

        $metrics = [
            // NFC触发数据
            'nfc_triggers' => $this->getNfcTriggerMetrics($merchantId),

            // 内容任务数据
            'content_tasks' => $this->getContentTaskMetrics($merchantId),

            // 设备状态数据
            'devices' => $this->getDeviceMetrics($merchantId),

            // 用户数据
            'users' => $this->getUserMetrics($merchantId),

            // 时间戳
            'timestamp' => date('Y-m-d H:i:s'),
            'generation_time' => round((microtime(true) - $startTime) * 1000, 2) // 毫秒
        ];

        // 缓存结果
        Cache::set($cacheKey, $metrics, self::CACHE_TTL_REALTIME);

        Log::info('实时指标数据生成', [
            'merchant_id' => $merchantId,
            'generation_time' => $metrics['generation_time'] . 'ms'
        ]);

        return $metrics;
    }

    /**
     * 获取商家仪表盘数据
     *
     * @param int $merchantId 商家ID
     * @param string $dimension 时间维度
     * @return array
     */
    public function getMerchantDashboard(int $merchantId, string $dimension = self::DIMENSION_DAY): array
    {
        $cacheKey = $this->getCacheKey('dashboard', $merchantId, $dimension);
        $cacheTtl = $this->getCacheTtl($dimension);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            Log::debug('商家仪表盘缓存命中', [
                'merchant_id' => $merchantId,
                'dimension' => $dimension
            ]);
            return $cached;
        }

        // 获取时间范围
        $timeRange = $this->getTimeRange($dimension);

        // 构建仪表盘数据
        $dashboard = [
            // 基础指标
            'metrics' => $this->getRealTimeMetrics($merchantId, false),

            // 趋势数据
            'trends' => $this->getTrendData($merchantId, $dimension, $timeRange),

            // 排行数据
            'rankings' => $this->getRankingData($merchantId, $timeRange),

            // 实时活动
            'recent_activities' => $this->getRecentActivities($merchantId, 10),

            // 告警信息
            'alerts' => $this->getActiveAlerts($merchantId),

            // 时间范围
            'time_range' => [
                'start' => $timeRange['start'],
                'end' => $timeRange['end'],
                'dimension' => $dimension
            ],

            // 生成时间
            'generated_at' => date('Y-m-d H:i:s')
        ];

        // 缓存结果
        Cache::set($cacheKey, $dashboard, $cacheTtl);

        return $dashboard;
    }

    /**
     * 获取设备实时状态
     *
     * @param int|null $merchantId 商家ID
     * @param int|null $deviceId 设备ID
     * @return array
     */
    public function getDeviceStatus(?int $merchantId = null, ?int $deviceId = null): array
    {
        $cacheKey = $this->getCacheKey('device_status', $merchantId, $deviceId);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // 构建查询
        $query = NfcDevice::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        if ($deviceId) {
            $query->where('id', $deviceId);
        }

        $devices = $query->select();

        // 处理设备状态
        $statusData = [
            'total' => count($devices),
            'online' => 0,
            'offline' => 0,
            'maintenance' => 0,
            'low_battery' => 0,
            'devices' => []
        ];

        foreach ($devices as $device) {
            // 统计状态
            if ($device->isOnline()) {
                $statusData['online']++;
            } elseif ($device->isMaintenance()) {
                $statusData['maintenance']++;
            } else {
                $statusData['offline']++;
            }

            // 统计低电量
            if ($device->isLowBattery()) {
                $statusData['low_battery']++;
            }

            // 详细设备信息
            $statusData['devices'][] = [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->device_name,
                'status' => $device->status,
                'status_text' => $device->status_text,
                'is_online' => $device->isOnline(),
                'battery_level' => $device->battery_level,
                'battery_status' => $device->battery_status,
                'last_heartbeat' => $device->last_heartbeat,
                'location' => $device->location
            ];
        }

        // 计算活跃率
        $statusData['active_rate'] = $statusData['total'] > 0
            ? round($statusData['online'] / $statusData['total'] * 100, 2)
            : 0;

        // 缓存结果
        Cache::set($cacheKey, $statusData, self::CACHE_TTL_REALTIME);

        return $statusData;
    }

    /**
     * 聚合数据
     *
     * @param int|null $merchantId 商家ID
     * @param string $dimension 时间维度
     * @param array $options 聚合选项
     * @return array
     */
    public function aggregateData(?int $merchantId = null, string $dimension = self::DIMENSION_DAY, array $options = []): array
    {
        $timeRange = $this->getTimeRange($dimension);

        $aggregated = [
            'dimension' => $dimension,
            'time_range' => $timeRange,
            'merchant_id' => $merchantId,

            // 触发数据聚合
            'triggers' => $this->aggregateTriggers($merchantId, $timeRange),

            // 内容任务聚合
            'content_tasks' => $this->aggregateContentTasks($merchantId, $timeRange),

            // 设备使用聚合
            'device_usage' => $this->aggregateDeviceUsage($merchantId, $timeRange),

            // 用户活跃度聚合
            'user_activity' => $this->aggregateUserActivity($merchantId, $timeRange),

            // 聚合时间
            'aggregated_at' => date('Y-m-d H:i:s')
        ];

        return $aggregated;
    }

    /**
     * 更新指标
     *
     * @param string $metricType 指标类型
     * @param int|null $merchantId 商家ID
     * @param array $data 指标数据
     * @return bool
     */
    public function updateMetrics(string $metricType, ?int $merchantId = null, array $data = []): bool
    {
        try {
            // 清除相关缓存
            $this->clearCache($merchantId);

            // 记录指标更新
            Log::info('实时指标更新', [
                'metric_type' => $metricType,
                'merchant_id' => $merchantId,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('更新指标失败', [
                'metric_type' => $metricType,
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除缓存
     *
     * @param int|null $merchantId 商家ID，null表示清除所有
     * @param string|null $type 缓存类型，null表示清除所有类型
     * @return bool
     */
    public function clearCache(?int $merchantId = null, ?string $type = null): bool
    {
        try {
            if ($merchantId === null && $type === null) {
                // 清除所有实时数据缓存
                Cache::tag('realtime_data')->clear();
            } elseif ($merchantId !== null && $type === null) {
                // 清除指定商家的所有缓存
                $patterns = ['metrics', 'dashboard', 'device_status'];
                foreach ($patterns as $pattern) {
                    $key = $this->getCacheKey($pattern, $merchantId);
                    Cache::delete($key);
                }
            } else {
                // 清除指定类型的缓存
                $key = $this->getCacheKey($type, $merchantId);
                Cache::delete($key);
            }

            Log::info('清除实时数据缓存', [
                'merchant_id' => $merchantId,
                'type' => $type
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('清除缓存失败', [
                'merchant_id' => $merchantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取NFC触发指标
     *
     * @param int|null $merchantId
     * @return array
     */
    protected function getNfcTriggerMetrics(?int $merchantId = null): array
    {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // 构建基础查询
        $query = DeviceTrigger::query();

        if ($merchantId) {
            $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            });
        }

        // 今日触发数
        $todayCount = (clone $query)
            ->where('create_time', '>=', $today . ' 00:00:00')
            ->where('create_time', '<=', $now)
            ->count();

        // 今日成功数
        $todaySuccess = (clone $query)
            ->where('create_time', '>=', $today . ' 00:00:00')
            ->where('create_time', '<=', $now)
            ->where('success', 1)
            ->count();

        // 本周触发数
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekCount = (clone $query)
            ->where('create_time', '>=', $weekStart . ' 00:00:00')
            ->where('create_time', '<=', $now)
            ->count();

        // 本月触发数
        $monthStart = date('Y-m-01');
        $monthCount = (clone $query)
            ->where('create_time', '>=', $monthStart . ' 00:00:00')
            ->where('create_time', '<=', $now)
            ->count();

        // 总触发数
        $totalCount = $query->count();

        // 计算成功率
        $successRate = $todayCount > 0 ? round($todaySuccess / $todayCount * 100, 2) : 0;

        // 计算环比增长（与昨天对比）
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayCount = DeviceTrigger::query()
            ->when($merchantId, function($q) use ($merchantId) {
                $q->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            })
            ->where('create_time', '>=', $yesterday . ' 00:00:00')
            ->where('create_time', '<=', $yesterday . ' 23:59:59')
            ->count();

        $trend = $yesterdayCount > 0
            ? round(($todayCount - $yesterdayCount) / $yesterdayCount * 100, 2)
            : 0;

        return [
            'total' => $totalCount,
            'today' => $todayCount,
            'week' => $weekCount,
            'month' => $monthCount,
            'success_rate' => $successRate,
            'trend' => $trend > 0 ? "+{$trend}%" : "{$trend}%"
        ];
    }

    /**
     * 获取内容任务指标
     *
     * @param int|null $merchantId
     * @return array
     */
    protected function getContentTaskMetrics(?int $merchantId = null): array
    {
        $query = ContentTask::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        // 总任务数
        $total = $query->count();

        // 各状态统计
        $pending = (clone $query)->where('status', ContentTask::STATUS_PENDING)->count();
        $processing = (clone $query)->where('status', ContentTask::STATUS_PROCESSING)->count();
        $completed = (clone $query)->where('status', ContentTask::STATUS_COMPLETED)->count();
        $failed = (clone $query)->where('status', ContentTask::STATUS_FAILED)->count();

        // 成功率
        $successRate = $total > 0 ? round($completed / $total * 100, 2) : 0;

        // 今日任务数
        $today = date('Y-m-d');
        $todayCount = (clone $query)
            ->where('create_time', '>=', $today . ' 00:00:00')
            ->count();

        return [
            'total' => $total,
            'today' => $todayCount,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $successRate
        ];
    }

    /**
     * 获取设备指标
     *
     * @param int|null $merchantId
     * @return array
     */
    protected function getDeviceMetrics(?int $merchantId = null): array
    {
        $query = NfcDevice::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $devices = $query->select();

        $total = count($devices);
        $online = 0;
        $offline = 0;
        $maintenance = 0;

        foreach ($devices as $device) {
            if ($device->isOnline()) {
                $online++;
            } elseif ($device->isMaintenance()) {
                $maintenance++;
            } else {
                $offline++;
            }
        }

        $activeRate = $total > 0 ? round($online / $total * 100, 2) : 0;

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'maintenance' => $maintenance,
            'active_rate' => $activeRate
        ];
    }

    /**
     * 获取用户指标
     *
     * @param int|null $merchantId
     * @return array
     */
    protected function getUserMetrics(?int $merchantId = null): array
    {
        $query = User::query();

        // 总用户数
        $total = $query->count();

        // 今日活跃用户（今天有触发记录的用户）
        $today = date('Y-m-d');
        $activeToday = DeviceTrigger::query()
            ->when($merchantId, function($q) use ($merchantId) {
                $q->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            })
            ->where('create_time', '>=', $today . ' 00:00:00')
            ->where('success', 1)
            ->distinct()
            ->count('user_id');

        // 今日新增用户
        $newToday = User::query()
            ->where('create_time', '>=', $today . ' 00:00:00')
            ->count();

        return [
            'total' => $total,
            'active_today' => $activeToday,
            'new_today' => $newToday
        ];
    }

    /**
     * 获取趋势数据
     *
     * @param int $merchantId
     * @param string $dimension
     * @param array $timeRange
     * @return array
     */
    protected function getTrendData(int $merchantId, string $dimension, array $timeRange): array
    {
        $trends = [];

        // 根据维度生成时间点
        $timePoints = $this->generateTimePoints($dimension, $timeRange);

        foreach ($timePoints as $point) {
            $trends[] = [
                'time' => $point['label'],
                'triggers' => $this->getTriggersAtTime($merchantId, $point['start'], $point['end']),
                'content_tasks' => $this->getContentTasksAtTime($merchantId, $point['start'], $point['end'])
            ];
        }

        return $trends;
    }

    /**
     * 获取排行数据
     *
     * @param int $merchantId
     * @param array $timeRange
     * @return array
     */
    protected function getRankingData(int $merchantId, array $timeRange): array
    {
        // 设备触发排行
        $deviceRanking = DeviceTrigger::query()
            ->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            })
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('success', 1)
            ->field('device_id, device_code, COUNT(*) as trigger_count')
            ->group('device_id')
            ->order('trigger_count', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        // 触发模式排行
        $modeRanking = DeviceTrigger::query()
            ->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            })
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('success', 1)
            ->field('trigger_mode, COUNT(*) as count')
            ->group('trigger_mode')
            ->order('count', 'desc')
            ->select()
            ->toArray();

        return [
            'devices' => $deviceRanking,
            'trigger_modes' => $modeRanking
        ];
    }

    /**
     * 获取最近活动
     *
     * @param int $merchantId
     * @param int $limit
     * @return array
     */
    protected function getRecentActivities(int $merchantId, int $limit = 10): array
    {
        return DeviceTrigger::query()
            ->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            })
            ->where('success', 1)
            ->order('create_time', 'desc')
            ->limit($limit)
            ->field('device_code, trigger_mode, response_type, create_time')
            ->select()
            ->toArray();
    }

    /**
     * 获取活跃告警
     *
     * @param int $merchantId
     * @return array
     */
    protected function getActiveAlerts(int $merchantId): array
    {
        $alerts = [];

        // 检查离线设备
        $offlineDevices = NfcDevice::getOfflineDevices($merchantId);
        if (count($offlineDevices) > 0) {
            $alerts[] = [
                'type' => 'device_offline',
                'level' => 'warning',
                'message' => '有 ' . count($offlineDevices) . ' 台设备离线',
                'count' => count($offlineDevices)
            ];
        }

        // 检查低电量设备
        $lowBatteryCount = NfcDevice::where('merchant_id', $merchantId)
            ->where('battery_level', '<=', 20)
            ->count();
        if ($lowBatteryCount > 0) {
            $alerts[] = [
                'type' => 'low_battery',
                'level' => 'warning',
                'message' => '有 ' . $lowBatteryCount . ' 台设备电量不足',
                'count' => $lowBatteryCount
            ];
        }

        // 检查失败任务
        $failedTaskCount = ContentTask::where('merchant_id', $merchantId)
            ->where('status', ContentTask::STATUS_FAILED)
            ->where('create_time', '>=', date('Y-m-d 00:00:00'))
            ->count();
        if ($failedTaskCount > 0) {
            $alerts[] = [
                'type' => 'task_failed',
                'level' => 'info',
                'message' => '今日有 ' . $failedTaskCount . ' 个任务失败',
                'count' => $failedTaskCount
            ];
        }

        return $alerts;
    }

    /**
     * 聚合触发数据
     *
     * @param int|null $merchantId
     * @param array $timeRange
     * @return array
     */
    protected function aggregateTriggers(?int $merchantId, array $timeRange): array
    {
        $query = DeviceTrigger::query()
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end']);

        if ($merchantId) {
            $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            });
        }

        $total = $query->count();
        $success = (clone $query)->where('success', 1)->count();
        $avgResponseTime = (clone $query)->where('success', 1)->avg('response_time');

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $total - $success,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'avg_response_time' => round($avgResponseTime, 2)
        ];
    }

    /**
     * 聚合内容任务数据
     *
     * @param int|null $merchantId
     * @param array $timeRange
     * @return array
     */
    protected function aggregateContentTasks(?int $merchantId, array $timeRange): array
    {
        $query = ContentTask::query()
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end']);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', ContentTask::STATUS_COMPLETED)->count();
        $avgGenerationTime = (clone $query)
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->avg('generation_time');

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => (clone $query)->where('status', ContentTask::STATUS_FAILED)->count(),
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'avg_generation_time' => round($avgGenerationTime, 2)
        ];
    }

    /**
     * 聚合设备使用数据
     *
     * @param int|null $merchantId
     * @param array $timeRange
     * @return array
     */
    protected function aggregateDeviceUsage(?int $merchantId, array $timeRange): array
    {
        $query = NfcDevice::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $totalDevices = $query->count();

        // 活跃设备（时间范围内有触发记录的设备）
        $activeDevices = DeviceTrigger::query()
            ->when($merchantId, function($q) use ($merchantId) {
                $q->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            })
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('success', 1)
            ->distinct()
            ->count('device_id');

        return [
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'usage_rate' => $totalDevices > 0 ? round($activeDevices / $totalDevices * 100, 2) : 0
        ];
    }

    /**
     * 聚合用户活跃度数据
     *
     * @param int|null $merchantId
     * @param array $timeRange
     * @return array
     */
    protected function aggregateUserActivity(?int $merchantId, array $timeRange): array
    {
        $activeUsers = DeviceTrigger::query()
            ->when($merchantId, function($q) use ($merchantId) {
                $q->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            })
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->where('success', 1)
            ->distinct()
            ->count('user_id');

        $newUsers = User::query()
            ->where('create_time', '>=', $timeRange['start'])
            ->where('create_time', '<=', $timeRange['end'])
            ->count();

        return [
            'active_users' => $activeUsers,
            'new_users' => $newUsers
        ];
    }

    /**
     * 获取时间范围
     *
     * @param string $dimension
     * @return array
     */
    protected function getTimeRange(string $dimension): array
    {
        $now = date('Y-m-d H:i:s');

        switch ($dimension) {
            case self::DIMENSION_REALTIME:
                // 最近1小时
                return [
                    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'end' => $now
                ];

            case self::DIMENSION_HOUR:
                // 最近24小时
                return [
                    'start' => date('Y-m-d H:i:s', strtotime('-24 hours')),
                    'end' => $now
                ];

            case self::DIMENSION_DAY:
                // 今天
                return [
                    'start' => date('Y-m-d 00:00:00'),
                    'end' => $now
                ];

            case self::DIMENSION_WEEK:
                // 本周
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                    'end' => $now
                ];

            case self::DIMENSION_MONTH:
                // 本月
                return [
                    'start' => date('Y-m-01 00:00:00'),
                    'end' => $now
                ];

            default:
                // 默认返回今天
                return [
                    'start' => date('Y-m-d 00:00:00'),
                    'end' => $now
                ];
        }
    }

    /**
     * 生成时间点
     *
     * @param string $dimension
     * @param array $timeRange
     * @return array
     */
    protected function generateTimePoints(string $dimension, array $timeRange): array
    {
        $points = [];
        $start = strtotime($timeRange['start']);
        $end = strtotime($timeRange['end']);

        switch ($dimension) {
            case self::DIMENSION_HOUR:
            case self::DIMENSION_REALTIME:
                // 按小时分组
                for ($time = $start; $time <= $end; $time += 3600) {
                    $points[] = [
                        'label' => date('H:00', $time),
                        'start' => date('Y-m-d H:00:00', $time),
                        'end' => date('Y-m-d H:59:59', $time)
                    ];
                }
                break;

            case self::DIMENSION_DAY:
            case self::DIMENSION_WEEK:
                // 按天分组
                for ($time = $start; $time <= $end; $time += 86400) {
                    $points[] = [
                        'label' => date('m-d', $time),
                        'start' => date('Y-m-d 00:00:00', $time),
                        'end' => date('Y-m-d 23:59:59', $time)
                    ];
                }
                break;

            case self::DIMENSION_MONTH:
                // 按天分组
                $currentMonth = date('Y-m', $start);
                for ($time = $start; $time <= $end; $time += 86400) {
                    if (date('Y-m', $time) == $currentMonth) {
                        $points[] = [
                            'label' => date('d', $time),
                            'start' => date('Y-m-d 00:00:00', $time),
                            'end' => date('Y-m-d 23:59:59', $time)
                        ];
                    }
                }
                break;
        }

        return $points;
    }

    /**
     * 获取指定时间的触发数
     *
     * @param int $merchantId
     * @param string $start
     * @param string $end
     * @return int
     */
    protected function getTriggersAtTime(int $merchantId, string $start, string $end): int
    {
        return DeviceTrigger::query()
            ->whereIn('device_id', function($subQuery) use ($merchantId) {
                $subQuery->table('nfc_devices')
                    ->where('merchant_id', $merchantId)
                    ->field('id');
            })
            ->where('create_time', '>=', $start)
            ->where('create_time', '<=', $end)
            ->where('success', 1)
            ->count();
    }

    /**
     * 获取指定时间的内容任务数
     *
     * @param int $merchantId
     * @param string $start
     * @param string $end
     * @return int
     */
    protected function getContentTasksAtTime(int $merchantId, string $start, string $end): int
    {
        return ContentTask::query()
            ->where('merchant_id', $merchantId)
            ->where('create_time', '>=', $start)
            ->where('create_time', '<=', $end)
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->count();
    }

    /**
     * 获取缓存键
     *
     * @param string $type
     * @param mixed ...$params
     * @return string
     */
    protected function getCacheKey(string $type, ...$params): string
    {
        $key = self::CACHE_PREFIX . $type;

        foreach ($params as $param) {
            if ($param !== null) {
                $key .= ':' . $param;
            }
        }

        return $key;
    }

    /**
     * 获取缓存时间
     *
     * @param string $dimension
     * @return int
     */
    protected function getCacheTtl(string $dimension): int
    {
        return match($dimension) {
            self::DIMENSION_REALTIME => self::CACHE_TTL_REALTIME,
            self::DIMENSION_HOUR => self::CACHE_TTL_HOUR,
            self::DIMENSION_DAY => self::CACHE_TTL_DAY,
            self::DIMENSION_WEEK => self::CACHE_TTL_WEEK,
            self::DIMENSION_MONTH => self::CACHE_TTL_MONTH,
            default => self::CACHE_TTL_DAY
        };
    }

    /**
     * 检查系统健康状态
     *
     * @return array
     */
    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // 检查Redis连接
        try {
            Cache::get('health_check');
            $health['checks']['redis'] = [
                'status' => 'ok',
                'message' => 'Redis连接正常'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['redis'] = [
                'status' => 'error',
                'message' => 'Redis连接失败: ' . $e->getMessage()
            ];
        }

        // 检查数据库连接
        try {
            Db::query('SELECT 1');
            $health['checks']['database'] = [
                'status' => 'ok',
                'message' => '数据库连接正常'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'error',
                'message' => '数据库连接失败: ' . $e->getMessage()
            ];
        }

        // 检查设备状态
        $offlineCount = NfcDevice::getOfflineDevices()->count();
        $totalCount = NfcDevice::count();
        $onlineRate = $totalCount > 0 ? ($totalCount - $offlineCount) / $totalCount * 100 : 0;

        if ($onlineRate < 50) {
            $health['status'] = 'warning';
        }

        $health['checks']['devices'] = [
            'status' => $onlineRate >= 80 ? 'ok' : ($onlineRate >= 50 ? 'warning' : 'error'),
            'message' => "设备在线率: {$onlineRate}%",
            'online_rate' => $onlineRate
        ];

        return $health;
    }
}
