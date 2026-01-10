<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ContentTask;
use app\model\ContentTemplate;
use app\model\NfcDevice;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Queue;

/**
 * 内容服务类
 * 处理内容生成相关的业务逻辑
 */
class ContentService
{
    /**
     * 创建内容生成任务
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @param array $data 任务数据
     * @return array
     * @throws \Exception
     */
    public function createGenerationTask(int $userId, ?int $merchantId, array $data): array
    {
        // 验证设备权限
        $device = $this->validateDevicePermission($data['device_id'] ?? 0, $userId, $merchantId);

        // 验证模板（如果指定了模板）
        $template = null;
        if (!empty($data['template_id'])) {
            $template = $this->validateTemplatePermission($data['template_id'], $data['type'], $merchantId);
        }

        // 检查用户配额
        $this->checkUserQuota($userId, $merchantId);

        // 构建任务数据
        $taskData = [
            'device_id' => $data['device_id'] ?? null,
            'merchant_id' => $data['merchant_id'],
            'user_id' => $userId,
            'template_id' => $data['template_id'] ?? null,
            'type' => strtoupper($data['type']),
            'status' => ContentTask::STATUS_PENDING,
            'input_data' => [
                'requirements' => $data['input_data'] ?? [],
                'template_config' => $template ? $template->content : null,
                'client_info' => [
                    'ip' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'create_time' => date('Y-m-d H:i:s')
                ]
            ]
        ];

        // 创建任务
        $task = ContentTask::create($taskData);

        if (!$task) {
            throw new \Exception('创建内容生成任务失败');
        }

        // 增加模板使用次数
        if ($template) {
            $template->incrementUsageCount();
        }

        // 将任务加入队列进行异步处理（带重试配置）
        $this->dispatchGenerationTask($task, 0);  // 初始重试次数为0

        // 记录操作日志
        Log::info('内容生成任务创建成功', [
            'task_id' => $task->id,
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'device_id' => $device->id,
            'type' => $data['type'],
            'template_id' => $template?->id
        ]);

        return [
            'task_id' => $task->id,
            'status' => $task->status,
            'type' => $task->type,
            'estimated_time' => $this->getEstimatedProcessingTime($task->type),
            'create_time' => $task->create_time,
            'message' => '任务已创建，预计' . $this->getEstimatedProcessingTime($task->type) . '秒完成',
            'retry_policy' => [
                'max_retries' => 3,
                'retry_on' => ['timeout', 'network_error', 'rate_limit']
            ]
        ];
    }

    /**
     * 获取任务状态
     *
     * @param int $userId 用户ID
     * @param string $taskId 任务ID
     * @return array
     * @throws \Exception
     */
    public function getTaskStatus(int $userId, string $taskId): array
    {
        $task = ContentTask::find($taskId);

        if (!$task) {
            throw new \Exception('任务未找到');
        }

        // 验证用户权限
        if ($task->user_id !== $userId) {
            throw new \Exception('无权访问该任务');
        }

        // 检查任务是否超时（仅检查processing状态）
        if ($task->status === 'processing') {
            $processingTime = time() - strtotime($task->update_time);
            $timeout = 600;  // 10分钟超时

            if ($processingTime > $timeout) {
                // 标记为失败
                $task->status = 'failed';
                $task->error_message = sprintf(
                    '任务处理超时（%d秒），已自动标记为失败',
                    $processingTime
                );
                $task->save();

                Log::warning('内容生成任务查询时发现超时', [
                    'task_id' => $task->id,
                    'user_id' => $userId,
                    'processing_time' => $processingTime
                ]);
            }
        }

        // 获取详细进度信息（4步骤）
        $progressInfo = $this->getDetailedProgress($task);

        $result = [
            'task_id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'progress' => $progressInfo['percentage'],
            'progress_details' => $progressInfo['details'],
            'current_step' => $progressInfo['current_step'],
            'step_name' => $progressInfo['step_name'],
            'elapsed_time' => $progressInfo['elapsed_time'],
            'create_time' => $task->create_time,
            'update_time' => $task->update_time,
            'complete_time' => $task->complete_time,
            'merchant_id' => $task->merchant_id,
            'device_id' => $task->device_id,
            'template_id' => $task->template_id,
            'ai_provider' => $task->ai_provider,
        ];

        // 如果任务完成，添加结果信息
        if ($task->status === ContentTask::STATUS_COMPLETED) {
            $result['result'] = $task->output_data;
            $result['generation_time'] = $task->generation_time;
        }

        // 如果任务失败，添加错误信息
        if ($task->status === ContentTask::STATUS_FAILED) {
            $result['error_message'] = $task->error_message;
        }

        // 如果任务正在处理或待处理，添加预估剩余时间
        if (in_array($task->status, [ContentTask::STATUS_PENDING, ContentTask::STATUS_PROCESSING])) {
            $result['estimated_remaining_time'] = $progressInfo['estimated_remaining_time'];
            $result['estimated_total_time'] = $progressInfo['estimated_total_time'];
        }

        return $result;
    }

