<?php
declare(strict_types=1);

namespace app\service;

use app\model\UserTask as UserTaskModel;

class UserTaskService
{
    public function getTaskList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = UserTaskModel::where('merchant_id', $merchantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)
            ->order('create_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getTaskDetail(int $id): ?array
    {
        $task = UserTaskModel::find($id);
        if (!$task) {
            return null;
        }
        return $task->toArray();
    }

    public function createTask(int $merchantId, array $data): array
    {
        $task = new UserTaskModel();
        $task->merchant_id = $merchantId;
        $task->user_id     = $data['user_id'] ?? null;
        $task->task_type   = $data['task_type'];
        $task->task_name   = $data['task_name'];
        $task->status      = UserTaskModel::STATUS_PENDING;
        $task->progress    = 0;
        $task->result_data = $data['result_data'] ?? null;
        $task->error_msg   = null;
        $task->save();

        return $task->toArray();
    }

    public function updateTaskProgress(int $id, int $progress, ?string $status = null): ?array
    {
        $task = UserTaskModel::find($id);
        if (!$task) {
            return null;
        }

        $task->progress = min(100, max(0, $progress));
        if ($status !== null) {
            $task->status = $status;
        }
        $task->save();

        return $task->toArray();
    }

    public function completeTask(int $id, ?array $resultData = null): ?array
    {
        $task = UserTaskModel::find($id);
        if (!$task) {
            return null;
        }

        $task->status      = UserTaskModel::STATUS_COMPLETED;
        $task->progress    = 100;
        $task->result_data = $resultData;
        $task->save();

        return $task->toArray();
    }

    public function failTask(int $id, string $errorMsg): ?array
    {
        $task = UserTaskModel::find($id);
        if (!$task) {
            return null;
        }

        $task->status    = UserTaskModel::STATUS_FAILED;
        $task->error_msg = $errorMsg;
        $task->save();

        return $task->toArray();
    }

    public function getTaskSummary(int $merchantId): array
    {
        $statuses = [
            UserTaskModel::STATUS_PENDING,
            UserTaskModel::STATUS_PROCESSING,
            UserTaskModel::STATUS_COMPLETED,
            UserTaskModel::STATUS_FAILED,
        ];

        $summary = [];
        foreach ($statuses as $status) {
            $summary[$status] = UserTaskModel::where('merchant_id', $merchantId)
                ->where('status', $status)
                ->count();
        }

        $summary['total'] = array_sum($summary);
        return $summary;
    }
}
