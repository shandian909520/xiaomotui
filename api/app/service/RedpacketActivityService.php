<?php
declare(strict_types=1);

namespace app\service;

use app\model\RedpacketActivity;
use app\model\RedpacketActivityStore;
use think\facade\Db;

class RedpacketActivityService
{
    public function getActivityList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = RedpacketActivity::where('merchant_id', $merchantId);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $query->whereLike('activity_name', '%' . addcslashes($filters['keyword'], '%_') . '%');
        }

        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)
            ->order('create_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getActivityDetail(int $id): ?array
    {
        $activity = RedpacketActivity::with(['stores'])->find($id);
        if (!$activity) {
            return null;
        }
        return $activity->toArray();
    }

    public function createActivity(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $activity = new RedpacketActivity();
            $activity->merchant_id     = $merchantId;
            $activity->activity_name   = $data['activity_name'] ?? '';
            $activity->budget_amount   = $data['budget_amount'] ?? 0;
            $activity->consumed_amount = 0;
            $activity->start_time      = $data['start_time'] ?? date('Y-m-d H:i:s');
            $activity->end_time        = $data['end_time'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
            $activity->status          = RedpacketActivity::STATUS_ACTIVE;
            $activity->rule_config     = $data['rule_config'] ?? null;
            $activity->fee_rate        = $data['fee_rate'] ?? 0.01;
            $activity->save();

            if (!empty($data['store_ids']) && is_array($data['store_ids'])) {
                $this->syncStores($activity->id, $data['store_ids']);
                $activity->store_count = count($data['store_ids']);
                $activity->save();
            }

            Db::commit();
            return $activity->toArray();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function updateActivity(int $id, array $data): ?array
    {
        $activity = RedpacketActivity::find($id);
        if (!$activity) {
            return null;
        }

        Db::startTrans();
        try {
            $allowFields = ['activity_name', 'budget_amount', 'start_time', 'end_time', 'rule_config', 'fee_rate'];
            foreach ($allowFields as $field) {
                if (array_key_exists($field, $data)) {
                    $activity->$field = $data[$field];
                }
            }
            $activity->save();

            if (isset($data['store_ids']) && is_array($data['store_ids'])) {
                RedpacketActivityStore::where('activity_id', $id)->delete();
                $this->syncStores($id, $data['store_ids']);
                $activity->store_count = count($data['store_ids']);
                $activity->save();
            }

            Db::commit();
            return $activity->toArray();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function toggleActivityStatus(int $id, int $status): ?array
    {
        $activity = RedpacketActivity::find($id);
        if (!$activity) {
            return null;
        }

        $activity->status = $status;
        $activity->save();
        return $activity->toArray();
    }

    public function getActivityStats(int $merchantId): array
    {
        $activities = RedpacketActivity::where('merchant_id', $merchantId)->select();

        $budgetTotal    = 0;
        $consumedTotal  = 0;
        $remainingTotal = 0;

        foreach ($activities as $item) {
            $budgetTotal    += $item->budget_amount;
            $consumedTotal  += $item->consumed_amount;
            $remainingTotal += ($item->budget_amount - $item->consumed_amount);
        }

        return [
            'budget_total'    => round($budgetTotal, 2),
            'consumed_total'  => round($consumedTotal, 2),
            'remaining_total' => round($remainingTotal, 2),
            'activity_count'  => $activities->count(),
        ];
    }

    public function getBalanceOverview(int $merchantId): array
    {
        $stats = $this->getActivityStats($merchantId);

        $activeConsumed = RedpacketActivity::where('merchant_id', $merchantId)
            ->where('status', RedpacketActivity::STATUS_ACTIVE)
            ->sum('consumed_amount');

        return [
            'account_balance'     => round($stats['budget_total'], 2),
            'budget_total'        => round($stats['budget_total'], 2),
            'remaining_budget'    => round($stats['remaining_total'], 2),
            'actual_consumed'     => round((float)$activeConsumed, 2),
            'fee_rate'            => 0.01,
            'activity_count'      => $stats['activity_count'],
        ];
    }

    private function syncStores(int $activityId, array $storeIds): void
    {
        $insertData = [];
        foreach ($storeIds as $storeId) {
            $insertData[] = [
                'activity_id'     => $activityId,
                'store_id'        => (int)$storeId,
                'consumed_amount' => 0,
                'send_count'      => 0,
            ];
        }
        if (!empty($insertData)) {
            (new RedpacketActivityStore())->insertAll($insertData);
        }
    }
}
