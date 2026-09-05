<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\SceneConfig;
use app\model\Merchant;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 聚合页服务
 * 模块 1: 顾客端 H5 聚合页一次性返回:
 *   - Wi-Fi 区块(SSID 脱敏 + 加密 config)
 *   - 发布任务(默认文案 / video)
 *   - 团购商品列表
 *   - 点评入口
 *   - 私域(企微/微信/QQ/电话)
 *   - 大转盘活动
 *
 *   数据来源: SceneConfig(JSON 字段) + 各 Service
 *   不做"一键代发"等违反平台规范的动作
 */
class AggregationPageService
{
    /**
     * 单次获取设备聚合页全部区块数据
     *
     * @param string $deviceCode 设备编码
     * @return array
     * @throws ValidateException
     */
    public function getByDeviceCode(string $deviceCode): array
    {
        $device = NfcDevice::findByCode($deviceCode);
        if (!$device) {
            throw new ValidateException('设备不存在: ' . $deviceCode);
        }

        // 获取门店场景配置(JSON 列族)
        $sceneConfig = $this->getSceneConfig($device);

        // 区块组装顺序遵循"连Wi-Fi → 看活动 → 发视频/写点评 → 加私域"的顾客旅程
        return [
            'device' => $this->formatDevice($device),
            'scene_config_summary' => $sceneConfig ? $sceneConfig->getConfigSummary() : [],
            'blocks' => [
                'wifi'       => $this->buildWifiBlock($device),
                'publish'    => $this->buildPublishBlock($device, $sceneConfig),
                'groupbuy'   => $this->buildGroupBuyBlock($device),
                'review'     => $this->buildReviewBlock($device, $sceneConfig),
                'contact'    => $this->buildContactBlock($device, $sceneConfig),
                'lottery'    => $this->buildLotteryBlock($device),
            ],
            'highlight'    => $this->extractHighlight($sceneConfig),
            'meta'         => [
                'funnel_session_id' => uniqid('fs_', true), // Agent E:前端埋点 session 关联
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 查找门店场景配置: 优先按 store_id,否则取商家首条
     */
    public function getSceneConfig(NfcDevice $device): ?SceneConfig
    {
        try {
            if (!empty($device->table_id)) {
                $cfg = SceneConfig::where('store_id', (int)$device->table_id)->find();
                if ($cfg) {
                    return $cfg;
                }
            }
            // 兜底:取商家任意一条
            return SceneConfig::where('merchant_id', $device->merchant_id)
                ->order('id', 'asc')
                ->find();
        } catch (\Throwable $e) {
            Log::warning('获取场景配置失败', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Wi-Fi 区块: SSID 脱敏 + 加密 token(脱敏:Li***)
     * 复用 Nfc.php::handleWifiMode 的安全模式,前端只展示加密后的 token
     */
    public function buildWifiBlock(NfcDevice $device): array
    {
        if (empty($device->wifi_ssid)) {
            return ['enabled' => false];
        }
        $ssid   = (string)$device->wifi_ssid;
        $masked = $this->maskSsid($ssid);

        // 复用加密机制(避免明文密码泄露)
        $wifiConfig = [
            'ssid'       => $ssid,
            'password'   => $device->getDecryptedWifiPassword(),
            'security'   => 'WPA2',
            'expires_at' => time() + 300,
        ];
        $encrypted = '';
        try {
            $encrypted = base64_encode(encrypt(json_encode($wifiConfig)));
        } catch (\Throwable $e) {
            Log::warning('Wi-Fi config 加密失败', ['device_id' => $device->id]);
        }

        return [
            'enabled'         => true,
            'ssid_masked'     => $masked,
            'ssid_full_token' => $encrypted,
            'expires_at'      => $wifiConfig['expires_at'],
            'connect_methods' => ['ios_mobileconfig', 'android_qr_uri', 'wechat'],
            'tips' => [
                '为保证安全,密码不直接展示',
                '点击"一键连接"按机型自动选择方案',
            ],
        ];
    }

    /**
     * 发布任务区块: 默认 + 候选文案(给"换一批")
     */
    public function buildPublishBlock(NfcDevice $device, ?SceneConfig $sceneConfig): array
    {
        $enabled = false;
        $default = (string)($device->promo_copywriting ?: '');
        if (!empty($default)) {
            $enabled = true;
        } elseif ($sceneConfig && !empty($sceneConfig->platform_config['enabled'])) {
            $enabled = true;
        }

        // 默认候选(预生成 3 条),由前端首屏文案显示
        $candidates = $this->buildDefaultPublishCandidates($device, $sceneConfig);

        return [
            'enabled'       => $enabled,
            'default'       => $default,
            'candidates'    => $candidates,
            'tags'          => $device->promo_tags ?: [],
            'platforms'     => ['douyin', 'kuaishou'],
            'reward'        => $this->formatPromoReward($device),
            'has_pool'      => false, // 由前端按需调换一批接口
        ];
    }

    /**
     * 团购区块: 复用 GroupBuyService::getItemsByDevice
     */
    public function buildGroupBuyBlock(NfcDevice $device): array
    {
        try {
            $groupBuyService = new GroupBuyService();
            $items = method_exists($groupBuyService, 'getItemsByDevice')
                ? $groupBuyService->getItemsByDevice((int)$device->id)
                : [];
            return [
                'enabled' => !empty($items),
                'list'    => $items,
            ];
        } catch (\Throwable $e) {
            Log::warning('团购区块加载失败', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            return ['enabled' => false, 'list' => []];
        }
    }

    /**
     * 点评区块: 平台入口列表 + 评价灵感草稿计数
     */
    public function buildReviewBlock(NfcDevice $device, ?SceneConfig $sceneConfig): array
    {
        $platforms = [];
        $config = is_array($sceneConfig->review_config ?? null) ? $sceneConfig->review_config : [];
        $enabled = !empty($config['enabled']);
        $list = $config['platforms'] ?? [];
        if (is_array($list)) {
            foreach ($list as $item) {
                if (!is_array($item)) continue;
                $platforms[] = [
                    'key'      => $item['key'] ?? '',
                    'name'     => $item['name'] ?? ($item['key'] ?? ''),
                    'jump_url' => $item['jump_url'] ?? '',
                    'icon'     => $item['icon'] ?? '',
                ];
            }
        }

        return [
            'enabled' => $enabled,
            'platforms' => $platforms,
            'insight_supported' => true, // 提示前端可调 getReviewDraft
        ];
    }

    /**
     * 私域区块: 企微/微信/QQ/电话
     */
    public function buildContactBlock(NfcDevice $device, ?SceneConfig $sceneConfig): array
    {
        $items = [];
        try {
            $contactService = new ContactService();
            $merchantId = (int)$device->merchant_id;
            foreach (['wework', 'wechat', 'phone'] as $type) {
                try {
                    $data = $contactService->generateContactData($merchantId, $type);
                    $items[] = [
                        'type'      => $type,
                        'type_name' => $data['type_name'] ?? $type,
                        'preview'   => $data['data'] ?? [],
                    ];
                } catch (\Throwable $e) {
                    // 该类型未配置,跳过
                    continue;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('私域区块加载失败', ['device_id' => $device->id]);
        }

        // QQ 入口(模块7): 优先用 ContactService::getQqConfig(Agent C 业务闭环);
        // 字段为空时回退到本地 readQqConfig 兜底读取(兼容老路径/字段未声明场景)
        $qqConfig = [];
        try {
            $qqConfig = (new ContactService())->getQqConfig((int)$device->id);
        } catch (\Throwable $e) {
            $qqConfig = [];
        }
        if (empty($qqConfig)) {
            $qqConfig = $this->readQqConfig($device);
        } else {
            // 统一字段名(readQqConfig / getQqConfig 输出结构一致)
            $qqConfig = [
                'qq_number'    => $qqConfig['qq_number']    ?? '',
                'qq_qrcode'    => $qqConfig['qq_qrcode']    ?? '',
                'qq_group_url' => $qqConfig['qq_group_url'] ?? '',
                'kefu_qrcode'  => $qqConfig['kefu_qrcode']  ?? '',
                'enabled'      => $qqConfig['enabled']      ?? true,
            ];
        }

        // bug B8: 任一字段有值即视为有 QQ 配置(避免 readQqConfig 路径丢 enabled 后误判关闭)
        $qqHasContent = ($qqConfig['qq_number'] ?? '') !== ''
            || ($qqConfig['qq_qrcode'] ?? '') !== ''
            || ($qqConfig['qq_group_url'] ?? '') !== ''
            || ($qqConfig['kefu_qrcode'] ?? '') !== '';

        return [
            'enabled' => !empty($items) || $qqHasContent,
            'items'   => $items,
            'qq'      => $qqConfig,
        ];
    }

    /**
     * 大转盘区块(模块6)
     */
    public function buildLotteryBlock(NfcDevice $device): array
    {
        try {
            if (!class_exists(\app\service\LotteryService::class)) {
                return ['enabled' => false];
            }
            $lotteryService = new LotteryService();
            $activity = $lotteryService->getActiveByDevice((int)$device->id);
            if (!$activity) {
                return ['enabled' => false];
            }
            return [
                'enabled'     => true,
                'activity_id' => $activity->id,
                'name'        => $activity->name,
                'daily_limit' => $activity->daily_limit,
                'description' => $activity->description,
                'started_at'  => $activity->start_at,
                'ended_at'    => $activity->end_at,
            ];
        } catch (\Throwable $e) {
            Log::warning('大转盘区块加载失败', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            return ['enabled' => false];
        }
    }

    /**
     * 提取今日主推(从 scene_config 中读取 promotion 字段)
     */
    public function extractHighlight(?SceneConfig $sceneConfig): array
    {
        if (!$sceneConfig) return [];
        $cfg = $sceneConfig->touch_config;
        if (!is_array($cfg) || empty($cfg['highlight'])) {
            return [];
        }
        return [
            'title' => $cfg['highlight']['title'] ?? '',
            'image' => $cfg['highlight']['image'] ?? '',
            'jump'  => $cfg['highlight']['jump_url'] ?? '',
        ];
    }

    protected function formatDevice(NfcDevice $device): array
    {
        return [
            'id'           => $device->id,
            'device_code'  => $device->device_code,
            'device_name'  => $device->device_name,
            'merchant_id'  => $device->merchant_id,
            'location'     => $device->location,
            'trigger_mode' => $device->trigger_mode,
            'device_type'  => $device->device_type,
        ];
    }

    /**
     * 默认发布候选(无文案池数据时的兜底)
     */
    protected function buildDefaultPublishCandidates(NfcDevice $device, ?SceneConfig $sceneConfig): array
    {
        $default = (string)($device->promo_copywriting ?: '');
        $merchantName = '';
        try {
            $merchant = Merchant::find((int)$device->merchant_id);
            if ($merchant) {
                $merchantName = $merchant->name ?? '';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $candidates = [];
        if ($default !== '') {
            $candidates[] = ['index' => 0, 'content' => $default];
        }
        if ($merchantName !== '') {
            $candidates[] = [
                'index'   => 1,
                'content' => "打卡 {$merchantName},体验感拉满!",
            ];
            $candidates[] = [
                'index'   => 2,
                'content' => "发现一家宝藏店「{$merchantName}」,推荐给你!",
            ];
        }
        return $candidates;
    }

    protected function formatPromoReward(NfcDevice $device): array
    {
        if (empty($device->promo_reward_coupon_id)) {
            return ['enabled' => false];
        }
        try {
            $coupon = \app\model\Coupon::find((int)$device->promo_reward_coupon_id);
            if (!$coupon) {
                return ['enabled' => false];
            }
            return [
                'enabled'        => true,
                'coupon_id'      => $coupon->id,
                'title'          => $coupon->title ?? '',
                'discount_type'  => $coupon->discount_type ?? '',
                'discount_value' => $coupon->discount_value ?? 0,
                'min_amount'     => $coupon->min_amount ?? 0,
            ];
        } catch (\Throwable $e) {
            return ['enabled' => false];
        }
    }

    protected function maskSsid(string $ssid): string
    {
        $len = mb_strlen($ssid);
        if ($len <= 2) {
            return mb_substr($ssid, 0, 1) . '***';
        }
        $first = mb_substr($ssid, 0, 2);
        return $first . '***';
    }

    /**
     * 读取 QQ 配置(模块7)
     * xmt_nfc_devices.qq_contact_config JSON 字段
     *
     * 字段可能尚未在 NfcDevice $schema 中声明,使用 getData / Db 兜底读取,
     * 避免触发 model "property not exists" 告警。
     */
    public function readQqConfig(NfcDevice $device): array
    {
        try {
            $raw = null;
            try {
                $raw = $device->getData('qq_contact_config');
            } catch (\Throwable $e) {
                $raw = null;
            }
            if ($raw === null || $raw === '') {
                $raw = \think\facade\Db::name('nfc_devices')
                    ->where('id', (int)$device->id)
                    ->value('qq_contact_config');
            }
            if (empty($raw)) {
                return [];
            }
            if (is_string($raw)) {
                $cfg = json_decode($raw, true) ?: [];
            } elseif (is_array($raw)) {
                $cfg = $raw;
            } else {
                $cfg = [];
            }
            return [
                'qq_number'    => $cfg['qq_number'] ?? '',
                'qq_qrcode'    => $cfg['qq_qrcode_url'] ?? ($cfg['qq_qrcode'] ?? ''),
                'qq_group_url' => $cfg['qq_group_url'] ?? '',
                'kefu_qrcode'  => $cfg['kefu_qrcode_url'] ?? '',
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
