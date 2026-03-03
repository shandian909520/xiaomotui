<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\Coupon as CouponModel;
use app\model\CouponUser;
use think\facade\Db;

/**
 * 优惠券控制器
 */
class Coupon extends BaseController
{
    /**
     * 领取优惠券
     */
    public function receive()
    {
        $couponId = $this->request->param('coupon_id/d');
        $source = $this->request->param('source', CouponUser::SOURCE_PROMOTION);
        $deviceId = $this->request->param('device_id/d', 0);
        
        if (!$couponId) {
            return $this->error('参数错误');
        }

        try {
            // 开启事务
            Db::startTrans();

            // 1. 查询优惠券
            $coupon = CouponModel::where('id', $couponId)
                ->where('status', CouponModel::STATUS_ENABLED)
                ->lock(true) // 悲观锁防止超发
                ->find();

            if (!$coupon) {
                Db::rollback();
                return $this->error('优惠券不存在或已下架');
            }

            // 2. 检查有效期
            $now = time();
            $startTime = is_numeric($coupon->start_time) ? $coupon->start_time : strtotime($coupon->start_time);
            $endTime = is_numeric($coupon->end_time) ? $coupon->end_time : strtotime($coupon->end_time);

            if ($now < $startTime) {
                Db::rollback();
                return $this->error('活动未开始');
            }
            if ($now > $endTime) {
                Db::rollback();
                return $this->error('活动已结束');
            }

            // 3. 检查库存
            if ($coupon->total_count > 0 && $coupon->used_count >= $coupon->total_count) {
                Db::rollback();
                return $this->error('优惠券已领完');
            }

            // 4. 检查每人限领
            $userCount = CouponUser::where('coupon_id', $couponId)
                ->where('user_id', $this->userId)
                ->count();

            if ($coupon->per_user_limit > 0 && $userCount >= $coupon->per_user_limit) {
                Db::rollback();
                return $this->error('您已达到领取上限');
            }

            // 5. 创建领取记录
            $couponUser = new CouponUser();
            $couponUser->coupon_id = $couponId;
            $couponUser->user_id = $this->userId;
            $couponUser->coupon_code = $this->generateCouponCode($couponId);
            $couponUser->use_status = CouponUser::STATUS_UNUSED;
            $couponUser->received_source = $source;
            $couponUser->device_id = $deviceId;
            $couponUser->save();

            // 6. 更新优惠券已领数量
            $coupon->used_count = $coupon->used_count + 1;
            $coupon->save();

            Db::commit();

            return $this->success('领取成功', $couponUser);

        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('领取失败: ' . $e->getMessage());
        }
    }

    /**
     * 我的优惠券列表
     */
    public function my()
    {
        $status = $this->request->param('status/d');
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);

        $query = CouponUser::where('user_id', $this->request->userId)
            ->with(['coupon' => function($query) {
                $query->field('id,name,type,value,min_amount,start_time,end_time');
            }]);

        if (!is_null($status) && in_array($status, [0, 1, 2])) {
            $query->where('use_status', $status);
        }

        $list = $query->order('create_time', 'desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        return $this->success($list, '获取成功');
    }

    /**
     * 使用优惠券
     */
    public function use()
    {
        $id = $this->request->param('id/d'); // coupon_users.id
        $orderId = $this->request->param('order_id/d', 0);

        if (!$id) {
            return $this->error('参数错误');
        }

        $couponUser = CouponUser::where('id', $id)
            ->where('user_id', $this->userId)
            ->find();

        if (!$couponUser) {
            return $this->error('优惠券不存在');
        }

        if ($couponUser->use_status != CouponUser::STATUS_UNUSED) {
            return $this->error('优惠券状态不可用');
        }

        // 检查是否过期
        $coupon = CouponModel::find($couponUser->coupon_id);
        $now = time();
        $endTime = is_numeric($coupon->end_time) ? $coupon->end_time : strtotime($coupon->end_time);

        if ($now > $endTime) {
            $couponUser->use_status = CouponUser::STATUS_EXPIRED;
            $couponUser->save();
            return $this->error('优惠券已过期');
        }

        $couponUser->use_status = CouponUser::STATUS_USED;
        $couponUser->used_time = time();
        $couponUser->order_id = $orderId;
        $couponUser->save();

        return $this->success('使用成功');
    }

    /**
     * 生成优惠券核销码
     */
    private function generateCouponCode($couponId): string
    {
        // 简单的生成规则：时间戳 + 随机数 + 优惠券ID
        return date('YmdHis') . mt_rand(1000, 9999) . $couponId;
    }
}
