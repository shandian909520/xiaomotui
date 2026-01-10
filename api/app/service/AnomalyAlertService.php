<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;

/**
 * 异常预警服务
 * 检测异常数据波动，分析可能原因，并发送预警通知
 */
class AnomalyAlertService
{
    /**
     * 异常类型常量
     */
    const ANOMALY_TYPES = [
        'DATA_SPIKE' => '数据突增',
        'DATA_DROP' => '数据骤降',
        'DEVICE_OFFLINE' => '设备离线',
        'DEVICE_LOW_BATTERY' => '设备低电',
        'CONTENT_FAIL_RATE' => '生成失败率高',
        'PUBLISH_FAIL_RATE' => '发布失败率高',
        'RESPONSE_SLOW' => '响应变慢',
        'CONVERSION_DROP' => '转化率下降',
        'ABNORMAL_TRAFFIC' => '异常流量',
        'API_ERROR_RATE' => 'API错误率高'
    ];

    /**
     * 严重等级常量
     */
    const SEVERITY_LEVELS = [
        'CRITICAL' => 1,   // 严重
        'HIGH' => 2,       // 高
        'MEDIUM' => 3,     // 中等
        'LOW' => 4         // 低
    ];

    /**
     * 处理状态常量
     */
    const STATUS = [
        'DETECTED' => '已检测',
        'NOTIFIED' => '已通知',
        'HANDLING' => '处理中',
        'RESOLVED' => '已解决',
        'IGNORED' => '已忽略'
    ];

