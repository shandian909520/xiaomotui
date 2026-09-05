<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Queue;
use think\facade\Log;
use app\model\ContentTask;
use app\service\AiContentService;
use app\service\JianyingVideoService;

/**
 * 内容生成队列任务命令
 *
 * 使用方式：
 * php think content:generate-queue
 */
class ContentGenerateQueue extends Command
{
    protected function configure()
    {
        $this->setName('content:generate-queue')
            ->setDescription('内容生成队列处理器');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('内容生成队列处理器启动...');
        $output->writeln('时间: ' . date('Y-m-d H:i:s'));

        // 支持优雅退出（pcntl 在 Windows 上不可用）
        $running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$running, $output) {
                $output->writeln('收到 SIGTERM 信号，准备退出...');
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running, $output) {
                $output->writeln('收到 SIGINT 信号，准备退出...');
                $running = false;
            });
        }

        $processedCount = 0;
        $maxProcess = 100; // 单次运行最多处理100个任务
        $aiService = new AiContentService();

        while ($running && $processedCount < $maxProcess) {
            try {
                // 从数据库获取待处理任务
                $tasks = ContentTask::getPendingTasks(5);

                if (empty($tasks) || count($tasks) === 0) {
                    $output->writeln('暂无待处理任务，等待中...');
                    for ($i = 0; $i < 50; $i++) {
                        if (!$running) break;
                        usleep(100000);
                    }
                    continue;
                }

                foreach ($tasks as $taskData) {
                    if (!$running) {
                        break 2;
                    }

                    // 使用乐观锁抢占任务，避免竞态条件
                    $taskId = $taskData['id'];
                    $affected = \think\facade\Db::name('content_task')
                        ->where('id', $taskId)
                        ->where('status', ContentTask::STATUS_PENDING)
                        ->update(['status' => ContentTask::STATUS_PROCESSING]);

                    if ($affected === 0) {
                        continue;
                    }

                    $task = ContentTask::find($taskId);
                    if (!$task) {
                        continue;
                    }

                    $output->writeln("处理任务 #{$task->id} (类型: {$task->type})");

                    try {
                        $result = $this->processTask($task, $aiService);

                        if ($result['success']) {
                            $task->status = ContentTask::STATUS_COMPLETED;
                            $task->output_data = $result['data'] ?? [];
                            $task->generation_time = (int)($result['generation_time'] ?? 0);
                            $task->complete_time = date('Y-m-d H:i:s');
                            $task->save();

                            $output->writeln("  -> 任务 #{$task->id} 完成");
                        } else {
                            $task->status = ContentTask::STATUS_FAILED;
                            $task->error_message = $result['error'] ?? '生成失败';
                            $task->complete_time = date('Y-m-d H:i:s');
                            $task->save();

                            $output->writeln("  -> 任务 #{$task->id} 失败: " . ($result['error'] ?? '未知'));
                        }
                    } catch (\Exception $e) {
                        $task->status = ContentTask::STATUS_FAILED;
                        $task->error_message = $e->getMessage();
                        $task->complete_time = date('Y-m-d H:i:s');
                        $task->save();

                        $output->writeln("  -> 任务 #{$task->id} 异常: " . $e->getMessage());
                        Log::error('队列处理任务异常', [
                            'task_id' => $task->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $processedCount++;
                }

                // 每批任务之间短暂休眠
                if ($running) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $output->writeln('队列处理异常: ' . $e->getMessage());
                Log::error('内容生成队列处理异常', ['error' => $e->getMessage()]);
                sleep(3);
            }
        }

        $output->writeln("队列处理器停止，共处理 {$processedCount} 个任务");

        return Command::SUCCESS;
    }

    /**
     * 处理单个任务
     */
    protected function processTask(ContentTask $task, AiContentService $aiService): array
    {
        $startTime = microtime(true);
        $inputData = is_array($task->input_data) ? $task->input_data : (json_decode($task->input_data, true) ?? []);

        return match ($task->type) {
            'TEXT' => $this->processTextTask($task, $inputData, $startTime, $aiService),
            'VIDEO' => $this->processVideoTask($task, $inputData, $startTime, $aiService),
            'IMAGE' => $this->processImageTask($task, $inputData, $startTime, $aiService),
            default => ['success' => false, 'error' => "不支持的内容类型: {$task->type}"],
        };
    }

    /**
     * 处理文案生成任务
     */
    protected function processTextTask(ContentTask $task, array $inputData, float $startTime, AiContentService $aiService): array
    {
        try {
            $result = $aiService->generateText([
                'provider' => $inputData['provider'] ?? 'wenxin',
                'scene' => $inputData['scene'] ?? '通用场景',
                'style' => $inputData['style'] ?? '吸引人的',
                'requirements' => $inputData['requirements'] ?? '',
                'platform' => $inputData['platform'] ?? 'ALL',
            ]);

            if ($result['status'] === AiContentService::STATUS_COMPLETED) {
                return [
                    'success' => true,
                    'data' => [
                        'text' => $result['text'],
                        'title' => $result['title'] ?? '',
                        'tags' => $result['tags'] ?? [],
                    ],
                    'generation_time' => round(microtime(true) - $startTime, 2),
                ];
            }

            return ['success' => false, 'error' => $result['error'] ?? '文案生成失败'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 处理视频生成任务
     */
    protected function processVideoTask(ContentTask $task, array $inputData, float $startTime, AiContentService $aiService): array
    {
        try {
            $result = $aiService->generateVideo([
                'provider' => $inputData['provider'] ?? 'jianying',
                'scene' => $inputData['scene'] ?? '通用场景',
                'style' => $inputData['style'] ?? '自然',
                'duration' => $inputData['duration'] ?? 15,
                'materials' => $inputData['materials'] ?? [],
            ]);

            if ($result['status'] === AiContentService::STATUS_COMPLETED) {
                return [
                    'success' => true,
                    'data' => [
                        'video_url' => $result['video_url'],
                        'duration' => $result['duration'],
                        'cover_url' => $result['cover_url'] ?? '',
                    ],
                    'generation_time' => round(microtime(true) - $startTime, 2),
                ];
            }

            return ['success' => false, 'error' => $result['error'] ?? '视频生成失败'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 处理图片生成任务
     */
    protected function processImageTask(ContentTask $task, array $inputData, float $startTime, AiContentService $aiService): array
    {
        try {

            if (!empty($inputData['template_id'])) {
                $result = $aiService->processTemplate($inputData['template_id'], $inputData);
                if ($result['status'] === AiContentService::STATUS_COMPLETED) {
                    return [
                        'success' => true,
                        'data' => $result['result'] ?? [],
                        'generation_time' => round(microtime(true) - $startTime, 2),
                    ];
                }
                return ['success' => false, 'error' => $result['error'] ?? '模板处理失败'];
            }

            return ['success' => false, 'error' => '图片生成缺少模板ID'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}