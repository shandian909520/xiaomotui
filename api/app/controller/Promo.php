<?php
declare(strict_types=1);

namespace app\controller;

use app\model\NfcDevice;
use app\model\DeviceTrigger;
use app\model\PromoPublish;
use app\model\Coupon;
use app\model\CouponUser;
use app\service\CacheService;
use think\Request;
use think\response\Json;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Cache;

/**
 * 推广发布确认控制器
 * 消费者碰NFC后确认发布领取奖励
 */
class Promo extends BaseController
{
    /**
     * 确认发布 - 消费者点击"我已发布"后调用
     * POST /api/promo/confirm-publish
     */
    public function confirmPublish(Request $request): Json
    {
        try {
            $deviceCode = $request->param('device_code', '');
            $platform = $request->param('platform', '');
            $triggerId = (int)$request->param('trigger_id', 0);
            $openid = $request->param('openid', '');

            // 参数验证
            if (empty($deviceCode) || empty($platform) || $triggerId <= 0) {
                return $this->error('参数不完整', 400);
            }

            if (!in_array($platform, ['douyin', 'kuaishou'])) {
                return $this->error('不支持的平台', 400);
            }

            // 用户标识：优先用openid，否则用IP
            $userIdentifier = $openid ?: ('ip_' . md5($request->ip()));

            // 查找设备
            $device = NfcDevice::findByDeviceCode($deviceCode);
            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            // 验证触发记录存在
            $trigger = DeviceTrigger::where('id', $triggerId)
                ->where('device_id', $device->id)
                ->find();

            if (!$trigger) {
                return $this->error('触发记录不存在', 404);
            }

            // === 防薅验证 ===

            // 1. 触发时间限制：触发后24小时内才能领取
            $triggerTime = strtotime($trigger->trigger_time ?? $trigger->create_time);
            if (time() - $triggerTime > 86400) {
                return $this->error('领取已过期，请重新扫码', 400);
            }

            // 2. 最少等待时间：触发后至少60秒才能确认（给用户下载视频+发布的时间）
            if (time() - $triggerTime < 60) {
                $remaining = 60 - (time() - $triggerTime);
                return $this->error("请先发布视频，{$remaining}秒后可领取奖励", 400);
            }

            // 3. 同一用户标识每天最多领取3次（跨设备）
            $todayStart = date('Y-m-d 00:00:00');
            $todayClaimCount = PromoPublish::where('user_openid', $userIdentifier)
                ->where('create_time', '>=', $todayStart)
                ->count();
            if ($todayClaimCount >= 3) {
                return $this->error('今日领取次数已达上限', 400);
            }

            // 检查是否已领取过奖励（防重复）
            $existingPublish = PromoPublish::where('trigger_id', $triggerId)
                ->where('platform', $platform)
                ->find();

            if ($existingPublish) {
                // 已领取，返回已有信息
                $couponInfo = null;
                if ($existingPublish->coupon_user_id) {
                    $couponUser = CouponUser::with(['coupon'])->find($existingPublish->coupon_user_id);
                    if ($couponUser) {
                        $couponInfo = [
                            'coupon_code' => $couponUser->coupon_code,
                            'title' => $couponUser->coupon->title ?? '',
                            'discount_type' => $couponUser->coupon->discount_type ?? '',
                            'discount_value' => $couponUser->coupon->discount_value ?? 0,
                            'valid_until' => $couponUser->coupon->end_time ?? '',
                        ];
                    }
                }

                return $this->success([
                    'status' => 'already_claimed',
                    'message' => '您已领取过该奖励',
                    'coupon' => $couponInfo,
                    'publish_id' => $existingPublish->id,
                ], '已领取过奖励');
            }

            // 获取奖励优惠券
            $rewardCouponId = $device->promo_reward_coupon_id;
            $couponUserRecord = null;
            $couponInfo = null;

            if ($rewardCouponId) {
                // 使用分布式锁防止并发
                $lockKey = 'promo_coupon_lock:' . $triggerId . ':' . $platform;
                $lock = Cache::lock($lockKey, 10);

                try {
                    if (!$lock->get(3)) {
                        return $this->error('正在处理中，请稍后再试', 429);
                    }

                    // 查询优惠券
                    $coupon = Coupon::where('id', $rewardCouponId)
                        ->where('status', 1)
                        ->where('start_time', '<=', date('Y-m-d H:i:s'))
                        ->where('end_time', '>=', date('Y-m-d H:i:s'))
                        ->where('total_count', '>', 0)
                        ->lock(true)
                        ->find();

                    if ($coupon) {
                        // 原子性减库存
                        $affected = Coupon::where('id', $coupon->id)
                            ->where('total_count', '>', 0)
                            ->dec('total_count', 1);

                        if ($affected > 0) {
                            // 生成优惠券码
                            $couponCode = 'PRM' . date('Ymd') . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

                            // 创建用户优惠券记录
                            $couponUserRecord = CouponUser::create([
                                'coupon_id' => $coupon->id,
                                'user_id' => $trigger->user_id ?? 0,
                                'coupon_code' => $couponCode,
                                'use_status' => 0,
                                'received_source' => 'promo_publish',
                                'device_id' => $device->id,
                            ]);

                            $couponInfo = [
                                'coupon_code' => $couponCode,
                                'title' => $coupon->title,
                                'description' => $coupon->description,
                                'discount_type' => $coupon->discount_type,
                                'discount_value' => $coupon->discount_value,
                                'min_amount' => $coupon->min_amount,
                                'valid_until' => $coupon->end_time,
                            ];
                        }
                    }

                    $lock->release();
                } catch (\Exception $e) {
                    $lock->release();
                    Log::error('推广优惠券发放失败', [
                        'trigger_id' => $triggerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 创建推广发布记录
            $promoPublish = PromoPublish::create([
                'trigger_id' => $triggerId,
                'device_id' => $device->id,
                'merchant_id' => $device->merchant_id,
                'user_id' => $trigger->user_id ?? null,
                'user_openid' => $userIdentifier,
                'platform' => $platform,
                'status' => PromoPublish::STATUS_CLAIMED,
                'coupon_user_id' => $couponUserRecord ? $couponUserRecord->id : null,
                'client_ip' => $request->ip(),
            ]);

            Log::info('推广发布确认成功', [
                'publish_id' => $promoPublish->id,
                'trigger_id' => $triggerId,
                'platform' => $platform,
                'device_id' => $device->id,
                'has_coupon' => $couponUserRecord !== null,
            ]);

            return $this->success([
                'status' => 'success',
                'message' => $couponInfo ? '发布成功，优惠券已发放！' : '发布确认成功！',
                'publish_id' => $promoPublish->id,
                'coupon' => $couponInfo,
            ], '发布确认成功');

        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('推广发布确认失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('操作失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 查询奖励领取状态
     * GET /api/promo/reward-status
     */
    public function rewardStatus(Request $request): Json
    {
        try {
            $triggerId = (int)$request->param('trigger_id', 0);

            if ($triggerId <= 0) {
                return $this->error('参数不完整', 400);
            }

            $publishes = PromoPublish::where('trigger_id', $triggerId)
                ->select();

            $result = [];
            foreach ($publishes as $publish) {
                $item = [
                    'platform' => $publish->platform,
                    'status' => $publish->status,
                    'create_time' => $publish->create_time,
                    'coupon' => null,
                ];

                if ($publish->coupon_user_id) {
                    $couponUser = CouponUser::with(['coupon'])->find($publish->coupon_user_id);
                    if ($couponUser) {
                        $item['coupon'] = [
                            'coupon_code' => $couponUser->coupon_code,
                            'title' => $couponUser->coupon->title ?? '',
                            'use_status' => $couponUser->use_status,
                        ];
                    }
                }

                $result[] = $item;
            }

            return $this->success([
                'trigger_id' => $triggerId,
                'publishes' => $result,
                'total' => count($result),
            ], '查询成功');

        } catch (\Exception $e) {
            Log::error('查询奖励状态失败', [
                'trigger_id' => $triggerId ?? 0,
                'error' => $e->getMessage(),
            ]);
            return $this->error('查询失败：' . $e->getMessage(), 500);
        }
    }
}
