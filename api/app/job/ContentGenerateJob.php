<?php
declare (strict_types = 1);

namespace app\job;

use think\queue\Job;
use think\facade\Log;
use app\model\ContentTask;
use app\service\AiContentService;
use app\service\JianyingVideoService;

/**
 * 内容生成队列任务
 * 处理视频、文案、图片等AI内容生成
 */
class ContentGenerateJob
{
    /**
     * 任务执行
     *
     * @param Job $job 任务对象
     * @param array $data 任务数据
     */
    public function fire(Job $job, array $data)
    {
        $taskId = $data['task_id'] ?? 0;
        $type = $data['type'] ?? 'TEXT';

        Log::info('内容生成队列任务开始', [
            'task_id' => $taskId,
            'type' => $type,
            'attempts' => $job->attempts()
        ]);

        try {
            // 获取任务详情
            $task = ContentTask::find($taskId);
            if (!$task) {
                Log::error('内容任务不存在', ['task_id' => $taskId]);
                $job->delete();
                return;
            }

            // 检查任务状态
            if ($task->status !== ContentTask::STATUS_PENDING) {
                Log::warning('任务状态不是待处理', [
                    'task_id' => $taskId,
                    'status' => $task->status
                ]);
                $job->delete();
                return;
            }

            // 更新任务状态为处理中
            $task->status = ContentTask::STATUS_PROCESSING;
            $task->save();

            // 根据内容类型调用不同的生成服务
            $result = match ($type) {
                'VIDEO' => $this->generateVideo($task),
                'TEXT' => $this->generateText($task),
                'IMAGE' => $this->generateImage($task),
                default => throw new \Exception("不支持的内容类型: {$type}")
            };

            if ($result['success']) {
                // 生成成功，更新任务
                $this->handleSuccess($task, $result);

                Log::info('内容生成成功', [
                    'task_id' => $taskId,
                    'type' => $type,
                    'generation_time' => $result['generation_time'] ?? 0
                ]);

                // 删除任务
                $job->delete();
            } else {
                // 生成失败，判断是否需要重试
                if ($job->attempts() >= 3) {
                    // 超过最大重试次数，标记为失败
                    $this->handleFailure($task, $result['error'] ?? '生成失败');

                    Log::error('内容生成失败，已达最大重试次数', [
                        'task_id' => $taskId,
                        'type' => $type,
                        'error' => $result['error'] ?? '未知错误'
                    ]);

                    $job->delete();
                } else {
                    // 重新放回队列，延迟60秒后重试
                    Log::warning('内容生成失败，将重试', [
                        'task_id' => $taskId,
                        'type' => $type,
                        'attempt' => $job->attempts(),
                        'error' => $result['error'] ?? '未知错误'
                    ]);

                    $job->release(60);
                }
            }

        } catch (\Exception $e) {
            Log::error('内容生成队列任务异常', [
                'task_id' => $taskId,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 判断是否需要重试
            if ($job->attempts() >= 3) {
                if (isset($task)) {
                    $this->handleFailure($task, $e->getMessage());
                }
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }

    /**
     * 生成视频内容
     *
     * @param ContentTask $task 任务对象
     * @return array
     */
    protected function generateVideo(ContentTask $task): array
    {
        $startTime = microtime(true);

        try {
            $inputData = json_decode($task->input_data, true) ?? [];

            // 使用剪映服务生成视频
            $jianyingService = new JianyingVideoService();

            // 构建视频参数
            $params = [
                'scene' => $inputData['scene'] ?? '通用',
                'style' => $inputData['style'] ?? 'vlog',
                'duration' => $inputData['duration'] ?? 15,
                'resolution' => $inputData['resolution'] ?? '1080p',
                'ratio' => $inputData['ratio'] ?? '9:16',
                'materials' => $inputData['materials'] ?? [],
                'text_overlays' => $inputData['text_overlays'] ?? []
            ];

            // 创建视频生成任务
            $result = $jianyingService->createVideoTask($params);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? '视频生成失败'
                ];
            }

            // 轮询查询视频生成状态
            $jianyingTaskId = $result['task_id'];
            $maxAttempts = 20; // 最多查询20次
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                sleep(5); // 每5秒查询一次

                $statusResult = $jianyingService->queryTaskStatus($jianyingTaskId);

                if ($statusResult['status'] === 'COMPLETED') {
                    // 视频生成完成
                    $generationTime = microtime(true) - $startTime;

                    return [
                        'success' => true,
                        'video_url' => $statusResult['video_url'],
                        'cover_url' => $statusResult['cover_url'] ?? '',
                        'duration' => $statusResult['duration'] ?? 0,
                        'file_size' => $statusResult['file_size'] ?? 0,
                        'generation_time' => round($generationTime, 2)
                    ];
                } elseif ($statusResult['status'] === 'FAILED') {
                    // 视频生成失败
                    return [
                        'success' => false,
                        'error' => $statusResult['error'] ?? '视频生成失败'
                    ];
                }

                $attempt++;
            }

            // 超时
            return [
                'success' => false,
                'error' => '视频生成超时'
            ];

        } catch (\Exception $e) {
            Log::error('视频生成异常', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成文案内容
     *
     * @param ContentTask $task 任务对象
     * @return array
     */
    protected function generateText(ContentTask $task): array
    {
        $startTime = microtime(true);

        try {
            $inputData = json_decode($task->input_data, true) ?? [];

            // 使用AI服务生成文案
            $aiService = new AiContentService();

            // 构建文案参数
            $params = [
                'provider' => $inputData['provider'] ?? 'wenxin',
                'scene' => $inputData['scene'] ?? '通用',
                'style' => $inputData['style'] ?? '吸引人的',
                'requirements' => $inputData['requirements'] ?? '',
                'platform' => $inputData['platform'] ?? 'ALL'
            ];

            // 生成文案
            $result = $aiService->generateText($params);

            if ($result['status'] === 'COMPLETED') {
                $generationTime = microtime(true) - $startTime;

                return [
                    'success' => true,
                    'text' => $result['text'],
                    'title' => $result['title'] ?? '',
                    'tags' => $result['tags'] ?? [],
                    'generation_time' => round($generationTime, 2)
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? '文案生成失败'
                ];
            }

        } catch (\Exception $e) {
            Log::error('文案生成异常', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成图片内容
     *
     * @param ContentTask $task 任务对象
     * @return array
     */
    protected function generateImage(ContentTask $task): array
    {
        $startTime = microtime(true);

        try {
            $inputData = json_decode($task->input_data, true) ?? [];

            // 使用模板处理
            $aiService = new AiContentService();

            if (!empty($inputData['template_id'])) {
                $result = $aiService->processTemplate(
                    $inputData['template_id'],
                    $inputData
                );

                if ($result['status'] === 'COMPLETED') {
                    $generationTime = microtime(true) - $startTime;

                    return [
                        'success' => true,
                        'image_url' => $result['result']['image_url'] ?? '',
                        'width' => $result['result']['width'] ?? 1080,
                        'height' => $result['result']['height'] ?? 1920,
                        'generation_time' => round($generationTime, 2)
                    ];
                }
            }

            return [
                'success' => false,
                'error' => '图片生成失败'
            ];

        } catch (\Exception $e) {
            Log::error('图片生成异常', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理生成成功
     *
     * @param ContentTask $task 任务对象
     * @param array $result 生成结果
     */
    protected function handleSuccess(ContentTask $task, array $result): void
    {
        $task->status = ContentTask::STATUS_COMPLETED;
        $task->output_data = json_encode($result);
        $task->generation_time = (int)($result['generation_time'] ?? 0);
        $task->complete_time = date('Y-m-d H:i:s');
        $task->save();
    }

    /**
     * 处理生成失败
     *
     * @param ContentTask $task 任务对象
     * @param string $error 错误信息
     */
    protected function handleFailure(ContentTask $task, string $error): void
    {
        $task->status = ContentTask::STATUS_FAILED;
        $task->error_message = $error;
        $task->save();
    }

    /**
     * 任务失败回调
     *
     * @param array $data 任务数据
     */
    public function failed(array $data)
    {
        $taskId = $data['task_id'] ?? 0;

        Log::error('内容生成队列任务最终失败', [
            'task_id' => $taskId,
            'data' => $data
        ]);

        // 标记任务为失败
        $task = ContentTask::find($taskId);
        if ($task) {
            $task->status = ContentTask::STATUS_FAILED;
            $task->error_message = '队列任务处理失败';
            $task->save();
        }
    }
}