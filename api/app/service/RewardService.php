<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Coupon;
use app\model\CouponUser;
use app\model\TaskBundle;
use app\model\TaskInstance;
use app\model\User;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 任务奖励发放服务
 * 按 bundle->reward_type 分发：redpacket/coupon/points/none
 */
class RewardService
{
    /** 任务引擎奖励发券来源标识 */
    public const SOURCE_TASK_REWARD = 'task_reward';

    /**
     * 发放奖励（幂等：reward_status=ISSUED 直接返回）
     * 预算/库存/限领失败：reward_status=FAILED 并记录原因，抛异常由上层感知（不回滚任务完成状态）
     */
    public function issue(TaskInstance $instance): array
    {
        if ($instance->reward_status === TaskInstance::REWARD_ISSUED) {
            return $instance->reward_data ?? [];
        }

        $bundle = TaskBundle::find($instance->bundle_id);
        if (!$bundle) {
            throw new ValidateException('任务包不存在，无法发放奖励');
        }

        $config = $bundle->reward_config ?? [];

        try {
            switch ($bundle->reward_type) {
                case TaskBundle::REWARD_REDPACKET:
                    $result = $this->issueRedpacket($instance, $config);
                    break;
                case TaskBundle::REWARD_COUPON:
                    $result = $this->issueCoupon($instance, $config);
                    break;
                case TaskBundle::REWARD_POINTS:
                    $result = $this->issuePoints($instance, $config);
                    break;
                case TaskBundle::REWARD_NONE:
                default:
                    $instance->reward_status = TaskInstance::REWARD_SKIPPED;
                    $instance->reward_data = ['skipped' => true];
                    $instance->save();
                    return ['skipped' => true];
            }

            $instance->reward_status = TaskInstance::REWARD_ISSUED;
            $instance->reward_data = $result;
            $instance->save();

            Log::info('任务奖励发放成功', [
                'instance_id' => $instance->id,
                'reward_type' => $bundle->reward_type,
                'result'      => $result,
            ]);
            return $result;
        } catch (\Exception $e) {
            $instance->reward_status = TaskInstance::REWARD_FAILED;
            $instance->reward_data = [
                'error' => $e->getMessage(),
                'time'  => date('Y-m-d H:i:s'),
            ];
            $instance->save();

            Log::error('任务奖励发放失败', [
                'instance_id' => $instance->id,
                'reward_type' => $bundle->reward_type,
                'error'       => $e->getMessage(),
            ]);
            // 抛出让上层感知，但任务完成状态保持不变
            throw new ValidateException('奖励发放失败：' . $e->getMessage());
        }
    }

    /**
     * 现金红包：走微信现金红包服务
     */
    protected function issueRedpacket(TaskInstance $instance, array $config): array
    {
        $openid = (string)($instance->openid ?? '');
        if ($openid === '' || str_starts_with($openid, 'anonymous_')) {
            throw new ValidateException('用户未授权微信，无法发放红包');
        }
        // reward_config.amount 单位：元（兼容 redpacket_amount 键名）
        $amountYuan = (float)($config['amount'] ?? $config['redpacket_amount'] ?? 0);
        if ($amountYuan <= 0) {
            throw new ValidateException('红包金额未配置');
        }
        $amountFen = (int)round($amountYuan * 100);

        $service = new WechatRedpacketService();
        $result = $service->sendRedpacket(
            $openid,
            $amountFen,
            (int)$instance->merchant_id,
            (int)($config['activity_id'] ?? $config['redpacket_id'] ?? 0),
            (string)($config['wishing'] ?? '完成任务，奖励送上'),
            request()->ip()
        );
        if (empty($result['success'])) {
            throw new ValidateException((string)($result['message'] ?? '红包发送失败'));
        }
        return [
            'type'        => 'redpacket',
            'amount_yuan' => $amountYuan,
            'mch_billno'  => $result['data']['mch_billno'] ?? '',
            'send_listid' => $result['data']['send_listid'] ?? '',
        ];
    }

    /**
     * 优惠券：reward_config.coupon_id
     */
    protected function issueCoupon(TaskInstance $instance, array $config): array
    {
        $couponId = (int)($config['coupon_id'] ?? 0);
        if ($couponId <= 0) {
            throw new ValidateException('奖励优惠券未配置');
        }
        $userId = $this->resolveUserId($instance);
        if (!$userId) {
            throw new ValidateException('用户未登录，无法发放优惠券');
        }

        $lock = Cache::lock('task_reward_coupon:' . $couponId, 10);
        try {
            if (!$lock->get(3)) {
                throw new ValidateException('奖励发放中，请稍后再试');
            }

            $coupon = Coupon::where('id', $couponId)
                ->where('status', Coupon::STATUS_ENABLED)
                ->where('start_time', '<=', date('Y-m-d H:i:s'))
                ->where('end_time', '>=', date('Y-m-d H:i:s'))
                ->find();
            if (!$coupon) {
                throw new ValidateException('奖励优惠券不存在或已失效');
            }
            if ((int)$coupon->total_count <= 0) {
                throw new ValidateException('奖励优惠券库存不足');
            }

            // 限领校验（本奖励来源）
            $limit = (int)($coupon->per_user_limit ?: 1);
            $received = CouponUser::where('coupon_id', $couponId)
                ->where('user_id', $userId)
                ->count();
            if ($coupon->per_user_limit && $received >= $limit) {
                throw new ValidateException('已达优惠券每人限领数量');
            }

            // 原子减库存
            $affected = Coupon::where('id', $couponId)
                ->where('total_count', '>', 0)
                ->dec('total_count', 1);
            if ($affected === 0) {
                throw new ValidateException('奖励优惠券已抢完');
            }

            $couponCode = 'CPN' . date('Ymd') . str_pad((string)mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $record = CouponUser::create([
                'coupon_id'       => $couponId,
                'user_id'         => $userId,
                'coupon_code'     => $couponCode,
                'use_status'      => CouponUser::STATUS_UNUSED,
                'received_source' => self::SOURCE_TASK_REWARD,
                'device_id'       => $instance->device_id,
            ]);
            $lock->release();

            return [
                'type'           => 'coupon',
                'coupon_user_id' => $record->id,
                'coupon_id'      => $couponId,
                'coupon_code'    => $couponCode,
                'title'          => $coupon->name,
                'valid_until'    => $coupon->end_time,
            ];
        } catch (ValidateException $e) {
            $lock->release();
            throw $e;
        } catch (\Exception $e) {
            $lock->release();
            throw new ValidateException('优惠券发放失败：' . $e->getMessage());
        }
    }

    /**
     * 积分：reward_config.points，走 User::addPoints
     */
    protected function issuePoints(TaskInstance $instance, array $config): array
    {
        $points = (int)($config['points_amount'] ?? $config['points'] ?? 0);
        if ($points <= 0) {
            throw new ValidateException('奖励积分未配置');
        }
        $userId = $this->resolveUserId($instance);
        if (!$userId) {
            throw new ValidateException('用户未登录，无法发放积分');
        }
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在，无法发放积分');
        }
        if (!$user->addPoints($points, '碰一碰任务奖励 #' . $instance->id)) {
            throw new ValidateException('积分发放失败');
        }
        return [
            'type'   => 'points',
            'points' => $points,
        ];
    }

    /**
     * 从实例解析用户ID
     */
    protected function resolveUserId(TaskInstance $instance): ?int
    {
        if ($instance->user_id) {
            return (int)$instance->user_id;
        }
        if ($instance->openid) {
            $user = User::where('openid', $instance->openid)->find();
            if ($user) {
                return (int)$user->id;
            }
        }
        return null;
    }
}
