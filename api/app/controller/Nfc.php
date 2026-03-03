<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\NfcService;
use app\validate\Nfc as NfcValidate;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * NFC控制器
 * 处理NFC设备相关的API请求
 */
class Nfc extends BaseController
{
    protected NfcService $nfcService;

    /**
     * 控制器初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->nfcService = new NfcService();
    }

    /**
     * NFC设备触发接口
     * POST /api/nfc/trigger
     *
     * 当用户使用NFC设备时触发此接口
     * 根据设备配置的触发模式返回相应的内容或操作
     *
     * 请求参数:
     * - device_code: 设备编码 (必填)
     * - user_location: 用户位置信息 (可选)
     *   - latitude: 纬度
     *   - longitude: 经度
     * - extra_data: 额外数据 (可选)
     *
     * 响应数据:
     * - trigger_id: 触发记录ID
     * - action: 操作类型 (generate_content/redirect/show_content等)
     * - redirect_url: 跳转链接 (如有)
     * - content_task_id: 内容生成任务ID (如有)
     * - message: 提示信息
     *
     * 性能要求: < 1秒响应
     */
    public function trigger()
    {
        $startTime = microtime(true);

        try {
            // 0. 频率限制检查（三级限流：IP + 用户 + 设备）
            $this->checkRateLimit();

            // 1. 获取并验证请求参数
            $data = $this->request->post();

            // 验证device_code参数
            if (empty($data['device_code'])) {
                return $this->validationError(['device_code' => '设备编码不能为空'], '参数验证失败');
            }

            $deviceCode = trim($data['device_code']);
            $userLocation = $data['user_location'] ?? [];
            $extraData = $data['extra_data'] ?? [];

            // 验证用户位置信息格式(如果提供)
            if (!empty($userLocation)) {
                if (!isset($userLocation['latitude']) || !isset($userLocation['longitude'])) {
                    return $this->validationError(['user_location' => '位置信息格式不正确'], '参数验证失败');
                }
            }

            // 2. 查询设备配置
            $device = \app\model\NfcDevice::findByCode($deviceCode);
            if (!$device) {
                Log::warning('NFC设备未找到', [
                    'device_code' => $deviceCode,
                    'ip' => $this->request->ip()
                ]);

                return $this->platformError('NFC_DEVICE_NOT_FOUND', [
                    'device_code' => $deviceCode
                ], 404);
            }

            // 3. 检查设备状态（PROMO模式跳过在线检查，NFC贴片是被动硬件不发心跳）
            $triggerMode = $device->trigger_mode;
            if ($triggerMode !== \app\model\NfcDevice::TRIGGER_PROMO && !$device->isOnline()) {
                Log::warning('NFC设备离线', [
                    'device_code' => $deviceCode,
                    'device_id' => $device->id,
                    'status' => $device->status
                ]);

                return $this->platformError('NFC_DEVICE_OFFLINE', [
                    'device_code' => $deviceCode,
                    'device_name' => $device->device_name,
                    'last_heartbeat' => $device->last_heartbeat
                ], 503);
            }

            // 4. 获取用户信息(从请求头或JWT)
            $userId = $this->request->userId ?? null;
            $userOpenid = $this->request->header('X-User-Openid', '');

            // 如果没有用户信息，使用匿名用户（基于IP稳定标识，同一IP同一天生成相同ID）
            if (empty($userOpenid)) {
                $userOpenid = 'anonymous_' . md5($this->request->ip() . date('Y-m-d'));
            }

            // 5. 更新设备心跳（PROMO模式也更新，用于统计活跃度）
            $device->updateHeartbeat();

            // 6. 根据触发模式处理请求
            $response = $this->handleTriggerMode($device, $triggerMode, $userId, $userOpenid, $userLocation, $extraData);

            // 7. 计算响应时间
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // 8. 记录触发事件
            $trigger = \app\model\DeviceTrigger::recordSuccess(
                $device->id,
                $deviceCode,
                $device->merchant_id,
                $userId,
                $userOpenid,
                $triggerMode,
                $response['action'],
                $response,
                $responseTime,
                $this->request->ip(),
                $this->request->header('User-Agent', '')
            );

            // 9. 添加trigger_id到响应
            $response['trigger_id'] = $trigger->id;

            // 10. 记录成功日志
            Log::info('NFC设备触发成功', [
                'trigger_id' => $trigger->id,
                'device_code' => $deviceCode,
                'trigger_mode' => $triggerMode,
                'action' => $response['action'],
                'response_time' => $responseTime . 'ms'
            ]);

            return $this->success($response, '设备触发成功');

        } catch (ValidateException $e) {
            return $this->validationError(['trigger' => $e->getMessage()], '触发失败');
        } catch (\Exception $e) {
            // 计算响应时间
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // 记录错误触发
            if (isset($device)) {
                \app\model\DeviceTrigger::recordError(
                    $device->id ?? null,
                    $deviceCode ?? '',
                    $device->merchant_id ?? 0,
                    $userId ?? null,
                    $userOpenid ?? '',
                    $triggerMode ?? '',
                    $e->getMessage(),
                    $responseTime,
                    $this->request->ip(),
                    $this->request->header('User-Agent', '')
                );
            }

            Log::error('NFC设备触发失败', [
                'error' => $e->getMessage(),
                'device_code' => $deviceCode ?? 'unknown',
                'response_time' => $responseTime . 'ms',
                'trace' => $e->getTraceAsString()
            ]);

            // 获取详细错误信息
            $detailedError = $this->getDetailedError($e, $deviceCode ?? 'unknown');

            return $this->error(
                $detailedError['message'],
                400,
                $detailedError['code'],
                [
                    'solution' => $detailedError['solution'],
                    'icon' => $detailedError['icon'],
                    'retry' => $detailedError['retry'],
                    'contact_merchant' => $detailedError['contact_merchant'] ?? false
                ]
            );
        }
    }

