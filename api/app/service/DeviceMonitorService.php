<?php
declare(strict_types=1);

namespace app\service;

use app\model\NfcDevice;
use app\model\DeviceAlert;
use app\model\Merchant;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;

/**
 * 设备监控服务
 * 负责监控设备心跳、检测离线状态、触发告警
 */
class DeviceMonitorService
{
    /**
     * 心跳超时时间（秒）
     */
    const HEARTBEAT_TIMEOUT = 300; // 5分钟

    /**
     * 告警冷却时间（秒）
     */
    const ALERT_COOLDOWN = 3600; // 1小时内不重复告警

    /**
     * 告警级别
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * 检查所有设备的在线状态
     *
     * @return array 检查结果统计
     */
    public static function checkAllDevices(): array
    {
        $startTime = microtime(true);
        Log::info('开始检查所有设备在线状态');

        // 获取所有激活的设备
        $devices = NfcDevice::where('status', 'active')
            ->field('id, device_code, merchant_id, name, last_heartbeat_time, status, priority')
            ->select()
            ->toArray();

        $stats = [
            'total' => count($devices),
            'online' => 0,
            'offline' => 0,
            'alerts_triggered' => 0,
            'errors' => 0,
            'execution_time' => 0
        ];

        foreach ($devices as $device) {
            try {
                $isOffline = self::checkDeviceHeartbeat($device);

                if ($isOffline) {
                    $stats['offline']++;
                    // 检查是否需要触发告警
                    if (self::shouldTriggerAlert($device)) {
                        self::triggerOfflineAlert($device);
                        $stats['alerts_triggered']++;
                    }
                } else {
                    $stats['online']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('检查设备状态失败', [
                    'device_id' => $device['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $stats['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('设备状态检查完成', $stats);

        return $stats;
    }

    /**
     * 检查单个设备心跳
     *
     * @param array $device 设备信息
     * @return bool 是否离线
     */
    protected static function checkDeviceHeartbeat(array $device): bool
    {
        $lastHeartbeat = $device['last_heartbeat_time'];

        // 如果从未有心跳记录，视为离线
        if (empty($lastHeartbeat)) {
            return true;
        }

        // 计算距离上次心跳的时间
        $lastHeartbeatTimestamp = strtotime($lastHeartbeat);
        $elapsed = time() - $lastHeartbeatTimestamp;

        // 超过超时时间视为离线
        if ($elapsed > self::HEARTBEAT_TIMEOUT) {
            // 更新设备状态为离线
            self::updateDeviceStatus($device['id'], 'offline');
            return true;
        }

        // 如果设备当前标记为离线，但心跳正常，更新为在线
        if ($device['status'] === 'offline') {
            self::updateDeviceStatus($device['id'], 'online');
        }

        return false;
    }

    /**
     * 更新设备在线状态
     *
     * @param int $deviceId 设备ID
     * @param string $status 状态 online/offline
     */
    protected static function updateDeviceStatus(int $deviceId, string $status): void
    {
        NfcDevice::where('id', $deviceId)->update([
            'online_status' => $status,
            'update_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 判断是否应该触发告警
     *
     * @param array $device 设备信息
     * @return bool 是否应该触发告警
     */
    protected static function shouldTriggerAlert(array $device): bool
    {
        $cacheKey = "device_alert_cooldown:{$device['id']}";

        // 检查是否在冷却期内
        if (Cache::has($cacheKey)) {
            return false;
        }

        return true;
    }

    /**
     * 触发设备离线告警
     *
     * @param array $device 设备信息
     */
    protected static function triggerOfflineAlert(array $device): void
    {
        // 获取商家信息
        $merchant = Merchant::find($device['merchant_id']);
        if (!$merchant) {
            Log::warning('设备对应的商家不存在', ['device_id' => $device['id']]);
            return;
        }

        // 确定告警级别
        $level = self::determineAlertLevel($device);

        // 创建告警记录
        $alert = DeviceAlert::create([
            'device_id' => $device['id'],
            'merchant_id' => $device['merchant_id'],
            'alert_type' => 'offline',
            'level' => $level,
            'title' => '设备离线告警',
            'message' => sprintf(
                '设备"%s"（编号：%s）已离线，可能影响正常使用',
                $device['name'] ?? '未命名设备',
                $device['device_code']
            ),
            'detail' => json_encode([
                'device_code' => $device['device_code'],
                'device_name' => $device['name'] ?? '',
                'last_heartbeat' => $device['last_heartbeat_time'],
                'offline_duration' => self::getOfflineDuration($device['last_heartbeat_time'])
            ]),
            'status' => 'pending',
            'is_read' => 0,
            'create_time' => date('Y-m-d H:i:s')
        ]);

        // 设置告警冷却
        $cacheKey = "device_alert_cooldown:{$device['id']}";
        Cache::set($cacheKey, true, self::ALERT_COOLDOWN);

        // 发送通知
        self::sendAlertNotifications($alert, $merchant, $device);

        Log::info('设备离线告警已触发', [
            'alert_id' => $alert->id,
            'device_id' => $device['id'],
            'merchant_id' => $merchant->id,
            'level' => $level
        ]);
    }

    /**
     * 确定告警级别
     *
     * @param array $device 设备信息
     * @return string 告警级别
     */
    protected static function determineAlertLevel(array $device): string
    {
        $offlineMinutes = self::getOfflineDuration($device['last_heartbeat_time']);

        // 根据设备优先级和离线时长确定告警级别
        $priority = $device['priority'] ?? 'normal';

        if ($offlineMinutes >= 60) { // 离线超过1小时
            return $priority === 'high' ? self::LEVEL_CRITICAL : self::LEVEL_ERROR;
        } elseif ($offlineMinutes >= 30) { // 离线超过30分钟
            return $priority === 'high' ? self::LEVEL_ERROR : self::LEVEL_WARNING;
        } else {
            return self::LEVEL_WARNING;
        }
    }

    /**
     * 获取设备离线时长（分钟）
     *
     * @param string|null $lastHeartbeat 最后心跳时间
     * @return int 离线分钟数
     */
    protected static function getOfflineDuration(?string $lastHeartbeat): int
    {
        if (empty($lastHeartbeat)) {
            return 9999; // 从未在线
        }

        $elapsed = time() - strtotime($lastHeartbeat);
        return (int) floor($elapsed / 60);
    }

    /**
     * 发送告警通知
     *
     * @param DeviceAlert $alert 告警记录
     * @param Merchant $merchant 商家信息
     * @param array $device 设备信息
     */
    protected static function sendAlertNotifications(DeviceAlert $alert, Merchant $merchant, array $device): void
    {
        $offlineMinutes = self::getOfflineDuration($device['last_heartbeat_time']);

        // 通知内容
        $notificationData = [
            'alert_id' => $alert->id,
            'alert_type' => 'device_offline',
            'device_code' => $device['device_code'],
            'device_name' => $device['name'] ?? '未命名设备',
            'offline_duration' => $offlineMinutes,
            'level' => $alert->level
        ];

        try {
            // 1. 小程序模板消息（所有级别）
            self::sendMiniProgramNotification($merchant, $notificationData);

            // 2. 短信通知（仅ERROR和CRITICAL级别）
            if (in_array($alert->level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
                self::sendSmsNotification($merchant, $notificationData);
            }

            // 3. 邮件通知（仅CRITICAL级别）
            if ($alert->level === self::LEVEL_CRITICAL) {
                self::sendEmailNotification($merchant, $notificationData);
            }

            // 更新告警通知状态
            $alert->save([
                'notified_at' => date('Y-m-d H:i:s'),
                'notification_channels' => json_encode([
                    'miniprogram' => true,
                    'sms' => in_array($alert->level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL]),
                    'email' => $alert->level === self::LEVEL_CRITICAL
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('发送告警通知失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 发送小程序模板消息
     *
     * @param Merchant $merchant 商家信息
     * @param array $data 通知数据
     */
    protected static function sendMiniProgramNotification(Merchant $merchant, array $data): void
    {
        try {
            // 获取商家用户的微信OpenID
            $openid = self::getMerchantWechatOpenid($merchant->id);
            if (empty($openid)) {
                Log::warning('商家未绑定微信，无法发送模板消息', [
                    'merchant_id' => $merchant->id
                ]);
                return;
            }

            // 使用微信模板消息服务发送
            $wechatTemplateService = new \app\service\WechatTemplateService('miniprogram');

            $wechatTemplateService->sendDeviceAlertNotification($merchant->id, $openid, $data);

            Log::info('设备告警小程序消息发送成功', [
                'merchant_id' => $merchant->id,
                'device_code' => $data['device_code']
            ]);
        } catch (\Exception $e) {
            Log::error('发送设备告警小程序消息失败', [
                'merchant_id' => $merchant->id,
                'device_code' => $data['device_code'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取商家用户的微信OpenID
     *
     * @param int $merchantId 商家ID
     * @return string|null
     */
    protected static function getMerchantWechatOpenid(int $merchantId): ?string
    {
        // 从数据库获取商家的微信OpenID
        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            return null;
        }

        // 获取关联用户的微信OpenID
        $user = $merchant->user;
        if (!$user || empty($user->wechat_openid)) {
            return null;
        }

        return $user->wechat_openid;
    }

    /**
     * 发送短信通知
     *
     * @param Merchant $merchant 商家信息
     * @param array $data 通知数据
     */
    protected static function sendSmsNotification(Merchant $merchant, array $data): void
    {
        // TODO: 实现短信发送
        // 需要集成短信服务商API（如阿里云、腾讯云）

        if (empty($merchant->phone)) {
            Log::warning('商家手机号为空，无法发送短信', ['merchant_id' => $merchant->id]);
            return;
        }

        $message = sprintf(
            '【小魔推】您的设备"%s"已离线%d分钟，请及时处理。设备编号：%s',
            $data['device_name'],
            $data['offline_duration'],
            $data['device_code']
        );

        Log::info('发送短信通知', [
            'merchant_id' => $merchant->id,
            'phone' => $merchant->phone,
            'message' => $message
        ]);
    }

    /**
     * 发送邮件通知
     *
     * @param Merchant $merchant 商家信息
     * @param array $data 通知数据
     */
    protected static function sendEmailNotification(Merchant $merchant, array $data): void
    {
        // TODO: 实现邮件发送
        // 需要配置邮件服务

        if (empty($merchant->email)) {
            Log::warning('商家邮箱为空，无法发送邮件', ['merchant_id' => $merchant->id]);
            return;
        }

        Log::info('发送邮件通知', [
            'merchant_id' => $merchant->id,
            'email' => $merchant->email,
            'device_code' => $data['device_code']
        ]);
    }

    /**
     * 获取设备告警统计
     *
     * @param int $merchantId 商家ID
     * @param int $days 统计天数
     * @return array 统计数据
     */
    public static function getAlertStatistics(int $merchantId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // 告警总数
        $totalAlerts = DeviceAlert::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate)
            ->count();

        // 按级别统计
        $alertsByLevel = DeviceAlert::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate)
            ->field('level, COUNT(*) as count')
            ->group('level')
            ->select()
            ->toArray();

        // 未读告警数
        $unreadCount = DeviceAlert::where('merchant_id', $merchantId)
            ->where('is_read', 0)
            ->count();

        // 待处理告警数
        $pendingCount = DeviceAlert::where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->count();

        return [
            'total_alerts' => $totalAlerts,
            'unread_count' => $unreadCount,
            'pending_count' => $pendingCount,
            'by_level' => array_column($alertsByLevel, 'count', 'level'),
            'period_days' => $days
        ];
    }

    /**
     * 标记告警为已读
     *
     * @param int $alertId 告警ID
     * @param int $merchantId 商家ID（用于权限验证）
     * @return bool 是否成功
     */
    public static function markAsRead(int $alertId, int $merchantId): bool
    {
        $alert = DeviceAlert::where('id', $alertId)
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$alert) {
            return false;
        }

        $alert->save([
            'is_read' => 1,
            'read_time' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    /**
     * 处理告警
     *
     * @param int $alertId 告警ID
     * @param int $merchantId 商家ID（用于权限验证）
     * @param string $action 处理动作: resolve/ignore
     * @param string $remark 处理备注
     * @return bool 是否成功
     */
    public static function handleAlert(int $alertId, int $merchantId, string $action, string $remark = ''): bool
    {
        $alert = DeviceAlert::where('id', $alertId)
            ->where('merchant_id', $merchantId)
            ->find();

        if (!$alert) {
            return false;
        }

        $status = $action === 'resolve' ? 'resolved' : 'ignored';

        $alert->save([
            'status' => $status,
            'handled_at' => date('Y-m-d H:i:s'),
            'handle_remark' => $remark,
            'is_read' => 1
        ]);

        // 如果是解决告警，清除冷却缓存（允许再次告警）
        if ($action === 'resolve') {
            $cacheKey = "device_alert_cooldown:{$alert->device_id}";
            Cache::delete($cacheKey);
        }

        return true;
    }
}