    /**
     * 获取详细的4步骤进度信息
     *
     * @param ContentTask $task 任务对象
     * @return array 进度信息
     */
    private function getDetailedProgress(ContentTask $task): array
    {
        // 4个步骤的权重分配：分析需求10% → AI模型50% → 生成内容30% → 质量检查10%
        $steps = [
            1 => ['name' => '分析需求', 'icon' => '🔍', 'weight' => 10],
            2 => ['name' => '调用AI模型', 'icon' => '🤖', 'weight' => 50],
            3 => ['name' => '生成内容', 'icon' => '✨', 'weight' => 30],
            4 => ['name' => '质量检查', 'icon' => '✅', 'weight' => 10]
        ];

        // 计算已用时间
        $elapsedTime = time() - strtotime($task->create_time);

        // 获取预估总时间
        $estimatedTotalTime = $this->getEstimatedProcessingTime($task->type);

        // 根据状态确定当前步骤和进度
        $currentStep = 0;
        $percentage = 0;
        $stepName = '';

        if ($task->status === ContentTask::STATUS_PENDING) {
            // 待处理：准备阶段
            $currentStep = 0;
            $percentage = 0;
            $stepName = '等待处理';
        } elseif ($task->status === ContentTask::STATUS_PROCESSING) {
            // 处理中：根据已用时间估算当前步骤
            $progressRatio = min($elapsedTime / $estimatedTotalTime, 0.99);

            if ($progressRatio < 0.1) {
                $currentStep = 1;
                $percentage = 10 * ($progressRatio / 0.1);
                $stepName = $steps[1]['name'];
            } elseif ($progressRatio < 0.6) {
                $currentStep = 2;
                $percentage = 10 + 50 * (($progressRatio - 0.1) / 0.5);
                $stepName = $steps[2]['name'];
            } elseif ($progressRatio < 0.9) {
                $currentStep = 3;
                $percentage = 60 + 30 * (($progressRatio - 0.6) / 0.3);
                $stepName = $steps[3]['name'];
            } else {
                $currentStep = 4;
                $percentage = 90 + 10 * (($progressRatio - 0.9) / 0.1);
                $stepName = $steps[4]['name'];
            }
        } elseif ($task->status === ContentTask::STATUS_COMPLETED) {
            // 已完成
            $currentStep = 4;
            $percentage = 100;
            $stepName = '已完成';
        } elseif ($task->status === ContentTask::STATUS_FAILED) {
            // 失败
            $currentStep = 0;
            $percentage = 0;
            $stepName = '生成失败';
        }

        // 构建步骤详情
        $stepDetails = [];
        foreach ($steps as $stepNum => $step) {
            $status = 'pending'; // pending, processing, completed

            if ($task->status === ContentTask::STATUS_COMPLETED) {
                $status = 'completed';
            } elseif ($currentStep > $stepNum) {
                $status = 'completed';
            } elseif ($currentStep === $stepNum) {
                $status = 'processing';
            }

            $stepDetails[] = [
                'step' => $stepNum,
                'name' => $step['name'],
                'icon' => $step['icon'],
                'status' => $status,
                'weight' => $step['weight']
            ];
        }

        // 计算剩余时间
        $estimatedRemainingTime = max(0, $estimatedTotalTime - $elapsedTime);

        return [
            'percentage' => round($percentage, 1),
            'current_step' => $currentStep,
            'step_name' => $stepName,
            'elapsed_time' => $elapsedTime,
            'estimated_total_time' => $estimatedTotalTime,
            'estimated_remaining_time' => $estimatedRemainingTime,
            'details' => $stepDetails
        ];
    }