    /**
     * 处理不同的触发模式
     *
     * @param \app\model\NfcDevice $device 设备对象
     * @param string $triggerMode 触发模式
     * @param int|null $userId 用户ID
     * @param string $userOpenid 用户OpenID
     * @param array $userLocation 用户位置
     * @param array $extraData 额外数据
     * @return array 响应数据
     */
    protected function handleTriggerMode($device, string $triggerMode, ?int $userId, string $userOpenid, array $userLocation, array $extraData): array
    {
        switch ($triggerMode) {
            case \app\model\NfcDevice::TRIGGER_VIDEO:
                // 视频展示模式 - 创建内容生成任务
                return $this->handleVideoMode($device, $userId);

            case \app\model\NfcDevice::TRIGGER_COUPON:
                // 优惠券模式 - 返回优惠券信息
                return $this->handleCouponMode($device, $userId, $userOpenid);

            case \app\model\NfcDevice::TRIGGER_WIFI:
                // WiFi连接模式 - 返回WiFi凭证
                return $this->handleWifiMode($device);

            case \app\model\NfcDevice::TRIGGER_CONTACT:
                // 联系方式模式 - 返回联系信息
                return $this->handleContactMode($device);

            case \app\model\NfcDevice::TRIGGER_MENU:
                // 菜单展示模式 - 返回菜单URL
                return $this->handleMenuMode($device);

            case \app\model\NfcDevice::TRIGGER_GROUP_BUY:
                // 团购跳转模式 - 返回跳转URL
                return $this->handleGroupBuyMode($device);

            case \app\model\NfcDevice::TRIGGER_PROMO:
                // 消费者推广模式 - 返回推广素材
                return $this->handlePromoMode($device);

            default:
                throw new \Exception('不支持的触发模式: ' . $triggerMode);
        }
    }

    /**
     * 处理视频模式
     */
    protected function handleVideoMode($device, ?int $userId): array
    {
        // 使用ContentService创建内容生成任务
        $contentService = new \app\service\ContentService();

        try {
            $result = $contentService->createGenerationTask(
                $userId ?: 0,
                $device->merchant_id,
                [
                    'device_id' => $device->id,
                    'merchant_id' => $device->merchant_id,
                    'type' => 'VIDEO',
                    'template_id' => $device->template_id
                ]
            );

            return [
                'action' => 'generate_content',
                'content_task_id' => $result['task_id'],
                'redirect_url' => $device->redirect_url ?: '',
                'message' => '内容生成任务已创建，预计' . ($result['estimated_time'] ?? 300) . '秒完成'
            ];
        } catch (\Exception $e) {
            // 如果创建任务失败，返回默认跳转
            return [
                'action' => 'redirect',
                'redirect_url' => $device->redirect_url ?: '',
                'message' => '暂时无法生成内容，请稍后再试'
            ];
        }
    }

    /**
     * 处理优惠券模式
     */
    protected function handleCouponMode($device, ?int $userId, string $userOpenid): array
    {
        // 查询可用优惠券
        $coupons = \app\model\Coupon::where('merchant_id', $device->merchant_id)
            ->where('status', 1)
            ->where('start_time', '<=', date('Y-m-d H:i:s'))
            ->where('end_time', '>=', date('Y-m-d H:i:s'))
            ->where('total_count', '>', 0)
            ->select();

        if ($coupons->isEmpty()) {
            return [
                'action' => 'show_message',
                'message' => '暂无可用优惠券',
                'redirect_url' => $device->redirect_url ?: ''
            ];
        }

        $coupon = $coupons[0];

        return [
            'action' => 'show_coupon',
            'coupon_id' => $coupon->id,
            'coupon_title' => $coupon->title,
            'coupon_description' => $coupon->description,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'redirect_url' => $device->redirect_url ?: '',
            'message' => '发现可用优惠券'
        ];
    }