    /**
     * 通知服务实例
     */
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    /**
     * 检测所有异常
     *
     * @param int|null $merchantId 商家ID
     * @return array 异常列表
     */
    public function detectAnomalies(?int $merchantId = null): array
    {
        $startTime = microtime(true);
        $anomalies = [];

        try {
            Log::info('开始检测异常', ['merchant_id' => $merchantId]);

            // 检测设备相关异常
            $deviceAnomalies = $this->detectDeviceAnomalies($merchantId);
            $anomalies = array_merge($anomalies, $deviceAnomalies);

            // 检测内容生成异常
            $contentAnomalies = $this->detectContentAnomalies($merchantId);
            $anomalies = array_merge($anomalies, $contentAnomalies);

            // 检测发布异常
            $publishAnomalies = $this->detectPublishAnomalies($merchantId);
            $anomalies = array_merge($anomalies, $publishAnomalies);

            // 检测数据波动异常
            $dataAnomalies = $this->detectDataAnomalies($merchantId);
            $anomalies = array_merge($anomalies, $dataAnomalies);

            // 记录所有检测到的异常
            foreach ($anomalies as $anomaly) {
                $this->recordAnomaly($anomaly);
            }

            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::info('异常检测完成', [
                'merchant_id' => $merchantId,
                'anomalies_count' => count($anomalies),
                'execution_time_ms' => $executionTime
            ]);

            return $anomalies;

        } catch (\Exception $e) {
            Log::error('异常检测失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * 检测数据异常波动
     *
     * @param string $metric 指标名称
     * @param float $currentValue 当前值
     * @param array $context 上下文
     * @return array|null 异常信息
     */
    public function detectDataAnomaly(string $metric, float $currentValue, array $context = []): ?array
    {
        try {
            $merchantId = $context['merchant_id'] ?? null;
            $lookbackPeriod = Config::get('anomaly.detection.lookback_period', 7);

            // 获取历史数据
            $historicalData = $this->getHistoricalData($metric, $merchantId, $lookbackPeriod);

            if (empty($historicalData)) {
                return null;
            }

            // 计算统计值
            $mean = array_sum($historicalData) / count($historicalData);
            $stdDev = $this->calculateStdDev($historicalData, $mean);

            // 3-Sigma规则检测异常
            $deviation = abs($currentValue - $mean);
            $zScore = $stdDev > 0 ? $deviation / $stdDev : 0;

            if ($zScore > 3) {
                $anomalyType = $currentValue > $mean ? 'DATA_SPIKE' : 'DATA_DROP';
                $deviationPercent = $mean > 0 ? (($currentValue - $mean) / $mean) * 100 : 0;

                $severity = $this->calculateDataAnomalySeverity($zScore, abs($deviationPercent));

                return [
                    'type' => $anomalyType,
                    'severity' => $severity,
                    'metric_name' => $metric,
                    'current_value' => $currentValue,
                    'expected_value' => $mean,
                    'deviation' => round($deviationPercent, 2),
                    'z_score' => round($zScore, 2),
                    'merchant_id' => $merchantId,
                    'context' => $context,
                    'detected_at' => date('Y-m-d H:i:s')
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('数据异常检测失败', [
                'metric' => $metric,
                'current_value' => $currentValue,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检测设备异常
     *
     * @param int $deviceId 设备ID
     * @return array|null 异常信息
     */
    public function detectDeviceAnomaly(int $deviceId): ?array
    {
        try {
            $device = Db::name('nfc_devices')->where('id', $deviceId)->find();

            if (!$device) {
                return null;
            }

            $anomalies = [];

            // 检测设备离线异常
            if ($device['status'] === 'offline') {
                $lastHeartbeat = strtotime($device['last_heartbeat'] ?? '1970-01-01');
                $offlineTime = time() - $lastHeartbeat;

                $threshold = Config::get('anomaly.thresholds.offline_threshold', 600);

                if ($offlineTime > $threshold) {
                    $anomalies[] = [
                        'type' => 'DEVICE_OFFLINE',
                        'severity' => $this->calculateOfflineSeverity($offlineTime),
                        'metric_name' => 'device_offline_time',
                        'current_value' => $offlineTime,
                        'expected_value' => 0,
                        'deviation' => 100,
                        'merchant_id' => $device['merchant_id'],
                        'extra_data' => [
                            'device_id' => $deviceId,
                            'device_code' => $device['device_code'],
                            'device_name' => $device['device_name'],
                            'last_heartbeat' => $device['last_heartbeat'],
                            'offline_duration' => $this->formatDuration($offlineTime)
                        ],
                        'detected_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            // 检测电量低异常
            if (isset($device['battery_level']) && $device['battery_level'] !== null) {
                $batteryThreshold = Config::get('anomaly.thresholds.battery_low_threshold', 20);

                if ($device['battery_level'] < $batteryThreshold) {
                    $anomalies[] = [
                        'type' => 'DEVICE_LOW_BATTERY',
                        'severity' => $this->calculateBatterySeverity($device['battery_level']),
                        'metric_name' => 'device_battery_level',
                        'current_value' => $device['battery_level'],
                        'expected_value' => $batteryThreshold,
                        'deviation' => round((($batteryThreshold - $device['battery_level']) / $batteryThreshold) * 100, 2),
                        'merchant_id' => $device['merchant_id'],
                        'extra_data' => [
                            'device_id' => $deviceId,
                            'device_code' => $device['device_code'],
                            'device_name' => $device['device_name'],
                            'battery_level' => $device['battery_level']
                        ],
                        'detected_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return !empty($anomalies) ? $anomalies[0] : null;

        } catch (\Exception $e) {
            Log::error('设备异常检测失败', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检测内容生成异常
     *
     * @param int $merchantId 商家ID
     * @param array $timeRange 时间范围
     * @return array|null 异常信息
     */
    public function detectContentGenerationAnomaly(int $merchantId, array $timeRange = []): ?array
    {
        try {
            $startTime = $timeRange['start'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
            $endTime = $timeRange['end'] ?? date('Y-m-d H:i:s');

            // 统计内容生成任务
            $totalTasks = Db::name('content_tasks')
                ->where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startTime, $endTime])
                ->count();

            if ($totalTasks === 0) {
                return null;
            }

            // 统计失败任务
            $failedTasks = Db::name('content_tasks')
                ->where('merchant_id', $merchantId)
                ->where('status', 'FAILED')
                ->where('create_time', 'between', [$startTime, $endTime])
                ->count();

            $failRate = $totalTasks > 0 ? ($failedTasks / $totalTasks) : 0;
            $failRateThreshold = Config::get('anomaly.thresholds.fail_rate', 0.2);

            if ($failRate > $failRateThreshold) {
                $severity = $this->calculateFailRateSeverity($failRate);

                return [
                    'type' => 'CONTENT_FAIL_RATE',
                    'severity' => $severity,
                    'metric_name' => 'content_generation_fail_rate',
                    'current_value' => round($failRate * 100, 2),
                    'expected_value' => round($failRateThreshold * 100, 2),
                    'deviation' => round((($failRate - $failRateThreshold) / $failRateThreshold) * 100, 2),
                    'merchant_id' => $merchantId,
                    'extra_data' => [
                        'total_tasks' => $totalTasks,
                        'failed_tasks' => $failedTasks,
                        'fail_rate' => round($failRate * 100, 2) . '%',
                        'time_range' => [$startTime, $endTime]
                    ],
                    'detected_at' => date('Y-m-d H:i:s')
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('内容生成异常检测失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 检测发布异常
     *
     * @param int $merchantId 商家ID
     * @param string $platform 平台
     * @return array|null 异常信息
     */
    public function detectPublishAnomaly(int $merchantId, string $platform = ''): ?array
    {
        try {
            $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $endTime = date('Y-m-d H:i:s');

            // 构建查询条件
            $where = [
                ['merchant_id', '=', $merchantId],
                ['create_time', 'between', [$startTime, $endTime]]
            ];

            if (!empty($platform)) {
                $where[] = ['platform', '=', $platform];
            }

            // 统计发布任务
            $totalPublish = Db::name('content_tasks')
                ->where($where)
                ->where('status', 'in', ['PUBLISHED', 'PUBLISH_FAILED'])
                ->count();

            if ($totalPublish === 0) {
                return null;
            }

            // 统计失败发布
            $failedPublish = Db::name('content_tasks')
                ->where($where)
                ->where('status', 'PUBLISH_FAILED')
                ->count();

            $failRate = $totalPublish > 0 ? ($failedPublish / $totalPublish) : 0;
            $failRateThreshold = Config::get('anomaly.thresholds.fail_rate', 0.2);

            if ($failRate > $failRateThreshold) {
                $severity = $this->calculateFailRateSeverity($failRate);

                return [
                    'type' => 'PUBLISH_FAIL_RATE',
                    'severity' => $severity,
                    'metric_name' => 'publish_fail_rate',
                    'current_value' => round($failRate * 100, 2),
                    'expected_value' => round($failRateThreshold * 100, 2),
                    'deviation' => round((($failRate - $failRateThreshold) / $failRateThreshold) * 100, 2),
                    'merchant_id' => $merchantId,
                    'extra_data' => [
                        'platform' => $platform ?: 'all',
                        'total_publish' => $totalPublish,
                        'failed_publish' => $failedPublish,
                        'fail_rate' => round($failRate * 100, 2) . '%',
                        'time_range' => [$startTime, $endTime]
                    ],
                    'detected_at' => date('Y-m-d H:i:s')
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('发布异常检测失败', [
                'merchant_id' => $merchantId,
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 分析异常原因
     *
     * @param array $anomaly 异常数据
     * @return array 可能原因列表
     */
    public function analyzeAnomalyCause(array $anomaly): array
    {
        $causes = [];
        $type = $anomaly['type'] ?? '';

        switch ($type) {
            case 'DATA_SPIKE':
                $causes = [
                    '营销活动导致流量突增',
                    '突发热点事件带来关注',
                    '爬虫或恶意攻击',
                    '系统缓存失效导致重复请求',
                    '合作渠道推广带来流量'
                ];
                break;

            case 'DATA_DROP':
                $causes = [
                    '系统故障或服务中断',
                    '设备大规模离线',
                    '竞品促销活动分流',
                    '网络故障影响访问',
                    '营销活动结束自然回落'
                ];
                break;

            case 'DEVICE_OFFLINE':
                $causes = [
                    '设备电量耗尽',
                    '网络连接故障',
                    '硬件设备损坏',
                    '固件升级中断',
                    '所在场地断电或网络维护'
                ];
                break;

            case 'DEVICE_LOW_BATTERY':
                $causes = [
                    '设备长时间未充电',
                    '电池老化损耗加快',
                    '设备使用频率过高',
                    '环境温度影响电池性能',
                    '充电设备故障'
                ];
                break;

            case 'CONTENT_FAIL_RATE':
                $causes = [
                    'AI服务API限流',
                    '内容生成配额不足',
                    '模板配置错误',
                    '输入数据格式不正确',
                    'AI服务商故障或维护',
                    '网络超时或连接失败'
                ];
                break;

            case 'PUBLISH_FAIL_RATE':
                $causes = [
                    '平台账号异常或被限制',
                    '平台API限流或维护',
                    '内容违反平台规则',
                    '网络连接不稳定',
                    '授权token过期',
                    '平台接口变更未适配'
                ];
                break;

            case 'RESPONSE_SLOW':
                $causes = [
                    '系统负载过高',
                    '数据库查询慢',
                    '外部API响应慢',
                    '网络带宽不足',
                    '代码性能问题',
                    '缓存失效导致大量查询'
                ];
                break;

            case 'CONVERSION_DROP':
                $causes = [
                    '用户体验问题',
                    '竞品优惠活动',
                    '支付渠道故障',
                    '页面加载过慢',
                    '内容质量下降',
                    '目标用户群体变化'
                ];
                break;

            case 'ABNORMAL_TRAFFIC':
                $causes = [
                    '爬虫或机器人访问',
                    '恶意攻击或DDoS',
                    '异常刷量行为',
                    '合作方异常调用',
                    '系统bug导致重复请求'
                ];
                break;

            case 'API_ERROR_RATE':
                $causes = [
                    '代码bug或异常未处理',
                    '第三方服务故障',
                    '数据库连接问题',
                    '参数验证失败',
                    '系统资源不足',
                    '配置错误'
                ];
                break;

            default:
                $causes = ['未知原因，需要进一步排查'];
        }

        return $causes;
    }

    /**
     * 发送预警通知
     *
     * @param array $anomaly 异常信息
     * @param array $recipients 接收者列表
     * @return bool 发送结果
     */
    public function sendAlert(array $anomaly, array $recipients = []): bool
    {
        try {
            $severity = $anomaly['severity'] ?? 'MEDIUM';
            $config = Config::get('anomaly.notifications', []);

            // 根据严重等级确定通知渠道
            $channels = $this->determineNotificationChannels($severity, $config);

            if (empty($channels)) {
                Log::info('无需发送通知', ['anomaly_type' => $anomaly['type']]);
                return true;
            }

            // 构建通知消息
            $message = $this->buildAlertMessage($anomaly);

            // 发送到各个渠道
            $success = true;
            foreach ($channels as $channel) {
                $result = $this->sendToChannel($channel, $message, $anomaly, $recipients);
                if (!$result) {
                    $success = false;
                }
            }

            // 更新异常状态为已通知
            if ($success && isset($anomaly['id'])) {
                Db::name('anomaly_alerts')
                    ->where('id', $anomaly['id'])
                    ->update([
                        'status' => 'NOTIFIED',
                        'notified_at' => date('Y-m-d H:i:s'),
                        'update_time' => date('Y-m-d H:i:s')
                    ]);
            }

            Log::info('异常预警通知发送完成', [
                'anomaly_id' => $anomaly['id'] ?? null,
                'anomaly_type' => $anomaly['type'],
                'channels' => $channels,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            Log::error('发送预警通知失败', [
                'anomaly' => $anomaly,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 记录异常
     *
     * @param array $anomaly 异常信息
     * @return int 记录ID
     */
    public function recordAnomaly(array $anomaly): int
    {
        try {
            // 检查是否存在相同的未解决异常（防止重复记录）
            $existing = Db::name('anomaly_alerts')
                ->where('merchant_id', $anomaly['merchant_id'] ?? null)
                ->where('type', $anomaly['type'])
                ->where('status', 'in', ['DETECTED', 'NOTIFIED', 'HANDLING'])
                ->where('create_time', '>', date('Y-m-d H:i:s', strtotime('-1 hour')))
                ->find();

            if ($existing) {
                // 更新现有记录
                Db::name('anomaly_alerts')
                    ->where('id', $existing['id'])
                    ->update([
                        'current_value' => $anomaly['current_value'],
                        'deviation' => $anomaly['deviation'],
                        'extra_data' => json_encode($anomaly['extra_data'] ?? $anomaly['context'] ?? []),
                        'update_time' => date('Y-m-d H:i:s')
                    ]);
                return $existing['id'];
            }

            // 分析可能原因
            $possibleCauses = $this->analyzeAnomalyCause($anomaly);

            // 插入新记录
            $insertData = [
                'merchant_id' => $anomaly['merchant_id'] ?? null,
                'type' => $anomaly['type'],
                'severity' => $anomaly['severity'],
                'metric_name' => $anomaly['metric_name'] ?? null,
                'current_value' => $anomaly['current_value'] ?? null,
                'expected_value' => $anomaly['expected_value'] ?? null,
                'deviation' => $anomaly['deviation'] ?? null,
                'possible_causes' => json_encode($possibleCauses),
                'status' => 'DETECTED',
                'extra_data' => json_encode($anomaly['extra_data'] ?? $anomaly['context'] ?? []),
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $id = Db::name('anomaly_alerts')->insertGetId($insertData);

            Log::info('异常记录成功', [
                'anomaly_id' => $id,
                'type' => $anomaly['type'],
                'severity' => $anomaly['severity']
            ]);

            // 自动发送通知
            $anomaly['id'] = $id;
            $this->sendAlert($anomaly);

            return $id;

        } catch (\Exception $e) {
            Log::error('记录异常失败', [
                'anomaly' => $anomaly,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 获取异常历史
     *
     * @param int $merchantId 商家ID
     * @param array $options 查询选项
     * @return array 异常历史列表
     */
    public function getAnomalyHistory(int $merchantId, array $options = []): array
    {
        try {
            $where = [['merchant_id', '=', $merchantId]];

            // 类型筛选
            if (!empty($options['type'])) {
                $where[] = ['type', '=', $options['type']];
            }

            // 严重等级筛选
            if (!empty($options['severity'])) {
                $where[] = ['severity', '=', $options['severity']];
            }

            // 状态筛选
            if (!empty($options['status'])) {
                $where[] = ['status', '=', $options['status']];
            }

            // 时间范围筛选
            if (!empty($options['start_time']) && !empty($options['end_time'])) {
                $where[] = ['create_time', 'between', [$options['start_time'], $options['end_time']]];
            }

            $page = $options['page'] ?? 1;
            $pageSize = $options['page_size'] ?? 20;

            $total = Db::name('anomaly_alerts')->where($where)->count();

            $list = Db::name('anomaly_alerts')
                ->where($where)
                ->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->each(function($item) {
                    $item['possible_causes'] = json_decode($item['possible_causes'], true);
                    $item['extra_data'] = json_decode($item['extra_data'], true);
                    $item['type_text'] = self::ANOMALY_TYPES[$item['type']] ?? $item['type'];
                    $item['status_text'] = self::STATUS[$item['status']] ?? $item['status'];
                    return $item;
                });

            return [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'list' => $list
            ];

        } catch (\Exception $e) {
            Log::error('获取异常历史失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'page' => 1,
                'page_size' => 20,
                'list' => []
            ];
        }
    }

    /**
     * 标记异常已处理
     *
     * @param int $anomalyId 异常ID
     * @param array $handleInfo 处理信息
     * @return bool 处理结果
     */
    public function markAsHandled(int $anomalyId, array $handleInfo): bool
    {
        try {
            $updateData = [
                'status' => 'RESOLVED',
                'resolved_at' => date('Y-m-d H:i:s'),
                'handle_notes' => $handleInfo['notes'] ?? '',
                'update_time' => date('Y-m-d H:i:s')
            ];

            $result = Db::name('anomaly_alerts')
                ->where('id', $anomalyId)
                ->update($updateData);

            if ($result) {
                Log::info('异常已标记为已处理', [
                    'anomaly_id' => $anomalyId,
                    'handler' => $handleInfo['handler'] ?? 'system'
                ]);
            }

            return $result > 0;

        } catch (\Exception $e) {
            Log::error('标记异常已处理失败', [
                'anomaly_id' => $anomalyId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检测异常恢复
     *
     * @param int $anomalyId 异常ID
     * @return bool 是否已恢复
     */
    public function checkRecovery(int $anomalyId): bool
    {
        try {
            $anomaly = Db::name('anomaly_alerts')->where('id', $anomalyId)->find();

            if (!$anomaly) {
                return false;
            }

            // 根据异常类型检查恢复情况
            $recovered = false;

            switch ($anomaly['type']) {
                case 'DEVICE_OFFLINE':
                    $extraData = json_decode($anomaly['extra_data'], true);
                    $deviceId = $extraData['device_id'] ?? 0;
                    if ($deviceId) {
                        $device = Db::name('nfc_devices')->where('id', $deviceId)->find();
                        $recovered = $device && $device['status'] === 'active';
                    }
                    break;

                case 'DEVICE_LOW_BATTERY':
                    $extraData = json_decode($anomaly['extra_data'], true);
                    $deviceId = $extraData['device_id'] ?? 0;
                    if ($deviceId) {
                        $device = Db::name('nfc_devices')->where('id', $deviceId)->find();
                        $recovered = $device && $device['battery_level'] >= 20;
                    }
                    break;

                case 'CONTENT_FAIL_RATE':
                case 'PUBLISH_FAIL_RATE':
                    // 检查最近的失败率是否恢复正常
                    $recentAnomaly = $this->detectContentGenerationAnomaly(
                        $anomaly['merchant_id'],
                        ['start' => date('Y-m-d H:i:s', strtotime('-15 minutes'))]
                    );
                    $recovered = $recentAnomaly === null;
                    break;

                default:
                    // 其他类型暂不自动检测恢复
                    $recovered = false;
            }

            if ($recovered) {
                $this->markAsHandled($anomalyId, [
                    'notes' => '系统自动检测已恢复',
                    'handler' => 'auto_recovery'
                ]);

                Log::info('异常已自动恢复', ['anomaly_id' => $anomalyId]);
            }

            return $recovered;

        } catch (\Exception $e) {
            Log::error('检测异常恢复失败', [
                'anomaly_id' => $anomalyId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 统计分析异常趋势
     *
     * @param int $merchantId 商家ID
     * @param int $days 天数
     * @return array 趋势分析
     */
    public function analyzeAnomalyTrend(int $merchantId, int $days = 7): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $endDate = date('Y-m-d');

            // 按日统计异常数量
            $dailyStats = Db::name('anomaly_alerts')
                ->where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('DATE(create_time) as date, COUNT(*) as count')
                ->group('date')
                ->order('date')
                ->select()
                ->toArray();

            // 按类型统计
            $typeStats = Db::name('anomaly_alerts')
                ->where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('type, COUNT(*) as count')
                ->group('type')
                ->select()
                ->each(function($item) {
                    $item['type_text'] = self::ANOMALY_TYPES[$item['type']] ?? $item['type'];
                    return $item;
                });

            // 按严重等级统计
            $severityStats = Db::name('anomaly_alerts')
                ->where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('severity, COUNT(*) as count')
                ->group('severity')
                ->select()
                ->toArray();

            // 总计
            $totalCount = array_sum(array_column($dailyStats, 'count'));
            $resolvedCount = Db::name('anomaly_alerts')
                ->where('merchant_id', $merchantId)
                ->where('status', 'RESOLVED')
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $resolutionRate = $totalCount > 0 ? round(($resolvedCount / $totalCount) * 100, 2) : 0;

            // 平均解决时间
            $avgResolutionTime = Db::name('anomaly_alerts')
                ->where('merchant_id', $merchantId)
                ->where('status', 'RESOLVED')
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->avg('TIMESTAMPDIFF(MINUTE, create_time, resolved_at)');

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => $days
                ],
                'summary' => [
                    'total_anomalies' => $totalCount,
                    'resolved_anomalies' => $resolvedCount,
                    'resolution_rate' => $resolutionRate,
                    'avg_resolution_time_minutes' => round($avgResolutionTime ?? 0, 2)
                ],
                'daily_stats' => $dailyStats,
                'type_stats' => $typeStats,
                'severity_stats' => $severityStats
            ];

        } catch (\Exception $e) {
            Log::error('分析异常趋势失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 检测设备相关异常
     */
    protected function detectDeviceAnomalies(?int $merchantId): array
    {
        $anomalies = [];

        try {
            $where = [];
            if ($merchantId !== null) {
                $where[] = ['merchant_id', '=', $merchantId];
            }

            $devices = Db::name('nfc_devices')->where($where)->select();

            foreach ($devices as $device) {
                $anomaly = $this->detectDeviceAnomaly($device['id']);
                if ($anomaly) {
                    $anomalies[] = $anomaly;
                }
            }
        } catch (\Exception $e) {
            Log::error('检测设备异常失败', ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * 检测内容相关异常
     */
    protected function detectContentAnomalies(?int $merchantId): array
    {
        $anomalies = [];

        try {
            if ($merchantId !== null) {
                $anomaly = $this->detectContentGenerationAnomaly($merchantId);
                if ($anomaly) {
                    $anomalies[] = $anomaly;
                }
            } else {
                // 获取所有商家
                $merchants = Db::name('merchants')->column('id');
                foreach ($merchants as $mid) {
                    $anomaly = $this->detectContentGenerationAnomaly($mid);
                    if ($anomaly) {
                        $anomalies[] = $anomaly;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('检测内容异常失败', ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * 检测发布相关异常
     */
    protected function detectPublishAnomalies(?int $merchantId): array
    {
        $anomalies = [];

        try {
            if ($merchantId !== null) {
                $anomaly = $this->detectPublishAnomaly($merchantId);
                if ($anomaly) {
                    $anomalies[] = $anomaly;
                }
            } else {
                // 获取所有商家
                $merchants = Db::name('merchants')->column('id');
                foreach ($merchants as $mid) {
                    $anomaly = $this->detectPublishAnomaly($mid);
                    if ($anomaly) {
                        $anomalies[] = $anomaly;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('检测发布异常失败', ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * 检测数据波动异常
     */
    protected function detectDataAnomalies(?int $merchantId): array
    {
        $anomalies = [];

        try {
            // 可以添加更多指标的检测
            // 例如：访问量、转化率、响应时间等

            // 这里作为示例，暂时返回空数组
        } catch (\Exception $e) {
            Log::error('检测数据波动异常失败', ['error' => $e->getMessage()]);
        }

        return $anomalies;
    }

    /**
     * 获取历史数据
     */
    protected function getHistoricalData(string $metric, ?int $merchantId, int $days): array
    {
        // 这里需要根据实际的指标数据存储方式来实现
        // 示例实现：从缓存或数据库获取历史数据

        $cacheKey = "historical_data:{$metric}:{$merchantId}:{$days}";
        $data = Cache::get($cacheKey);

        if ($data !== false && is_array($data)) {
            return $data;
        }

        // 这里应该从实际的数据源获取历史数据
        // 暂时返回空数组（实际使用时需要实现真实的数据获取逻辑）
        return [];
    }

    /**
     * 计算标准差
     */
    protected function calculateStdDev(array $data, float $mean): float
    {
        $count = count($data);
        if ($count === 0) {
            return 0;
        }

        $variance = 0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / $count);
    }

    /**
     * 计算数据异常严重等级
     */
    protected function calculateDataAnomalySeverity(float $zScore, float $deviationPercent): string
    {
        if ($zScore >= 5 || abs($deviationPercent) >= 200) {
            return 'CRITICAL';
        } elseif ($zScore >= 4 || abs($deviationPercent) >= 100) {
            return 'HIGH';
        } elseif ($zScore >= 3.5 || abs($deviationPercent) >= 50) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * 计算离线严重等级
     */
    protected function calculateOfflineSeverity(int $offlineTime): string
    {
        if ($offlineTime >= 7200) { // 2小时
            return 'CRITICAL';
        } elseif ($offlineTime >= 3600) { // 1小时
            return 'HIGH';
        } elseif ($offlineTime >= 1800) { // 30分钟
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * 计算电量严重等级
     */
    protected function calculateBatterySeverity(int $batteryLevel): string
    {
        if ($batteryLevel <= 5) {
            return 'CRITICAL';
        } elseif ($batteryLevel <= 10) {
            return 'HIGH';
        } elseif ($batteryLevel <= 15) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * 计算失败率严重等级
     */
    protected function calculateFailRateSeverity(float $failRate): string
    {
        if ($failRate >= 0.5) { // 50%
            return 'CRITICAL';
        } elseif ($failRate >= 0.3) { // 30%
            return 'HIGH';
        } elseif ($failRate >= 0.2) { // 20%
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }

    /**
     * 确定通知渠道
     */
    protected function determineNotificationChannels(string $severity, array $config): array
    {
        $allChannels = $config['channels'] ?? ['system'];
        $channels = [];

        foreach ($allChannels as $channel) {
            $channelConfig = $config[$channel] ?? [];
            if (!isset($channelConfig['enabled']) || $channelConfig['enabled']) {
                $severityLevels = $channelConfig['severity_levels'] ?? ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
                if (in_array($severity, $severityLevels)) {
                    $channels[] = $channel;
                }
            }
        }

        return $channels;
    }

    /**
     * 构建预警消息
     */
    protected function buildAlertMessage(array $anomaly): string
    {
        $typeText = self::ANOMALY_TYPES[$anomaly['type']] ?? $anomaly['type'];
        $severityText = ['CRITICAL' => '严重', 'HIGH' => '高', 'MEDIUM' => '中等', 'LOW' => '低'][$anomaly['severity']] ?? '未知';

        $message = "【异常预警】{$typeText}\n";
        $message .= "严重等级：{$severityText}\n";
        $message .= "指标名称：{$anomaly['metric_name']}\n";
        $message .= "当前值：{$anomaly['current_value']}\n";
        $message .= "期望值：{$anomaly['expected_value']}\n";
        $message .= "偏差：{$anomaly['deviation']}%\n";
        $message .= "检测时间：{$anomaly['detected_at']}\n";

        return $message;
    }

    /**
     * 发送到指定渠道
     */
    protected function sendToChannel(string $channel, string $message, array $anomaly, array $recipients): bool
    {
        try {
            switch ($channel) {
                case 'system':
                    return $this->sendSystemNotification($message, $anomaly);
                case 'sms':
                    return $this->sendSmsNotification($message, $anomaly, $recipients);
                case 'email':
                    return $this->sendEmailNotification($message, $anomaly, $recipients);
                case 'wechat':
                    return $this->sendWechatNotification($message, $anomaly);
                default:
                    Log::warning('不支持的通知渠道', ['channel' => $channel]);
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('发送通知失败', [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送系统通知
     */
    protected function sendSystemNotification(string $message, array $anomaly): bool
    {
        try {
            $merchantId = $anomaly['merchant_id'] ?? 0;
            $cacheKey = "anomaly_notification:merchant_{$merchantId}";
            $notifications = Cache::get($cacheKey, []);

            $notifications[] = [
                'id' => $anomaly['id'] ?? 0,
                'type' => 'anomaly_alert',
                'title' => self::ANOMALY_TYPES[$anomaly['type']] ?? $anomaly['type'],
                'message' => $message,
                'severity' => $anomaly['severity'],
                'data' => $anomaly,
                'read' => false,
                'create_time' => date('Y-m-d H:i:s')
            ];

            // 保留最近100条通知
            if (count($notifications) > 100) {
                $notifications = array_slice($notifications, -100);
            }

            Cache::set($cacheKey, $notifications, 7 * 24 * 3600);

            return true;
        } catch (\Exception $e) {
            Log::error('发送系统通知失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 发送短信通知
     */
    protected function sendSmsNotification(string $message, array $anomaly, array $recipients): bool
    {
        // 实际项目中集成短信服务商SDK
        Log::info('短信通知（模拟）', ['message' => $message, 'recipients' => $recipients]);
        return true;
    }

    /**
     * 发送邮件通知
     */
    protected function sendEmailNotification(string $message, array $anomaly, array $recipients): bool
    {
        // 实际项目中集成邮件服务
        Log::info('邮件通知（模拟）', ['message' => $message, 'recipients' => $recipients]);
        return true;
    }

    /**
     * 发送微信通知
     */
    protected function sendWechatNotification(string $message, array $anomaly): bool
    {
        // 实际项目中集成微信通知服务
        Log::info('微信通知（模拟）', ['message' => $message]);
        return true;
    }

    /**
     * 格式化时长
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . '分钟';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . '小时';
        } else {
            return round($seconds / 86400, 1) . '天';
        }
    }
}
