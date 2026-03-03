<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\ContentTask;
use think\facade\Log;

/**
 * 检查超时任务命令
 * 定时检查AI内容生成任务，将超时任务标记为失败
 *
 * 执行方式：
 * php think check:timeout-task
 *
 * 配置定时任务（crontab）：
 * 每分钟执行: cd /path/to/api && php think check:timeout-task >> /dev/null 2>&1
 */
class CheckTimeoutTask extends Command
{
    /**
     * 超时时间（秒）
     */
    const TIMEOUT_SECONDS = 600;  // 10分钟

    protected function configure()
    {
        $this->setName('check:timeout-task')
            ->setDescription('检查并处理超时的内容生成任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始检查超时任务...');

        try {
            // 查找所有处理中的任务
            $processingTasks = ContentTask::where('status', 'processing')
                ->select();

            $timeoutCount = 0;
            $now = time();

            foreach ($processingTasks as $task) {
                // 计算处理时长
                $updateTime = strtotime($task->update_time);
                $processingTime = $now - $updateTime;

                // 超时检查
                if ($processingTime > self::TIMEOUT_SECONDS) {
                    // 标记为失败
                    $task->status = 'failed';
                    $task->error_message = sprintf(
                        '任务处理超时（%d秒），已自动标记为失败',
                        $processingTime
                    );
                    $task->save();

                    $timeoutCount++;

                    Log::warning('内容生成任务超时', [
                        'task_id' => $task->id,
                        'user_id' => $task->user_id,
                        'type' => $task->type,
                        'processing_time' => $processingTime,
                        'timeout_limit' => self::TIMEOUT_SECONDS
                    ]);

                    // TODO: 发送通知给用户
                    // event('ContentTaskTimeout', [$task]);
                }
            }

            $output->writeln(sprintf(
                '检查完成：共检查 %d 个任务，发现 %d 个超时任务',
                count($processingTasks),
                $timeoutCount
            ));

            Log::info('超时任务检查完成', [
                'total_tasks' => count($processingTasks),
                'timeout_tasks' => $timeoutCount
            ]);

            return 0;  // 成功

        } catch (\Exception $e) {
            $output->error('检查超时任务失败：' . $e->getMessage());

            Log::error('检查超时任务失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;  // 失败
        }
    }
}