    /**
     * 处理WiFi模式
     * 安全优化：不直接返回密码，返回加密的WiFi配置
     */
    protected function handleWifiMode($device): array
    {
        if (empty($device->wifi_ssid)) {
            throw new \Exception('设备未配置WiFi信息');
        }

        // 生成临时加密的WiFi配置（5分钟有效）
        $wifiConfig = [
            'ssid' => $device->wifi_ssid,
            'password' => $device->wifi_password ?: '',
            'security' => 'WPA2',
            'expires_at' => time() + 300  // 5分钟后过期
        ];

        // 使用AES加密WiFi配置
        $encryptedConfig = encrypt(json_encode($wifiConfig));

        return [
            'action' => 'show_wifi',
            'wifi_ssid' => $device->wifi_ssid,  // SSID可以明文
            // 密码不直接返回，返回加密后的完整配置
            'wifi_config' => base64_encode($encryptedConfig),
            'expires_at' => time() + 300,
            'redirect_url' => $device->redirect_url ?: '',
            'message' => 'WiFi连接信息（已加密传输）'
        ];
    }

    /**
     * 处理联系方式模式
     */
    protected function handleContactMode($device): array
    {
        $merchant = $device->merchant;
        if (!$merchant) {
            throw new \Exception('商家信息不存在');
        }

        return [
            'action' => 'show_contact',
            'merchant_name' => $merchant->name,
            'contact_phone' => $merchant->contact_phone ?? '',
            'address' => $merchant->address ?? '',
            'qr_code_url' => $merchant->qr_code_url ?? '',
            'redirect_url' => $device->redirect_url ?: '',
            'message' => '商家联系方式'
        ];
    }

    /**
     * 处理菜单模式
     */
    protected function handleMenuMode($device): array
    {
        return [
            'action' => 'show_menu',
            'menu_url' => $device->redirect_url ?: '',
            'redirect_url' => $device->redirect_url ?: '',
            'message' => '查看电子菜单'
        ];
    }

    /**
     * 处理团购模式
     */
    protected function handleGroupBuyMode($device): array
    {
        $config = $device->group_buy_config;

        if (empty($config)) {
            throw new \Exception('设备未配置团购信息');
        }

        // 构建跳转URL
        $redirectUrl = $device->redirect_url;

        // 如果有自定义URL，使用自定义URL
        if (!empty($config['custom_url'])) {
            $redirectUrl = $config['custom_url'];
        }

        // 添加追踪参数
        if (!empty($config['tracking_params'])) {
            $params = http_build_query($config['tracking_params']);
            $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . $params;
        }

        return [
            'action' => 'redirect',
            'redirect_url' => $redirectUrl,
            'platform' => $config['platform'] ?? 'CUSTOM',
            'deal_name' => $config['deal_name'] ?? '',
            'original_price' => $config['original_price'] ?? 0,
            'group_price' => $config['group_price'] ?? 0,
            'message' => '即将跳转到团购页面'
        ];
    }

