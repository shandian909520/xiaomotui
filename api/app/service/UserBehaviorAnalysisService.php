<?php
declare (strict_types = 1);

namespace app\service;

use app\model\User;
use app\model\DeviceTrigger;
use app\model\ContentTask;
use app\model\NfcDevice;
use app\model\ContentTemplate;
use app\model\CouponUser;
use app\model\PublishTask;
use app\model\Statistics;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;
use think\Exception;

/**
 * 用户行为分析服务
 * 提供用户画像、使用时段、热门场景等深度分析功能
 */
class UserBehaviorAnalysisService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'user_behavior:';

    /**
     * 缓存时间(秒)
     */
    const CACHE_TTL_SHORT = 300;      // 5分钟
    const CACHE_TTL_MEDIUM = 1800;    // 30分钟
    const CACHE_TTL_LONG = 3600;      // 1小时
    const CACHE_TTL_DAY = 86400;      // 1天

    /**
     * 用户画像维度
     */
    const PROFILE_DIMENSIONS = [
        'basic_info' => '基本信息',
        'activity_level' => '活跃度',
        'consumption' => '消费行为',
        'content_preference' => '内容偏好',
        'device_usage' => '设备使用',
        'time_pattern' => '时间模式',
        'engagement' => '互动程度',
        'value_score' => '价值评分'
    ];

    /**
     * 行为事件类型
     */
    const BEHAVIOR_EVENTS = [
        'nfc_trigger' => 'NFC触发',
        'content_generate' => '内容生成',
        'content_share' => '内容分享',
        'coupon_receive' => '优惠券领取',
        'coupon_use' => '优惠券使用',
        'platform_publish' => '平台发布',
        'wifi_connect' => 'WiFi连接',
        'contact_add' => '好友添加'
    ];

    /**
     * 用户价值等级
     */
    const USER_VALUE_LEVELS = [
        'high' => '高价值用户',
        'medium' => '中等价值用户',
        'low' => '低价值用户',
        'inactive' => '不活跃用户'
    ];

    /**
     * 生成用户画像
     *
     * @param int $userId 用户ID
     * @param array $options 分析选项
     * @return array 用户画像数据
     */
    public function generateUserProfile(int $userId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('profile', $userId);

        // 尝试从缓存获取
        if (!isset($options['refresh']) || !$options['refresh']) {
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                Log::debug('用户画像缓存命中', ['user_id' => $userId]);
                return $cached;
            }
        }

        Log::info('生成用户画像', ['user_id' => $userId]);

        try {
            // 获取用户基本信息
            $user = User::find($userId);
            if (!$user) {
                throw new Exception('用户不存在');
            }

            // 构建用户画像
            $profile = [
                'user_id' => $userId,
                'basic_info' => $this->getBasicInfo($user),
                'activity_level' => $this->getActivityLevel($userId),
                'consumption' => $this->getConsumptionBehavior($userId),
                'content_preference' => $this->getContentPreference($userId),
                'device_usage' => $this->getDeviceUsage($userId),
                'time_pattern' => $this->getTimePattern($userId),
                'engagement' => $this->getEngagement($userId),
                'value_score' => $this->calculateValueScore($userId),
                'tags' => $this->getUserTags($userId),
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // 缓存结果
            Cache::set($cacheKey, $profile, self::CACHE_TTL_MEDIUM);

            Log::info('用户画像生成成功', ['user_id' => $userId]);

            return $profile;

        } catch (Exception $e) {
            Log::error('生成用户画像失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 批量生成用户画像
     *
     * @param array $userIds 用户ID列表
     * @return array
     */
    public function batchGenerateUserProfiles(array $userIds): array
    {
        $profiles = [];

        foreach ($userIds as $userId) {
            try {
                $profiles[$userId] = $this->generateUserProfile($userId);
            } catch (Exception $e) {
                Log::warning('批量生成用户画像失败', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                $profiles[$userId] = null;
            }
        }

        return $profiles;
    }

    /**
     * 获取用户标签
     *
     * @param int $userId 用户ID
     * @return array 标签列表
     */
    public function getUserTags(int $userId): array
    {
        $tags = [];

        // 基于活跃度的标签
        $activityLevel = $this->getActivityLevel($userId);
        if ($activityLevel['active_days'] >= 20) {
            $tags[] = '活跃用户';
        } elseif ($activityLevel['active_days'] >= 10) {
            $tags[] = '中度活跃';
        } else {
            $tags[] = '轻度使用';
        }

        // 基于会员等级的标签
        $user = User::find($userId);
        if ($user) {
            if ($user->isPremium()) {
                $tags[] = '高级会员';
            } elseif ($user->isVip()) {
                $tags[] = 'VIP会员';
            }
        }

        // 基于价值分数的标签
        $valueScore = $this->calculateValueScore($userId);
        if ($valueScore['total_score'] >= 80) {
            $tags[] = '高价值用户';
        } elseif ($valueScore['total_score'] >= 50) {
            $tags[] = '潜力用户';
        }

        // 基于内容偏好的标签
        $contentPref = $this->getContentPreference($userId);
        if (isset($contentPref['most_used_type'])) {
            $tags[] = $contentPref['most_used_type'] . '内容偏好';
        }

        // 基于最近活跃时间
        $today = date('Y-m-d');
        $recentTrigger = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->order('create_time', 'desc')
            ->find();

        if ($recentTrigger) {
            $daysSinceActive = (strtotime($today) - strtotime(date('Y-m-d', strtotime($recentTrigger->create_time)))) / 86400;
            if ($daysSinceActive > 30) {
                $tags[] = '流失风险';
            } elseif ($daysSinceActive <= 1) {
                $tags[] = '近期活跃';
            }
        }

        return array_unique($tags);
    }

    /**
     * 分析用户活跃时段
     *
     * @param int|null $merchantId 商家ID（null表示全局）
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array 时段分布数据
     */
    public function analyzeActiveHours(?int $merchantId, string $startDate, string $endDate): array
    {
        $cacheKey = $this->getCacheKey('active_hours', $merchantId, $startDate, $endDate);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('分析用户活跃时段', [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 构建查询
            $query = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('success', 1);

            if ($merchantId !== null) {
                $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }

            // 按小时统计
            $hourlyData = $query->field('HOUR(create_time) as hour, COUNT(*) as count')
                ->group('hour')
                ->order('hour', 'asc')
                ->select()
                ->toArray();

            // 初始化24小时数据
            $hours = [];
            for ($h = 0; $h < 24; $h++) {
                $hours[$h] = [
                    'hour' => str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00',
                    'count' => 0,
                    'percentage' => 0
                ];
            }

            // 填充实际数据
            $totalCount = 0;
            foreach ($hourlyData as $item) {
                $hour = (int)$item['hour'];
                $count = (int)$item['count'];
                $hours[$hour]['count'] = $count;
                $totalCount += $count;
            }

            // 计算百分比
            if ($totalCount > 0) {
                foreach ($hours as $h => &$hourData) {
                    $hourData['percentage'] = round($hourData['count'] / $totalCount * 100, 2);
                }
            }

            // 找出高峰时段
            $peakHours = [];
            $sortedHours = $hours;
            usort($sortedHours, function($a, $b) {
                return $b['count'] - $a['count'];
            });

            for ($i = 0; $i < min(5, count($sortedHours)); $i++) {
                if ($sortedHours[$i]['count'] > 0) {
                    $peakHours[] = $sortedHours[$i]['hour'];
                }
            }

            $result = [
                'hourly_distribution' => array_values($hours),
                'peak_hours' => $peakHours,
                'total_triggers' => $totalCount,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

            return $result;

        } catch (Exception $e) {
            Log::error('分析用户活跃时段失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析用户访问频率
     *
     * @param int $userId 用户ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function analyzeVisitFrequency(int $userId, string $startDate, string $endDate): array
    {
        Log::info('分析用户访问频率', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 按天统计访问次数
            $dailyVisits = DeviceTrigger::where('user_id', $userId)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('success', 1)
                ->field('DATE(create_time) as date, COUNT(*) as count')
                ->group('date')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            // 计算总天数
            $start = strtotime($startDate);
            $end = strtotime($endDate);
            $totalDays = floor(($end - $start) / 86400) + 1;

            // 活跃天数
            $activeDays = count($dailyVisits);

            // 总访问次数
            $totalVisits = array_sum(array_column($dailyVisits, 'count'));

            // 平均每天访问次数
            $avgVisitsPerDay = $activeDays > 0 ? round($totalVisits / $activeDays, 2) : 0;

            // 访问频率等级
            $frequencyLevel = 'low';
            if ($avgVisitsPerDay >= 10) {
                $frequencyLevel = 'very_high';
            } elseif ($avgVisitsPerDay >= 5) {
                $frequencyLevel = 'high';
            } elseif ($avgVisitsPerDay >= 2) {
                $frequencyLevel = 'medium';
            }

            return [
                'total_days' => $totalDays,
                'active_days' => $activeDays,
                'total_visits' => $totalVisits,
                'avg_visits_per_day' => $avgVisitsPerDay,
                'frequency_level' => $frequencyLevel,
                'daily_visits' => $dailyVisits,
                'activity_rate' => $totalDays > 0 ? round($activeDays / $totalDays * 100, 2) : 0
            ];

        } catch (Exception $e) {
            Log::error('分析用户访问频率失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取用户留存率
     *
     * @param int|null $merchantId 商家ID
     * @param string $date 基准日期
     * @param array $periods 留存周期 [1, 7, 30]
     * @return array
     */
    public function getRetentionRate(?int $merchantId, string $date, array $periods = [1, 7, 30]): array
    {
        $cacheKey = $this->getCacheKey('retention', $merchantId, $date);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('计算用户留存率', [
            'merchant_id' => $merchantId,
            'date' => $date,
            'periods' => $periods
        ]);

        try {
            // 获取基准日期的新增用户
            $baseQuery = User::where('create_time', '>=', $date . ' 00:00:00')
                ->where('create_time', '<=', $date . ' 23:59:59');

            $newUsers = $baseQuery->column('id');
            $newUserCount = count($newUsers);

            if ($newUserCount === 0) {
                return [
                    'base_date' => $date,
                    'new_user_count' => 0,
                    'retention' => []
                ];
            }

            // 计算各周期的留存
            $retention = [];
            foreach ($periods as $period) {
                $checkDate = date('Y-m-d', strtotime($date . " +{$period} days"));

                // 查询在检查日期活跃的用户数
                $activeQuery = DeviceTrigger::whereIn('user_id', $newUsers)
                    ->where('create_time', '>=', $checkDate . ' 00:00:00')
                    ->where('create_time', '<=', $checkDate . ' 23:59:59')
                    ->where('success', 1);

                if ($merchantId !== null) {
                    $activeQuery->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                }

                $activeUserCount = $activeQuery->distinct()->count('user_id');
                $retentionRate = round($activeUserCount / $newUserCount * 100, 2);

                $retention[] = [
                    'period' => $period,
                    'check_date' => $checkDate,
                    'active_users' => $activeUserCount,
                    'retention_rate' => $retentionRate
                ];
            }

            $result = [
                'base_date' => $date,
                'new_user_count' => $newUserCount,
                'retention' => $retention
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_LONG);

            return $result;

        } catch (Exception $e) {
            Log::error('计算用户留存率失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析热门场景
     *
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param int $limit 返回数量
     * @return array
     */
    public function analyzeHotScenes(?int $merchantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('hot_scenes', $merchantId, $startDate, $endDate);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('分析热门场景', [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 按触发模式统计
            $query = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('success', 1);

            if ($merchantId !== null) {
                $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }

            $sceneStats = $query->field('trigger_mode, COUNT(*) as count, COUNT(DISTINCT user_id) as user_count')
                ->group('trigger_mode')
                ->order('count', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            // 计算总数用于百分比
            $totalCount = array_sum(array_column($sceneStats, 'count'));

            // 添加百分比和场景名称
            foreach ($sceneStats as &$scene) {
                $scene['percentage'] = $totalCount > 0 ? round($scene['count'] / $totalCount * 100, 2) : 0;
                $scene['scene_name'] = self::BEHAVIOR_EVENTS['nfc_trigger'] ?? $scene['trigger_mode'];
            }

            $result = [
                'scenes' => $sceneStats,
                'total_triggers' => $totalCount,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

            return $result;

        } catch (Exception $e) {
            Log::error('分析热门场景失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析热门设备
     *
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param int $limit 返回数量
     * @return array
     */
    public function analyzeHotDevices(?int $merchantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('hot_devices', $merchantId, $startDate, $endDate);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('分析热门设备', [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 按设备统计
            $query = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('success', 1);

            if ($merchantId !== null) {
                $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }

            $deviceStats = $query->field('device_id, device_code, COUNT(*) as trigger_count, COUNT(DISTINCT user_id) as user_count')
                ->group('device_id')
                ->order('trigger_count', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            // 获取设备详细信息
            $deviceIds = array_column($deviceStats, 'device_id');
            $devices = NfcDevice::whereIn('id', $deviceIds)->select()->toArray();
            $deviceMap = array_column($devices, null, 'id');

            // 合并设备信息
            foreach ($deviceStats as &$stat) {
                $deviceId = $stat['device_id'];
                if (isset($deviceMap[$deviceId])) {
                    $stat['device_name'] = $deviceMap[$deviceId]['device_name'] ?? '';
                    $stat['location'] = $deviceMap[$deviceId]['location'] ?? '';
                } else {
                    $stat['device_name'] = '';
                    $stat['location'] = '';
                }
            }

            $result = [
                'devices' => $deviceStats,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

            return $result;

        } catch (Exception $e) {
            Log::error('分析热门设备失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析热门内容模板
     *
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param int $limit 返回数量
     * @return array
     */
    public function analyzeHotTemplates(?int $merchantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $cacheKey = $this->getCacheKey('hot_templates', $merchantId, $startDate, $endDate);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('分析热门内容模板', [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 按模板统计
            $query = ContentTask::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('status', ContentTask::STATUS_COMPLETED);

            if ($merchantId !== null) {
                $query->where('merchant_id', $merchantId);
            }

            $templateStats = $query->field('template_id, COUNT(*) as usage_count, COUNT(DISTINCT user_id) as user_count')
                ->group('template_id')
                ->order('usage_count', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            // 获取模板详细信息
            $templateIds = array_column($templateStats, 'template_id');
            $templateIds = array_filter($templateIds); // 过滤null值

            $templates = [];
            if (!empty($templateIds)) {
                $templates = ContentTemplate::whereIn('id', $templateIds)->select()->toArray();
            }
            $templateMap = array_column($templates, null, 'id');

            // 合并模板信息
            foreach ($templateStats as &$stat) {
                $templateId = $stat['template_id'];
                if ($templateId && isset($templateMap[$templateId])) {
                    $stat['template_name'] = $templateMap[$templateId]['name'] ?? '';
                    $stat['template_type'] = $templateMap[$templateId]['type'] ?? '';
                } else {
                    $stat['template_name'] = '自定义生成';
                    $stat['template_type'] = '';
                }
            }

            $result = [
                'templates' => $templateStats,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

            return $result;

        } catch (Exception $e) {
            Log::error('分析热门内容模板失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析用户行为路径
     *
     * @param int $userId 用户ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array 行为路径
     */
    public function analyzeUserJourney(int $userId, string $startDate, string $endDate): array
    {
        Log::info('分析用户行为路径', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            // 获取用户的所有行为事件
            $triggers = DeviceTrigger::where('user_id', $userId)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->field('id, device_code, trigger_mode, response_type, create_time')
                ->order('create_time', 'asc')
                ->select()
                ->toArray();

            // 构建行为路径
            $journey = [];
            foreach ($triggers as $trigger) {
                $journey[] = [
                    'timestamp' => $trigger['create_time'],
                    'event_type' => 'nfc_trigger',
                    'event_name' => 'NFC触发',
                    'trigger_mode' => $trigger['trigger_mode'],
                    'response_type' => $trigger['response_type'],
                    'device_code' => $trigger['device_code']
                ];
            }

            // 获取内容生成记录
            $contentTasks = ContentTask::where('user_id', $userId)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->field('id, type, status, create_time')
                ->order('create_time', 'asc')
                ->select()
                ->toArray();

            foreach ($contentTasks as $task) {
                $journey[] = [
                    'timestamp' => $task['create_time'],
                    'event_type' => 'content_generate',
                    'event_name' => '内容生成',
                    'content_type' => $task['type'],
                    'status' => $task['status']
                ];
            }

            // 按时间排序
            usort($journey, function($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });

            return [
                'user_id' => $userId,
                'journey' => $journey,
                'total_events' => count($journey),
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

        } catch (Exception $e) {
            Log::error('分析用户行为路径失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析转化漏斗
     *
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array 漏斗数据
     */
    public function analyzeConversionFunnel(?int $merchantId, string $startDate, string $endDate): array
    {
        $cacheKey = $this->getCacheKey('funnel', $merchantId, $startDate, $endDate);

        // 尝试从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('分析转化漏斗', [
            'merchant_id' => $merchantId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            $baseQuery = function() use ($merchantId, $startDate, $endDate) {
                $query = Db::name('device_triggers')
                    ->where('create_time', '>=', $startDate . ' 00:00:00')
                    ->where('create_time', '<=', $endDate . ' 23:59:59');

                if ($merchantId !== null) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                }

                return $query;
            };

            // 第1步：NFC触发
            $step1Users = $baseQuery()->distinct()->count('user_id');

            // 第2步：触发成功
            $step2Users = $baseQuery()->where('success', 1)->distinct()->count('user_id');

            // 第3步：生成内容
            $contentQuery = ContentTask::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59');
            if ($merchantId !== null) {
                $contentQuery->where('merchant_id', $merchantId);
            }
            $step3Users = $contentQuery->distinct()->count('user_id');

            // 第4步：内容生成成功
            $step4Users = (clone $contentQuery)->where('status', ContentTask::STATUS_COMPLETED)
                ->distinct()->count('user_id');

            // 第5步：发布到平台
            $publishQuery = PublishTask::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59');
            if ($merchantId !== null) {
                $publishQuery->whereIn('content_task_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('content_tasks')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }
            $step5Users = $publishQuery->distinct()->count('user_id');

            // 构建漏斗数据
            $funnel = [
                [
                    'step' => 1,
                    'name' => 'NFC触发',
                    'users' => $step1Users,
                    'conversion_rate' => 100,
                    'drop_rate' => 0
                ],
                [
                    'step' => 2,
                    'name' => '触发成功',
                    'users' => $step2Users,
                    'conversion_rate' => $step1Users > 0 ? round($step2Users / $step1Users * 100, 2) : 0,
                    'drop_rate' => $step1Users > 0 ? round(($step1Users - $step2Users) / $step1Users * 100, 2) : 0
                ],
                [
                    'step' => 3,
                    'name' => '生成内容',
                    'users' => $step3Users,
                    'conversion_rate' => $step2Users > 0 ? round($step3Users / $step2Users * 100, 2) : 0,
                    'drop_rate' => $step2Users > 0 ? round(($step2Users - $step3Users) / $step2Users * 100, 2) : 0
                ],
                [
                    'step' => 4,
                    'name' => '生成成功',
                    'users' => $step4Users,
                    'conversion_rate' => $step3Users > 0 ? round($step4Users / $step3Users * 100, 2) : 0,
                    'drop_rate' => $step3Users > 0 ? round(($step3Users - $step4Users) / $step3Users * 100, 2) : 0
                ],
                [
                    'step' => 5,
                    'name' => '发布平台',
                    'users' => $step5Users,
                    'conversion_rate' => $step4Users > 0 ? round($step5Users / $step4Users * 100, 2) : 0,
                    'drop_rate' => $step4Users > 0 ? round(($step4Users - $step5Users) / $step4Users * 100, 2) : 0
                ]
            ];

            // 整体转化率
            $overallConversionRate = $step1Users > 0 ? round($step5Users / $step1Users * 100, 2) : 0;

            $result = [
                'funnel' => $funnel,
                'overall_conversion_rate' => $overallConversionRate,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

            return $result;

        } catch (Exception $e) {
            Log::error('分析转化漏斗失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 用户分群
     *
     * @param int|null $merchantId 商家ID
     * @param array $criteria 分群条件
     * @return array 分群结果
     */
    public function segmentUsers(?int $merchantId, array $criteria): array
    {
        Log::info('用户分群', [
            'merchant_id' => $merchantId,
            'criteria' => $criteria
        ]);

        try {
            $query = User::query();

            // 按会员等级筛选
            if (isset($criteria['member_level'])) {
                $query->whereIn('member_level', (array)$criteria['member_level']);
            }

            // 按积分范围筛选
            if (isset($criteria['points_min'])) {
                $query->where('points', '>=', $criteria['points_min']);
            }
            if (isset($criteria['points_max'])) {
                $query->where('points', '<=', $criteria['points_max']);
            }

            // 按注册时间筛选
            if (isset($criteria['register_start'])) {
                $query->where('create_time', '>=', $criteria['register_start']);
            }
            if (isset($criteria['register_end'])) {
                $query->where('create_time', '<=', $criteria['register_end']);
            }

            // 按性别筛选
            if (isset($criteria['gender'])) {
                $query->where('gender', $criteria['gender']);
            }

            // 按状态筛选
            if (isset($criteria['status'])) {
                $query->where('status', $criteria['status']);
            }

            $users = $query->select()->toArray();

            // 进一步按行为筛选
            if (isset($criteria['active_days_min']) || isset($criteria['trigger_count_min'])) {
                $filteredUsers = [];
                foreach ($users as $user) {
                    $pass = true;

                    // 检查活跃天数
                    if (isset($criteria['active_days_min'])) {
                        $activityLevel = $this->getActivityLevel($user['id']);
                        if ($activityLevel['active_days'] < $criteria['active_days_min']) {
                            $pass = false;
                        }
                    }

                    // 检查触发次数
                    if (isset($criteria['trigger_count_min']) && $pass) {
                        $triggerCount = DeviceTrigger::where('user_id', $user['id'])
                            ->where('success', 1)
                            ->count();
                        if ($triggerCount < $criteria['trigger_count_min']) {
                            $pass = false;
                        }
                    }

                    if ($pass) {
                        $filteredUsers[] = $user;
                    }
                }
                $users = $filteredUsers;
            }

            return [
                'total_users' => count($users),
                'users' => array_slice($users, 0, 100), // 限制返回数量
                'criteria' => $criteria
            ];

        } catch (Exception $e) {
            Log::error('用户分群失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取高价值用户
     *
     * @param int|null $merchantId 商家ID
     * @param int $limit 返回数量
     * @return array
     */
    public function getHighValueUsers(?int $merchantId, int $limit = 100): array
    {
        Log::info('获取高价值用户', ['merchant_id' => $merchantId]);

        try {
            // 基础查询
            $query = User::where('status', User::STATUS_NORMAL);

            // 获取所有用户
            $users = $query->select()->toArray();

            // 计算价值分数
            $userScores = [];
            foreach ($users as $user) {
                $score = $this->calculateValueScore($user['id']);
                $userScores[] = [
                    'user_id' => $user['id'],
                    'nickname' => $user['nickname'],
                    'phone' => $user['phone'],
                    'member_level' => $user['member_level'],
                    'value_score' => $score['total_score'],
                    'score_details' => $score
                ];
            }

            // 按价值分数排序
            usort($userScores, function($a, $b) {
                return $b['value_score'] - $a['value_score'];
            });

            // 筛选高价值用户（分数>=80）
            $highValueUsers = array_filter($userScores, function($user) {
                return $user['value_score'] >= 80;
            });

            return [
                'total_count' => count($highValueUsers),
                'users' => array_slice($highValueUsers, 0, $limit)
            ];

        } catch (Exception $e) {
            Log::error('获取高价值用户失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取流失风险用户
     *
     * @param int|null $merchantId 商家ID
     * @param int $days 未活跃天数
     * @param int $limit 返回数量
     * @return array
     */
    public function getChurnRiskUsers(?int $merchantId, int $days = 30, int $limit = 100): array
    {
        Log::info('获取流失风险用户', [
            'merchant_id' => $merchantId,
            'days' => $days
        ]);

        try {
            $checkDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            // 查询长时间未活跃的用户
            $query = User::where('status', User::STATUS_NORMAL)
                ->where('last_login_time', '<', $checkDate)
                ->orWhere('last_login_time', 'null');

            $users = $query->limit($limit)->select()->toArray();

            // 补充详细信息
            $riskUsers = [];
            foreach ($users as $user) {
                // 获取最后活跃时间
                $lastTrigger = DeviceTrigger::where('user_id', $user['id'])
                    ->where('success', 1)
                    ->order('create_time', 'desc')
                    ->find();

                $lastActiveTime = $lastTrigger ? $lastTrigger->create_time : $user['create_time'];
                $inactiveDays = floor((time() - strtotime($lastActiveTime)) / 86400);

                $riskUsers[] = [
                    'user_id' => $user['id'],
                    'nickname' => $user['nickname'],
                    'phone' => $user['phone'],
                    'member_level' => $user['member_level'],
                    'last_active_time' => $lastActiveTime,
                    'inactive_days' => $inactiveDays,
                    'risk_level' => $inactiveDays > 60 ? 'high' : ($inactiveDays > 30 ? 'medium' : 'low')
                ];
            }

            // 按未活跃天数排序
            usort($riskUsers, function($a, $b) {
                return $b['inactive_days'] - $a['inactive_days'];
            });

            return [
                'total_count' => count($riskUsers),
                'users' => $riskUsers
            ];

        } catch (Exception $e) {
            Log::error('获取流失风险用户失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 生成营销建议
     *
     * @param int $merchantId 商家ID
     * @param array $analysisData 分析数据
     * @return array 建议列表
     */
    public function generateMarketingSuggestions(int $merchantId, array $analysisData = []): array
    {
        Log::info('生成营销建议', ['merchant_id' => $merchantId]);

        $suggestions = [];

        try {
            // 如果没有提供分析数据，则获取基础数据
            if (empty($analysisData)) {
                $today = date('Y-m-d');
                $weekAgo = date('Y-m-d', strtotime('-7 days'));

                $analysisData = [
                    'active_hours' => $this->analyzeActiveHours($merchantId, $weekAgo, $today),
                    'hot_scenes' => $this->analyzeHotScenes($merchantId, $weekAgo, $today),
                    'funnel' => $this->analyzeConversionFunnel($merchantId, $weekAgo, $today)
                ];
            }

            // 基于活跃时段的建议
            if (isset($analysisData['active_hours'])) {
                $peakHours = $analysisData['active_hours']['peak_hours'] ?? [];
                if (!empty($peakHours)) {
                    $suggestions[] = [
                        'type' => 'timing',
                        'priority' => 'high',
                        'title' => '优化推送时间',
                        'content' => '用户活跃高峰时段为：' . implode(', ', $peakHours) . '，建议在这些时段发送营销信息以获得更好的效果。',
                        'action' => '调整推送时间策略'
                    ];
                }
            }

            // 基于热门场景的建议
            if (isset($analysisData['hot_scenes'])) {
                $topScene = $analysisData['hot_scenes']['scenes'][0] ?? null;
                if ($topScene) {
                    $suggestions[] = [
                        'type' => 'content',
                        'priority' => 'medium',
                        'title' => '强化热门场景',
                        'content' => "'{$topScene['trigger_mode']}' 是最受欢迎的触发场景，占比 {$topScene['percentage']}%，建议增加此类场景的内容和设备部署。",
                        'action' => '扩大热门场景覆盖'
                    ];
                }
            }

            // 基于转化漏斗的建议
            if (isset($analysisData['funnel'])) {
                $funnel = $analysisData['funnel']['funnel'] ?? [];
                foreach ($funnel as $step) {
                    if ($step['drop_rate'] > 30) {
                        $suggestions[] = [
                            'type' => 'conversion',
                            'priority' => 'high',
                            'title' => "优化 '{$step['name']}' 环节",
                            'content' => "在 '{$step['name']}' 环节流失率高达 {$step['drop_rate']}%，建议分析原因并优化用户体验。",
                            'action' => '改进转化流程'
                        ];
                    }
                }
            }

            // 获取流失风险用户
            $churnRiskUsers = $this->getChurnRiskUsers($merchantId, 30, 10);
            if ($churnRiskUsers['total_count'] > 0) {
                $suggestions[] = [
                    'type' => 'retention',
                    'priority' => 'high',
                    'title' => '挽回流失用户',
                    'content' => "检测到 {$churnRiskUsers['total_count']} 名用户有流失风险，建议发送唤回活动或优惠券进行召回。",
                    'action' => '启动用户召回计划'
                ];
            }

            // 按优先级排序
            usort($suggestions, function($a, $b) {
                $priorityMap = ['high' => 3, 'medium' => 2, 'low' => 1];
                return $priorityMap[$b['priority']] - $priorityMap[$a['priority']];
            });

            return [
                'merchant_id' => $merchantId,
                'suggestions' => $suggestions,
                'total_count' => count($suggestions),
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            Log::error('生成营销建议失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 生成个性化推荐
     *
     * @param int $userId 用户ID
     * @return array 推荐内容
     */
    public function generatePersonalizedRecommendations(int $userId): array
    {
        Log::info('生成个性化推荐', ['user_id' => $userId]);

        try {
            $user = User::find($userId);
            if (!$user) {
                throw new Exception('用户不存在');
            }

            $recommendations = [];

            // 获取用户内容偏好
            $contentPref = $this->getContentPreference($userId);

            // 推荐相似类型的内容模板
            if (isset($contentPref['most_used_type'])) {
                $templates = ContentTemplate::where('type', $contentPref['most_used_type'])
                    ->where('status', 1)
                    ->order('usage_count', 'desc')
                    ->limit(5)
                    ->select()
                    ->toArray();

                if (!empty($templates)) {
                    $recommendations[] = [
                        'type' => 'template',
                        'title' => '为您推荐的内容模板',
                        'items' => $templates,
                        'reason' => "基于您喜欢的 '{$contentPref['most_used_type']}' 内容类型"
                    ];
                }
            }

            // 推荐活跃时段
            $timePattern = $this->getTimePattern($userId);
            if (isset($timePattern['peak_hours']) && !empty($timePattern['peak_hours'])) {
                $recommendations[] = [
                    'type' => 'timing',
                    'title' => '最佳使用时段',
                    'content' => '根据您的使用习惯，' . implode(', ', $timePattern['peak_hours']) . ' 是您最活跃的时段。',
                    'reason' => '基于您的历史活跃时间'
                ];
            }

            // 推荐会员升级（如果不是高级会员）
            if (!$user->isPremium()) {
                $recommendations[] = [
                    'type' => 'membership',
                    'title' => '升级会员享受更多特权',
                    'content' => '升级到高级会员可享受更多模板、更快生成速度和专属客服。',
                    'reason' => '提升您的使用体验'
                ];
            }

            return [
                'user_id' => $userId,
                'recommendations' => $recommendations,
                'total_count' => count($recommendations),
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            Log::error('生成个性化推荐失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 检测异常数据
     *
     * @param int|null $merchantId 商家ID
     * @param string $date 检测日期
     * @return array 异常数据列表
     */
    public function detectAnomalies(?int $merchantId, string $date): array
    {
        Log::info('检测异常数据', [
            'merchant_id' => $merchantId,
            'date' => $date
        ]);

        $anomalies = [];

        try {
            // 获取过去7天的平均值作为基准
            $weekAgo = date('Y-m-d', strtotime($date . ' -7 days'));
            $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));

            // 检测触发量异常
            $todayTriggers = DeviceTrigger::where('create_time', '>=', $date . ' 00:00:00')
                ->where('create_time', '<=', $date . ' 23:59:59')
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->count();

            $avgTriggers = DeviceTrigger::where('create_time', '>=', $weekAgo . ' 00:00:00')
                ->where('create_time', '<=', $yesterday . ' 23:59:59')
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->count() / 7;

            // 如果今日触发量低于平均值的50%，视为异常
            if ($avgTriggers > 0 && $todayTriggers < $avgTriggers * 0.5) {
                $anomalies[] = [
                    'type' => 'low_triggers',
                    'severity' => 'warning',
                    'metric' => 'NFC触发量',
                    'current_value' => $todayTriggers,
                    'expected_value' => round($avgTriggers),
                    'deviation' => round(($todayTriggers - $avgTriggers) / $avgTriggers * 100, 2),
                    'message' => '今日NFC触发量显著低于平均水平'
                ];
            }

            // 检测失败率异常
            $todayFailures = DeviceTrigger::where('create_time', '>=', $date . ' 00:00:00')
                ->where('create_time', '<=', $date . ' 23:59:59')
                ->where('success', 0)
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->count();

            if ($todayTriggers > 0) {
                $failureRate = $todayFailures / $todayTriggers * 100;
                if ($failureRate > 20) {
                    $anomalies[] = [
                        'type' => 'high_failure_rate',
                        'severity' => 'error',
                        'metric' => '触发失败率',
                        'current_value' => round($failureRate, 2),
                        'threshold' => 20,
                        'message' => '触发失败率过高，可能存在系统问题'
                    ];
                }
            }

            // 检测设备离线异常
            if ($merchantId !== null) {
                $totalDevices = NfcDevice::where('merchant_id', $merchantId)->count();
                $offlineDevices = NfcDevice::where('merchant_id', $merchantId)
                    ->where('status', '!=', 'ONLINE')
                    ->count();

                if ($totalDevices > 0) {
                    $offlineRate = $offlineDevices / $totalDevices * 100;
                    if ($offlineRate > 30) {
                        $anomalies[] = [
                            'type' => 'device_offline',
                            'severity' => 'warning',
                            'metric' => '设备离线率',
                            'current_value' => round($offlineRate, 2),
                            'offline_count' => $offlineDevices,
                            'total_count' => $totalDevices,
                            'message' => '设备离线率较高，请检查设备状态'
                        ];
                    }
                }
            }

            return [
                'date' => $date,
                'merchant_id' => $merchantId,
                'anomalies' => $anomalies,
                'total_count' => count($anomalies),
                'detected_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            Log::error('检测异常数据失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 分析异常原因
     *
     * @param array $anomaly 异常数据
     * @return array 可能原因
     */
    public function analyzeAnomalyCause(array $anomaly): array
    {
        $possibleCauses = [];

        switch ($anomaly['type']) {
            case 'low_triggers':
                $possibleCauses = [
                    '设备离线或故障',
                    '用户活跃度降低',
                    '节假日或特殊时期',
                    '竞争对手促销活动',
                    '设备位置调整',
                ];
                break;

            case 'high_failure_rate':
                $possibleCauses = [
                    '网络连接问题',
                    '服务器响应超时',
                    'API接口异常',
                    '设备硬件故障',
                    '配置错误',
                ];
                break;

            case 'device_offline':
                $possibleCauses = [
                    '设备电量不足',
                    '网络信号差',
                    '设备被移动或损坏',
                    '系统更新或维护',
                    '环境因素影响',
                ];
                break;

            default:
                $possibleCauses = [
                    '数据采集异常',
                    '统计口径变化',
                    '系统升级影响',
                ];
        }

        return [
            'anomaly_type' => $anomaly['type'],
            'possible_causes' => $possibleCauses,
            'recommended_actions' => $this->getRecommendedActions($anomaly['type'])
        ];
    }

    /**
     * 获取实时数据概览
     *
     * @param int|null $merchantId 商家ID
     * @return array 实时数据
     */
    public function getRealTimeOverview(?int $merchantId): array
    {
        $cacheKey = $this->getCacheKey('realtime_overview', $merchantId);

        // 短时间缓存
        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        Log::info('获取实时数据概览', ['merchant_id' => $merchantId]);

        try {
            $now = date('Y-m-d H:i:s');
            $today = date('Y-m-d');

            // 今日统计
            $todayTriggers = DeviceTrigger::where('create_time', '>=', $today . ' 00:00:00')
                ->where('create_time', '<=', $now)
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->count();

            $todaySuccess = DeviceTrigger::where('create_time', '>=', $today . ' 00:00:00')
                ->where('create_time', '<=', $now)
                ->where('success', 1)
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->count();

            $todayUsers = DeviceTrigger::where('create_time', '>=', $today . ' 00:00:00')
                ->where('create_time', '<=', $now)
                ->where('success', 1)
                ->when($merchantId, function($query, $merchantId) {
                    $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                        $subQuery->table('nfc_devices')
                            ->where('merchant_id', $merchantId)
                            ->field('id');
                    });
                })
                ->distinct()
                ->count('user_id');

            $todayContent = ContentTask::where('create_time', '>=', $today . ' 00:00:00')
                ->where('create_time', '<=', $now)
                ->when($merchantId, function($query, $merchantId) {
                    $query->where('merchant_id', $merchantId);
                })
                ->count();

            $overview = [
                'today_triggers' => $todayTriggers,
                'today_success' => $todaySuccess,
                'today_users' => $todayUsers,
                'today_content' => $todayContent,
                'success_rate' => $todayTriggers > 0 ? round($todaySuccess / $todayTriggers * 100, 2) : 0,
                'timestamp' => $now
            ];

            // 缓存1分钟
            Cache::set($cacheKey, $overview, self::CACHE_TTL_SHORT);

            return $overview;

        } catch (Exception $e) {
            Log::error('获取实时数据概览失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取实时活跃用户
     *
     * @param int|null $merchantId 商家ID
     * @param int $minutes 时间范围（分钟）
     * @return array
     */
    public function getRealTimeActiveUsers(?int $merchantId, int $minutes = 30): array
    {
        Log::info('获取实时活跃用户', [
            'merchant_id' => $merchantId,
            'minutes' => $minutes
        ]);

        try {
            $timeThreshold = date('Y-m-d H:i:s', time() - $minutes * 60);

            $query = DeviceTrigger::where('create_time', '>=', $timeThreshold)
                ->where('success', 1);

            if ($merchantId !== null) {
                $query->whereIn('device_id', function($subQuery) use ($merchantId) {
                    $subQuery->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }

            $activeUserIds = $query->distinct()->column('user_id');

            // 获取用户详情
            $users = [];
            if (!empty($activeUserIds)) {
                $users = User::whereIn('id', $activeUserIds)
                    ->field('id, nickname, avatar, member_level')
                    ->select()
                    ->toArray();
            }

            return [
                'time_range_minutes' => $minutes,
                'active_user_count' => count($users),
                'users' => $users,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            Log::error('获取实时活跃用户失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    // ========== 私有辅助方法 ==========

    /**
     * 获取用户基本信息
     */
    protected function getBasicInfo(User $user): array
    {
        return [
            'user_id' => $user->id,
            'nickname' => $user->nickname,
            'gender' => $user->gender,
            'gender_text' => $user->gender_text,
            'member_level' => $user->member_level,
            'member_level_text' => $user->member_level_text,
            'points' => $user->points,
            'register_time' => $user->create_time,
            'last_login_time' => $user->last_login_time
        ];
    }

    /**
     * 获取活跃度
     */
    protected function getActivityLevel(int $userId): array
    {
        // 总触发次数
        $totalTriggers = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->count();

        // 活跃天数
        $activeDays = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->field('DATE(create_time) as date')
            ->group('date')
            ->count();

        // 最近30天活跃天数
        $recent30Days = DeviceTrigger::where('user_id', $userId)
            ->where('create_time', '>=', date('Y-m-d', strtotime('-30 days')))
            ->where('success', 1)
            ->field('DATE(create_time) as date')
            ->group('date')
            ->count();

        // 活跃等级
        $activityLevel = 'inactive';
        if ($recent30Days >= 20) {
            $activityLevel = 'very_active';
        } elseif ($recent30Days >= 10) {
            $activityLevel = 'active';
        } elseif ($recent30Days >= 5) {
            $activityLevel = 'moderate';
        } elseif ($recent30Days > 0) {
            $activityLevel = 'occasional';
        }

        return [
            'total_triggers' => $totalTriggers,
            'active_days' => $activeDays,
            'recent_30_days' => $recent30Days,
            'activity_level' => $activityLevel
        ];
    }

    /**
     * 获取消费行为
     */
    protected function getConsumptionBehavior(int $userId): array
    {
        // 优惠券领取数
        $couponReceived = CouponUser::where('user_id', $userId)->count();

        // 优惠券使用数
        $couponUsed = CouponUser::where('user_id', $userId)
            ->where('status', 'USED')
            ->count();

        // 内容生成次数
        $contentGenerated = ContentTask::where('user_id', $userId)
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->count();

        return [
            'coupon_received' => $couponReceived,
            'coupon_used' => $couponUsed,
            'coupon_use_rate' => $couponReceived > 0 ? round($couponUsed / $couponReceived * 100, 2) : 0,
            'content_generated' => $contentGenerated
        ];
    }

    /**
     * 获取内容偏好
     */
    protected function getContentPreference(int $userId): array
    {
        $contentTasks = ContentTask::where('user_id', $userId)
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->field('type, COUNT(*) as count')
            ->group('type')
            ->order('count', 'desc')
            ->select()
            ->toArray();

        $mostUsedType = $contentTasks[0]['type'] ?? null;
        $totalGenerated = array_sum(array_column($contentTasks, 'count'));

        return [
            'content_types' => $contentTasks,
            'most_used_type' => $mostUsedType,
            'total_generated' => $totalGenerated
        ];
    }

    /**
     * 获取设备使用情况
     */
    protected function getDeviceUsage(int $userId): array
    {
        $deviceStats = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->field('device_code, COUNT(*) as count')
            ->group('device_code')
            ->order('count', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        $mostUsedDevice = $deviceStats[0] ?? null;
        $totalDevices = count($deviceStats);

        return [
            'device_stats' => $deviceStats,
            'most_used_device' => $mostUsedDevice ? $mostUsedDevice['device_code'] : null,
            'total_devices_used' => $totalDevices
        ];
    }

    /**
     * 获取时间模式
     */
    protected function getTimePattern(int $userId): array
    {
        $hourlyStats = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->field('HOUR(create_time) as hour, COUNT(*) as count')
            ->group('hour')
            ->order('count', 'desc')
            ->select()
            ->toArray();

        $peakHours = [];
        foreach (array_slice($hourlyStats, 0, 3) as $stat) {
            $peakHours[] = str_pad((string)$stat['hour'], 2, '0', STR_PAD_LEFT) . ':00';
        }

        return [
            'hourly_distribution' => $hourlyStats,
            'peak_hours' => $peakHours
        ];
    }

    /**
     * 获取互动程度
     */
    protected function getEngagement(int $userId): array
    {
        // 分享次数（这里假设，实际需要根据业务实现）
        $shareCount = 0;

        // 平均响应时间
        $avgResponseTime = DeviceTrigger::where('user_id', $userId)
            ->where('success', 1)
            ->avg('response_time');

        return [
            'share_count' => $shareCount,
            'avg_response_time' => round($avgResponseTime ?? 0, 2)
        ];
    }

    /**
     * 计算价值分数
     */
    protected function calculateValueScore(int $userId): array
    {
        $scores = [];

        // 活跃度得分 (0-30分)
        $activityLevel = $this->getActivityLevel($userId);
        $activityScore = min(30, $activityLevel['recent_30_days'] * 1.5);
        $scores['activity_score'] = round($activityScore, 2);

        // 消费行为得分 (0-25分)
        $consumption = $this->getConsumptionBehavior($userId);
        $consumptionScore = min(25, $consumption['content_generated'] * 2 + $consumption['coupon_used'] * 1);
        $scores['consumption_score'] = round($consumptionScore, 2);

        // 会员等级得分 (0-20分)
        $user = User::find($userId);
        $memberScore = 0;
        if ($user) {
            if ($user->isPremium()) {
                $memberScore = 20;
            } elseif ($user->isVip()) {
                $memberScore = 15;
            } else {
                $memberScore = 10;
            }
        }
        $scores['member_score'] = $memberScore;

        // 互动得分 (0-15分)
        $engagement = $this->getEngagement($userId);
        $engagementScore = min(15, $engagement['share_count'] * 3);
        $scores['engagement_score'] = round($engagementScore, 2);

        // 忠诚度得分 (0-10分)
        $registerDays = 0;
        if ($user) {
            $registerDays = floor((time() - strtotime($user->create_time)) / 86400);
        }
        $loyaltyScore = min(10, $registerDays / 10);
        $scores['loyalty_score'] = round($loyaltyScore, 2);

        // 总分
        $totalScore = array_sum($scores);
        $scores['total_score'] = round($totalScore, 2);

        // 价值等级
        if ($totalScore >= 80) {
            $scores['value_level'] = 'high';
        } elseif ($totalScore >= 50) {
            $scores['value_level'] = 'medium';
        } else {
            $scores['value_level'] = 'low';
        }

        return $scores;
    }

    /**
     * 获取推荐行动
     */
    protected function getRecommendedActions(string $anomalyType): array
    {
        $actions = [
            'low_triggers' => [
                '检查设备在线状态',
                '分析用户活跃度变化',
                '启动促销活动提升参与度',
                '优化设备位置布局'
            ],
            'high_failure_rate' => [
                '检查网络连接状态',
                '排查API接口问题',
                '检查设备硬件状态',
                '查看系统日志分析错误原因'
            ],
            'device_offline' => [
                '检查设备电量状态',
                '测试网络连接',
                '现场检查设备状态',
                '联系技术支持'
            ]
        ];

        return $actions[$anomalyType] ?? ['联系技术支持进行排查'];
    }

    /**
     * 获取缓存键
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
     * 清除缓存
     *
     * @param int|null $userId 用户ID
     * @return bool
     */
    public function clearCache(?int $userId = null): bool
    {
        try {
            if ($userId !== null) {
                // 清除指定用户的缓存
                $patterns = ['profile', 'journey'];
                foreach ($patterns as $pattern) {
                    $key = $this->getCacheKey($pattern, $userId);
                    Cache::delete($key);
                }
            } else {
                // 清除所有用户行为分析缓存
                Cache::tag('user_behavior')->clear();
            }

            Log::info('清除用户行为分析缓存', ['user_id' => $userId]);
            return true;

        } catch (Exception $e) {
            Log::error('清除缓存失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
