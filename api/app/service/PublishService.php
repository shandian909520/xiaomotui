<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\Exception;
use app\model\PublishTask;
use app\model\ContentTask;
use app\model\PlatformAccount;

/**
 * 平台发布服务
 * 管理内容到各平台的发布任务
 */
class PublishService
{
    /**
     * 创建发布任务
     *
     * @param array $params 发布参数
     * @return array
     */
    public function createPublishTask(array $params): array
    {
        Log::info('创建发布任务', $params);

        try {
            // 验证必要参数
            if (empty($params['content_task_id'])) {
                throw new Exception('内容任务ID不能为空');
            }

            if (empty($params['user_id'])) {
                throw new Exception('用户ID不能为空');
            }

            if (empty($params['platforms']) || !is_array($params['platforms'])) {
                throw new Exception('发布平台配置不能为空');
            }

            // 验证内容任务是否存在且已完成
            $contentTask = ContentTask::find($params['content_task_id']);
            if (!$contentTask) {
                throw new Exception('内容任务不存在');
            }

            if ($contentTask->status !== ContentTask::STATUS_COMPLETED) {
                throw new Exception('内容任务未完成，无法发布');
            }

            // 验证平台账号
            $validatedPlatforms = $this->validatePlatforms(
                $params['user_id'],
                $params['platforms']
            );

            if (empty($validatedPlatforms)) {
                throw new Exception('没有有效的平台账号');
            }

            // 创建发布任务
            $publishTask = new PublishTask();
            $publishTask->content_task_id = $params['content_task_id'];
            $publishTask->user_id = $params['user_id'];
            $publishTask->platforms = $validatedPlatforms;
            $publishTask->status = PublishTask::STATUS_PENDING;

            // 设置定时发布时间
            if (!empty($params['scheduled_time'])) {
                $scheduledTime = strtotime($params['scheduled_time']);
                if ($scheduledTime <= time()) {
                    throw new Exception('定时发布时间必须晚于当前时间');
                }
                $publishTask->scheduled_time = date('Y-m-d H:i:s', $scheduledTime);
            }

            $publishTask->save();

            Log::info('发布任务创建成功', [
                'task_id' => $publishTask->id,
                'platforms_count' => count($validatedPlatforms)
            ]);

            return [
                'success' => true,
                'task_id' => $publishTask->id,
                'status' => $publishTask->status,
                'platforms_count' => count($validatedPlatforms),
                'scheduled_time' => $publishTask->scheduled_time,
                'message' => '发布任务已创建'
            ];

        } catch (Exception $e) {
            Log::error('创建发布任务失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 验证平台配置
     *
     * @param int $userId 用户ID
     * @param array $platforms 平台配置列表
     * @return array
     */
    protected function validatePlatforms(int $userId, array $platforms): array
    {
        $validatedPlatforms = [];

        foreach ($platforms as $platformConfig) {
            if (empty($platformConfig['platform'])) {
                Log::warning('平台配置缺少platform字段', $platformConfig);
                continue;
            }

            $platform = $platformConfig['platform'];

            // 获取平台账号
            $account = !empty($platformConfig['account_id'])
                ? PlatformAccount::find($platformConfig['account_id'])
                : PlatformAccount::findByUserAndPlatform($userId, $platform);

            if (!$account) {
                Log::warning('平台账号不存在', [
                    'user_id' => $userId,
                    'platform' => $platform
                ]);
                continue;
            }

            // 检查账号是否有效
            if (!$account->isValid()) {
                Log::warning('平台账号已失效', [
                    'account_id' => $account->id,
                    'platform' => $platform
                ]);
                continue;
            }

            // 添加到有效平台列表
            $validatedPlatforms[] = [
                'platform' => $platform,
                'account_id' => $account->id,
                'platform_uid' => $account->platform_uid,
                'platform_name' => $account->platform_name,
                'config' => $platformConfig['config'] ?? []
            ];
        }

        return $validatedPlatforms;
    }

    /**
     * 执行发布任务
     *
     * @param int $taskId 任务ID
     * @return array
     */
    public function executePublishTask(int $taskId): array
    {
        Log::info('开始执行发布任务', ['task_id' => $taskId]);

        try {
            // 获取发布任务
            $publishTask = PublishTask::find($taskId);
            if (!$publishTask) {
                throw new Exception('发布任务不存在');
            }

            // 检查任务状态
            if ($publishTask->status !== PublishTask::STATUS_PENDING) {
                throw new Exception('任务状态不是待发布');
            }

            // 检查定时发布时间
            if (!$publishTask->isScheduledTimeReached()) {
                throw new Exception('未到定时发布时间');
            }

            // 标记为发布中
            $publishTask->startPublishing();

            // 获取内容任务
            $contentTask = ContentTask::find($publishTask->content_task_id);
            if (!$contentTask) {
                throw new Exception('内容任务不存在');
            }

            // 获取内容数据
            $contentData = $contentTask->output_data;

            // 执行各平台发布
            $results = [];
            foreach ($publishTask->platforms as $platformConfig) {
                $result = $this->publishToPlatform(
                    $platformConfig,
                    $contentData,
                    $contentTask->type
                );
                $results[] = $result;
            }

            // 更新发布任务状态
            $publishTask->complete($results);

            Log::info('发布任务执行完成', [
                'task_id' => $taskId,
                'status' => $publishTask->status,
                'results_count' => count($results)
            ]);

            return [
                'success' => true,
                'task_id' => $taskId,
                'status' => $publishTask->status,
                'results' => $results
            ];

        } catch (Exception $e) {
            Log::error('执行发布任务失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            // 标记任务失败
            if (isset($publishTask)) {
                $publishTask->markAsFailed($e->getMessage());
            }

            return [
                'success' => false,
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 发布到指定平台
     *
     * @param array $platformConfig 平台配置
     * @param array $contentData 内容数据
     * @param string $contentType 内容类型
     * @return array
     */
    protected function publishToPlatform(array $platformConfig, array $contentData, string $contentType): array
    {
        $platform = $platformConfig['platform'];
        $startTime = microtime(true);

        Log::info('开始发布到平台', [
            'platform' => $platform,
            'content_type' => $contentType
        ]);

        try {
            // 根据平台调用不同的发布方法
            $result = match ($platform) {
                'DOUYIN' => $this->publishToDouyin($platformConfig, $contentData, $contentType),
                'XIAOHONGSHU' => $this->publishToXiaohongshu($platformConfig, $contentData, $contentType),
                'WECHAT' => $this->publishToWechat($platformConfig, $contentData, $contentType),
                'WEIBO' => $this->publishToWeibo($platformConfig, $contentData, $contentType),
                default => throw new Exception("不支持的平台: {$platform}")
            };

            $publishTime = microtime(true) - $startTime;

            return [
                'platform' => $platform,
                'success' => true,
                'platform_post_id' => $result['post_id'] ?? '',
                'platform_url' => $result['url'] ?? '',
                'publish_time' => round($publishTime, 2),
                'message' => '发布成功'
            ];

        } catch (Exception $e) {
            $publishTime = microtime(true) - $startTime;

            Log::error('发布到平台失败', [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return [
                'platform' => $platform,
                'success' => false,
                'error' => $e->getMessage(),
                'publish_time' => round($publishTime, 2)
            ];
        }
    }

    /**
     * 发布到抖音
     *
     * @param array $platformConfig 平台配置
     * @param array $contentData 内容数据
     * @param string $contentType 内容类型
     * @return array
     */
    protected function publishToDouyin(array $platformConfig, array $contentData, string $contentType): array
    {
        // 实际开发中应调用抖音开放平台API
        // 这里返回模拟数据

        Log::info('发布到抖音', [
            'platform_uid' => $platformConfig['platform_uid'],
            'content_type' => $contentType
        ]);

        // 模拟API调用
        sleep(1);

        return [
            'post_id' => 'dy_' . uniqid(),
            'url' => 'https://www.douyin.com/video/' . uniqid(),
            'message' => '发布到抖音成功'
        ];
    }

    /**
     * 发布到小红书
     *
     * @param array $platformConfig 平台配置
     * @param array $contentData 内容数据
     * @param string $contentType 内容类型
     * @return array
     */
    protected function publishToXiaohongshu(array $platformConfig, array $contentData, string $contentType): array
    {
        // 实际开发中应调用小红书API
        // 这里返回模拟数据

        Log::info('发布到小红书', [
            'platform_uid' => $platformConfig['platform_uid'],
            'content_type' => $contentType
        ]);

        // 模拟API调用
        sleep(1);

        return [
            'post_id' => 'xhs_' . uniqid(),
            'url' => 'https://www.xiaohongshu.com/explore/' . uniqid(),
            'message' => '发布到小红书成功'
        ];
    }

    /**
     * 发布到微信
     *
     * @param array $platformConfig 平台配置
     * @param array $contentData 内容数据
     * @param string $contentType 内容类型
     * @return array
     */
    protected function publishToWechat(array $platformConfig, array $contentData, string $contentType): array
    {
        // 实际开发中应调用微信API
        // 这里返回模拟数据

        Log::info('发布到微信', [
            'platform_uid' => $platformConfig['platform_uid'],
            'content_type' => $contentType
        ]);

        // 模拟API调用
        sleep(1);

        return [
            'post_id' => 'wx_' . uniqid(),
            'url' => 'https://mp.weixin.qq.com/s/' . uniqid(),
            'message' => '发布到微信成功'
        ];
    }

    /**
     * 发布到微博
     *
     * @param array $platformConfig 平台配置
     * @param array $contentData 内容数据
     * @param string $contentType 内容类型
     * @return array
     */
    protected function publishToWeibo(array $platformConfig, array $contentData, string $contentType): array
    {
        // 实际开发中应调用微博API
        // 这里返回模拟数据

        Log::info('发布到微博', [
            'platform_uid' => $platformConfig['platform_uid'],
            'content_type' => $contentType
        ]);

        // 模拟API调用
        sleep(1);

        return [
            'post_id' => 'wb_' . uniqid(),
            'url' => 'https://weibo.com/' . uniqid(),
            'message' => '发布到微博成功'
        ];
    }

    /**
     * 批量执行待发布任务
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function executePendingTasks(int $limit = 10): array
    {
        $tasks = PublishTask::getPendingTasks($limit);

        $successCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($tasks as $taskData) {
            $result = $this->executePublishTask($taskData['id']);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }

            $results[] = $result;
        }

        Log::info('批量执行待发布任务完成', [
            'total' => count($tasks),
            'success' => $successCount,
            'failed' => $failedCount
        ]);

        return [
            'total' => count($tasks),
            'success' => $successCount,
            'failed' => $failedCount,
            'results' => $results
        ];
    }

    /**
     * 取消发布任务
     *
     * @param int $taskId 任务ID
     * @return bool
     */
    public function cancelPublishTask(int $taskId): bool
    {
        try {
            $publishTask = PublishTask::find($taskId);
            if (!$publishTask) {
                throw new Exception('发布任务不存在');
            }

            if ($publishTask->status !== PublishTask::STATUS_PENDING) {
                throw new Exception('只能取消待发布状态的任务');
            }

            return $publishTask->delete();

        } catch (Exception $e) {
            Log::error('取消发布任务失败', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}