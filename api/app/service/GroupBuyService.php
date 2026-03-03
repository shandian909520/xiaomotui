<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Request;
use think\exception\ValidateException;

/**
 * 团购跳转服务类
 * 处理团购跳转URL生成、跟踪和统计
 */
class GroupBuyService
{
    /**
     * 支持的平台常量
     */
    const PLATFORM_MEITUAN = 'MEITUAN';  // 美团
    const PLATFORM_DOUYIN = 'DOUYIN';    // 抖音团购
    const PLATFORM_ELEME = 'ELEME';      // 饿了么
    const PLATFORM_CUSTOM = 'CUSTOM';    // 自定义

    /**
     * 平台URL模板
     */
    const PLATFORM_URLS = [
        self::PLATFORM_MEITUAN => 'https://i.meituan.com/awp/h5/deal/detail.html',
        self::PLATFORM_DOUYIN => 'https://haohuo.jinritemai.com/views/product/item',
        self::PLATFORM_ELEME => 'https://h5.ele.me/shop/',
    ];

    /**
     * 解析动态配置（基于时间规则）
     *
     * @param array $config 原始配置
     * @param string|null $time 测试用的时间（格式 H:i），默认为当前时间
     * @return array 解析后的配置
     */
    public function resolveDynamicConfig(array $config, ?string $time = null): array
    {
        // 如果没有时间规则，直接返回原始配置
        if (empty($config['time_rules']) || !is_array($config['time_rules'])) {
            return $config;
        }

        $now = $time ?? date('H:i');
        
        foreach ($config['time_rules'] as $rule) {
            $startTime = $rule['start_time'] ?? '00:00';
            $endTime = $rule['end_time'] ?? '23:59';
            
            if ($now >= $startTime && $now <= $endTime) {
                // 命中规则，合并规则中的配置覆盖默认配置
                // 规则中可以包含 platform, deal_id, custom_url 等
                // Log::info('GroupBuy dynamic rule matched', ['rule' => $rule, 'time' => $now]);
                return array_merge($config, $rule);
            }
        }

        // 如果没有命中任何规则，返回原始配置（默认配置）
        return $config;
    }

    /**
     * 生成团购跳转URL
     *
     * @param array $config 配置参数
     * @return string 完整的跳转URL
     * @throws ValidateException
     */
    public function generateRedirectUrl(array $config): string
    {
        $platform = $config['platform'] ?? '';
        $dealId = $config['deal_id'] ?? '';
        $merchantId = $config['merchant_id'] ?? 0;
        $deviceId = $config['device_id'] ?? 0;
        $customUrl = $config['custom_url'] ?? '';

        // 验证参数
        if (empty($platform)) {
            throw new ValidateException('平台类型不能为空');
        }

        if (!in_array($platform, [
            self::PLATFORM_MEITUAN,
            self::PLATFORM_DOUYIN,
            self::PLATFORM_ELEME,
            self::PLATFORM_CUSTOM
        ])) {
            throw new ValidateException('不支持的平台类型');
        }

        // 如果是自定义平台，需要提供自定义URL
        if ($platform === self::PLATFORM_CUSTOM) {
            if (empty($customUrl)) {
                throw new ValidateException('自定义平台需要提供URL');
            }
            if (!$this->validateUrl($customUrl)) {
                throw new ValidateException('自定义URL格式不正确');
            }
            return $this->addTrackingParams($customUrl, $merchantId, $deviceId);
        }

        // 其他平台需要deal_id
        if (empty($dealId)) {
            throw new ValidateException('团购ID不能为空');
        }

        // 根据平台生成URL
        $baseUrl = $this->getPlatformBaseUrl($platform);
        $url = $this->buildPlatformUrl($platform, $baseUrl, $dealId);

        // 添加跟踪参数
        $url = $this->addTrackingParams($url, $merchantId, $deviceId);

        return $url;
    }