    /**
     * 计算任务进度（旧方法，保留兼容性）
     *
     * @param string $status 任务状态
     * @return int 进度百分比（0-100）
     */
    private function calculateProgress(string $status): int
    {
        return match($status) {
            ContentTask::STATUS_PENDING => 0,
            ContentTask::STATUS_PROCESSING => 50,
            ContentTask::STATUS_COMPLETED => 100,
            ContentTask::STATUS_FAILED => 0,
            default => 0
        };
    }

    /**
     * 批量获取任务状态
     *
     * @param int $userId 用户ID
     * @param array $taskIds 任务ID列表
     * @return array
     */
    public function getBatchTaskStatus(int $userId, array $taskIds): array
    {
        $tasks = ContentTask::where('user_id', $userId)
                           ->whereIn('id', $taskIds)
                           ->select();

        $results = [];
        foreach ($taskIds as $taskId) {
            $task = $tasks->where('id', $taskId)->first();

            if ($task) {
                $results[] = [
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'success' => true,
                    'data' => [
                        'type' => $task->type,
                        'create_time' => $task->create_time,
                        'complete_time' => $task->complete_time,
                        'output_data' => $task->output_data,
                        'error_message' => $task->error_message
                    ]
                ];
            } else {
                $results[] = [
                    'task_id' => $taskId,
                    'status' => 'not_found',
                    'success' => false,
                    'error' => '任务未找到或无权访问'
                ];
            }
        }

        return $results;
    }

    /**
     * 获取模板列表
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @param array $params 查询参数
     * @return array
     */
    public function getTemplateList(int $userId, ?int $merchantId, array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;
        $type = $params['type'] ?? '';
        $category = $params['category'] ?? '';
        $style = $params['style'] ?? '';
        $keyword = $params['keyword'] ?? '';
        $includeSystem = $params['include_system'] ?? 'true';
        $sort = $params['sort'] ?? 'usage_count';

        // 构建查询条件
        $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED);

        // 类型筛选
        if (!empty($type)) {
            $query->where('type', strtoupper($type));
        }

        // 分类筛选
        if (!empty($category)) {
            $query->where('category', $category);
        }

        // 风格筛选
        if (!empty($style)) {
            $query->where('style', $style);
        }

        // 关键词搜索
        if (!empty($keyword)) {
            $query->where(function($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")
                  ->whereOr('category', 'like', "%{$keyword}%")
                  ->whereOr('style', 'like', "%{$keyword}%");
            });
        }

        // 商家和系统模板筛选
        if ($merchantId && strtolower($includeSystem) === 'true') {
            // 包含系统模板和商家模板
            $query->where(function($q) use ($merchantId) {
                $q->whereNull('merchant_id')
                  ->whereOr('merchant_id', $merchantId);
            });
        } elseif ($merchantId) {
            // 只包含商家模板
            $query->where('merchant_id', $merchantId);
        } else {
            // 只包含系统模板
            $query->whereNull('merchant_id');
        }

        // 排序
        $sortField = match($sort) {
            'create_time' => 'create_time',
            'name' => 'name',
            'update_time' => 'update_time',
            default => 'usage_count'
        };

        $sortOrder = in_array($sort, ['create_time', 'update_time']) ? 'desc' : 'asc';
        if ($sort === 'usage_count') {
            $sortOrder = 'desc';
        }

        $query->order($sortField, $sortOrder);

        // 分页查询
        $total = $query->count();
        $templates = $query->page($page, $limit)->select();

