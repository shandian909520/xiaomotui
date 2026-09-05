<?php
declare (strict_types = 1);

namespace app\action\plugins;

use app\action\AbstractActionPlugin;
use app\common\Lock;
use app\model\Coupon;
use app\model\CouponUser;
use app\model\TaskAction;
use app\model\TaskInstance;
use app\model\User;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 领取优惠券动作（系统直判）
 * start 内直接调用发券逻辑（库存/限领校验），领取成功 verify 返回 true
 */
class ClaimCouponAction extends AbstractActionPlugin
{
    /** 任务引擎发券来源标识 */
    public const SOURCE_TASK_ENGINE = 'task_engine';

    public static function key(): string
    {
        return 'claim_coupon';
    }

    public function meta(): array
    {
        return [
            'name'        => '领取优惠券',
            'icon'        => '🎫',
            'description' => '用户点击领取商家优惠券，系统自动校验库存与限领，领取成功即完成任务',
            'platform'    => '通用',
        ];
    }

    public function capability(): array
    {
        return [
            'verify_method'         => 'system',
            'fallback_verify_method'=> null,
            'need_proof'            => false,
            'env'                   => ['wechat_h5', 'browser'],
        ];
    }

    public function renderCard(TaskInstance $instance, TaskAction $action): array
    {
        $coupon = $this->findCoupon($action, false);
        if (!$coupon) {
            return [
                'jump_type'   => 'none',
                'scheme_url'  => null,
                'qrcode_url'  => null,
                'copy_text'   => null,
                'guide_steps' => ['优惠券配置无效，请联系商家'],
            ];
        }

        return [
            'jump_type'   => 'none',
            'scheme_url'  => null,
            'qrcode_url'  => null,
            'copy_text'   => $coupon->name,
            'guide_steps' => [
                '1. 点击"立即领取"按钮领取优惠券',
                '2. 领取成功后任务自动完成',
            ],
        ];
    }

    public function start(TaskInstance $instance, TaskAction $action): array
    {
        $card = $this->renderCard($instance, $action);
        try {
            // start 阶段即尝试发券（系统直判类动作，领取即完成主体动作）
            $result = $this->claim($instance, $action);
            $card['claim_result'] = $result;
        } catch (\Exception $e) {
            // 发券失败不阻断流程，verify 阶段可重试
            $card['claim_result'] = ['success' => false, 'message' => $e->getMessage()];
        }
        return $card;
    }

    public function verify(TaskInstance $instance, TaskAction $action, array $payload = []): bool
    {
        // 已领取过（含本来源）直接判定完成
        $userId = $this->resolveUserId($instance);
        if (!$userId) {
            return false;
        }
        $coupon = $this->findCoupon($action, false);
        if (!$coupon) {
            return false;
        }
        if (CouponUser::where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->where('received_source', self::SOURCE_TASK_ENGINE)
            ->count() > 0
        ) {
            return true;
        }
        // 未领取则再尝试一次
        try {
            $result = $this->claim($instance, $action);
            return (bool)$result['success'];
        } catch (\Exception $e) {
            Log::warning('任务引擎领券验证失败', [
                'instance_id' => $instance->id,
                'action_id'   => $action->id,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function rollback(TaskInstance $instance, TaskAction $action): void
    {
        // 回滚：回收本任务发放的券（未使用的置为过期）
        $coupon = $this->findCoupon($action, false);
        if (!$coupon) {
            return;
        }
        $updated = CouponUser::where('coupon_id', $coupon->id)
            ->where('received_source', self::SOURCE_TASK_ENGINE)
            ->where('use_status', CouponUser::STATUS_UNUSED)
            ->update(['use_status' => CouponUser::STATUS_EXPIRED, 'update_time' => date('Y-m-d H:i:s')]);
        if ($updated > 0) {
            // 回收库存
            Coupon::where('id', $coupon->id)->inc('total_count', $updated)->update();
        }
    }

    /**
     * 发券核心逻辑（参照 NfcService::handleCouponTrigger 的防超发写法）
     */
    protected function claim(TaskInstance $instance, TaskAction $action): array
    {
        $coupon = $this->findCoupon($action, true);
        $userId = $this->resolveUserId($instance);
        if (!$userId) {
            throw new ValidateException('用户未登录，无法领取优惠券');
        }

        $lockKey = 'task_coupon_lock:coupon:' . $coupon->id;
        $lockToken = null;
        try {
            $lockToken = Lock::acquire($lockKey, 10, 3);
            if ($lockToken === null) {
                throw new ValidateException('优惠券正在发放中，请稍后再试');
            }

            // 限领校验
            $received = CouponUser::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            $limit = (int)($coupon->per_user_limit ?: 1);
            if ($coupon->per_user_limit && $received >= $limit) {
                Lock::release($lockKey, $lockToken);
                throw new ValidateException('已达每人限领数量');
            }

            // 原子减库存（total_count 即剩余量，与 NFC 发券路径保持一致）
            $affected = Coupon::where('id', $coupon->id)
                ->where('total_count', '>', 0)
                ->dec('total_count', 1);
            if ($affected === 0) {
                throw new ValidateException('优惠券已抢完');
            }

            $couponCode = 'CPN' . date('Ymd') . str_pad((string)mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $record = CouponUser::create([
                'coupon_id'        => $coupon->id,
                'user_id'          => $userId,
                'coupon_code'      => $couponCode,
                'use_status'       => CouponUser::STATUS_UNUSED,
                'received_source'  => self::SOURCE_TASK_ENGINE,
                'device_id'        => $instance->device_id,
            ]);
            Lock::release($lockKey, $lockToken);

            return [
                'success'        => true,
                'coupon_user_id' => $record->id,
                'coupon_id'      => $coupon->id,
                'coupon_code'    => $couponCode,
                'title'          => $coupon->name,
                'valid_until'    => $coupon->end_time,
            ];
        } catch (ValidateException $e) {
            Lock::release($lockKey, $lockToken ?? '');
            throw $e;
        } catch (\Exception $e) {
            Lock::release($lockKey, $lockToken ?? '');
            throw new ValidateException('优惠券领取失败：' . $e->getMessage());
        }
    }

    /**
     * 查找动作配置指定的优惠券
     */
    protected function findCoupon(TaskAction $action, bool $throw = true): ?Coupon
    {
        $couponId = (int)($this->config($action, 'coupon_id', 0) ?: 0);
        if ($couponId <= 0 && $throw) {
            throw new ValidateException('动作未配置优惠券');
        }
        if ($couponId <= 0) {
            return null;
        }
        $coupon = Coupon::where('id', $couponId)
            ->where('status', Coupon::STATUS_ENABLED)
            ->where('start_time', '<=', date('Y-m-d H:i:s'))
            ->where('end_time', '>=', date('Y-m-d H:i:s'))
            ->find();
        if (!$coupon && $throw) {
            throw new ValidateException('优惠券不存在或已失效');
        }
        return $coupon;
    }

    /**
     * 从实例解析用户ID（优先 user_id，其次按 openid 查）
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
