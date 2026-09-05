<?php
declare (strict_types = 1);

namespace app\service;

use app\model\LotteryActivity;
use app\model\LotteryPrize;
use app\model\LotteryRecord;
use app\model\NfcDevice;
use think\facade\Db;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 大转盘抽奖服务(模块6)
 *
 * 抽奖核心算法:
 *   1. 按概率分布(0~1)逐个奖项扫描,落点 → 中奖奖项
 *   2. 库存为 0 的奖项 → 视为"谢谢参与"(降级)
 *   3. 总概率应为 1.0(100%);如不足 1.0,剩余部分自动并入"谢谢参与"
 *   4. 扣库存采用乐观 SQL:UPDATE ... SET stock = stock - 1 WHERE id=? AND stock>0
 *      影响行数 = 0 表示库存不足,递归降级到下一个候选奖项
 *   5. 同一 user_hash 每天不超过 activity.daily_limit
 */
class LotteryService
{
    /**
     * 查找某设备的有效活动(优先设备级,其次商家级)
     */
    public function getActiveByDevice(int $deviceId): ?LotteryActivity
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $query = LotteryActivity::where('status', LotteryActivity::STATUS_ENABLED)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->order('id', 'desc');
        $rows = $query->select();
        if ($rows->isEmpty()) {
            return null;
        }
        // 优先 device_id 命中
        foreach ($rows as $row) {
            if ((int)$row->device_id === $deviceId) {
                return $row;
            }
        }
        // 其次 merchant_id 通用
        foreach ($rows as $row) {
            if ((int)$row->merchant_id === (int)$device->merchant_id && (int)$row->device_id === 0) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 抽奖
     *
     * @param int    $activityId
     * @param string $userHash
     * @param int    $deviceId
     * @return array
     */
    public function drawLottery(int $activityId, string $userHash, int $deviceId): array
    {
        $activity = LotteryActivity::find($activityId);
        if (!$activity) {
            throw new ValidateException('活动不存在');
        }
        // 表字段为 status(1=启用),同时校验时间窗口
        if ((int)$activity->status !== LotteryActivity::STATUS_ENABLED) {
            throw new ValidateException('活动未开始或已结束');
        }
        $now = date('Y-m-d H:i:s');
        if ($activity->start_at > $now || $activity->end_at < $now) {
            throw new ValidateException('活动不在有效时间内');
        }

        // 限流:每日上限
        $this->checkDailyLimit($activity, $userHash);

        // 加载所有启用奖项
        $prizes = LotteryPrize::where('activity_id', $activityId)
            ->where('status', LotteryPrize::STATUS_ENABLED)
            ->order('sort', 'desc')
            ->order('id', 'asc')
            ->select();
        if ($prizes->isEmpty()) {
            throw new ValidateException('活动未配置奖项');
        }

        // 选中奖项(可能降级到 THANKS)
        $selected = $this->pickPrize($prizes);
        // 扣库存
        $picked = $selected ? $this->consumeStock($selected) : null;
        if (!$picked) {
            // 扣库存失败:降级到 THANKS
            $thanksPrize = $this->findThanksPrize($prizes);
            if ($thanksPrize) {
                $picked = $thanksPrize;
            } else {
                // 都没有库存/谢谢参与:返 null 表示未中奖
                $picked = null;
            }
        }

        // 落库记录
        $record = $this->saveRecord($activity, $deviceId, $userHash, $picked);

        return [
            'record_id'   => (int)$record->id,
            'prize_id'    => $picked ? (int)$picked->id : 0,
            'prize_name'  => $picked ? (string)$picked->name : '谢谢参与',
            'prize_type'  => $picked ? (string)$picked->prize_type : 'THANKS',
            'is_winning'  => $picked && $picked->prize_type !== LotteryPrize::TYPE_THANKS,
            'coupon'      => $this->attachCouponIfNeeded($picked, $deviceId, $userHash, $activity),
        ];
    }

    /**
     * 查询我的中奖记录
     */
    public function myRecords(int $deviceId, string $userHash, int $limit = 20): array
    {
        $list = LotteryRecord::where('device_id', $deviceId)
            ->where('user_hash', $userHash)
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $out = [];
        foreach ($list as $r) {
            $out[] = [
                'id'           => (int)$r->id,
                'activity_id'  => (int)$r->activity_id,
                'prize_name'   => (string)($r->prize_name ?? '谢谢参与'),
                'prize_type'   => (string)($r->prize_type ?? 'THANKS'),
                'status'       => (string)$r->status,
                'claimed_at'   => $r->claimed_at,
                'claim_code'   => (string)($r->claim_code ?? ''),
                'create_time'  => $r->create_time,
            ];
        }
        return $out;
    }

    /**
     * 算法:
     *   - 累计概率扫描;落点 → 中奖
     *   - 排除库存=0 奖项(其权重重新归零,不参与命中)
     *   - 命中后将"扣除一次库存"的尝试交给 consumeStock(乐观 SQL)
     */
    protected function pickPrize(\think\Collection $prizes): ?LotteryPrize
    {
        $candidates = [];
        $total = 0.0;
        foreach ($prizes as $p) {
            if ((int)$p->stock === 0) {
                continue;
            }
            $total += (float)$p->probability;
            $candidates[] = $p;
        }
        if ($total <= 0 || empty($candidates)) {
            // 没有候选,让方法返回 null 走降级
            return null;
        }
        $hit = mt_rand() / mt_getrandmax() * $total;
        $acc = 0.0;
        foreach ($candidates as $p) {
            $acc += (float)$p->probability;
            if ($hit <= $acc) {
                return $p;
            }
        }
        return end($candidates);
    }

    protected function consumeStock(LotteryPrize $prize): ?LotteryPrize
    {
        if ((int)$prize->stock <= 0) {
            return null;
        }
        $id = (int)$prize->id;
        try {
            $affected = Db::name('lottery_prizes')
                ->where('id', $id)
                ->where('stock', '>', 0)
                ->dec('stock')
                ->update([
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            if ($affected > 0) {
                $prize->stock = max(0, (int)$prize->stock - 1);
                return $prize;
            }
        } catch (\Throwable $e) {
            Log::error('扣减库存失败', ['prize_id' => $id, 'error' => $e->getMessage()]);
        }
        return null;
    }

    protected function findThanksPrize(\think\Collection $prizes): ?LotteryPrize
    {
        foreach ($prizes as $p) {
            if ($p->prize_type === LotteryPrize::TYPE_THANKS) {
                return $p;
            }
        }
        return null;
    }

    protected function saveRecord(LotteryActivity $activity, int $deviceId, string $userHash, ?LotteryPrize $prize): LotteryRecord
    {
        $data = [
            'activity_id'  => (int)$activity->id,
            'device_id'    => $deviceId,
            'user_hash'    => $userHash,
            'prize_id'     => $prize ? (int)$prize->id : null,
            'prize_name'   => $prize ? (string)$prize->name : '谢谢参与',
            'prize_type'   => $prize ? (string)$prize->prize_type : LotteryPrize::TYPE_THANKS,
            'status'       => LotteryRecord::STATUS_PENDING,
            'ip'           => request()->ip(),
            'ua'           => substr((string)request()->header('User-Agent'), 0, 255),
        ];
        return LotteryRecord::create($data);
    }

    protected function checkDailyLimit(LotteryActivity $activity, string $userHash): void
    {
        $limit = (int)$activity->daily_limit;
        if ($limit <= 0) {
            return;
        }
        $today = date('Y-m-d');
        $count = LotteryRecord::where('activity_id', (int)$activity->id)
            ->where('user_hash', $userHash)
            ->whereLike('create_time', $today . '%')
            ->count();
        if ($count >= $limit) {
            throw new ValidateException('今日抽奖次数已用完,明天再来吧');
        }
    }

    protected function attachCouponIfNeeded(?LotteryPrize $prize, int $deviceId, string $userHash, LotteryActivity $activity): array
    {
        if (!$prize || $prize->prize_type !== LotteryPrize::TYPE_COUPON || empty($prize->coupon_id)) {
            return [];
        }
        // 优惠券发放是较重的业务动作,如未集成优惠券领取服务则仅占位返回
        try {
            if (class_exists(\app\model\CouponUser::class)) {
                $cuId = \think\facade\Db::name('coupon_users')->insertGetId([
                    'user_id'    => 0,
                    'coupon_id'  => (int)$prize->coupon_id,
                    'source'     => 'lottery',
                    'source_id'  => (int)$activity->id,
                    'user_hash'  => $userHash,
                    'status'     => 0,
                    'create_time'=> date('Y-m-d H:i:s'),
                ]);
                return [
                    'coupon_id'     => (int)$prize->coupon_id,
                    'coupon_user_id'=> (int)$cuId,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('抽奖奖品优惠券发放失败(占位返回)', [
                'prize_id' => $prize->id,
                'error' => $e->getMessage(),
            ]);
        }
        return ['coupon_id' => (int)$prize->coupon_id, 'pending' => true];
    }
}
