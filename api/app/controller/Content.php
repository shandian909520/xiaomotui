<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\ContentService;
use app\validate\Content as ContentValidate;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 内容控制器
 * 处理内容生成相关的API请求
 */
class Content extends BaseController
{
    protected ContentService $contentService;

    /**
     * 控制器初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->contentService = new ContentService();
    }

    /**
     * 创建内容生成任务
     * POST /api/content/generate
     *
     * 创建一个内容生成任务，支持视频、菜单、图片等类型
     * 任务将异步处理，返回任务ID供后续查询状态
     */
    public function generate()
    {
        try {
            $data = $this->request->post();

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取商家ID（可选，如果是商家用户）
            $merchantId = $this->request->merchant_id ?? null;

            // 数据验证
            $this->validate($data, 'Content.generate');

            // 处理内容生成任务创建
            $result = $this->contentService->createGenerationTask(
                $userId,
                $merchantId,
                $data
            );

            // 记录任务创建日志
            Log::info('内容生成任务创建成功', [
                'task_id' => $result['task_id'],
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'type' => $data['type'],
                'device_id' => $data['device_id'] ?? null
            ]);

            // 使用专用的内容生成状态响应格式
            return $this->contentGenerationStatus('pending', $result);

        } catch (ValidateException $e) {
            return $this->validationError(['generate' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('内容生成任务创建失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId ?? null,
                'data' => $data ?? []
            ]);

            // 根据错误类型返回不同的错误码
            if (strpos($e->getMessage(), '设备未找到') !== false) {
                return $this->platformError('DEVICE_NOT_FOUND', [
                    'device_id' => $data['device_id'] ?? 'unknown'
                ], 404);
            }

            if (strpos($e->getMessage(), '模板未找到') !== false) {
                return $this->platformError('TEMPLATE_NOT_FOUND', [
                    'template_id' => $data['template_id'] ?? 'unknown'
                ], 404);
            }

            if (strpos($e->getMessage(), '配额不足') !== false) {
                return $this->platformError('QUOTA_INSUFFICIENT', null, 429);
            }

            return $this->error($e->getMessage(), 400, 'content_generation_failed');
        }
    }

    /**
     * 查询任务状态
     * GET /api/content/task/{task_id}/status
     *
     * 根据任务ID查询内容生成任务的状态和结果
     * 支持查询单个任务或批量查询多个任务
     */
    public function taskStatus($taskId = null)
    {
        try {
            // 从路径参数或query参数获取task_id（优先路径参数）
            if (!$taskId) {
                $taskId = $this->request->param('task_id', '');
            }
            $taskIds = $this->request->param('task_ids', '');

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 数据验证
            if (empty($taskId) && empty($taskIds)) {
                return $this->validationError(['task_id' => '任务ID不能为空']);
            }

            // 批量查询还是单个查询
            if (!empty($taskIds)) {
                // 批量查询
                $this->validate(['task_ids' => $taskIds], 'Content.batchTaskStatus');
                $taskIdArray = explode(',', $taskIds);
                $result = $this->contentService->getBatchTaskStatus($userId, $taskIdArray);

                return $this->batchResponse($result, '批量任务状态查询完成');
            } else {
                // 单个查询
                $this->validate(['task_id' => $taskId], 'Content.taskStatus');
                $result = $this->contentService->getTaskStatus($userId, $taskId);

                // 使用专用的内容生成状态响应格式
                return $this->contentGenerationStatus($result['status'], $result);
            }

        } catch (ValidateException $e) {
            return $this->validationError(['task_status' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('查询任务状态失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'task_id' => $taskId ?? null,
                'task_ids' => $taskIds ?? null
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $taskId ?: $taskIds
                ], 404);
            }

            if (strpos($e->getMessage(), '无权访问') !== false) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            return $this->error($e->getMessage(), 400, 'task_status_query_failed');
        }
    }

    /**
     * 获取模板列表
     * GET /api/content/templates
     *
     * 获取可用的内容模板列表，支持分页、筛选和搜索
     * 包括系统模板和用户自定义模板
     */
    public function templates()
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取商家ID（可选）
            $merchantId = $this->request->merchant_id ?? null;

            // 获取查询参数
            $params = [
                'page' => (int)$this->request->param('page', 1),
                'limit' => (int)$this->request->param('limit', 20),
                'type' => $this->request->param('type', ''),
                'category' => $this->request->param('category', ''),
                'style' => $this->request->param('style', ''),
                'keyword' => $this->request->param('keyword', ''),
                'include_system' => $this->request->param('include_system', 'true'),
                'sort' => $this->request->param('sort', 'usage_count')
            ];

            // 参数验证
            $this->validate($params, 'Content.templates');

            // 获取模板列表
            $result = $this->contentService->getTemplateList($userId, $merchantId, $params);

            // 记录模板查询日志
            Log::info('获取内容模板列表', [
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'params' => $params,
                'total' => $result['total']
            ]);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $params['page'],
                $params['limit'],
                '获取模板列表成功'
            );

        } catch (ValidateException $e) {
            return $this->validationError(['templates' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取模板列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'merchant_id' => $merchantId ?? null,
                'params' => $params ?? []
            ]);

            return $this->error($e->getMessage(), 400, 'get_templates_failed');
        }
    }

    /**
     * 获取任务历史列表
     * GET /api/content/task-history
     *
     * 获取用户的内容生成任务历史，支持分页和筛选
     */
    public function taskHistory()
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取商家ID（可选）
            $merchantId = $this->request->merchant_id ?? null;

            // 获取查询参数
            $params = [
                'page' => (int)$this->request->param('page', 1),
                'limit' => (int)$this->request->param('limit', 20),
                'type' => $this->request->param('type', ''),
                'status' => $this->request->param('status', ''),
                'device_id' => $this->request->param('device_id', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date' => $this->request->param('end_date', '')
            ];

            // 参数验证
            $this->validate($params, 'Content.taskHistory');

            // 获取任务历史
            $result = $this->contentService->getTaskHistory($userId, $merchantId, $params);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $params['page'],
                $params['limit'],
                '获取任务历史成功'
            );

        } catch (ValidateException $e) {
            return $this->validationError(['task_history' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取任务历史失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'merchant_id' => $merchantId ?? null
            ]);

            return $this->error($e->getMessage(), 400, 'get_task_history_failed');
        }
    }

    /**
     * 重新生成内容
     * POST /api/content/regenerate
     *
     * 基于已有任务重新生成内容
     */
    public function regenerate()
    {
        try {
            $data = $this->request->post();

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 数据验证
            $this->validate($data, 'Content.regenerate');

            // 处理重新生成任务
            $result = $this->contentService->regenerateContent($userId, $data['task_id'], $data);

            Log::info('内容重新生成任务创建成功', [
                'original_task_id' => $data['task_id'],
                'new_task_id' => $result['task_id'],
                'user_id' => $userId
            ]);

            return $this->contentGenerationStatus('pending', $result);

        } catch (ValidateException $e) {
            return $this->validationError(['regenerate' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('内容重新生成失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'data' => $data ?? []
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $data['task_id'] ?? 'unknown'
                ], 404);
            }

            return $this->error($e->getMessage(), 400, 'content_regeneration_failed');
        }
    }

    /**
     * 取消任务
     * POST /api/content/cancel-task
     *
     * 取消正在处理的内容生成任务
     */
    public function cancelTask()
    {
        try {
            $data = $this->request->post();

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 数据验证
            $this->validate($data, 'Content.cancelTask');

            // 处理任务取消
            $result = $this->contentService->cancelTask($userId, $data['task_id']);

            Log::info('内容生成任务取消成功', [
                'task_id' => $data['task_id'],
                'user_id' => $userId
            ]);

            return $this->success($result, '任务取消成功');

        } catch (ValidateException $e) {
            return $this->validationError(['cancel_task' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('取消任务失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'data' => $data ?? []
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $data['task_id'] ?? 'unknown'
                ], 404);
            }

            if (strpos($e->getMessage(), '任务无法取消') !== false) {
                return $this->platformError('TASK_CANNOT_CANCEL', [
                    'task_id' => $data['task_id'] ?? 'unknown'
                ], 400);
            }

            return $this->error($e->getMessage(), 400, 'cancel_task_failed');
        }
    }

    /**
     * 获取内容生成统计
     * GET /api/content/stats
     *
     * 获取用户的内容生成统计信息
     */
    public function stats()
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取商家ID（可选）
            $merchantId = $this->request->merchant_id ?? null;

            // 获取查询参数
            $params = [
                'start_date' => $this->request->param('start_date', ''),
                'end_date' => $this->request->param('end_date', ''),
                'device_id' => $this->request->param('device_id', '')
            ];

            // 获取统计数据
            $result = $this->contentService->getContentStats($userId, $merchantId, $params);

            return $this->success($result, '获取统计信息成功');

        } catch (\Exception $e) {
            Log::error('获取内容生成统计失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'merchant_id' => $merchantId ?? null
            ]);

            return $this->error($e->getMessage(), 400, 'get_stats_failed');
        }
    }

    /**
     * 获取我的内容列表
     * GET /api/content/my
     */
    public function my()
    {
        try {
            // 获取用户ID
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');
            $type = $this->request->param('type', '');

            // 构建查询
            $query = \app\model\ContentTask::where('user_id', $userId);

            if ($status) {
                $query->where('status', $status);
            }

            if ($type) {
                $query->where('type', $type);
            }

            // 分页查询
            $list = $query->order('create_time', 'desc')
                ->page($page, $limit)
                ->select();

            $total = $query->count();

            return $this->success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], '获取成功');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_my_content_failed');
        }
    }

    /**
     * 提交内容反馈
     */
    public function submitFeedback()
    {
        try {
            $data = $this->request->post();

            // 验证必填字段
            $this->validate($data, [
                'task_id|任务ID' => 'require|integer',
                'feedback_type|反馈类型' => 'require|in:like,dislike'
            ]);

            // 获取任务信息
            $task = \app\model\ContentTask::find($data['task_id']);
            if (!$task) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            // 验证用户权限
            if ($task->user_id !== $this->userId) {
                return $this->error('无权操作', 403, 'permission_denied');
            }

            // 如果是dislike，检查是否有原因
            if ($data['feedback_type'] === 'dislike') {
                $reasons = $data['reasons'] ?? [];
                $otherReason = $data['other_reason'] ?? '';

                if (empty($reasons) && empty($otherReason)) {
                    return $this->error('点踩时请至少选择一个原因或填写其他原因', 400, 'reasons_required');
                }
            }

            // 创建或更新反馈
            $feedbackData = [
                'task_id' => $data['task_id'],
                'user_id' => $this->userId,
                'merchant_id' => $this->merchantId,
                'feedback_type' => $data['feedback_type'],
                'reasons' => $data['reasons'] ?? [],
                'other_reason' => $data['other_reason'] ?? '',
                'submit_time' => $data['submit_time'] ?? date('Y-m-d H:i:s')
            ];

            $feedback = \app\model\ContentFeedback::createOrUpdateFeedback($feedbackData);

            return $this->success([
                'feedback_id' => $feedback->id,
                'feedback_type' => $feedback->feedback_type,
                'submit_time' => $feedback->submit_time
            ], '反馈提交成功');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'submit_feedback_failed');
        }
    }

    /**
     * 获取反馈统计
     */
    public function feedbackStats()
    {
        try {
            $merchantId = $this->merchantId;
            $startDate = $this->request->get('start_date');
            $endDate = $this->request->get('end_date');

            // 获取满意度统计
            $satisfactionStats = \app\model\ContentFeedback::getSatisfactionStats($merchantId, $startDate, $endDate);

            // 获取不满意原因统计
            $dislikeReasons = \app\model\ContentFeedback::getDislikeReasonsStats($merchantId, $startDate, $endDate);

            return $this->success([
                'satisfaction' => $satisfactionStats,
                'dislike_reasons' => $dislikeReasons
            ], '获取成功');

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_feedback_stats_failed');
        }
    }
}