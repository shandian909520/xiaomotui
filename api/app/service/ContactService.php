<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Merchant;
use app\model\NfcDevice;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Db;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 联系方式服务类
 * 处理好友添加相关的业务逻辑
 */
class ContactService
{
    /**
     * HTTP客户端
     */
    private Client $httpClient;

    /**
     * 企业微信配置
     */
    private array $weworkConfig;

    /**
     * 个人微信配置
     */
    private array $wechatConfig;

    /**
     * 联系方式类型配置
     */
    private array $typesConfig;

    /**
     * 缓存配置
     */
    private array $cacheConfig;

    /**
     * 限流配置
     */
    private array $rateLimitConfig;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);

        // 加载配置
        $config = config('contact');
        $this->weworkConfig = $config['wework'] ?? [];
        $this->wechatConfig = $config['wechat'] ?? [];
        $this->typesConfig = $config['types'] ?? [];
        $this->cacheConfig = $config['cache'] ?? [];
        $this->rateLimitConfig = $config['rate_limit'] ?? [];
    }

    /**
     * 生成添加联系方式的响应数据
     * @param int $merchantId 商家ID
     * @param string $contactType 联系方式类型 (wework/wechat/phone)
     * @param array $options 额外选项
     * @return array
     * @throws \Exception
     */
    public function generateContactData(int $merchantId, string $contactType, array $options = []): array
    {
        // 验证联系方式类型
        if (!in_array($contactType, ['wework', 'wechat', 'phone'])) {
            throw new \Exception('不支持的联系方式类型');
        }

        // 获取商家联系方式配置
        $merchantConfig = $this->getMerchantContactConfig($merchantId);

        // 验证配置是否有效
        if (!$this->validateContactConfig($merchantId, $contactType)) {
            throw new \Exception('商家未配置该联系方式或配置无效');
        }

        $contactConfig = $merchantConfig[$contactType] ?? [];

        // 根据类型生成不同的响应数据
        $result = match($contactType) {
            'wework' => $this->generateWeworkData($contactConfig, $options),
            'wechat' => $this->generateWechatData($contactConfig, $options),
            'phone' => $this->generatePhoneData($contactConfig, $options),
            default => throw new \Exception('不支持的联系方式类型')
        };

        // 记录日志
        Log::info('生成联系方式数据成功', [
            'merchant_id' => $merchantId,
            'contact_type' => $contactType,
            'options' => $options
        ]);

        return [
            'type' => $contactType,
            'type_name' => $this->typesConfig[$contactType]['name'] ?? $contactType,
            'data' => $result,
            'config' => [
                'description' => $contactConfig['description'] ?? '',
                'icon' => $this->typesConfig[$contactType]['icon'] ?? '',
            ]
        ];
    }

    /**
     * 生成企业微信添加链接
     * @param string $weworkUserId 企业微信用户ID
     * @param array $params 额外参数
     * @return string
     * @throws \Exception
     */
    public function generateWeworkContactUrl(string $weworkUserId, array $params = []): string
    {
        if (empty($weworkUserId)) {
            throw new \Exception('企业微信用户ID不能为空');
        }

        // 获取企业微信配置
        $corpId = $this->weworkConfig['corp_id'] ?? '';
        if (empty($corpId)) {
            throw new \Exception('企业微信配置不完整');
        }

        // 构建添加好友链接
        // 企业微信添加好友的URL格式
        $url = "https://work.weixin.qq.com/ca/{$weworkUserId}";

        // 如果有额外参数，添加到URL中
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $url .= '?' . $queryString;
        }

        Log::info('生成企业微信添加链接', [
            'wework_user_id' => $weworkUserId,
            'url' => $url,
            'params' => $params
        ]);

        return $url;
    }

    /**
     * 生成个人微信二维码
     * @param string $wechatId 微信号
     * @return array 包含二维码URL和过期时间
     * @throws \Exception
     */
    public function generateWechatQrcode(string $wechatId): array
    {
        if (empty($wechatId)) {
            throw new \Exception('微信号不能为空');
        }

        // 检查是否使用微信API生成二维码
        if ($this->wechatConfig['use_wechat_api'] ?? false) {
            return $this->generateWechatQrcodeByApi($wechatId);
        }

        // 手动模式：返回预先上传的二维码URL
        $qrcodeUrl = $this->wechatConfig['qrcode_url_prefix'] . md5($wechatId) . '.jpg';

        $result = [
            'qrcode_url' => $qrcodeUrl,
            'wechat_id' => $wechatId,
            'expire_time' => $this->wechatConfig['qrcode_expire'] ?? 0,
            'mode' => 'manual'
        ];

        Log::info('生成个人微信二维码（手动模式）', [
            'wechat_id' => $wechatId,
            'qrcode_url' => $qrcodeUrl
        ]);

        return $result;
    }

    /**
     * 通过微信API生成二维码
     * @param string $wechatId 微信号
     * @return array
     * @throws \Exception
     */
    private function generateWechatQrcodeByApi(string $wechatId): array
    {
        // 这里可以集成微信小程序的二维码生成API
        // 目前先返回占位数据
        throw new \Exception('微信API生成二维码功能暂未实现，请使用手动模式');
    }

    /**
     * 验证联系方式配置
     * @param int $merchantId 商家ID
     * @param string $contactType 联系方式类型
     * @return bool
     */
    public function validateContactConfig(int $merchantId, string $contactType): bool
    {
        try {
            $config = $this->getMerchantContactConfig($merchantId);

            if (!isset($config[$contactType])) {
                return false;
            }

            $contactConfig = $config[$contactType];

            // 检查是否启用
            if (!($contactConfig['enabled'] ?? false)) {
                return false;
            }

            // 根据类型验证必要字段
            $isValid = match($contactType) {
                'wework' => !empty($contactConfig['user_id']),
                'wechat' => !empty($contactConfig['wechat_id']) || !empty($contactConfig['qr_code']),
                'phone' => !empty($contactConfig['phone_number']),
                default => false
            };

            return $isValid;

        } catch (\Exception $e) {
            Log::error('验证联系方式配置失败', [
                'merchant_id' => $merchantId,
                'contact_type' => $contactType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 记录联系添加行为
     * @param int $deviceId 设备ID
     * @param int|null $userId 用户ID
     * @param string $contactType 联系方式类型
     * @param array $extraData 额外数据
     * @return bool
     */
    public function recordContactAction(int $deviceId, ?int $userId, string $contactType, array $extraData = []): bool
    {
        try {
            // 检查是否启用日志记录
            $loggingConfig = config('contact.logging', []);
            if (!($loggingConfig['enabled'] ?? true)) {
                return true;
            }

            // 检查限流
            if (!$this->checkRateLimit($deviceId, $userId)) {
                Log::warning('联系添加行为触发过于频繁', [
                    'device_id' => $deviceId,
                    'user_id' => $userId,
                    'contact_type' => $contactType
                ]);
                throw new \Exception('操作过于频繁，请稍后再试');
            }

            // 获取设备信息
            $device = NfcDevice::find($deviceId);
            if (!$device) {
                throw new \Exception('设备不存在');
            }

            // 构建记录数据
            $logData = [
                'device_id' => $deviceId,
                'merchant_id' => $device->merchant_id,
                'user_id' => $userId,
                'contact_type' => $contactType,
                'trigger_time' => date('Y-m-d H:i:s'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
                'extra_data' => json_encode($extraData),
            ];

            // 记录到数据库
            try {
                Db::name('contact_actions')->insert($logData);
            } catch (\Exception $dbError) {
                // 如果表不存在，记录到日志
                Log::warning('无法写入contact_actions表，可能表不存在', [
                    'error' => $dbError->getMessage()
                ]);
            }

            // 同时记录到日志
            Log::info('联系添加行为记录', $logData);

            // 更新限流缓存
            $this->updateRateLimitCache($deviceId, $userId);

            return true;

        } catch (\Exception $e) {
            Log::error('记录联系添加行为失败', [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'contact_type' => $contactType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取商家联系方式配置
     * @param int $merchantId 商家ID
     * @return array
     * @throws \Exception
     */
    public function getMerchantContactConfig(int $merchantId): array
    {
        // 检查缓存
        if ($this->cacheConfig['enabled'] ?? true) {
            $cacheKey = ($this->cacheConfig['config_prefix'] ?? 'contact_config:') . $merchantId;
            $cached = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        }

        // 从数据库获取商家信息
        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            throw new \Exception('商家不存在');
        }

        // 获取商家的联系方式配置
        // 这里假设商家表有一个contact_config字段存储JSON配置
        // 如果没有，可以从其他字段组合
        $config = $this->buildMerchantContactConfig($merchant);

        // 缓存配置
        if ($this->cacheConfig['enabled'] ?? true) {
            $ttl = $this->cacheConfig['merchant_config_ttl'] ?? 3600;
            Cache::set($cacheKey, $config, $ttl);
        }

        return $config;
    }

    /**
     * 构建商家联系方式配置
     * @param Merchant $merchant 商家模型
     * @return array
     */
    private function buildMerchantContactConfig(Merchant $merchant): array
    {
        // 获取默认配置
        $defaults = config('contact.merchant_defaults', []);

        // 尝试从商家数据中获取配置
        // 如果商家表有contact_config字段（JSON类型）
        $customConfig = [];
        if (property_exists($merchant, 'contact_config') && !empty($merchant->contact_config)) {
            $customConfig = is_string($merchant->contact_config)
                ? json_decode($merchant->contact_config, true)
                : $merchant->contact_config;
        }

        // 合并配置：从商家基本信息中提取联系方式
        $config = [
            'wework' => array_merge($defaults['wework'] ?? [], $customConfig['wework'] ?? []),
            'wechat' => array_merge($defaults['wechat'] ?? [], $customConfig['wechat'] ?? [
                'wechat_id' => $merchant->wechat_id ?? '',
            ]),
            'phone' => array_merge($defaults['phone'] ?? [], $customConfig['phone'] ?? [
                'phone_number' => $merchant->contact_phone ?? '',
            ]),
        ];

        return $config;
    }

    /**
     * 生成企业微信数据
     * @param array $config 配置
     * @param array $options 选项
     * @return array
     */
    private function generateWeworkData(array $config, array $options): array
    {
        $weworkUserId = $config['user_id'] ?? '';
        $qrCode = $config['qr_code'] ?? '';
        $welcomeMessage = $config['welcome_message'] ?? '您好，欢迎添加我们的企业微信！';

        // 生成添加链接
        $contactUrl = '';
        if (!empty($weworkUserId)) {
            try {
                $contactUrl = $this->generateWeworkContactUrl($weworkUserId, $options);
            } catch (\Exception $e) {
                Log::error('生成企业微信链接失败', ['error' => $e->getMessage()]);
            }
        }

        return [
            'wework_user_id' => $weworkUserId,
            'contact_url' => $contactUrl,
            'qr_code' => $qrCode,
            'welcome_message' => $welcomeMessage,
            'auto_reply' => $config['auto_reply'] ?? true,
        ];
    }

    /**
     * 生成个人微信数据
     * @param array $config 配置
     * @param array $options 选项
     * @return array
     */
    private function generateWechatData(array $config, array $options): array
    {
        $wechatId = $config['wechat_id'] ?? '';
        $qrCode = $config['qr_code'] ?? '';
        $nickname = $config['nickname'] ?? '';

        // 如果有微信号但没有二维码，尝试生成
        if (!empty($wechatId) && empty($qrCode)) {
            try {
                $qrcodeData = $this->generateWechatQrcode($wechatId);
                $qrCode = $qrcodeData['qrcode_url'] ?? '';
            } catch (\Exception $e) {
                Log::error('生成个人微信二维码失败', ['error' => $e->getMessage()]);
            }
        }

        return [
            'wechat_id' => $wechatId,
            'qr_code' => $qrCode,
            'nickname' => $nickname,
            'description' => $config['description'] ?? '扫码添加微信好友',
        ];
    }

    /**
     * 生成电话数据
     * @param array $config 配置
     * @param array $options 选项
     * @return array
     */
    private function generatePhoneData(array $config, array $options): array
    {
        return [
            'phone_number' => $config['phone_number'] ?? '',
            'available_time' => $config['available_time'] ?? '9:00-18:00',
            'description' => $config['description'] ?? '工作时间欢迎来电咨询',
        ];
    }

    /**
     * 检查限流
     * @param int $deviceId 设备ID
     * @param int|null $userId 用户ID
     * @return bool
     */
    private function checkRateLimit(int $deviceId, ?int $userId): bool
    {
        if (!($this->rateLimitConfig['enabled'] ?? true)) {
            return true;
        }

        // 检查重复触发间隔
        $interval = $this->rateLimitConfig['duplicate_trigger_interval'] ?? 60;
        $cacheKey = "contact_rate_limit:{$deviceId}:" . ($userId ?? 'guest');

        $lastTrigger = Cache::get($cacheKey);
        if ($lastTrigger !== null && $lastTrigger !== false) {
            $elapsed = time() - (int)$lastTrigger;
            if ($elapsed < $interval) {
                return false;
            }
        }

        return true;
    }

    /**
     * 更新限流缓存
     * @param int $deviceId 设备ID
     * @param int|null $userId 用户ID
     */
    private function updateRateLimitCache(int $deviceId, ?int $userId): void
    {
        $interval = $this->rateLimitConfig['duplicate_trigger_interval'] ?? 60;
        $cacheKey = "contact_rate_limit:{$deviceId}:" . ($userId ?? 'guest');
        Cache::set($cacheKey, time(), $interval + 60);
    }

    /**
     * 获取企业微信Access Token
     * @return string
     * @throws \Exception
     */
    private function getWeworkAccessToken(): string
    {
        $corpId = $this->weworkConfig['corp_id'] ?? '';
        $contactSecret = $this->weworkConfig['contact_secret'] ?? '';

        if (empty($corpId) || empty($contactSecret)) {
            throw new \Exception('企业微信配置不完整');
        }

        // 检查缓存
        $cacheKey = ($this->cacheConfig['wework_token_prefix'] ?? 'wework_access_token:') . $corpId;
        $token = Cache::get($cacheKey);

        if ($token) {
            return $token;
        }

        // 从企业微信API获取
        $url = $this->weworkConfig['api_domain'] . '/cgi-bin/gettoken';

        try {
            $response = $this->httpClient->get($url, [
                'query' => [
                    'corpid' => $corpId,
                    'corpsecret' => $contactSecret,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                throw new \Exception($result['errmsg'] ?? '获取企业微信Access Token失败', $result['errcode']);
            }

            if (!isset($result['access_token'])) {
                throw new \Exception('企业微信返回数据格式错误');
            }

            // 缓存Token
            $expiresIn = ($result['expires_in'] ?? 7200) - 300;
            Cache::set($cacheKey, $result['access_token'], $expiresIn);

            return $result['access_token'];

        } catch (RequestException $e) {
            throw new \Exception('企业微信API调用失败: ' . $e->getMessage());
        }
    }

    /**
     * 清除商家联系方式配置缓存
     * @param int $merchantId 商家ID
     * @return bool
     */
    public function clearMerchantContactCache(int $merchantId): bool
    {
        if (!($this->cacheConfig['enabled'] ?? true)) {
            return true;
        }

        $cacheKey = ($this->cacheConfig['config_prefix'] ?? 'contact_config:') . $merchantId;
        return Cache::delete($cacheKey);
    }

    /**
     * 批量清除联系方式配置缓存
     * @param array $merchantIds 商家ID列表
     * @return int 清除成功的数量
     */
    public function batchClearContactCache(array $merchantIds): int
    {
        $count = 0;
        foreach ($merchantIds as $merchantId) {
            if ($this->clearMerchantContactCache($merchantId)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取联系方式统计信息
     * @param int $merchantId 商家ID
     * @param array $params 查询参数
     * @return array
     */
    public function getContactStats(int $merchantId, array $params = []): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        try {
            // 总触发次数
            $total = Db::name('contact_actions')
                ->where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            // 按类型统计
            $byType = Db::name('contact_actions')
                ->where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('contact_type, count(*) as count')
                ->group('contact_type')
                ->select()
                ->toArray();

            $byTypeMap = [
                'wework' => 0,
                'wechat' => 0,
                'phone' => 0,
            ];
            foreach ($byType as $item) {
                $byTypeMap[$item['contact_type']] = (int)$item['count'];
            }

            // 按设备统计
            $byDevice = Db::name('contact_actions')
                ->alias('ca')
                ->leftJoin('nfc_devices nd', 'ca.device_id = nd.id')
                ->where('ca.merchant_id', $merchantId)
                ->where('ca.trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('ca.device_id, nd.device_name, nd.device_code, count(*) as count')
                ->group('ca.device_id')
                ->order('count', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            // 按日期统计
            $byDate = Db::name('contact_actions')
                ->where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('DATE(trigger_time) as date, count(*) as count')
                ->group('DATE(trigger_time)')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            return [
                'total_contacts' => $total,
                'by_type' => $byTypeMap,
                'by_device' => $byDevice,
                'by_date' => $byDate,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('获取联系方式统计失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            // 返回空数据
            return [
                'total_contacts' => 0,
                'by_type' => [
                    'wework' => 0,
                    'wechat' => 0,
                    'phone' => 0,
                ],
                'by_device' => [],
                'by_date' => [],
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'error' => '统计数据获取失败'
            ];
        }
    }
}