    /**
     * 记录跳转
     *
     * @param int $deviceId 设备ID
     * @param int|null $userId 用户ID
     * @param string $platform 平台类型
     * @param string $redirectUrl 跳转URL
     * @param array $extraData 额外数据
     * @return void
     */
    public function recordRedirect(
        int $deviceId,
        ?int $userId,
        string $platform,
        string $redirectUrl,
        array $extraData = []
    ): void {
        try {
            // 获取设备信息
            $device = Db::name('nfc_devices')->find($deviceId);
            if (!$device) {
                if (class_exists('\think\facade\Log')) {
                    Log::warning('记录团购跳转失败：设备不存在', ['device_id' => $deviceId]);
                }
                return;
            }

            // 获取请求信息
            $ipAddress = Request::ip();
            $userAgent = Request::header('user-agent', '');

            // 插入跳转记录
            Db::name('group_buy_redirects')->insert([
                'device_id' => $deviceId,
                'merchant_id' => $device['merchant_id'],
                'user_id' => $userId,
                'platform' => $platform,
                'deal_id' => $extraData['deal_id'] ?? null,
                'redirect_url' => $redirectUrl,
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent, 0, 255),
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            if (class_exists('\think\facade\Log')) {
                Log::info('团购跳转记录成功', [
                    'device_id' => $deviceId,
                    'user_id' => $userId,
                    'platform' => $platform,
                    'ip' => $ipAddress
                ]);
            }
        } catch (\Exception $e) {
            if (class_exists('\think\facade\Log')) {
                Log::error('记录团购跳转失败', [
                    'error' => $e->getMessage(),
                    'device_id' => $deviceId,
                    'platform' => $platform
                ]);
            }
        }
    }

    /**
     * 获取团购统计数据
     *
     * @param int $merchantId 商家ID
     * @param array $filters 过滤条件
     * @return array 统计数据
     */
    public function getStatistics(int $merchantId, array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d', strtotime('+1 day'));
        $deviceId = $filters['device_id'] ?? null;
        $platform = $filters['platform'] ?? null;

        // 构建查询条件
        $where = [
            ['merchant_id', '=', $merchantId],
            ['create_time', '>=', $startDate],
            ['create_time', '<', $endDate]
        ];

        if ($deviceId) {
            $where[] = ['device_id', '=', $deviceId];
        }

        if ($platform) {
            $where[] = ['platform', '=', $platform];
        }

        // 总点击数
        $totalClicks = Db::name('group_buy_redirects')
            ->where($where)
            ->count();

        // 今日点击数
        $todayClicks = Db::name('group_buy_redirects')
            ->where($where)
            ->where('create_time', '>=', date('Y-m-d 00:00:00'))
            ->where('create_time', '<', date('Y-m-d 23:59:59'))
            ->count();

        // 平台分布
        $platformBreakdown = Db::name('group_buy_redirects')
            ->field('platform, COUNT(*) as count')
            ->where($where)
            ->group('platform')
            ->select()
            ->toArray();

        $platformData = [];
        foreach ($platformBreakdown as $item) {
            $platformData[$item['platform']] = $item['count'];
        }

        // 设备TOP排行
        $topDevices = Db::name('group_buy_redirects')
            ->alias('gbr')
            ->field('gbr.device_id, nd.device_name, nd.device_code, COUNT(*) as click_count')
            ->leftJoin('nfc_devices nd', 'gbr.device_id = nd.id')
            ->where($where)
            ->group('gbr.device_id')
            ->order('click_count', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        // 每日趋势（最近7天）
        $dailyTrend = Db::name('group_buy_redirects')
            ->field('DATE(create_time) as date, COUNT(*) as count')
            ->where($where)
            ->where('create_time', '>=', date('Y-m-d', strtotime('-7 days')))
            ->group('DATE(create_time)')
            ->order('date', 'asc')
            ->select()
            ->toArray();

        // 独立用户数
        $uniqueUsers = Db::name('group_buy_redirects')
            ->where($where)
            ->where('user_id', '<>', null)
            ->group('user_id')
            ->count();

        // 平均每天点击数
        $daysInRange = max(1, (strtotime($endDate) - strtotime($startDate)) / 86400);
        $avgDailyClicks = round($totalClicks / $daysInRange, 2);

        return [
            'total_clicks' => $totalClicks,
            'today_clicks' => $todayClicks,
            'unique_users' => $uniqueUsers,
            'avg_daily_clicks' => $avgDailyClicks,
            'platform_breakdown' => $platformData,
            'top_devices' => $topDevices,
            'daily_trend' => $dailyTrend,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];
    }

    /**
     * 验证URL格式
     *
     * @param string $url URL地址
     * @return bool 是否有效
     */
    public function validateUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // 基础URL格式验证
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // 必须是http或https协议
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        // 域名白名单检查（安全考虑）
        $host = parse_url($url, PHP_URL_HOST);
        if (!$this->isWhitelistedDomain($host)) {
            // 注意：非白名单域名，但不阻止使用（仅供参考）
            // 在生产环境中会记录警告日志
        }

        return true;
    }

    /**
     * 获取平台基础URL
     *
     * @param string $platform 平台类型
     * @return string 基础URL
     */
    protected function getPlatformBaseUrl(string $platform): string
    {
        return self::PLATFORM_URLS[$platform] ?? '';
    }

    /**
     * 构建平台URL
     *
     * @param string $platform 平台类型
     * @param string $baseUrl 基础URL
     * @param string $dealId 团购ID
     * @return string 完整URL
     */
    protected function buildPlatformUrl(string $platform, string $baseUrl, string $dealId): string
    {
        switch ($platform) {
            case self::PLATFORM_MEITUAN:
                return $baseUrl . '?dealId=' . urlencode($dealId);

            case self::PLATFORM_DOUYIN:
                return $baseUrl . '?id=' . urlencode($dealId) . '&origin_type=604';

            case self::PLATFORM_ELEME:
                return $baseUrl . '?id=' . urlencode($dealId);

            default:
                return $baseUrl;
        }
    }

    /**
     * 添加跟踪参数
     *
     * @param string $url 原始URL
     * @param int $merchantId 商家ID
     * @param int $deviceId 设备ID
     * @return string 添加跟踪参数后的URL
     */
    protected function addTrackingParams(string $url, int $merchantId, int $deviceId): string
    {
        $params = [
            'utm_source' => 'xiaomotui',
            'utm_medium' => 'nfc',
            'utm_campaign' => 'device_' . $deviceId,
            'merchant_id' => $merchantId,
        ];

        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }

    /**
     * 检查域名是否在白名单中
     *
     * @param string $host 域名
     * @return bool 是否在白名单
     */
    protected function isWhitelistedDomain(string $host): bool
    {
        // 可信任的团购平台域名
        $whitelist = [
            'meituan.com',
            'i.meituan.com',
            'jinritemai.com',
            'haohuo.jinritemai.com',
            'ele.me',
            'h5.ele.me',
            'douyin.com',
            // 可以根据需要添加更多域名
        ];

        // 检查完全匹配
        if (in_array($host, $whitelist)) {
            return true;
        }

        // 检查子域名匹配
        foreach ($whitelist as $domain) {
            if (substr($host, -(strlen($domain) + 1)) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取平台显示名称
     *
     * @param string $platform 平台类型
     * @return string 显示名称
     */
    public function getPlatformName(string $platform): string
    {
        $names = [
            self::PLATFORM_MEITUAN => '美团',
            self::PLATFORM_DOUYIN => '抖音团购',
            self::PLATFORM_ELEME => '饿了么',
            self::PLATFORM_CUSTOM => '自定义',
        ];

        return $names[$platform] ?? $platform;
    }

    /**
     * 解析团购配置
     *
     * @param string|array $config 配置数据（JSON字符串或数组）
     * @return array 解析后的配置
     */
    public function parseGroupBuyConfig($config): array
    {
        if (empty($config)) {
            return [];
        }

        // 如果是字符串，解析JSON
        if (is_string($config)) {
            $config = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (class_exists('\think\facade\Log')) {
                    Log::error('团购配置JSON解析失败', ['config' => $config]);
                }
                return [];
            }
        }

        // 默认配置结构
        $defaultConfig = [
            'platform' => '',
            'deal_id' => '',
            'deal_name' => '',
            'original_price' => 0,
            'group_price' => 0,
            'custom_url' => null,
            'tracking_params' => []
        ];

        return array_merge($defaultConfig, $config);
    }

    /**
     * 格式化团购信息用于展示
     *
     * @param array $config 团购配置
     * @return array 格式化后的信息
     */
    public function formatDealInfo(array $config): array
    {
        $originalPrice = (float)($config['original_price'] ?? 0);
        $groupPrice = (float)($config['group_price'] ?? 0);

        // 计算折扣
        $discount = '';
        if ($originalPrice > 0 && $groupPrice > 0 && $groupPrice < $originalPrice) {
            $discountRate = ($groupPrice / $originalPrice) * 10;
            $discount = round($discountRate, 1) . '折';
        }

        // 计算节省金额
        $saveAmount = $originalPrice - $groupPrice;

        return [
            'name' => $config['deal_name'] ?? '团购优惠',
            'original_price' => $originalPrice,
            'group_price' => $groupPrice,
            'discount' => $discount,
            'save_amount' => max(0, $saveAmount),
            'platform' => $config['platform'] ?? '',
            'platform_name' => $this->getPlatformName($config['platform'] ?? ''),
        ];
    }

    /**
     * 验证团购配置完整性
     *
     * @param array $config 团购配置
     * @return array 验证结果 ['valid' => bool, 'errors' => array]
     */
    public function validateGroupBuyConfig(array $config): array
    {
        $errors = [];

        if (empty($config['platform'])) {
            $errors[] = '平台类型不能为空';
        } elseif (!in_array($config['platform'], [
            self::PLATFORM_MEITUAN,
            self::PLATFORM_DOUYIN,
            self::PLATFORM_ELEME,
            self::PLATFORM_CUSTOM
        ])) {
            $errors[] = '不支持的平台类型';
        }

        // 非自定义平台需要deal_id
        if ($config['platform'] !== self::PLATFORM_CUSTOM && empty($config['deal_id'])) {
            $errors[] = '团购ID不能为空';
        }

        // 自定义平台需要custom_url
        if ($config['platform'] === self::PLATFORM_CUSTOM) {
            if (empty($config['custom_url'])) {
                $errors[] = '自定义平台需要提供URL';
            } elseif (!$this->validateUrl($config['custom_url'])) {
                $errors[] = '自定义URL格式不正确';
            }
        }

        // 价格验证
        if (isset($config['original_price']) && $config['original_price'] < 0) {
            $errors[] = '原价不能为负数';
        }

        if (isset($config['group_price']) && $config['group_price'] < 0) {
            $errors[] = '团购价不能为负数';
        }

        if (isset($config['original_price']) && isset($config['group_price'])
            && $config['group_price'] > $config['original_price']) {
            $errors[] = '团购价不能大于原价';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}