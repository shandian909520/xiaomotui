<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\User;
use app\model\ContentTask;
use app\model\DeviceTrigger;
use app\model\Coupon;
use app\model\CouponUser;
use app\service\CacheService;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Request;

/**
 * NFC服务类
 */
class NfcService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'nfc_';

    /**
     * 设备配置缓存时间(秒)
     */
    const CONFIG_CACHE_TTL = 300;

    /**
     * NFC设备触发处理
     *
     * @param string $deviceCode 设备编码
     * @param string $triggerMode 触发模式
     * @param string $openid 用户openid
     * @return array
     * @throws \Exception
     */
    public function handleTrigger(string $deviceCode, string $triggerMode, string $openid): array
    {
        $startTime = microtime(true);
        $clientIp = Request::ip();
        $userAgent = Request::header('user-agent', '');

        try {
            // 查找设备
            $device = NfcDevice::findByDeviceCode($deviceCode);
            if (!$device) {
                $responseTime = (int)((microtime(true) - $startTime) * 1000);

                // 记录失败
                DeviceTrigger::recordError(
                    null,
                    $deviceCode,
                    0,
                    null,
                    $openid,
                    $triggerMode,
                    'NFC设备未找到',
                    $responseTime,
                    $clientIp,
                    $userAgent
                );

                throw new ValidateException('NFC设备未找到');
            }

            // 检查设备状态（PROMO模式跳过在线检查）
            if ($triggerMode !== 'PROMO' && !$device->isOnline()) {
                $responseTime = (int)((microtime(true) - $startTime) * 1000);

                // 记录失败
                DeviceTrigger::recordError(
                    $device->id,
                    $deviceCode,
                    $device->merchant_id,
                    null,
                    $openid,
                    $triggerMode,
                    'NFC设备离线',
                    $responseTime,
                    $clientIp,
                    $userAgent
                );

                throw new ValidateException('NFC设备离线');
            }

            // 验证用户（PROMO模式允许匿名用户）
            $user = $this->getUserFromCache($openid);
            if (!$user && $triggerMode !== 'PROMO') {
                $responseTime = (int)((microtime(true) - $startTime) * 1000);

                // 记录失败
                DeviceTrigger::recordError(
                    $device->id,
                    $deviceCode,
                    $device->merchant_id,
                    null,
                    $openid,
                    $triggerMode,
                    '用户不存在',
                    $responseTime,
                    $clientIp,
                    $userAgent
                );

                throw new ValidateException('用户不存在');
            }

            // 更新设备心跳
            $device->updateHeartbeat();

            // 根据触发模式处理
            $result = $this->processTriggerMode($device, $triggerMode, $user);

            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // 记录成功触发
            DeviceTrigger::recordSuccess(
                $device->id,
                $deviceCode,
                $device->merchant_id,
                $user ? $user->id : null,
                $openid,
                $triggerMode,
                $result['type'],
                $result,
                $responseTime,
                $clientIp,
                $userAgent
            );

            // 记录日志
            Log::info('NFC设备触发成功', [
                'device_code' => $deviceCode,
                'trigger_mode' => $triggerMode,
                'user_id' => $user ? $user->id : null,
                'response_time' => $responseTime,
                'result_type' => $result['type']
            ]);

            return $result;

        } catch (ValidateException $e) {
            // 已在上面记录过错误，直接抛出
            throw $e;
        } catch (\Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // 记录未知错误
            DeviceTrigger::recordError(
                $device->id ?? null,
                $deviceCode,
                $device->merchant_id ?? 0,
                $user->id ?? null,
                $openid,
                $triggerMode,
                $e->getMessage(),
                $responseTime,
                $clientIp,
                $userAgent
            );

            throw $e;
        }
    }

    /**
     * 设备状态上报处理
     *
     * @param string $deviceCode 设备编码
     * @param array $statusData 状态数据
     * @return array
     * @throws \Exception
     */
    public function handleDeviceStatus(string $deviceCode, array $statusData): array
    {
        // 查找设备
        $device = NfcDevice::findByDeviceCode($deviceCode);
        if (!$device) {
            throw new ValidateException('NFC设备未找到');
        }

        // 更新设备状态
        $this->updateDeviceStatus($device, $statusData);

        // 检查设备健康状态
        $healthStatus = $this->checkDeviceHealth($device, $statusData);

        // 记录状态变更日志
        Log::info('NFC设备状态上报', [
            'device_code' => $deviceCode,
            'status_data' => $statusData,
            'health_status' => $healthStatus
        ]);

        return [
            'device_id' => $device->id,
            'device_code' => $deviceCode,
            'status' => $device->status,
            'battery_level' => $device->battery_level,
            'health_status' => $healthStatus,
            'last_update' => $device->update_time
        ];
    }

    /**
     * 获取设备配置
     *
     * @param string $deviceCode 设备编码
     * @return array
     * @throws \Exception
     */
    public function getDeviceConfig(string $deviceCode): array
    {
        // 先从缓存获取
        $config = CacheService::getDeviceConfig($deviceCode);
        if ($config !== null) {
            return $config;
        }

        // 查找设备
        $device = NfcDevice::findByDeviceCode($deviceCode);
        if (!$device) {
            throw new ValidateException('NFC设备未找到');
        }

        // 构建配置数据
        $config = [
            'device_id' => $device->id,
            'device_code' => $device->device_code,
            'device_name' => $device->device_name,
            'type' => $device->type,
            'trigger_mode' => $device->trigger_mode,
            'template_id' => $device->template_id,
            'redirect_url' => $device->redirect_url,
            'wifi_config' => [
                'ssid' => $device->wifi_ssid,
                'password' => $device->wifi_password
            ],
            'content_config' => $this->getContentConfig($device),
            'status' => $device->status,
            'update_time' => $device->update_time
        ];

        // 缓存配置
        CacheService::setDeviceConfig($deviceCode, $config);

        return $config;
    }

    /**
     * 处理不同的触发模式
     *
     * @param NfcDevice $device
     * @param string $triggerMode
     * @param User $user
     * @return array
     */
    protected function processTriggerMode(NfcDevice $device, string $triggerMode, ?User $user): array
    {
        switch ($triggerMode) {
            case 'VIDEO':
                return $this->handleVideoTrigger($device, $user);
            case 'COUPON':
                return $this->handleCouponTrigger($device, $user);
            case 'WIFI':
                return $this->handleWifiTrigger($device, $user);
            case 'CONTACT':
                return $this->handleContactTrigger($device, $user);
            case 'MENU':
                return $this->handleMenuTrigger($device, $user);
            case 'GROUP_BUY':
                return $this->handleGroupBuyTrigger($device, $user);
            case 'PROMO':
                return $this->handlePromoTrigger($device, $user);
            default:
                throw new ValidateException('不支持的触发模式');
        }
    }

    /**
     * 处理推广触发 - 消费者碰NFC获取推广素材
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handlePromoTrigger(NfcDevice $device, ?User $user): array
    {
        // 获取商家信息
        $merchant = $device->merchant;
        if (!$merchant) {
            throw new ValidateException('商家信息不存在');
        }

        // 获取推广视频（优先从素材库获取）
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
            throw new ValidateException('推广视频未配置，请联系商家');
        }

        // 获取推广文案
        $copywriting = $device->promo_copywriting ?: '推荐一家超赞的店！';

        // 获取话题标签
        $tags = $device->promo_tags ?: [];

        // 获取奖励优惠券预览
        $reward = null;
        if ($device->promo_reward_coupon_id) {
            $coupon = Coupon::where('id', $device->promo_reward_coupon_id)
                ->where('status', 1)
                ->find();
            if ($coupon) {
                $reward = [
                    'type' => 'coupon',
                    'id' => $coupon->id,
                    'title' => $coupon->title,
                    'description' => $coupon->description,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'min_amount' => $coupon->min_amount,
                    'remaining' => $coupon->total_count,
                ];
            }
        }

        return [
            'type' => 'promo',
            'merchant' => [
                'name' => $merchant->name,
                'logo' => $merchant->logo_url ?: '',
                'description' => $merchant->description ?: '',
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
        ];
    }

    /**
     * 处理视频展示触发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleVideoTrigger(NfcDevice $device, User $user): array
    {
        // 先从缓存获取视频内容
        $contentTask = $this->getDeviceContentFromCache($device->id, 'video');

        if (!$contentTask) {
            // 如果没有现成的视频，创建内容生成任务
            $this->createContentGenerationTask($device, 'video', $user);
            throw new ValidateException('视频内容生成中，请稍后再试');
        }

        // 检查视频文件是否存在
        if (!$contentTask->content_url || !$this->checkContentFileExists($contentTask->content_url)) {
            // 清除无效缓存
            CacheService::clearDeviceContent($device->id, 'video');
            throw new ValidateException('视频内容文件不存在');
        }

        return [
            'type' => 'video',
            'content_id' => $contentTask->id,
            'video_url' => $contentTask->content_url,
            'thumbnail_url' => $contentTask->extra_data['thumbnail_url'] ?? '',
            'title' => $contentTask->title ?: '精彩视频内容',
            'description' => $contentTask->description ?: '',
            'duration' => $contentTask->extra_data['duration'] ?? 0,
            'file_size' => $contentTask->extra_data['file_size'] ?? 0,
            'resolution' => $contentTask->extra_data['resolution'] ?? '1080p',
            'redirect_url' => $device->redirect_url,
            'share_url' => $this->generateShareUrl($contentTask),
            'created_at' => $contentTask->create_time
        ];
    }

    /**
     * 处理优惠券触发
     * 安全优化：添加分布式锁和原子操作，防止并发超发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleCouponTrigger(NfcDevice $device, User $user): array
    {
        // 使用Redis分布式锁防止并发
        $lockKey = 'coupon_lock:merchant:' . $device->merchant_id;
        $lock = Cache::lock($lockKey, 10);  // 10秒锁定时间

        try {
            // 获取锁，最多等待3秒
            if (!$lock->get(3)) {
                throw new ValidateException('优惠券正在发放中，请稍后再试');
            }

            // 查询可用优惠券（使用数据库锁）
            $coupon = Coupon::where('merchant_id', $device->merchant_id)
                ->where('status', 1)
                ->where('start_time', '<=', date('Y-m-d H:i:s'))
                ->where('end_time', '>=', date('Y-m-d H:i:s'))
                ->where('total_count', '>', 0)  // 必须有库存
                ->lock(true)  // 加行级锁（for update）
                ->find();

            if (!$coupon) {
                throw new ValidateException('暂无可用优惠券');
            }

            // 检查用户是否已领取
            $userCoupon = CouponUser::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->find();

            if ($userCoupon) {
                // 已领取，释放锁后返回
                $lock->release();

                return [
                    'type' => 'coupon',
                    'status' => 'already_received',
                    'coupon_id' => $userCoupon->id,
                    'coupon_code' => $userCoupon->coupon_code,
                    'title' => $coupon->title,
                    'description' => $coupon->description,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'min_amount' => $coupon->min_amount,
                    'use_status' => $userCoupon->use_status,
                    'valid_until' => $coupon->end_time,
                    'received_at' => $userCoupon->create_time,
                    'redirect_url' => $device->redirect_url
                ];
            }

            // 原子性减库存（使用decrement，自动加锁）
            $affected = Coupon::where('id', $coupon->id)
                ->where('total_count', '>', 0)  // 再次确认有库存
                ->dec('total_count', 1);

            if ($affected === 0) {
                // 减库存失败，说明已被抢完
                throw new ValidateException('优惠券已抢完');
            }

            // 创建用户优惠券记录
            $couponCode = $this->generateCouponCode();
            $newCouponUser = CouponUser::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'coupon_code' => $couponCode,
                'use_status' => 0,
                'received_source' => 'nfc_device',
                'device_id' => $device->id
            ]);

            // 清除相关缓存
            CacheService::clearMerchantCoupons($device->merchant_id);
            CacheService::clearUserCoupons($user->id, $device->merchant_id);

            // 释放锁
            $lock->release();

            Log::info('用户领取优惠券成功', [
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'device_id' => $device->id,
                'remaining_count' => $coupon->total_count - 1
            ]);

            return [
                'type' => 'coupon',
                'status' => 'new_received',
                'coupon_id' => $newCouponUser->id,
                'coupon_code' => $couponCode,
                'title' => $coupon->title,
                'description' => $coupon->description,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'min_amount' => $coupon->min_amount,
                'use_conditions' => $coupon->use_conditions ?? '',
                'valid_until' => $coupon->end_time,
                'received_at' => $newCouponUser->create_time,
                'redirect_url' => $device->redirect_url,
                'usage_instructions' => '请在有效期内使用，具体使用方法请查看商家说明'
            ];

        } catch (ValidateException $e) {
            // 验证异常，释放锁后抛出
            $lock->release();
            throw $e;
        } catch (\Exception $e) {
            // 其他异常，释放锁并记录日志
            $lock->release();

            Log::error('优惠券领取失败', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);

            throw new ValidateException('优惠券领取失败：' . $e->getMessage());
        }
    }

    /**
     * 处理WiFi连接触发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleWifiTrigger(NfcDevice $device, User $user): array
    {
        // 使用专门的WiFi服务处理
        $wifiService = new WifiService();

        // 生成WiFi配置（微信小程序格式）
        $wifiConfig = $wifiService->generateWifiConfig($device, WifiService::PLATFORM_WECHAT, $user);

        // 添加NFC触发特有的字段
        $wifiConfig['type'] = 'wifi';
        $wifiConfig['redirect_url'] = $device->redirect_url;

        return $wifiConfig;
    }

    /**
     * 处理联系方式触发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleContactTrigger(NfcDevice $device, User $user): array
    {
        // 获取商家联系方式
        $merchant = $device->merchant;
        if (!$merchant) {
            throw new ValidateException('商家信息不存在');
        }

        // 生成联系人vCard格式数据
        $vCardData = $this->generateVCardData($merchant);

        return [
            'type' => 'contact',
            'merchant_name' => $merchant->name,
            'logo_url' => $merchant->logo_url ?: '',
            'phone' => $merchant->contact_phone,
            'phone_display' => $this->formatPhoneNumber($merchant->contact_phone),
            'email' => $merchant->email ?: '',
            'website' => $merchant->website ?: '',
            'address' => $merchant->address,
            'location' => [
                'latitude' => $merchant->latitude,
                'longitude' => $merchant->longitude,
                'address_detail' => $merchant->address_detail ?: ''
            ],
            'business_hours' => $merchant->business_hours ?: '营业时间请咨询商家',
            'business_hours_structured' => $this->parseBusinessHours($merchant->business_hours),
            'description' => $merchant->description ?: '',
            'services' => $merchant->services ? explode(',', $merchant->services) : [],
            'social_media' => [
                'wechat' => $merchant->wechat_id ?: '',
                'weibo' => $merchant->weibo_id ?: '',
                'douyin' => $merchant->douyin_id ?: ''
            ],
            'vcard_data' => $vCardData,
            'qr_code_url' => $this->generateContactQrCode($vCardData),
            'quick_actions' => [
                'call' => 'tel:' . $merchant->contact_phone,
                'navigate' => $this->generateNavigationUrl($merchant),
                'save_contact' => $vCardData
            ],
            'redirect_url' => $device->redirect_url,
            'last_updated' => $merchant->update_time
        ];
    }

    /**
     * 处理菜单展示触发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleMenuTrigger(NfcDevice $device, User $user): array
    {
        // 先从缓存获取菜单内容
        $contentTask = $this->getDeviceContentFromCache($device->id, 'menu');

        if (!$contentTask) {
            // 如果没有现成的菜单，创建菜单生成任务
            $this->createContentGenerationTask($device, 'menu', $user);
            throw new ValidateException('菜单内容生成中，请稍后再试');
        }

        // 检查菜单内容是否存在
        if (!$contentTask->content_url || !$this->checkContentFileExists($contentTask->content_url)) {
            // 清除无效缓存
            CacheService::clearDeviceContent($device->id, 'menu');
            throw new ValidateException('菜单内容文件不存在');
        }

        // 解析菜单内容
        $menuData = $this->parseMenuContent($contentTask);

        return [
            'type' => 'menu',
            'content_id' => $contentTask->id,
            'menu_url' => $contentTask->content_url,
            'title' => $contentTask->title ?: '精美菜单',
            'description' => $contentTask->description ?: '',
            'menu_data' => $menuData,
            'format' => $contentTask->extra_data['format'] ?? 'html',
            'language' => $contentTask->extra_data['language'] ?? 'zh-CN',
            'currency' => $contentTask->extra_data['currency'] ?? 'CNY',
            'last_updated' => $contentTask->update_time,
            'download_url' => $this->generateMenuDownloadUrl($contentTask),
            'print_version_url' => $this->generatePrintMenuUrl($contentTask),
            'features' => [
                'interactive' => true,
                'searchable' => true,
                'multilingual' => false,
                'allergen_info' => true
            ],
            'share_url' => $this->generateShareUrl($contentTask),
            'redirect_url' => $device->redirect_url
        ];
    }

    /**
     * 更新设备状态
     *
     * @param NfcDevice $device
     * @param array $statusData
     */
    protected function updateDeviceStatus(NfcDevice $device, array $statusData): void
    {
        // 更新基础状态
        if (isset($statusData['status'])) {
            $device->setDeviceStatus((int)$statusData['status']);
        }

        // 更新电池电量
        if (isset($statusData['battery_level'])) {
            $device->updateBatteryLevel((int)$statusData['battery_level']);
        }

        // 更新心跳时间
        $device->updateHeartbeat();

        // 更新其他状态信息（扩展字段）
        $extraData = [];
        if (isset($statusData['signal_strength'])) {
            $extraData['signal_strength'] = (int)$statusData['signal_strength'];
        }
        if (isset($statusData['temperature'])) {
            $extraData['temperature'] = (float)$statusData['temperature'];
        }
        if (isset($statusData['location'])) {
            $extraData['location'] = $statusData['location'];
        }

        if (!empty($extraData)) {
            // 这里可以扩展设备模型添加extra_data字段存储额外信息
            // $device->extra_data = json_encode($extraData);
            // $device->save();
        }
    }

    /**
     * 检查设备健康状态
     *
     * @param NfcDevice $device
     * @param array $statusData
     * @return string
     */
    protected function checkDeviceHealth(NfcDevice $device, array $statusData): string
    {
        $healthIssues = [];

        // 检查电池电量
        if ($device->isLowBattery()) {
            $healthIssues[] = 'low_battery';
        }

        // 检查信号强度
        if (isset($statusData['signal_strength']) && $statusData['signal_strength'] < 30) {
            $healthIssues[] = 'weak_signal';
        }

        // 检查温度
        if (isset($statusData['temperature'])) {
            $temp = (float)$statusData['temperature'];
            if ($temp > 70 || $temp < -10) {
                $healthIssues[] = 'temperature_abnormal';
            }
        }

        // 检查错误信息
        if (!empty($statusData['error_code'])) {
            $healthIssues[] = 'device_error';
        }

        return empty($healthIssues) ? 'healthy' : implode(',', $healthIssues);
    }

    /**
     * 获取内容配置
     *
     * @param NfcDevice $device
     * @return array
     */
    protected function getContentConfig(NfcDevice $device): array
    {
        // 获取设备关联的内容模板配置
        if ($device->template_id && $device->template) {
            return [
                'template_id' => $device->template_id,
                'template_name' => $device->template->name,
                'template_config' => $device->template->config_data ?? []
            ];
        }

        return [];
    }

    /**
     * 清除设备配置缓存
     *
     * @param string $deviceCode
     */
    public function clearConfigCache(string $deviceCode): void
    {
        CacheService::clearDeviceConfig($deviceCode);
        CacheService::clearDeviceStatus($deviceCode);
    }

    /**
     * 批量更新设备状态
     *
     * @param array $devices 设备状态数据数组
     * @return array
     */
    public function batchUpdateDeviceStatus(array $devices): array
    {
        $results = [];

        foreach ($devices as $deviceData) {
            try {
                $result = $this->handleDeviceStatus(
                    $deviceData['device_code'],
                    $deviceData
                );
                $results[] = [
                    'success' => true,
                    'device_code' => $deviceData['device_code'],
                    'data' => $result
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'device_code' => $deviceData['device_code'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 获取设备统计信息
     *
     * @param int $merchantId
     * @return array
     */
    public function getDeviceStats(int $merchantId): array
    {
        $onlineDevices = NfcDevice::getOnlineDevices($merchantId);
        $offlineDevices = NfcDevice::getOfflineDevices($merchantId);
        $totalDevices = NfcDevice::getByMerchantId($merchantId);

        return [
            'total' => count($totalDevices),
            'online' => count($onlineDevices),
            'offline' => count($offlineDevices),
            'maintenance' => NfcDevice::where('merchant_id', $merchantId)
                ->where('status', NfcDevice::STATUS_MAINTENANCE)
                ->count()
        ];
    }

    /**
     * 创建内容生成任务
     *
     * @param NfcDevice $device
     * @param string $type
     * @param User $user
     * @return ContentTask
     */
    protected function createContentGenerationTask(NfcDevice $device, string $type, User $user): ContentTask
    {
        // 检查是否已有进行中的任务
        $existingTask = ContentTask::where('device_id', $device->id)
            ->where('type', $type)
            ->whereIn('status', ['pending', 'processing'])
            ->find();

        if ($existingTask) {
            return $existingTask;
        }

        // 创建新任务
        $task = ContentTask::create([
            'device_id' => $device->id,
            'merchant_id' => $device->merchant_id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $this->getDefaultContentTitle($type),
            'description' => $this->getDefaultContentDescription($type),
            'status' => 'pending',
            'priority' => 'normal'
        ]);

        // 触发异步生成任务
        $this->triggerContentGeneration($task);

        return $task;
    }

    /**
     * 检查内容文件是否存在
     *
     * @param string $url
     * @return bool
     */
    protected function checkContentFileExists(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // 如果是本地文件
        if (strpos($url, 'http') !== 0) {
            return file_exists($url);
        }

        // 如果是远程文件，检查HTTP状态
        $headers = @get_headers($url);
        return $headers && strpos($headers[0], '200') !== false;
    }

    /**
     * 生成分享链接
     *
     * @param ContentTask $contentTask
     * @return string
     */
    protected function generateShareUrl(ContentTask $contentTask): string
    {
        return config('app.domain') . '/share/content/' . $contentTask->id;
    }

    /**
     * 生成优惠券代码
     *
     * @return string
     */
    protected function generateCouponCode(): string
    {
        return 'CPN' . date('Ymd') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 格式化电话号码
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // 格式化中国手机号码
        if (preg_match('/^(\d{3})(\d{4})(\d{4})$/', $phone, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        return $phone;
    }

    /**
     * 生成vCard联系人数据
     *
     * @param object $merchant
     * @return string
     */
    protected function generateVCardData($merchant): string
    {
        $vcard = "BEGIN:VCARD\n";
        $vcard .= "VERSION:3.0\n";
        $vcard .= "FN:" . $merchant->name . "\n";
        $vcard .= "ORG:" . $merchant->name . "\n";

        if ($merchant->contact_phone) {
            $vcard .= "TEL:" . $merchant->contact_phone . "\n";
        }

        if ($merchant->email) {
            $vcard .= "EMAIL:" . $merchant->email . "\n";
        }

        if ($merchant->website) {
            $vcard .= "URL:" . $merchant->website . "\n";
        }

        if ($merchant->address) {
            $vcard .= "ADR:;;" . $merchant->address . ";;;\n";
        }

        $vcard .= "END:VCARD\n";

        return $vcard;
    }

    /**
     * 生成联系人二维码
     *
     * @param string $vCardData
     * @return string
     */
    protected function generateContactQrCode(string $vCardData): string
    {
        return config('app.domain') . '/qr/contact/' . base64_encode($vCardData);
    }

    /**
     * 生成导航链接
     *
     * @param object $merchant
     * @return string
     */
    protected function generateNavigationUrl($merchant): string
    {
        if ($merchant->latitude && $merchant->longitude) {
            return "https://maps.google.com/maps?q={$merchant->latitude},{$merchant->longitude}";
        }

        return "https://maps.google.com/maps?q=" . urlencode($merchant->address);
    }

    /**
     * 解析营业时间
     *
     * @param string $businessHours
     * @return array
     */
    protected function parseBusinessHours(string $businessHours): array
    {
        // 简单的营业时间解析，可以根据实际格式进行调整
        if (empty($businessHours)) {
            return [];
        }

        return [
            'raw' => $businessHours,
            'is_24_hours' => strpos($businessHours, '24小时') !== false,
            'is_closed' => strpos($businessHours, '暂停营业') !== false,
        ];
    }

    /**
     * 解析菜单内容
     *
     * @param ContentTask $contentTask
     * @return array
     */
    protected function parseMenuContent(ContentTask $contentTask): array
    {
        // 根据内容类型解析菜单数据
        $extraData = $contentTask->extra_data ?? [];

        return [
            'categories' => $extraData['categories'] ?? [],
            'items_count' => $extraData['items_count'] ?? 0,
            'price_range' => $extraData['price_range'] ?? '',
            'special_offers' => $extraData['special_offers'] ?? [],
            'dietary_options' => $extraData['dietary_options'] ?? []
        ];
    }

    /**
     * 生成菜单下载链接
     *
     * @param ContentTask $contentTask
     * @return string
     */
    protected function generateMenuDownloadUrl(ContentTask $contentTask): string
    {
        return config('app.domain') . '/download/menu/' . $contentTask->id;
    }

    /**
     * 生成打印版菜单链接
     *
     * @param ContentTask $contentTask
     * @return string
     */
    protected function generatePrintMenuUrl(ContentTask $contentTask): string
    {
        return config('app.domain') . '/print/menu/' . $contentTask->id;
    }

    /**
     * 获取默认内容标题
     *
     * @param string $type
     * @return string
     */
    protected function getDefaultContentTitle(string $type): string
    {
        $titles = [
            'video' => '精彩视频内容',
            'menu' => '精美菜单',
            'image' => '精美图片'
        ];

        return $titles[$type] ?? '内容';
    }

    /**
     * 获取默认内容描述
     *
     * @param string $type
     * @return string
     */
    protected function getDefaultContentDescription(string $type): string
    {
        $descriptions = [
            'video' => '系统自动生成的视频内容',
            'menu' => '系统自动生成的菜单内容',
            'image' => '系统自动生成的图片内容'
        ];

        return $descriptions[$type] ?? '系统自动生成的内容';
    }

    /**
     * 触发内容生成
     *
     * @param ContentTask $task
     */
    protected function triggerContentGeneration(ContentTask $task): void
    {
        // 这里可以调用队列系统或异步服务来生成内容
        // 例如：Queue::push('ContentGenerationJob', ['task_id' => $task->id]);

        // 暂时标记为处理中
        $task->status = 'processing';
        $task->save();

        Log::info('触发内容生成任务', [
            'task_id' => $task->id,
            'type' => $task->type,
            'device_id' => $task->device_id
        ]);
    }

    /**
     * 从缓存获取用户信息
     *
     * @param string $openid
     * @return User|null
     */
    protected function getUserFromCache(string $openid): ?User
    {
        // 先从缓存获取
        $userData = CacheService::getUserByOpenid($openid);
        if ($userData !== null) {
            $user = new User();
            $user->data($userData);
            return $user;
        }

        // 从数据库查询
        $user = User::where('openid', $openid)->find();
        if ($user) {
            // 缓存用户信息
            CacheService::setUserByOpenid($openid, $user->toArray());
            return $user;
        }

        return null;
    }

    /**
     * 从缓存获取设备内容
     *
     * @param int $deviceId
     * @param string $type
     * @return ContentTask|null
     */
    protected function getDeviceContentFromCache(int $deviceId, string $type): ?ContentTask
    {
        // 先从缓存获取
        $contentData = CacheService::getDeviceContent($deviceId, $type);
        if ($contentData !== null) {
            $contentTask = new ContentTask();
            $contentTask->data($contentData);
            return $contentTask;
        }

        // 从数据库查询
        $contentTask = ContentTask::where('device_id', $deviceId)
            ->where('type', $type)
            ->where('status', 'completed')
            ->order('create_time', 'desc')
            ->find();

        if ($contentTask) {
            // 缓存内容信息
            CacheService::setDeviceContent($deviceId, $type, $contentTask->toArray());
            return $contentTask;
        }

        return null;
    }

    /**
     * 从缓存获取商家优惠券
     *
     * @param int $merchantId
     * @return array
     */
    protected function getMerchantCouponsFromCache(int $merchantId): array
    {
        // 先从缓存获取
        $coupons = CacheService::getMerchantCoupons($merchantId);
        if ($coupons !== null) {
            return $coupons;
        }

        // 从数据库查询
        $coupons = Coupon::getAvailableCoupons($merchantId);

        // 缓存优惠券信息
        CacheService::setMerchantCoupons($merchantId, $coupons);

        return $coupons;
    }

    /**
     * 处理团购跳转触发
     *
     * @param NfcDevice $device
     * @param User $user
     * @return array
     */
    protected function handleGroupBuyTrigger(NfcDevice $device, User $user): array
    {
        // 检查团购配置
        if (empty($device->group_buy_config)) {
            throw new ValidateException('设备未配置团购信息');
        }

        // 使用团购服务处理
        $groupBuyService = new GroupBuyService();

        // 解析团购配置
        $config = $groupBuyService->parseGroupBuyConfig($device->group_buy_config);

        // 应用动态规则（时间感知）
        $config = $groupBuyService->resolveDynamicConfig($config);

        // 验证配置完整性
        $validation = $groupBuyService->validateGroupBuyConfig($config);
        if (!$validation['valid']) {
            throw new ValidateException('团购配置不完整：' . implode(', ', $validation['errors']));
        }

        // 生成跳转URL
        try {
            $redirectUrl = $groupBuyService->generateRedirectUrl([
                'platform' => $config['platform'],
                'deal_id' => $config['deal_id'] ?? '',
                'merchant_id' => $device->merchant_id,
                'device_id' => $device->id,
                'custom_url' => $config['custom_url'] ?? ''
            ]);
        } catch (\Exception $e) {
            Log::error('生成团购跳转URL失败', [
                'device_id' => $device->id,
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            throw new ValidateException('生成跳转链接失败：' . $e->getMessage());
        }

        // 记录跳转
        $groupBuyService->recordRedirect(
            $device->id,
            $user->id,
            $config['platform'],
            $redirectUrl,
            ['deal_id' => $config['deal_id'] ?? null]
        );

        // 格式化团购信息
        $dealInfo = $groupBuyService->formatDealInfo($config);

        return [
            'type' => 'group_buy',
            'action' => 'redirect',
            'redirect_url' => $redirectUrl,
            'deal_info' => $dealInfo,
            'platform' => $config['platform'],
            'platform_name' => $dealInfo['platform_name'],
            'tips' => '即将跳转到' . $dealInfo['platform_name'] . '团购页面'
        ];
    }

    /**
     * 性能监控 - 记录方法执行时间
     *
     * @param string $method
     * @param float $startTime
     * @param array $context
     */
    protected function logPerformance(string $method, float $startTime, array $context = []): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // 毫秒

        // 如果执行时间超过500ms，记录警告
        if ($executionTime > 500) {
            Log::warning('NFC服务方法执行缓慢', array_merge([
                'method' => $method,
                'execution_time' => $executionTime,
                'threshold' => 500
            ], $context));
        } else {
            Log::debug('NFC服务性能', array_merge([
                'method' => $method,
                'execution_time' => $executionTime
            ], $context));
        }
    }
}