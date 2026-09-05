<?php
declare(strict_types=1);

namespace app\service;

use app\model\EmployeeStats;
use app\model\EmployeeRanking;
use think\facade\Db;

class EmployeeStatsService
{
    public function getStatsByEmployee(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($filters['store_id'])) {
            $query->where('store_id', (int)$filters['store_id']);
        }
        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }

        $list = $query->field([
            'employee_id',
            'SUM(completed_count) as total_completed',
            'SUM(exposure_count) as total_exposure',
            'SUM(like_count) as total_like',
            'SUM(publish_count) as total_publish',
        ])->group('employee_id')
          ->page($page, $limit)
          ->select()
          ->toArray();

        $total = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->group('employee_id')
            ->count();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getStatsByStore(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }

        $list = $query->field([
            'store_id',
            'COUNT(DISTINCT employee_id) as employee_count',
            'SUM(completed_count) as total_completed',
            'SUM(exposure_count) as total_exposure',
            'SUM(like_count) as total_like',
            'SUM(publish_count) as total_publish',
        ])->group('store_id')
          ->page($page, $limit)
          ->select()
          ->toArray();

        $total = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->group('store_id')
            ->count();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getStatsByTask(int $merchantId, array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($filters['store_id'])) {
            $query->where('store_id', (int)$filters['store_id']);
        }

        return $query->field([
            'task_type',
            'SUM(target_count) as total_target',
            'SUM(completed_count) as total_completed',
            'SUM(exposure_count) as total_exposure',
            'SUM(like_count) as total_like',
            'SUM(publish_count) as total_publish',
            'COUNT(DISTINCT employee_id) as employee_count',
        ])->group('task_type')
          ->select()
          ->toArray();
    }

    public function getRankings(int $merchantId, string $periodType, string $rankType): array
    {
        $periodType = $periodType ?: 'week';
        $rankType   = $rankType ?: 'high_creator';

        [$periodStart, $periodEnd] = $this->getDateRange($periodType);

        return EmployeeRanking::where('merchant_id', $merchantId)
            ->where('period_type', $periodType)
            ->where('rank_type', $rankType)
            ->where('period_start', $periodStart)
            ->order('rank_num', 'asc')
            ->select()
            ->toArray();
    }

    public function getPublishDetails(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = Db::table('xmt_employee_stats')
            ->where('merchant_id', $merchantId)
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($filters['store_id'])) {
            $query->where('store_id', (int)$filters['store_id']);
        }
        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }
        if (!empty($filters['employee_name'])) {
            $query->whereExists(function ($q) use ($filters) {
                $q->table('xmt_employees')
                  ->whereRaw('xmt_employees.id = xmt_employee_stats.employee_id')
                  ->whereLike('name', '%' . addcslashes($filters['employee_name'], '%_') . '%');
            });
        }

        $total = (clone $query)->count();
        $list  = $query->order('date', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getDateRange(string $periodType): array
    {
        $now = new \DateTime();
        $start = match ($periodType) {
            'day'       => (clone $now)->format('Y-m-d'),
            'week'      => (clone $now)->modify('monday this week')->format('Y-m-d'),
            'month'     => (clone $now)->modify('first day of this month')->format('Y-m-d'),
            'quarter'   => $this->getQuarterStart($now),
            'half_year' => (clone $now)->modify('-6 months')->format('Y-m-d'),
            'year'      => (clone $now)->modify('first day of January')->format('Y-m-d'),
            default     => (clone $now)->format('Y-m-d'),
        };

        $end = $now->format('Y-m-d');
        return [$start, $end];
    }

    private function resolveDateRange(array $filters): array
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            return [$filters['start_date'], $filters['end_date']];
        }

        $periodType = $filters['period_type'] ?? 'day';
        return $this->getDateRange($periodType);
    }

    private function getQuarterStart(\DateTime $date): string
    {
        $month = (int)$date->format('n');
        $quarterMonth = (int)(floor(($month - 1) / 3) * 3 + 1);
        return $date->format('Y') . '-' . str_pad((string)$quarterMonth, 2, '0', STR_PAD_LEFT) . '-01';
    }
}
