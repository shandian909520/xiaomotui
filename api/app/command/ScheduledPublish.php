<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;
use think\facade\Db;
use app\service\PublishService;
use app\model\PublishTask;

/**
 * 定时发布命令
 * 处理已到定时发布时间的待发布任务
 */
class ScheduledPublish extends Command
{
    /**
     * 发布服务
     *
     * @var PublishService
     */
    protected $publishService;

    /**
     * 命令配置
     */
    protected function configure()
    {
        $this->setName('publish:scheduled')
            ->setDescription('处理定时发布任务')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '单次处理的最大任务数', 50)
            ->addOption('dry-run', null, Option::VALUE_NONE, '试运行模式（不实际发布）');
    }

    /**
     * 命令执行
     *
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $startTime = microtime(true);
        $limit = (int)$input->getOption('limit');
        $dryRun = $input->getOption('dry-run');
        $verbose = $output->isVerbose(); // 使用 ThinkPHP 内置的 verbose 选项

        // 初始化发布服务
        $this->publishService = app(PublishService::class);

        // 显示开始信息
        $this->showStartInfo($output, $limit, $dryRun, $verbose);

        try {
            // 获取待处理的定时发布任务
            $tasks = $this->getPendingScheduledTasks($limit);

            if (empty($tasks)) {
                $output->writeln('<info>没有需要处理的定时发布任务</info>');
                Log::info('定时发布命令执行完成', ['tasks_count' => 0]);
                return Command::SUCCESS;
            }

            $output->writeln("<info>找到 " . count($tasks) . " 个待处理任务</info>");
            $output->writeln('');

            // 处理任务统计
            $stats = [
                'total' => count($tasks),
                'success' => 0,
                'failed' => 0,
                'partial' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            // 逐个处理任务
            foreach ($tasks as $taskData) {
                $result = $this->processTask($taskData, $output, $dryRun, $verbose);

                // 更新统计
                if ($result['skipped']) {
                    $stats['skipped']++;
                } elseif ($result['status'] === PublishTask::STATUS_COMPLETED) {
                    $stats['success']++;
                } elseif ($result['status'] === PublishTask::STATUS_PARTIAL) {
                    $stats['partial']++;
                } elseif ($result['status'] === PublishTask::STATUS_FAILED) {
                    $stats['failed']++;
                    if (!empty($result['error'])) {
                        $stats['errors'][] = [
                            'task_id' => $taskData['id'],
                            'error' => $result['error']
                        ];
                    }
                }
            }

            // 显示执行结果
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->showResults($output, $stats, $executionTime, $dryRun);

            // 记录日志
            $this->logResults($stats, $executionTime, $dryRun);

            // 返回结果
            return ($stats['failed'] === 0) ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $output->writeln('<error>命令执行失败: ' . $e->getMessage() . '</error>');
            Log::error('定时发布命令执行失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * 显示开始信息
     *
     * @param Output $output
     * @param int $limit
     * @param bool $dryRun
     * @param bool $verbose
     */
    protected function showStartInfo(Output $output, int $limit, bool $dryRun, bool $verbose): void
    {
        $output->writeln('');
        $output->writeln('<info>=== 定时发布任务处理 ===</info>');
        $output->writeln('<comment>执行时间: ' . date('Y-m-d H:i:s') . '</comment>');
        $output->writeln('<comment>处理限制: ' . $limit . ' 个任务</comment>');

        if ($dryRun) {
            $output->writeln('<comment>模式: 试运行（不会实际发布）</comment>');
        }

        if ($verbose) {
            $output->writeln('<comment>详细输出: 已启用</comment>');
        }

        $output->writeln('');
    }

    /**
     * 获取待处理的定时发布任务
     *
     * @param int $limit
     * @return array
     */
    protected function getPendingScheduledTasks(int $limit): array
    {
        $currentTime = date('Y-m-d H:i:s');

        // 查询待处理的定时发布任务
        $tasks = Db::name('publish_tasks')
            ->where('status', PublishTask::STATUS_PENDING)
            ->whereNotNull('scheduled_time')
            ->where('scheduled_time', '<=', $currentTime)
            ->order('scheduled_time', 'asc')
            ->order('create_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        Log::info('查询待处理定时发布任务', [
            'current_time' => $currentTime,
            'found_tasks' => count($tasks)
        ]);

        return $tasks;
    }

    /**
     * 处理单个任务
     *
     * @param array $taskData
     * @param Output $output
     * @param bool $dryRun
     * @param bool $verbose
     * @return array
     */
    protected function processTask(array $taskData, Output $output, bool $dryRun, bool $verbose): array
    {
        $taskId = $taskData['id'];
        $scheduledTime = $taskData['scheduled_time'];

        if ($verbose) {
            $output->writeln("<comment>处理任务 #{$taskId}</comment>");
            $output->writeln("  定时时间: {$scheduledTime}");
            $output->writeln("  内容任务ID: {$taskData['content_task_id']}");
            $output->writeln("  平台数量: " . count(json_decode($taskData['platforms'], true)));
        } else {
            $output->write("处理任务 #{$taskId}... ");
        }

        $result = [
            'task_id' => $taskId,
            'status' => null,
            'skipped' => false,
            'error' => null
        ];

        try {
            // 试运行模式
            if ($dryRun) {
                if ($verbose) {
                    $output->writeln('  <info>试运行: 跳过实际发布</info>');
                } else {
                    $output->writeln('<info>跳过（试运行）</info>');
                }

                $result['skipped'] = true;
                return $result;
            }

            // 使用事务确保数据一致性
            Db::startTrans();

            try {
                // 执行发布任务
                $publishResult = $this->publishService->executePublishTask($taskId);

                if ($publishResult['success']) {
                    $result['status'] = $publishResult['status'];

                    if ($verbose) {
                        $output->writeln('  <info>✓ 发布成功</info>');
                        if (!empty($publishResult['results'])) {
                            foreach ($publishResult['results'] as $platformResult) {
                                $status = $platformResult['success'] ? '✓' : '✗';
                                $platform = $platformResult['platform'];
                                $message = $platformResult['message'] ?? ($platformResult['error'] ?? '');
                                $output->writeln("    {$status} {$platform}: {$message}");
                            }
                        }
                    } else {
                        $statusText = $this->getStatusText($result['status']);
                        $output->writeln("<info>{$statusText}</info>");
                    }
                } else {
                    $result['status'] = PublishTask::STATUS_FAILED;
                    $result['error'] = $publishResult['error'] ?? '未知错误';

                    if ($verbose) {
                        $output->writeln('  <error>✗ 发布失败: ' . $result['error'] . '</error>');
                    } else {
                        $output->writeln('<error>失败</error>');
                    }
                }

                Db::commit();

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $result['status'] = PublishTask::STATUS_FAILED;
            $result['error'] = $e->getMessage();

            if ($verbose) {
                $output->writeln('  <error>✗ 异常: ' . $e->getMessage() . '</error>');
            } else {
                $output->writeln('<error>异常</error>');
            }

            Log::error('处理定时发布任务失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        if ($verbose) {
            $output->writeln('');
        }

        return $result;
    }

    /**
     * 显示执行结果
     *
     * @param Output $output
     * @param array $stats
     * @param float $executionTime
     * @param bool $dryRun
     */
    protected function showResults(Output $output, array $stats, float $executionTime, bool $dryRun): void
    {
        $output->writeln('');
        $output->writeln('<info>=== 执行结果 ===</info>');
        $output->writeln("总任务数: {$stats['total']}");

        if ($dryRun) {
            $output->writeln("跳过数: {$stats['skipped']} (试运行模式)");
        } else {
            $output->writeln("<info>成功: {$stats['success']}</info>");

            if ($stats['partial'] > 0) {
                $output->writeln("<comment>部分成功: {$stats['partial']}</comment>");
            }

            if ($stats['failed'] > 0) {
                $output->writeln("<error>失败: {$stats['failed']}</error>");

                // 显示错误详情
                if (!empty($stats['errors'])) {
                    $output->writeln('');
                    $output->writeln('<error>错误详情:</error>');
                    foreach ($stats['errors'] as $error) {
                        $output->writeln("  任务 #{$error['task_id']}: {$error['error']}");
                    }
                }
            }
        }

        $output->writeln("执行时间: {$executionTime}ms");
        $output->writeln('');
    }

    /**
     * 记录执行结果日志
     *
     * @param array $stats
     * @param float $executionTime
     * @param bool $dryRun
     */
    protected function logResults(array $stats, float $executionTime, bool $dryRun): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'dry_run' => $dryRun,
            'stats' => $stats,
            'execution_time_ms' => $executionTime
        ];

        if ($dryRun) {
            Log::info('定时发布命令执行完成（试运行）', $logData);
        } elseif ($stats['failed'] > 0) {
            Log::warning('定时发布命令执行完成（有失败任务）', $logData);
        } else {
            Log::info('定时发布命令执行完成', $logData);
        }
    }

    /**
     * 获取状态文本
     *
     * @param string $status
     * @return string
     */
    protected function getStatusText(string $status): string
    {
        return match ($status) {
            PublishTask::STATUS_COMPLETED => '完全成功',
            PublishTask::STATUS_PARTIAL => '部分成功',
            PublishTask::STATUS_FAILED => '失败',
            default => '未知状态'
        };
    }
}