<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Queue;
use think\facade\Log;
use app\model\ContentTask;

/**
 * 内容队列服务
 * 管理内容生成队列的入队、出队和监控
 */
class ContentQueueService
{
    /**
     * 队列名称
     */
    const QUEUE_NAME = 'content_generate';

    /**
     * 推送任务到队列
     *
     * @param int $taskId 任务ID
     * @param string $type 内容类型
     * @param string $priority 优先级 low/normal/high
     * @return bool
     */
    public function pushToQueue(int $taskId, string $type, string $priority = 'normal'): bool
    {
        try {
            $jobData = [
                'task_id' => $taskId,
                'type' => $type,
                'priority' => $priority,
                'push_time' => time()
            ];

            // 根据优先级设置延迟时间
            $delay = match ($priority) {
                'high' => 0,      // 立即执行
                'normal' => 2,    // 延迟2秒
                'low' => 5,       // 延迟5秒
                default => 2
            };

            // 推送到队列
            Queue::later($delay, 'app\job\ContentGenerateJob', $jobData, self::QUEUE_NAME);

            Log::info('任务已推送到队列', [
                'task_id' => $taskId,
                'type' => $type,
                'priority' => $priority,
                'delay' => $delay
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('推送任务到队列失败', [
                'task_id' => $taskId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * 批量推送任务到队列
     *
     * @param array $tasks 任务列表 [['task_id' => 1, 'type' => 'VIDEO'], ...]
     * @return array
     */
    public function batchPushToQueue(array $tasks): array
    {
        $successCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($tasks as $task) {
            $taskId = $task['task_id'] ?? 0;
            $type = $task['type'] ?? 'TEXT';
            $priority = $task['priority'] ?? 'normal';

            $success = $this->pushToQueue($taskId, $type, $priority);

            if ($success) {
                $successCount++;
            } else {
                $failedCount++;
            }

            $results[] = [
                'task_id' => $taskId,
                'success' => $success
            ];
        }

        Log::info('批量推送任务到队列完成', [
            'total' => count($tasks),
            'success' => $successCount,
            'failed' => $failedCount
        ]);

        return [
            'total' => count($tasks),
            'success' => $successCount,
            'failed' => $failedCount,
            'details' => $results
        ];
    }

    /**
     * 获取队列状态
     *
     * @return array
     */
    public function getQueueStatus(): array
    {
        try {
            // 获取待处理任务数量
            $pendingCount = ContentTask::where('status', ContentTask::STATUS_PENDING)->count();

            // 获取处理中任务数量
            $processingCount = ContentTask::where('status', ContentTask::STATUS_PROCESSING)->count();

            // 获取今日完成任务数量
            $todayCompleted = ContentTask::where('status', ContentTask::STATUS_COMPLETED)
                ->whereTime('complete_time', 'today')
                ->count();

            // 获取今日失败任务数量
            $todayFailed = ContentTask::where('status', ContentTask::STATUS_FAILED)
                ->whereTime('update_time', 'today')
                ->count();

            return [
                'pending' => $pendingCount,
                'processing' => $processingCount,
                'today_completed' => $todayCompleted,
                'today_failed' => $todayFailed,
                'queue_name' => self::QUEUE_NAME,
                'timestamp' => time()
            ];

        } catch (\Exception $e) {
            Log::error('获取队列状态失败', ['error' => $e->getMessage()]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 重新推送失败的任务
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function retryFailedTasks(int $limit = 10): array
    {
        try {
            // 获取失败的任务
            $failedTasks = ContentTask::where('status', ContentTask::STATUS_FAILED)
                ->order('update_time', 'desc')
                ->limit($limit)
                ->select();

            $retryCount = 0;
            $results = [];

            foreach ($failedTasks as $task) {
                // 重置任务状态
                $task->reset();

                // 推送到队列
                $success = $this->pushToQueue($task->id, $task->type);

                if ($success) {
                    $retryCount++;
                }

                $results[] = [
                    'task_id' => $task->id,
                    'type' => $task->type,
                    'success' => $success
                ];
            }

            Log::info('重新推送失败任务', [
                'total' => count($failedTasks),
                'retry_count' => $retryCount
            ]);

            return [
                'total' => count($failedTasks),
                'retry_count' => $retryCount,
                'details' => $results
            ];

        } catch (\Exception $e) {
            Log::error('重新推送失败任务异常', ['error' => $e->getMessage()]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 清理超时任务
     *
     * @param int $timeoutMinutes 超时分钟数
     * @return array
     */
    public function cleanTimeoutTasks(int $timeoutMinutes = 30): array
    {
        try {
            // 重置超时任务
            $resetCount = ContentTask::resetTimeoutTasks($timeoutMinutes);

            Log::info('清理超时任务', [
                'timeout_minutes' => $timeoutMinutes,
                'reset_count' => $resetCount
            ]);

            return [
                'timeout_minutes' => $timeoutMinutes,
                'reset_count' => $resetCount,
                'message' => "已重置 {$resetCount} 个超时任务"
            ];

        } catch (\Exception $e) {
            Log::error('清理超时任务失败', ['error' => $e->getMessage()]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取队列统计信息
     *
     * @param string $period 统计周期 today/week/month
     * @return array
     */
    public function getQueueStats(string $period = 'today'): array
    {
        try {
            $query = ContentTask::query();

            // 根据周期设置时间范围
            switch ($period) {
                case 'today':
                    $query->whereTime('create_time', 'today');
                    break;
                case 'week':
                    $query->whereTime('create_time', 'week');
                    break;
                case 'month':
                    $query->whereTime('create_time', 'month');
                    break;
            }

            $total = $query->count();
            $completed = $query->where('status', ContentTask::STATUS_COMPLETED)->count();
            $failed = $query->where('status', ContentTask::STATUS_FAILED)->count();
            $pending = $query->where('status', ContentTask::STATUS_PENDING)->count();
            $processing = $query->where('status', ContentTask::STATUS_PROCESSING)->count();

            // 计算平均生成时间
            $avgTime = ContentTask::where('status', ContentTask::STATUS_COMPLETED)
                ->whereTime('create_time', $period)
                ->avg('generation_time');

            // 按类型统计
            $typeStats = ContentTask::field('type, count(*) as count')
                ->whereTime('create_time', $period)
                ->group('type')
                ->select()
                ->toArray();

            return [
                'period' => $period,
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'pending' => $pending,
                'processing' => $processing,
                'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
                'avg_generation_time' => round($avgTime ?? 0, 2),
                'type_stats' => $typeStats
            ];

        } catch (\Exception $e) {
            Log::error('获取队列统计信息失败', ['error' => $e->getMessage()]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 测试队列连接
     *
     * @return bool
     */
    public function testQueueConnection(): bool
    {
        try {
            // 推送一个测试任务
            Queue::push('app\job\ContentGenerateJob', [
                'task_id' => 0,
                'type' => 'TEST',
                'test' => true
            ], self::QUEUE_NAME);

            Log::info('队列连接测试成功');

            return true;

        } catch (\Exception $e) {
            Log::error('队列连接测试失败', ['error' => $e->getMessage()]);

            return false;
        }
    }
}