        // 转换为数组格式
        $templateList = [];
        foreach ($templates as $template) {
            $templateList[] = [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'type_text' => $template->type_text,
                'category' => $template->category,
                'style' => $template->style,
                'preview_url' => $template->preview_url,
                'usage_count' => $template->usage_count,
                'is_public' => $template->is_public,
                'is_public_text' => $template->is_public_text,
                'template_source' => $template->template_source,
                'create_time' => $template->create_time,
                'content' => $template->content // 模板配置内容
            ];
        }

        return [
            'list' => $templateList,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 获取任务历史
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @param array $params 查询参数
     * @return array
     */
    public function getTaskHistory(int $userId, ?int $merchantId, array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        // 构建查询条件
        $query = ContentTask::where('user_id', $userId);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        // 类型筛选
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        // 状态筛选
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 设备筛选
        if (!empty($params['device_id'])) {
            $query->where('device_id', $params['device_id']);
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('create_time', '>=', $params['start_date'] . ' 00:00:00');
        }

        if (!empty($params['end_date'])) {
            $query->where('create_time', '<=', $params['end_date'] . ' 23:59:59');
        }

        // 排序
        $query->order('create_time', 'desc');

        // 分页查询
        $total = $query->count();
        $tasks = $query->page($page, $limit)->select();

        // 转换为数组格式
        $taskList = [];
        foreach ($tasks as $task) {
            $taskList[] = [
                'id' => $task->id,
                'type' => $task->type,
                'type_text' => $task->type_text,
                'status' => $task->status,
                'status_text' => $task->status_text,
                'generation_time' => $task->generation_time,
                'error_message' => $task->error_message,
                'create_time' => $task->create_time,
                'update_time' => $task->update_time,
                'complete_time' => $task->complete_time,
                'device_info' => $task->device ? [
                    'device_code' => $task->device->device_code,
                    'device_name' => $task->device->device_name
                ] : null
            ];
        }

        return [
            'list' => $taskList,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 重新生成内容
     *
     * @param int $userId 用户ID
     * @param string $taskId 原任务ID
     * @param array $data 调整参数
     * @return array
     * @throws \Exception
     */
    public function regenerateContent(int $userId, string $taskId, array $data): array
    {
        $originalTask = ContentTask::find($taskId);

        if (!$originalTask) {
            throw new \Exception('原任务未找到');
        }

        if ($originalTask->user_id !== $userId) {
            throw new \Exception('无权访问该任务');
        }

        // 构建新任务数据（基于原任务）
        $newTaskData = [
            'device_id' => $originalTask->device_id,
            'merchant_id' => $originalTask->merchant_id,
            'user_id' => $userId,
            'template_id' => $originalTask->template_id,
            'type' => $originalTask->type,
            'status' => ContentTask::STATUS_PENDING,
            'input_data' => array_merge($originalTask->input_data ?? [], [
                'regenerated_from' => $originalTask->id,
                'regenerate_reason' => $data['regenerate_reason'] ?? '',
                'adjust_params' => $data['adjust_params'] ?? [],
                'regenerate_time' => date('Y-m-d H:i:s')
            ])
        ];

        // 创建新任务
        $newTask = ContentTask::create($newTaskData);

        if (!$newTask) {
            throw new \Exception('创建重新生成任务失败');
        }

        // 将任务加入队列
        $this->dispatchGenerationTask($newTask);

        Log::info('内容重新生成任务创建成功', [
            'original_task_id' => $originalTask->id,
            'new_task_id' => $newTask->id,
            'user_id' => $userId
        ]);

        return [
            'task_id' => $newTask->id,
            'status' => $newTask->status,
            'type' => $newTask->type,
            'original_task_id' => $originalTask->id,
            'estimated_time' => $this->getEstimatedProcessingTime($newTask->type),
            'create_time' => $newTask->create_time,
            'message' => '重新生成任务已创建'
        ];
    }

    /**
     * 取消任务
     *
     * @param int $userId 用户ID
     * @param string $taskId 任务ID
     * @return array
     * @throws \Exception
     */
    public function cancelTask(int $userId, string $taskId): array
    {
        $task = ContentTask::find($taskId);

        if (!$task) {
            throw new \Exception('任务未找到');
        }

        if ($task->user_id !== $userId) {
            throw new \Exception('无权访问该任务');
        }

        // 只有待处理和处理中的任务可以取消
        if (!in_array($task->status, [ContentTask::STATUS_PENDING, ContentTask::STATUS_PROCESSING])) {
            throw new \Exception('任务无法取消');
        }

        // 更新任务状态
        $task->status = ContentTask::STATUS_FAILED;
        $task->error_message = '用户取消了任务';
        $task->complete_time = date('Y-m-d H:i:s');
        $task->save();

        // 如果任务在队列中，尝试取消队列任务
        // 这里可以实现具体的队列取消逻辑

        return [
            'task_id' => $task->id,
            'status' => $task->status,
            'cancelled_at' => $task->complete_time
        ];
    }

    /**
     * 获取内容生成统计
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @param array $params 查询参数
     * @return array
     */
    public function getContentStats(int $userId, ?int $merchantId, array $params): array
    {
        $query = ContentTask::where('user_id', $userId);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        // 设备筛选
        if (!empty($params['device_id'])) {
            $query->where('device_id', $params['device_id']);
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('create_time', '>=', $params['start_date'] . ' 00:00:00');
        }

        if (!empty($params['end_date'])) {
            $query->where('create_time', '<=', $params['end_date'] . ' 23:59:59');
        }

        // 基础统计
        $total = $query->count();
        $pending = $query->where('status', ContentTask::STATUS_PENDING)->count();
        $processing = $query->where('status', ContentTask::STATUS_PROCESSING)->count();
        $completed = $query->where('status', ContentTask::STATUS_COMPLETED)->count();
        $failed = $query->where('status', ContentTask::STATUS_FAILED)->count();

        // 按类型统计
        $typeStats = ContentTask::where('user_id', $userId)
                                ->field('type, count(*) as count')
                                ->group('type')
                                ->select()
                                ->toArray();

        $typeCount = [];
        foreach ($typeStats as $stat) {
            $typeCount[$stat['type']] = $stat['count'];
        }

        // 计算成功率
        $successRate = $total > 0 ? round($completed / $total * 100, 2) : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $successRate,
            'type_count' => $typeCount,
            'avg_processing_time' => $this->getAverageProcessingTime($userId, $merchantId),
            'recent_activity' => $this->getRecentActivity($userId, 10)
        ];
    }

    /**
     * 验证设备权限（可选）
     *
     * @param int $deviceId 设备ID
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @return NfcDevice|null
     * @throws \Exception
     */
    private function validateDevicePermission(int $deviceId, int $userId, ?int $merchantId): ?NfcDevice
    {
        if (!$deviceId) {
            return null;
        }

        $device = NfcDevice::find($deviceId);

        if (!$device) {
            throw new \Exception('设备未找到');
        }

        // 如果用户有商家ID，检查设备是否属于该商家
        if ($merchantId && $device->merchant_id !== $merchantId) {
            throw new \Exception('无权使用该设备');
        }

        // 检查设备状态
        if ($device->status !== 1) {
            throw new \Exception('设备不可用');
        }

        return $device;
    }

    /**
     * 验证模板权限
     *
     * @param int $templateId 模板ID
     * @param string $type 内容类型
     * @param int|null $merchantId 商家ID
     * @return ContentTemplate
     * @throws \Exception
     */
    private function validateTemplatePermission(int $templateId, string $type, ?int $merchantId): ContentTemplate
    {
        $template = ContentTemplate::find($templateId);

        if (!$template) {
            throw new \Exception('模板未找到');
        }

        if ($template->status !== ContentTemplate::STATUS_ENABLED) {
            throw new \Exception('模板已被禁用');
        }

        // 检查类型匹配
        if (strtoupper($type) !== $template->type) {
            throw new \Exception('模板类型与内容类型不匹配');
        }

        // 检查权限（系统模板或商家模板）
        if ($template->merchant_id && $template->merchant_id !== $merchantId) {
            throw new \Exception('无权使用该模板');
        }

        return $template;
    }

    /**
     * 检查用户配额
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @throws \Exception
     */
    private function checkUserQuota(int $userId, ?int $merchantId): void
    {
        // 这里可以实现用户配额检查逻辑
        // 例如检查用户今日生成次数、总配额等

        // 示例：检查今日生成次数
        $todayCount = ContentTask::where('user_id', $userId)
                                ->where('create_time', '>=', date('Y-m-d 00:00:00'))
                                ->count();

        $dailyLimit = 100; // 每日限制100次，可从配置中读取
        if ($todayCount >= $dailyLimit) {
            throw new \Exception('今日生成次数已达上限');
        }
    }


    /**
     * 分发生成任务到队列
     *
     * @param ContentTask $task 任务
     */
    /**
     * 分发生成任务到队列
     *
     * @param ContentTask $task 任务对象
     * @param int $retryCount 当前重试次数
     * @return void
     */
    private function dispatchGenerationTask(ContentTask $task, int $retryCount = 0): void
    {
        // 这里可以实现具体的队列分发逻辑
        // Queue::push('app\job\ContentGenerationJob', [
        //     'task_id' => $task->id,
        //     'retry_count' => $retryCount
        // ]);

        Log::info('内容生成任务已加入队列', [
            'task_id' => $task->id,
            'type' => $task->type,
            'retry_count' => $retryCount
        ]);
    }

    /**
     * 处理生成任务失败（队列Job中调用）
     * 实现智能重试机制
     *
     * @param ContentTask $task 任务对象
     * @param \Exception $error 错误对象
     * @param int $retryCount 当前重试次数
     * @return void
     */
    public function handleGenerationFailure(ContentTask $task, \Exception $error, int $retryCount): void
    {
        $maxRetries = 3;
        $retryDelays = [5, 15, 30];  // 递增延迟（秒）

        // 错误分类
        $errorType = $this->classifyGenerationError($error);

        // 不可重试的错误类型
        $nonRetryableErrors = ['quota_exceeded', 'content_violation', 'invalid_params', 'template_not_found'];

        if (in_array($errorType, $nonRetryableErrors)) {
            // 不可重试，直接标记为失败
            $task->status = ContentTask::STATUS_FAILED;
            $task->error_message = $error->getMessage();
            $task->save();

            // 通知用户
            $this->notifyUserFailure($task, '生成失败', $error->getMessage());

            Log::warning('内容生成任务最终失败（不可重试）', [
                'task_id' => $task->id,
                'error_type' => $errorType,
                'error_message' => $error->getMessage()
            ]);

            return;
        }

        // 可重试的错误，且未超过最大次数
        if ($retryCount < $maxRetries) {
            $delaySeconds = $retryDelays[$retryCount];

            Log::info('内容生成任务准备重试', [
                'task_id' => $task->id,
                'retry_count' => $retryCount + 1,
                'max_retries' => $maxRetries,
                'delay_seconds' => $delaySeconds,
                'error_type' => $errorType,
                'error_message' => $error->getMessage()
            ]);

            // 更新任务重试信息
            $task->retry_count = $retryCount + 1;
            $task->last_retry_time = date('Y-m-d H:i:s');
            $task->save();

            // 延迟重试（使用队列延迟功能）
            // Queue::later($delaySeconds, 'app\job\ContentGenerationJob', [
            //     'task_id' => $task->id,
            //     'retry_count' => $retryCount + 1
            // ]);

            // TODO: 实际部署时使用真实队列系统
            sleep($delaySeconds);
            $this->dispatchGenerationTask($task, $retryCount + 1);

        } else {
            // 超过最大重试次数，最终失败
            $task->status = ContentTask::STATUS_FAILED;
            $task->error_message = sprintf(
                '生成失败（已重试%d次）：%s',
                $maxRetries,
                $error->getMessage()
            );
            $task->save();

            // 退款AI费用（如果已扣费）
            $this->refundAICost($task);

            // 通知用户
            $this->notifyUserFailure(
                $task,
                '生成最终失败',
                "任务在重试{$retryCount}次后仍然失败，已为您退还AI费用"
            );

            Log::error('内容生成任务最终失败', [
                'task_id' => $task->id,
                'retry_count' => $retryCount,
                'error_type' => $errorType,
                'error_message' => $error->getMessage()
            ]);
        }
    }

    /**
     * 分类生成错误类型
     *
     * @param \Exception $error 错误对象
     * @return string 错误类型
     */
    private function classifyGenerationError(\Exception $error): string
    {
        $message = $error->getMessage();

        if (stripos($message, 'timeout') !== false || stripos($message, 'timed out') !== false) {
            return 'timeout';
        }

        if (stripos($message, 'network') !== false || stripos($message, 'connection') !== false) {
            return 'network_error';
        }

        if (stripos($message, 'rate limit') !== false || stripos($message, 'too many requests') !== false) {
            return 'rate_limit';
        }

        if (stripos($message, 'quota') !== false || stripos($message, 'insufficient') !== false) {
            return 'quota_exceeded';
        }

        if (stripos($message, 'violation') !== false || stripos($message, 'inappropriate') !== false) {
            return 'content_violation';
        }

        if (stripos($message, 'invalid') !== false || stripos($message, 'parameter') !== false) {
            return 'invalid_params';
        }

        if (stripos($message, 'template') !== false && stripos($message, 'not found') !== false) {
            return 'template_not_found';
        }

        return 'unknown_error';
    }

    /**
     * 退款AI费用
     *
     * @param ContentTask $task 任务对象
     * @return void
     */
    private function refundAICost(ContentTask $task): void
    {
        // TODO: 实现AI费用退款逻辑
        // 1. 查询该任务是否已扣费
        // 2. 如果已扣费，退还到商家账户
        // 3. 记录退款日志

        Log::info('AI费用退款', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'merchant_id' => $task->merchant_id
        ]);
    }

    /**
     * 通知用户生成失败
     *
     * @param ContentTask $task 任务对象
     * @param string $title 通知标题
     * @param string $message 通知消息
     * @return void
     */
    private function notifyUserFailure(ContentTask $task, string $title, string $message): void
    {
        // TODO: 实现用户通知逻辑
        // 1. 小程序模板消息
        // 2. 站内消息
        // 3. 短信通知（重要任务）

        Log::info('发送失败通知', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'title' => $title,
            'message' => $message
        ]);
    }