    /**
     * 处理推广模式 - 消费者碰NFC获取推广素材
     */
    protected function handlePromoMode($device): array
    {
        // 获取商家信息
        $merchant = $device->merchant;
        if (!$merchant) {
            throw new \Exception('商家信息不存在');
        }

        // 获取推广视频（从素材库 materials 表获取）
        $videoUrl = '';
        $videoThumbnail = '';
        $videoDuration = 0;
        $videoTitle = '';

        if ($device->promo_video_id) {
            // 优先从素材库获取
            $material = \app\model\Material::find($device->promo_video_id);
            if ($material) {
                $videoUrl = $material->file_url ?? '';
                $videoThumbnail = $material->thumbnail_url ?? '';
                $videoDuration = $material->duration ?? 0;
                $videoTitle = $material->title ?? $material->name ?? '';
            }

            // 兼容：如果素材库没找到，尝试从模板表获取
            if (empty($videoUrl)) {
                $template = \app\model\ContentTemplate::find($device->promo_video_id);
                if ($template) {
                    $videoUrl = $template->content_url ?? '';
                    $videoThumbnail = $template->thumbnail_url ?? '';
                    $videoDuration = $template->extra_data['duration'] ?? 0;
                    $videoTitle = $template->name ?? '';
                }
            }
        }

        if (empty($videoUrl)) {
            throw new \Exception('推广视频未配置，请联系商家');
        }

        // 获取推广文案和标签
        $copywriting = $device->promo_copywriting ?: '推荐一家超赞的店！';
        $tags = $device->promo_tags ?: [];

        // 获取奖励优惠券预览
        $reward = null;
        if ($device->promo_reward_coupon_id) {
            $coupon = \app\model\Coupon::where('id', $device->promo_reward_coupon_id)
                ->where('status', 1)
                ->find();
            if ($coupon) {
                $reward = [
                    'type' => 'coupon',
                    'id' => $coupon->id,
                    'title' => $coupon->title,
                    'description' => $coupon->description ?? '',
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'min_amount' => $coupon->min_amount ?? 0,
                    'remaining' => $coupon->total_count,
                ];
            }
        }

        return [
            'type' => 'promo',
            'action' => 'show_promo',
            'merchant' => [
                'name' => $merchant->name,
                'logo' => $merchant->logo_url ?? '',
                'description' => $merchant->description ?? '',
            ],
            'video' => [
                'url' => $videoUrl,
                'thumbnail' => $videoThumbnail,
                'duration' => $videoDuration,
                'title' => $videoTitle,
            ],
            'copywriting' => $copywriting,
            'tags' => $tags,
            'reward' => $reward,
            'platforms' => ['douyin', 'kuaishou'],
            'message' => '推广素材加载成功',
        ];
    }

