<?php

namespace app\job;

use think\queue\Job;
use think\facade\Cache;
use think\facade\Log;
use app\service\VideoDedupService;

class PromoVariantJob
{
    public function fire(Job $job, array $data): void
    {
        $taskId = $data['task_id'] ?? '';
        $templateId = $data['template_id'] ?? 0;
        $count = $data['count'] ?? 0;

        try {
            Cache::set("promo_task:{$taskId}", array_merge(
                Cache::get("promo_task:{$taskId}", []),
                ['status' => 'processing', 'progress' => 10, 'start_time' => date('Y-m-d H:i:s')]
            ), 3600);

            $dedupService = new VideoDedupService();
            $result = $dedupService->generateVariants($templateId, $count);

            Cache::set("promo_task:{$taskId}", [
                'task_id' => $taskId,
                'template_id' => $templateId,
                'count' => $count,
                'status' => 'completed',
                'progress' => 100,
                'result' => $result,
                'create_time' => $data['create_time'] ?? date('Y-m-d H:i:s'),
                'complete_time' => date('Y-m-d H:i:s'),
            ], 3600);

            Log::info('队列：视频变体生成完成', [
                'task_id' => $taskId,
                'template_id' => $templateId,
                'success' => $result['success'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ]);

            $job->delete();
        } catch (\Exception $e) {
            Log::error('队列：视频变体生成失败', [
                'task_id' => $taskId,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            Cache::set("promo_task:{$taskId}", [
                'task_id' => $taskId,
                'template_id' => $templateId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'create_time' => $data['create_time'] ?? date('Y-m-d H:i:s'),
                'fail_time' => date('Y-m-d H:i:s'),
            ], 3600);

            if ($job->attempts() < 3) {
                $job->release(30);
            } else {
                $job->delete();
            }
        }
    }

    public function failed(array $data): void
    {
        $taskId = $data['task_id'] ?? '';
        Log::error('队列任务最终失败', ['task_id' => $taskId, 'data' => $data]);

        if ($taskId) {
            Cache::set("promo_task:{$taskId}", [
                'task_id' => $taskId,
                'status' => 'failed',
                'error' => '任务执行失败，已达到最大重试次数',
                'fail_time' => date('Y-m-d H:i:s'),
            ], 3600);
        }
    }
}