    /**
     * 获取预估处理时间（秒）
     *
     * @param string $type 内容类型
     * @return int
     */
    private function getEstimatedProcessingTime(string $type): int
    {
        return match(strtoupper($type)) {
            'VIDEO' => 300, // 5分钟
            'IMAGE' => 60,  // 1分钟
            'TEXT' => 30,   // 30秒
            default => 60
        };
    }

    /**
     * 获取预估剩余时间（秒）
     *
     * @param ContentTask $task 任务
     * @return int
     */
    private function getEstimatedRemainingTime(ContentTask $task): int
    {
        $elapsed = time() - strtotime($task->update_time);
        $estimated = $this->getEstimatedProcessingTime($task->type);

        return max(0, $estimated - $elapsed);
    }

    /**
     * 获取平均处理时间
     *
     * @param int $userId 用户ID
     * @param int|null $merchantId 商家ID
     * @return float
     */
    private function getAverageProcessingTime(int $userId, ?int $merchantId): float
    {
        $query = ContentTask::where('user_id', $userId)
                           ->where('status', ContentTask::STATUS_COMPLETED)
                           ->whereNotNull('generation_time');

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        return (float)$query->avg('generation_time') ?: 0;
    }

    /**
     * 获取最近活动
     *
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array
     */
    private function getRecentActivity(int $userId, int $limit): array
    {
        $tasks = ContentTask::where('user_id', $userId)
                           ->order('create_time', 'desc')
                           ->limit($limit)
                           ->select();

        $activities = [];
        foreach ($tasks as $task) {
            $activities[] = [
                'task_id' => $task->id,
                'type' => $task->type,
                'status' => $task->status,
                'create_time' => $task->create_time,
                'complete_time' => $task->complete_time
            ];
        }

        return $activities;
    }
}