    /**
     * NFC设备状态上报接口
     * POST /api/nfc/device-status
     *
     * NFC设备定期上报设备状态信息
     * 包括在线状态、电池电量、信号强度、温度等
     */
    public function deviceStatus()
    {
        try {
            $data = $this->request->post();

            // 数据验证
            $this->validate($data, 'Nfc.deviceStatus');

            // 处理设备状态上报
            $result = $this->nfcService->handleDeviceStatus(
                $data['device_code'],
                $data
            );

            // 使用专用的NFC设备状态响应格式
            $statusText = $this->getDeviceStatusText($result['status']);

            return $this->nfcDeviceStatus($result, $statusText);

        } catch (ValidateException $e) {
            return $this->validationError(['device_status' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('NFC设备状态上报失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);

            if (strpos($e->getMessage(), '设备未找到') !== false) {
                return $this->platformError('NFC_DEVICE_NOT_FOUND', [
                    'device_code' => $data['device_code'] ?? 'unknown'
                ], 404);
            }

            return $this->error($e->getMessage(), 400, 'device_status_failed');
        }
    }

    /**
     * 获取NFC设备配置接口
     * GET /api/nfc/config
     *
     * 根据设备编码获取设备的配置信息
     * 包括触发模式、内容模板、WiFi配置等
     */
    public function getConfig()
    {
        try {
            $deviceCode = $this->request->param('device_code', '');

            // 数据验证
            $this->validate(['device_code' => $deviceCode], 'Nfc.getConfig');

            // 获取设备配置
            $config = $this->nfcService->getDeviceConfig($deviceCode);

            // 记录配置获取日志
            Log::info('获取NFC设备配置', [
                'device_code' => $deviceCode,
                'config_keys' => array_keys($config)
            ]);

            return $this->success($config, '获取设备配置成功');

        } catch (ValidateException $e) {
            return $this->validationError(['device_code' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取NFC设备配置失败', [
                'error' => $e->getMessage(),
                'device_code' => $deviceCode ?? 'unknown'
            ]);

            if (strpos($e->getMessage(), '设备未找到') !== false) {
                return $this->platformError('NFC_DEVICE_NOT_FOUND', [
                    'device_code' => $deviceCode ?? 'unknown'
                ], 404);
            }

            return $this->error($e->getMessage(), 400, 'get_config_failed');
        }
    }

    /**
     * 批量设备状态上报接口
     * POST /api/nfc/batch-status
     *
     * 支持多个设备同时上报状态信息
     */
    public function batchDeviceStatus()
    {
        try {
            $data = $this->request->post();

            // 验证批量数据格式
            if (!isset($data['devices']) || !is_array($data['devices'])) {
                throw new ValidateException('devices参数必须是数组');
            }

            if (count($data['devices']) > 100) {
                throw new ValidateException('单次最多支持100个设备状态上报');
            }

            // 批量处理设备状态
            $results = $this->nfcService->batchUpdateDeviceStatus($data['devices']);

            // 统计处理结果
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failureCount = count($results) - $successCount;

            Log::info('批量NFC设备状态上报', [
                'total' => count($results),
                'success' => $successCount,
                'failure' => $failureCount
            ]);

            return $this->batchResponse($results, "批量状态上报完成，成功{$successCount}个，失败{$failureCount}个");

        } catch (ValidateException $e) {
            return $this->validationError(['batch_status' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('批量NFC设备状态上报失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'batch_status_failed');
        }
    }

    /**
     * 获取设备统计信息接口
     * GET /api/nfc/stats
     *
     * 获取指定商家的设备统计信息
     * 需要商家认证
     */
    public function deviceStats()
    {
        try {
            // 获取商家ID（从JWT中间件解析）
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取设备统计
            $stats = $this->nfcService->getDeviceStats($merchantId);

            return $this->success($stats, '获取设备统计成功');

        } catch (\Exception $e) {
            Log::error('获取NFC设备统计失败', [
                'error' => $e->getMessage(),
                'merchant_id' => $merchantId ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'get_stats_failed');
        }
    }

    /**
     * 清除设备配置缓存接口
     * POST /api/nfc/clear-config-cache
     *
     * 管理员接口，用于清除指定设备的配置缓存
     */
    public function clearConfigCache()
    {
        try {
            $data = $this->request->post();

            if (empty($data['device_code'])) {
                throw new ValidateException('设备编码不能为空');
            }

            // 清除缓存
            $this->nfcService->clearConfigCache($data['device_code']);

            Log::info('清除NFC设备配置缓存', [
                'device_code' => $data['device_code']
            ]);

            return $this->success(null, '缓存清除成功');

        } catch (ValidateException $e) {
            return $this->validationError(['device_code' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('清除NFC设备配置缓存失败', [
                'error' => $e->getMessage(),
                'device_code' => $data['device_code'] ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'clear_cache_failed');
        }
    }

    /**
     * 设备健康检查接口
     * GET /api/nfc/health-check
     *
     * 检查指定设备的健康状态
     */
    public function healthCheck()
    {
        try {
            $deviceCode = $this->request->param('device_code', '');

            if (empty($deviceCode)) {
                throw new ValidateException('设备编码不能为空');
            }

            // 获取设备配置（包含健康状态检查）
            $config = $this->nfcService->getDeviceConfig($deviceCode);

            // 简化健康检查响应
            $healthData = [
                'device_code' => $config['device_code'],
                'device_name' => $config['device_name'],
                'status' => $this->getDeviceStatusText($config['status'] ?? 0),
                'last_update' => $config['update_time'],
                'health_status' => 'healthy' // 这里可以扩展更详细的健康检查逻辑
            ];

            return $this->success($healthData, '设备健康检查完成');

        } catch (ValidateException $e) {
            return $this->validationError(['device_code' => $e->getMessage()]);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), '设备未找到') !== false) {
                return $this->platformError('NFC_DEVICE_NOT_FOUND', [
                    'device_code' => $deviceCode
                ], 404);
            }

            return $this->error($e->getMessage(), 400, 'health_check_failed');
        }
    }

    /**
     * 获取设备状态文本描述
     *
     * @param int $status
     * @return string
     */
    protected function getDeviceStatusText(int $status): string
    {
        $statusMap = [
            0 => 'offline',
            1 => 'online',
            2 => 'maintenance'
        ];

        return $statusMap[$status] ?? 'unknown';
    }

    /**
     * 获取设备列表接口
     * GET /api/nfc/devices
     *
     * 获取商家的NFC设备列表，支持分页和筛选
     */
    public function deviceList()
    {
        try {
            // 获取商家ID（从JWT中间件解析）
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');
            $type = $this->request->param('type', '');

            // 构建查询条件
            $where = ['merchant_id' => $merchantId];
            if ($status !== '') {
                $where['status'] = (int)$status;
            }
            if ($type !== '') {
                $where['type'] = $type;
            }

            // 查询设备列表
            $devices = \app\model\NfcDevice::where($where)
                ->page($page, $limit)
                ->order('create_time', 'desc')
                ->select();

            $total = \app\model\NfcDevice::where($where)->count();

            // 转换为数组格式
            $deviceList = [];
            foreach ($devices as $device) {
                $deviceList[] = [
                    'id' => $device->id,
                    'device_code' => $device->device_code,
                    'device_name' => $device->device_name,
                    'type' => $device->type,
                    'type_text' => $device->type_text,
                    'trigger_mode' => $device->trigger_mode,
                    'trigger_mode_text' => $device->trigger_mode_text,
                    'status' => $device->status,
                    'status_text' => $device->status_text,
                    'battery_level' => $device->battery_level,
                    'battery_status' => $device->battery_status,
                    'is_online' => $device->is_online,
                    'last_heartbeat' => $device->last_heartbeat,
                    'location' => $device->location,
                    'create_time' => $device->create_time
                ];
            }

            return $this->paginate($deviceList, $total, $page, $limit, '获取设备列表成功');

        } catch (\Exception $e) {
            Log::error('获取NFC设备列表失败', [
                'error' => $e->getMessage(),
                'merchant_id' => $merchantId ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'get_device_list_failed');
        }
    }

    /**
     * 配置设备团购信息接口
     * PUT /api/nfc/device/{device_id}/group-buy
     *
     * 为指定设备配置团购信息
     */
    public function configureGroupBuy()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $data = $this->request->post();

            // 获取商家ID（从JWT中间件解析）
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            if (!$deviceId) {
                return $this->error('设备ID不能为空', 400, 'device_id_required');
            }

            // 查找设备
            $device = \app\model\NfcDevice::find($deviceId);
            if (!$device) {
                return $this->error('设备不存在', 404, 'device_not_found');
            }

            // 验证设备归属
            if ($device->merchant_id != $merchantId) {
                return $this->error('无权操作此设备', 403, 'device_access_denied');
            }

            // 验证团购配置数据
            $this->validate($data, [
                'platform' => 'require|in:MEITUAN,DOUYIN,ELEME,CUSTOM',
                'deal_id' => 'requireIf:platform,MEITUAN,DOUYIN,ELEME',
                'custom_url' => 'requireIf:platform,CUSTOM|url',
                'deal_name' => 'max:100',
                'original_price' => 'number|>=:0',
                'group_price' => 'number|>=:0',
            ]);

            // 使用团购服务验证配置
            $groupBuyService = new \app\service\GroupBuyService();
            $validation = $groupBuyService->validateGroupBuyConfig($data);

            if (!$validation['valid']) {
                return $this->validationError(['group_buy' => implode(', ', $validation['errors'])]);
            }

            // 构建配置数据
            $config = [
                'platform' => $data['platform'],
                'deal_id' => $data['deal_id'] ?? '',
                'deal_name' => $data['deal_name'] ?? '',
                'original_price' => (float)($data['original_price'] ?? 0),
                'group_price' => (float)($data['group_price'] ?? 0),
                'custom_url' => $data['custom_url'] ?? null,
                'tracking_params' => [
                    'utm_source' => 'xiaomotui',
                    'utm_medium' => 'nfc',
                    'utm_campaign' => 'device_' . $deviceId
                ]
            ];

            // 更新设备配置
            $device->group_buy_config = $config;
            $device->trigger_mode = 'GROUP_BUY';
            $device->save();

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($device->device_code);

            Log::info('配置设备团购信息成功', [
                'device_id' => $deviceId,
                'merchant_id' => $merchantId,
                'platform' => $config['platform']
            ]);

            return $this->success([
                'device_id' => $deviceId,
                'config' => $config,
                'deal_info' => $groupBuyService->formatDealInfo($config)
            ], '配置团购信息成功');

        } catch (ValidateException $e) {
            return $this->validationError(['group_buy' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('配置设备团购信息失败', [
                'error' => $e->getMessage(),
                'device_id' => $deviceId ?? 0,
                'merchant_id' => $merchantId ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'configure_group_buy_failed');
        }
    }

    /**
     * 获取设备团购配置接口
     * GET /api/nfc/device/{device_id}/group-buy
     *
     * 获取指定设备的团购配置信息
     */
    public function getGroupBuyConfig()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);

            // 获取商家ID（从JWT中间件解析）
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            if (!$deviceId) {
                return $this->error('设备ID不能为空', 400, 'device_id_required');
            }

            // 查找设备
            $device = \app\model\NfcDevice::find($deviceId);
            if (!$device) {
                return $this->error('设备不存在', 404, 'device_not_found');
            }

            // 验证设备归属
            if ($device->merchant_id != $merchantId) {
                return $this->error('无权访问此设备', 403, 'device_access_denied');
            }

            // 检查是否配置了团购
            if (empty($device->group_buy_config)) {
                return $this->success([
                    'device_id' => $deviceId,
                    'configured' => false,
                    'config' => null
                ], '设备未配置团购信息');
            }

            // 使用团购服务解析配置
            $groupBuyService = new \app\service\GroupBuyService();
            $config = $groupBuyService->parseGroupBuyConfig($device->group_buy_config);
            $dealInfo = $groupBuyService->formatDealInfo($config);

            return $this->success([
                'device_id' => $deviceId,
                'configured' => true,
                'config' => $config,
                'deal_info' => $dealInfo
            ], '获取团购配置成功');

        } catch (\Exception $e) {
            Log::error('获取设备团购配置失败', [
                'error' => $e->getMessage(),
                'device_id' => $deviceId ?? 0,
                'merchant_id' => $merchantId ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'get_group_buy_config_failed');
        }
    }

    /**
     * 获取团购统计接口
     * GET /api/merchant/{merchant_id}/group-buy/statistics
     *
     * 获取商家的团购跳转统计数据
     */
    public function getGroupBuyStatistics()
    {
        try {
            // 获取商家ID（从JWT中间件解析）
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取筛选参数
            $filters = [
                'start_date' => $this->request->param('start_date', date('Y-m-d', strtotime('-30 days'))),
                'end_date' => $this->request->param('end_date', date('Y-m-d', strtotime('+1 day'))),
                'device_id' => $this->request->param('device_id', null),
                'platform' => $this->request->param('platform', null)
            ];

            // 使用团购服务获取统计数据
            $groupBuyService = new \app\service\GroupBuyService();
            $statistics = $groupBuyService->getStatistics($merchantId, $filters);

            Log::info('获取团购统计数据', [
                'merchant_id' => $merchantId,
                'filters' => $filters
            ]);

            return $this->success($statistics, '获取团购统计成功');

        } catch (\Exception $e) {
            Log::error('获取团购统计失败', [
                'error' => $e->getMessage(),
                'merchant_id' => $merchantId ?? 'unknown'
            ]);

            return $this->error($e->getMessage(), 400, 'get_group_buy_statistics_failed');
        }
    }

    /**
     * 频率限制检查（三级限流）
     * 防止恶意刷量和DDoS攻击
     *
     * @throws \Exception
     */
    protected function checkRateLimit(): void
    {
        $ip = $this->request->ip();
        $userId = $this->request->user_id ?? null;
        $deviceCode = $this->request->post('device_code', '');

        // 1. IP级限流（最严格）- 防止匿名攻击
        $ipKey = 'nfc_rate:ip:' . $ip;
        $ipCount = \think\facade\Cache::get($ipKey, 0);

        if ($ipCount >= 10) {  // 每分钟最多10次
            Log::warning('NFC触发IP频率超限', [
                'ip' => $ip,
                'count' => $ipCount
            ]);
            throw new \Exception('触发过于频繁，请稍后再试（每分钟最多10次）');
        }

        \think\facade\Cache::set($ipKey, $ipCount + 1, 60);  // 1分钟过期

        // 2. 用户级限流（已登录用户）
        if ($userId) {
            $userKey = 'nfc_rate:user:' . $userId;
            $userCount = \think\facade\Cache::get($userKey, 0);

            if ($userCount >= 30) {  // 每分钟最多30次
                Log::warning('NFC触发用户频率超限', [
                    'user_id' => $userId,
                    'count' => $userCount
                ]);
                throw new \Exception('触发过于频繁，请稍后再试（每分钟最多30次）');
            }

            \think\facade\Cache::set($userKey, $userCount + 1, 60);
        }

        // 3. 设备级限流（防止单个设备被刷）
        if ($deviceCode) {
            $deviceKey = 'nfc_rate:device:' . $deviceCode;
            $deviceCount = \think\facade\Cache::get($deviceKey, 0);

            if ($deviceCount >= 100) {  // 每分钟最多100次
                Log::warning('NFC触发设备频率超限', [
                    'device_code' => $deviceCode,
                    'count' => $deviceCount
                ]);
                throw new \Exception('设备触发过于频繁，请稍后再试');
            }

            \think\facade\Cache::set($deviceKey, $deviceCount + 1, 60);
        }
    }

    /**
     * 获取详细的错误信息
     * 将技术错误转换为用户友好的错误提示，并提供解决方案
     *
     * @param \Exception $e 异常对象
     * @param string $deviceCode 设备编码
     * @return array 包含错误代码、消息、解决方案、图标等信息
     */
    protected function getDetailedError(\Exception $e, string $deviceCode): array
    {
        $errorMessage = $e->getMessage();

        // 错误映射表：关键词 => 详细错误信息
        $errorMap = [
            // ========== 设备相关错误 ==========
            '设备不存在' => [
                'code' => 'DEVICE_NOT_FOUND',
                'message' => '设备未找到',
                'solution' => '请确认设备二维码是否正确，或联系商家确认设备状态',
                'icon' => '❓',
                'retry' => false,
                'contact_merchant' => true
            ],
            '设备已离线' => [
                'code' => 'DEVICE_OFFLINE',
                'message' => '设备暂时离线',
                'solution' => '设备可能断网或关机，请稍后重试或告知商家设备编号：' . $deviceCode,
                'icon' => '📴',
                'retry' => true,
                'contact_merchant' => true
            ],
            '设备未激活' => [
                'code' => 'DEVICE_INACTIVE',
                'message' => '设备未激活',
                'solution' => '该设备还未完成激活配置，请联系商家激活后再试',
                'icon' => '🔒',
                'retry' => false,
                'contact_merchant' => true
            ],
            '设备已禁用' => [
                'code' => 'DEVICE_DISABLED',
                'message' => '设备已被禁用',
                'solution' => '该设备因违规或欠费被暂停使用，请联系商家处理',
                'icon' => '🚫',
                'retry' => false,
                'contact_merchant' => true
            ],
            '设备配置错误' => [
                'code' => 'DEVICE_CONFIG_ERROR',
                'message' => '设备配置异常',
                'solution' => '设备配置信息不完整，请联系商家重新配置',
                'icon' => '⚙️',
                'retry' => false,
                'contact_merchant' => true
            ],

            // ========== 频率限制错误 ==========
            '触发过于频繁' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => '操作太频繁了',
                'solution' => '为了保护系统，请稍后再试（每分钟限制10-30次）',
                'icon' => '⏱️',
                'retry' => true,
                'contact_merchant' => false
            ],
            '设备触发过于频繁' => [
                'code' => 'DEVICE_RATE_LIMIT',
                'message' => '该设备使用过于频繁',
                'solution' => '请稍等1分钟后重试',
                'icon' => '⏱️',
                'retry' => true,
                'contact_merchant' => false
            ],

            // ========== 网络/超时错误 ==========
            'timeout' => [
                'code' => 'TIMEOUT_ERROR',
                'message' => '请求超时',
                'solution' => '网络响应较慢，请检查网络连接后重试',
                'icon' => '⏳',
                'retry' => true,
                'contact_merchant' => false
            ],
            'Connection' => [
                'code' => 'CONNECTION_ERROR',
                'message' => '连接失败',
                'solution' => '无法连接到服务器，请检查网络设置',
                'icon' => '📡',
                'retry' => true,
                'contact_merchant' => false
            ],

            // ========== 权限/认证错误 ==========
            '未授权' => [
                'code' => 'UNAUTHORIZED',
                'message' => '需要登录',
                'solution' => '请先登录后再使用此功能',
                'icon' => '🔐',
                'retry' => false,
                'contact_merchant' => false
            ],
            '无权访问' => [
                'code' => 'FORBIDDEN',
                'message' => '权限不足',
                'solution' => '您没有权限访问此设备，请联系商家授权',
                'icon' => '🔐',
                'retry' => false,
                'contact_merchant' => true
            ],

            // ========== 业务逻辑错误 ==========
            'AI生成失败' => [
                'code' => 'AI_GENERATION_FAILED',
                'message' => 'AI内容生成失败',
                'solution' => '内容生成服务暂时不可用，请稍后重试',
                'icon' => '🤖',
                'retry' => true,
                'contact_merchant' => false
            ],
            '模板不存在' => [
                'code' => 'TEMPLATE_NOT_FOUND',
                'message' => '内容模板未找到',
                'solution' => '设备关联的内容模板已被删除，请联系商家重新配置',
                'icon' => '📄',
                'retry' => false,
                'contact_merchant' => true
            ],
            '配额不足' => [
                'code' => 'QUOTA_EXCEEDED',
                'message' => '使用配额已用完',
                'solution' => '商家的AI生成配额已用完，请联系商家充值',
                'icon' => '📊',
                'retry' => false,
                'contact_merchant' => true
            ],

            // ========== 数据错误 ==========
            '参数错误' => [
                'code' => 'INVALID_PARAMS',
                'message' => '请求参数错误',
                'solution' => '请求数据格式不正确，请重新扫描或触发',
                'icon' => '❌',
                'retry' => true,
                'contact_merchant' => false
            ],
            'Validation' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '数据验证失败',
                'solution' => '提交的数据不符合要求，请重试',
                'icon' => '❌',
                'retry' => true,
                'contact_merchant' => false
            ],
        ];

        // 遍历错误映射表，查找匹配的错误
        foreach ($errorMap as $keyword => $errorInfo) {
            if (stripos($errorMessage, $keyword) !== false) {
                return $errorInfo;
            }
        }

        // 未匹配到已知错误，返回通用错误信息
        return [
            'code' => 'UNKNOWN_ERROR',
            'message' => '触发失败',
            'solution' => '发生了未知错误，请重试。如果问题持续，请联系客服。错误详情：' . $errorMessage,
            'icon' => '❌',
            'retry' => true,
            'contact_merchant' => true
        ];
    }
}