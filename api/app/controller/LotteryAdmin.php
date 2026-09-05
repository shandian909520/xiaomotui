<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\LotteryActivity;
use app\model\LotteryPrize;
use app\model\LotteryRecord;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 抽奖后台 Admin 控制器(模块6)
 * 商家/平台管理员调用,需通过 Auth + OperationLog 中间件(按现状路由挂载)
 *
 * 仅做最小可用的 CRUD,校验和分页均居中在此处。
 */
class LotteryAdmin extends BaseController
{
    /**
     * 活动列表
     */
    public function activityList()
    {
        try {
            $merchantId = (int)$this->request->param('merchant_id', 0);
            $page       = (int)$this->request->param('page', 1);
            $limit      = (int)$this->request->param('limit', 20);
            $status     = $this->request->param('status', '');
            // bug B7: 支持按设备过滤(admin 端 ActivityList 前端传 device_id)
            $deviceId   = (int)$this->request->param('device_id', 0);

            $where = [];
            if ($merchantId > 0) {
                $where[] = ['merchant_id', '=', $merchantId];
            }
            if ($status !== '') {
                $where[] = ['status', '=', (int)$status];
            }
            if ($deviceId > 0) {
                $where[] = ['device_id', '=', $deviceId];
            }

            $total = LotteryActivity::where($where)->count();
            $list  = LotteryActivity::where($where)
                ->order('id', 'desc')
                ->page($page, $limit)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $limit, '获取活动列表成功');
        } catch (\Exception $e) {
            Log::error('获取抽奖活动列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_activities_failed');
        }
    }

    /**
     * 创建活动
     */
    public function createActivity()
    {
        try {
            $data = $this->request->post();
            $this->validateActivity($data);
            $data['status'] = isset($data['status']) ? (int)$data['status'] : LotteryActivity::STATUS_ENABLED;
            $row = LotteryActivity::create($data);
            return $this->success($row->toArray(), '创建活动成功');
        } catch (ValidateException $e) {
            return $this->validationError(['activity' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建抽奖活动失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_activity_failed');
        }
    }

    /**
     * 更新活动
     */
    public function updateActivity($id)
    {
        try {
            $row = LotteryActivity::find((int)$id);
            if (!$row) {
                return $this->error('活动不存在', 404, 'activity_not_found');
            }
            $data = $this->request->post();
            $this->validateActivity($data, true);
            $row->save($data);
            return $this->success($row->toArray(), '更新活动成功');
        } catch (ValidateException $e) {
            return $this->validationError(['activity' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新抽奖活动失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_activity_failed');
        }
    }

    /**
     * 切换活动状态
     */
    public function toggleActivity($id)
    {
        try {
            $row = LotteryActivity::find((int)$id);
            if (!$row) {
                return $this->error('活动不存在', 404, 'activity_not_found');
            }
            $row->status = ((int)$row->status === LotteryActivity::STATUS_ENABLED)
                ? LotteryActivity::STATUS_DISABLED
                : LotteryActivity::STATUS_ENABLED;
            $row->save();
            return $this->success(['id' => (int)$row->id, 'status' => (int)$row->status], '切换成功');
        } catch (\Exception $e) {
            Log::error('切换抽奖活动状态失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'toggle_activity_failed');
        }
    }

    /**
     * 奖品列表(按活动)
     */
    public function prizes($activityId = null)
    {
        try {
            $aid = (int)($activityId ?: $this->request->param('activity_id', 0));
            if ($aid <= 0) {
                return $this->validationError(['activity_id' => '活动ID不能为空']);
            }
            $list = LotteryPrize::where('activity_id', $aid)
                ->order('sort', 'desc')
                ->order('id', 'asc')
                ->select()
                ->toArray();
            return $this->success(['list' => $list, 'total' => count($list)], '获取奖品列表成功');
        } catch (\Exception $e) {
            Log::error('获取奖品列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_prizes_failed');
        }
    }

    /**
     * 新增奖品
     */
    public function createPrize()
    {
        try {
            $data = $this->request->post();
            $this->validatePrize($data);
            $row = LotteryPrize::create($data);
            return $this->success($row->toArray(), '创建奖品成功');
        } catch (ValidateException $e) {
            return $this->validationError(['prize' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建抽奖奖品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_prize_failed');
        }
    }

    /**
     * 更新奖品
     */
    public function updatePrize($id)
    {
        try {
            $row = LotteryPrize::find((int)$id);
            if (!$row) {
                return $this->error('奖品不存在', 404, 'prize_not_found');
            }
            $data = $this->request->post();
            $this->validatePrize($data, true);
            $row->save($data);
            return $this->success($row->toArray(), '更新奖品成功');
        } catch (ValidateException $e) {
            return $this->validationError(['prize' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新抽奖奖品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_prize_failed');
        }
    }

    /**
     * 删除奖品
     */
    public function deletePrize($id)
    {
        try {
            $row = LotteryPrize::find((int)$id);
            if (!$row) {
                return $this->error('奖品不存在', 404, 'prize_not_found');
            }
            $row->delete();
            return $this->success(['id' => (int)$id], '删除奖品成功');
        } catch (\Exception $e) {
            Log::error('删除抽奖奖品失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_prize_failed');
        }
    }

    /**
     * 中奖记录列表
     */
    public function recordList()
    {
        try {
            $activityId = (int)$this->request->param('activity_id', 0);
            $page       = (int)$this->request->param('page', 1);
            $limit      = (int)$this->request->param('limit', 20);
            $status     = $this->request->param('status', '');

            $where = [];
            if ($activityId > 0) {
                $where[] = ['activity_id', '=', $activityId];
            }
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }

            $total = LotteryRecord::where($where)->count();
            $list  = LotteryRecord::where($where)
                ->order('id', 'desc')
                ->page($page, $limit)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $limit, '获取抽奖记录成功');
        } catch (\Exception $e) {
            Log::error('获取抽奖记录失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_records_failed');
        }
    }

    /**
     * 标记兑奖
     */
    public function claimRecord($id)
    {
        try {
            $row = LotteryRecord::find((int)$id);
            if (!$row) {
                return $this->error('记录不存在', 404, 'record_not_found');
            }
            $row->status = LotteryRecord::STATUS_CLAIMED;
            $row->claimed_at = date('Y-m-d H:i:s');
            $row->claim_code = $row->claim_code ?: strtoupper(bin2hex(random_bytes(4)));
            $row->save();
            return $this->success($row->toArray(), '兑奖成功');
        } catch (\Exception $e) {
            Log::error('兑奖失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'claim_record_failed');
        }
    }

    /**
     * 简单字段校验
     */
    protected function validateActivity(array $data, bool $partial = false): void
    {
        if (!$partial || array_key_exists('name', $data)) {
            if (empty($data['name'])) {
                throw new ValidateException('活动名称不能为空');
            }
        }
        if (!$partial || array_key_exists('start_at', $data)) {
            if (empty($data['start_at'])) {
                throw new ValidateException('活动开始时间不能为空');
            }
        }
        if (!$partial || array_key_exists('end_at', $data)) {
            if (empty($data['end_at'])) {
                throw new ValidateException('活动结束时间不能为空');
            }
        }
        if (isset($data['start_at'], $data['end_at'])
            && strtotime((string)$data['end_at']) <= strtotime((string)$data['start_at'])) {
            throw new ValidateException('结束时间必须晚于开始时间');
        }
        if (isset($data['daily_limit']) && (int)$data['daily_limit'] < 0) {
            throw new ValidateException('每日次数不能为负数');
        }
    }

    protected function validatePrize(array $data, bool $partial = false): void
    {
        if (!$partial || array_key_exists('activity_id', $data)) {
            if (empty($data['activity_id'])) {
                throw new ValidateException('活动ID不能为空');
            }
            $exist = LotteryActivity::find((int)$data['activity_id']);
            if (!$exist) {
                throw new ValidateException('活动不存在');
            }
        }
        if (!$partial || array_key_exists('name', $data)) {
            if (empty($data['name'])) {
                throw new ValidateException('奖品名称不能为空');
            }
        }
        if (isset($data['probability'])) {
            $p = (float)$data['probability'];
            if ($p < 0 || $p > 1) {
                throw new ValidateException('概率必须在 0~1 之间');
            }
        }
        if (isset($data['prize_type'])) {
            $valid = [
                LotteryPrize::TYPE_THANKS,
                LotteryPrize::TYPE_COUPON,
                LotteryPrize::TYPE_POINTS,
                LotteryPrize::TYPE_CUSTOM,
            ];
            if (!in_array($data['prize_type'], $valid, true)) {
                throw new ValidateException('奖品类型不合法');
            }
            if ($data['prize_type'] === LotteryPrize::TYPE_COUPON && empty($data['coupon_id'])) {
                throw new ValidateException('优惠券类型奖品必须指定 coupon_id');
            }
        }
    }
}
