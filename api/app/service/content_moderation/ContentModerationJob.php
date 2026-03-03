<?php
declare(strict_types=1);

namespace app\service\content_moderation;

use think\facade\Log;
use think\queue\Job;
use think\facade\Db;

/**
 * 内容审核异步队列任务
 * 用于异步处理内容审核请求
 */
class ContentModerationJob
{
    /**
     * 执行队列任务
     *
     * @param Job $job 队列任务
     * @param array $data 任务数据
     * @return void
     */
    public function fire(Job $job, array $data): void
    {
        try {
            Log::info('开始处理内容审核队列任务', [
                'job_id' => $job->getJobId(),
                'data' => $data,
            ]);

            $result = $this->process($data);

            if ($result['success']) {
                // 标记任务完成
                $job->delete();

                Log::info('内容审核队列任务完成', [
                    'job_id' => $job->getJobId(),
                    'result' => $result,
                ]);
            } else {
                // 处理失败,重试
                if ($job->attempts() < 3) {
                    $delay = 60 * $job->attempts(); // 递增延迟
                    $job->release($delay);

                    Log::warning('内容审核队列任务失败,将在' . $delay . '秒后重试', [
                        'job_id' => $job->getJobId(),
                        'attempts' => $job->attempts(),
                        'error' => $result['error'] ?? '未知错误',
                    ]);
                } else {
                    // 超过最大重试次数,标记为失败
                    $job->delete();

                    Log::error('内容审核队列任务失败,超过最大重试次数', [
                        'job_id' => $job->getJobId(),
                        'attempts' => $job->attempts(),
                        'error' => $result['error'] ?? '未知错误',
                    ]);

                    // 更新任务状态为失败
                    $this->updateTaskStatus($data['task_id'] ?? null, 'failed', $result['error'] ?? '未知错误');
                }
            }

        } catch (\Exception $e) {
            Log::error('内容审核队列任务异常', [
                'job_id' => $job->getJobId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 异常也要重试
            if ($job->attempts() < 3) {
                $job->release(60 * $job->attempts());
            } else {
                $job->delete();
            }
        }
    }

    /**
     * 处理审核任务
     *
     * @param array $data 任务数据
     * @return array
     */
    private function process(array $data): array
    {
        try {
            $contentType = $data['content_type'] ?? 'text';
            $content = $data['content'] ?? '';
            $taskId = $data['task_id'] ?? null;
            $materialId = $data['material_id'] ?? null;
            $options = $data['options'] ?? [];

            // 更新任务状态为处理中
            $this->updateTaskStatus($taskId, 'processing');

            // 获取服务商
            $provider = ModerationProviderFactory::create($data['provider'] ?? '');
            if (!$provider) {
                throw new \Exception('无法创建服务商实例');
            }

            // 根据内容类型调用不同的审核方法
            $result = null;
            switch ($contentType) {
                case 'text':
                    $result = $provider->checkText($content, $options);
                    break;
                case 'image':
                    $result = $provider->checkImage($content, $options);
                    break;
                case 'video':
                    $result = $provider->checkVideo($content, $options);
                    break;
                case 'audio':
                    $result = $provider->checkAudio($content, $options);
                    break;
                default:
                    throw new \Exception('不支持的内容类型: ' . $contentType);
            }

            // 保存审核结果
            $this->saveModerationResult($taskId, $materialId, $result);

            // 更新任务状态为完成
            $this->updateTaskStatus($taskId, 'completed', null, $result);

            // 如果有素材ID,更新素材状态
            if ($materialId) {
                $this->updateMaterialStatus($materialId, $result);
            }

            return [
                'success' => true,
                'result' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('处理内容审核任务失败', [
                'task_id' => $data['task_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 更新任务状态
     *
     * @param string|null $taskId 任务ID
     * @param string $status 状态
     * @param string|null $error 错误信息
     * @param array|null $result 审核结果
     * @return void
     */
    private function updateTaskStatus(
        ?string $taskId,
        string $status,
        ?string $error = null,
        ?array $result = null
    ): void {
        if (!$taskId) {
            return;
        }

        try {
            $updateData = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($error) {
                $updateData['error_message'] = $error;
            }

            if ($result) {
                $updateData['result'] = json_encode($result);
            }

            if ($status === 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'processing') {
                $updateData['started_at'] = date('Y-m-d H:i:s');
            }

            Db::name('content_moderation_tasks')
                ->where('task_id', $taskId)
                ->update($updateData);

        } catch (\Exception $e) {
            Log::error('更新任务状态失败', [
                'task_id' => $taskId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 保存审核结果
     *
     * @param string|null $taskId 任务ID
     * @param string|null $materialId 素材ID
     * @param array $result 审核结果
     * @return void
     */
    private function saveModerationResult(?string $taskId, ?string $materialId, array $result): void
    {
        try {
            $data = [
                'task_id' => $taskId,
                'material_id' => $materialId,
                'provider' => $result['provider'] ?? '',
                'pass' => $result['pass'] ?? false,
                'score' => $result['score'] ?? 0,
                'confidence' => $result['confidence'] ?? 0,
                'suggestion' => $result['suggestion'] ?? 'review',
                'violations' => json_encode($result['violations'] ?? []),
                'check_time' => $result['check_time'] ?? date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            Db::name('content_moderation_results')->insert($data);

        } catch (\Exception $e) {
            Log::error('保存审核结果失败', [
                'task_id' => $taskId,
                'material_id' => $materialId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 更新素材状态
     *
     * @param string $materialId 素材ID
     * @param array $result 审核结果
     * @return void
     */
    private function updateMaterialStatus(string $materialId, array $result): void
    {
        try {
            $suggestion = $result['suggestion'] ?? 'review';
            $status = 'PENDING';

            switch ($suggestion) {
                case 'pass':
                    $status = 'APPROVED';
                    break;
                case 'reject':
                    $status = 'REJECTED';
                    break;
                case 'review':
                    $status = 'PENDING';
                    break;
            }

            Db::name('materials')
                ->where('id', $materialId)
                ->update([
                    'moderation_status' => $status,
                    'moderation_score' => $result['score'] ?? 0,
                    'moderation_time' => $result['check_time'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

        } catch (\Exception $e) {
            Log::error('更新素材状态失败', [
                'material_id' => $materialId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 将审核任务加入队列
     *
     * @param string $contentType 内容类型
     * @param string $content 内容
     * @param string $provider 服务商
     * @param array $options 选项
     * @param string|null $materialId 素材ID
     * @return string|null 任务ID
     */
    public static function dispatch(
        string $contentType,
        string $content,
        string $provider,
        array $options = [],
        ?string $materialId = null
    ): ?string {
        try {
            // 生成任务ID
            $taskId = uniqid('mod_task_', true);

            // 创建任务记录
            Db::name('content_moderation_tasks')->insert([
                'task_id' => $taskId,
                'material_id' => $materialId,
                'content_type' => $contentType,
                'provider' => $provider,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 准备队列数据
            $queueData = [
                'task_id' => $taskId,
                'material_id' => $materialId,
                'content_type' => $contentType,
                'content' => $content,
                'provider' => $provider,
                'options' => $options,
            ];

            // 加入队列
            $queueName = Config::get('content_moderation.queue.queue_name', 'content_moderation');
            \think\facade\Queue::push(self::class, $queueData, $queueName);

            Log::info('内容审核任务已加入队列', [
                'task_id' => $taskId,
                'content_type' => $contentType,
                'provider' => $provider,
            ]);

            return $taskId;

        } catch (\Exception $e) {
            Log::error('添加内容审核任务到队列失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}
