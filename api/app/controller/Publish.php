<?php
declare (strict_types = 1);

namespace app\controller;

use think\exception\ValidateException;
use think\facade\Log;
use app\service\PublishService;
use app\service\PlatformOAuthService;
use app\model\PlatformAccount;

/**
 * 发布控制器
 * 处理内容发布到各平台（抖音、小红书、微信、微博等）的相关功能
 */
class Publish extends BaseController
{
    /**
     * 发布服务实例
     */
    protected PublishService $publishService;

    /**
     * OAuth服务实例
     */
    protected PlatformOAuthService $oauthService;

    /**
     * 控制器初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->publishService = new PublishService();
        $this->oauthService = new PlatformOAuthService();
    }

    /**
     * 发布内容到平台
     * POST /api/publish
     *
     * 将生成的内容发布到指定的社交媒体平台
     * 支持单个或多个平台同时发布，支持定时发布
     *
     * 请求参数:
     * {
     *     "content_task_id": 123,           // 内容任务ID，必填
     *     "platforms": [                     // 发布平台列表，必填
     *         {
     *             "platform": "DOUYIN",      // 平台名称：DOUYIN/KUAISHOU/XIAOHONGSHU
     *             "account_id": 456,         // 平台账号ID
     *             "config": {                // 平台专属配置，可选
     *                 "title": "自定义标题",
     *                 "tags": ["咖啡", "探店"],
     *                 "location": "北京市朝阳区",
     *                 "cover_url": "封面图片URL",
     *                 "privacy": "PUBLIC"    // 公开性：PUBLIC/PRIVATE/FRIENDS
     *             }
     *         }
     *     ],
     *     "scheduled_time": "2024-01-01 18:00:00"  // 定时发布时间(可选)
     * }
     *
     * 响应数据:
     * {
     *     "publish_task_id": 789,            // 发布任务ID
     *     "status": "PENDING",               // 任务状态：PENDING/PROCESSING/SUCCESS/FAILED
     *     "platforms_count": 1,              // 发布平台数量
     *     "scheduled": false,                // 是否定时发布
     *     "scheduled_time": null,            // 定时发布时间
     *     "message": "发布任务已创建"
     * }
     */
    public function publish()
    {
        try {
            $data = $this->request->post();

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取商家ID（可选）
            $merchantId = $this->request->merchant_id ?? null;

            // 数据验证
            // TODO: 创建 Publish 验证器
            // $this->validate($data, 'Publish.create');

            // 基本参数验证
            if (empty($data['content_task_id'])) {
                return $this->validationError(['content_task_id' => '内容任务ID不能为空']);
            }

            if (empty($data['platforms']) || !is_array($data['platforms'])) {
                return $this->validationError(['platforms' => '发布平台列表不能为空']);
            }

            // 组装参数
            $params = [
                'content_task_id' => $data['content_task_id'],
                'user_id' => $userId,
                'platforms' => $data['platforms'],
                'scheduled_time' => $data['scheduled_time'] ?? null
            ];

            // 调用发布服务创建发布任务
            $result = $this->publishService->createPublishTask($params);

            if (!$result['success']) {
                return $this->error($result['error'], 400, 'create_publish_task_failed');
            }

            // 记录日志
            Log::info('发布任务创建成功', [
                'user_id' => $userId,
                'task_id' => $result['task_id'],
                'platforms_count' => $result['platforms_count']
            ]);

            return $this->success([
                'publish_task_id' => $result['task_id'],
                'status' => $result['status'],
                'platforms_count' => $result['platforms_count'],
                'scheduled' => !empty($result['scheduled_time']),
                'scheduled_time' => $result['scheduled_time'],
                'message' => $result['message']
            ], '发布任务已创建');

        } catch (ValidateException $e) {
            return $this->validationError(['publish' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建发布任务失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId ?? null,
                'data' => $data ?? []
            ]);

            return $this->error($e->getMessage(), 400, 'create_publish_task_failed');
        }
    }

    /**
     * 平台授权
     * GET /api/publish/platform/{platform}/auth
     *
     * 获取指定平台的OAuth授权URL
     * 用户需要访问此URL完成平台授权
     *
     * 路径参数:
     * @param string $platform 平台名称：douyin/kuaishou/xiaohongshu
     *
     * 响应数据:
     * {
     *     "auth_url": "https://platform.com/oauth/authorize?xxx",  // 授权URL
     *     "state": "random_state_string",                          // 状态码（用于验证回调）
     *     "expires_in": 600                                        // 状态码有效期（秒）
     * }
     */
    public function platformAuth($platform)
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 验证平台参数
            $supportedPlatforms = ['douyin', 'kuaishou', 'xiaohongshu'];
            $platform = strtolower($platform);

            if (!in_array($platform, $supportedPlatforms)) {
                return $this->error('不支持的平台类型', 400, 'unsupported_platform');
            }

            // TODO: 调用发布服务生成授权URL
            // $result = $this->publishService->generateAuthUrl($userId, $platform);

            // 临时响应数据（待实现）
            $state = md5(uniqid((string)mt_rand(), true));
            $result = [
                'auth_url' => "https://{$platform}.com/oauth/authorize?state={$state}",  // TODO: 实际授权URL
                'state' => $state,
                'expires_in' => 600
            ];

            Log::info('生成平台授权URL', [
                'user_id' => $userId,
                'platform' => $platform,
                'state' => $state
            ]);

            return $this->success($result, '获取授权链接成功');

        } catch (\Exception $e) {
            Log::error('生成平台授权URL失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'platform' => $platform
            ]);

            return $this->error($e->getMessage(), 400, 'get_auth_url_failed');
        }
    }

    /**
     * 平台授权回调
     * GET /api/publish/oauth/callback/{platform}
     *
     * 处理平台OAuth授权回调
     * 获取access_token并保存账号信息
     *
     * 路径参数:
     * @param string $platform 平台名称：douyin/xiaohongshu/kuaishou/weibo/bilibili
     *
     * 请求参数:
     * - code: 授权码（query参数）
     * - state: 状态码（query参数）
     * - merchant_id: 商户ID（query参数）
     *
     * 响应数据:
     * {
     *     "account_id": 123,
     *     "platform": "douyin",
     *     "platform_name": "抖音",
     *     "nickname": "用户昵称",
     *     "avatar": "头像URL",
     *     "expires_at": 1234567890
     * }
     */
    public function authCallback($platform)
    {
        try {
            // 从query参数获取授权信息
            $code = $this->request->param('code', '');
            $state = $this->request->param('state', '');
            $merchantId = (int)$this->request->param('merchant_id', 0);

            // 验证平台参数
            $platform = strtolower($platform);
            $supportedPlatforms = ['douyin', 'xiaohongshu', 'kuaishou', 'weibo', 'bilibili'];

            if (!in_array($platform, $supportedPlatforms)) {
                return $this->error('不支持的平台类型', 400, 'unsupported_platform');
            }

            // 参数验证
            if (empty($code)) {
                return $this->validationError(['code' => '授权码不能为空']);
            }

            if (empty($state)) {
                return $this->validationError(['state' => '状态码不能为空']);
            }

            if (empty($merchantId)) {
                return $this->validationError(['merchant_id' => '商户ID不能为空']);
            }

            // 临时设置merchant_id到request,供OAuth服务使用
            $this->request->merchant_id = $merchantId;

            // 调用OAuth服务处理回调
            $result = $this->oauthService->handleCallback($platform, $code, $state);

            Log::info('平台授权回调处理成功', [
                'merchant_id' => $merchantId,
                'platform' => $platform,
                'account_id' => $result['account_id']
            ]);

            // 重定向到前端页面,带上授权结果
            $redirectUrl = env('FRONTEND_URL', 'http://localhost:8080') . '/platform/auth?status=success&platform=' . $platform;

            return redirect($redirectUrl);

        } catch (\Exception $e) {
            Log::error('平台授权回调处理失败', [
                'error' => $e->getMessage(),
                'merchant_id' => $merchantId ?? null,
                'platform' => $platform ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            // 重定向到前端页面,带上错误信息
            $redirectUrl = env('FRONTEND_URL', 'http://localhost:8080') . '/platform/auth?status=error&message=' . urlencode($e->getMessage());

            return redirect($redirectUrl);
        }
    }

    /**
     * 查询发布任务状态
     * GET /api/publish/task/{id}
     *
     * 根据任务ID查询发布任务的状态和结果
     *
     * 路径参数:
     * @param int $id 发布任务ID
     *
     * 响应数据:
     * {
     *     "task_id": 789,
     *     "content_task_id": 123,
     *     "status": "SUCCESS",                    // 任务状态：PENDING/PROCESSING/SUCCESS/FAILED/PARTIAL_SUCCESS
     *     "platforms": [
     *         {
     *             "platform": "DOUYIN",
     *             "account_id": 456,
     *             "status": "SUCCESS",
     *             "platform_post_id": "abc123",   // 平台内容ID
     *             "platform_url": "https://...",  // 平台内容链接
     *             "error": null,
     *             "published_at": "2024-01-01 18:00:00"
     *         }
     *     ],
     *     "total_count": 1,
     *     "success_count": 1,
     *     "failed_count": 0,
     *     "created_at": "2024-01-01 17:55:00",
     *     "updated_at": "2024-01-01 18:00:05"
     * }
     */
    public function taskStatus($id = null)
    {
        try {
            // 从路径参数或query参数获取task_id（优先路径参数）
            if (!$id) {
                $id = $this->request->param('task_id', '');
            }

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['task_id' => '任务ID不能为空']);
            }

            // 查询任务
            $task = \app\model\PublishTask::find($id);

            if (!$task) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            // 检查权限
            if ($task->user_id != $userId) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            // 统计结果
            $platforms = $task->platforms ?? [];
            $results = $task->results ?? [];
            $totalCount = count($platforms);
            $successCount = 0;
            $failedCount = 0;

            foreach ($results as $result) {
                if (isset($result['success']) && $result['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            }

            $result = [
                'task_id' => $task->id,
                'content_task_id' => $task->content_task_id,
                'status' => $task->status,
                'platforms' => $platforms,
                'results' => $results,
                'total_count' => $totalCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'scheduled_time' => $task->scheduled_time,
                'publish_time' => $task->publish_time,
                'created_at' => $task->create_time,
                'updated_at' => $task->update_time
            ];

            return $this->success($result, '获取任务状态成功');

        } catch (\Exception $e) {
            Log::error('查询发布任务状态失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'task_id' => $id
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            if (strpos($e->getMessage(), '无权访问') !== false) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            return $this->error($e->getMessage(), 400, 'get_task_status_failed');
        }
    }

    /**
     * 获取发布任务列表
     * GET /api/publish/tasks
     *
     * 获取用户的发布任务列表，支持分页、筛选和排序
     *
     * 查询参数:
     * - page: 页码，默认1
     * - limit: 每页数量，默认20
     * - status: 任务状态筛选，可选：PENDING/PROCESSING/SUCCESS/FAILED/PARTIAL_SUCCESS
     * - platform: 平台筛选，可选：DOUYIN/KUAISHOU/XIAOHONGSHU
     * - content_task_id: 内容任务ID筛选
     * - start_date: 开始日期，格式：Y-m-d
     * - end_date: 结束日期，格式：Y-m-d
     * - sort: 排序字段，默认created_at，可选：created_at/updated_at/status
     * - order: 排序方向，默认desc，可选：asc/desc
     *
     * 响应数据:
     * {
     *     "list": [...],           // 任务列表
     *     "total": 100,            // 总数
     *     "page": 1,               // 当前页码
     *     "per_page": 20,          // 每页数量
     *     "total_pages": 5         // 总页数
     * }
     */
    public function tasks()
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
                'status' => $this->request->param('status', ''),
                'platform' => $this->request->param('platform', ''),
                'content_task_id' => $this->request->param('content_task_id', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date' => $this->request->param('end_date', ''),
                'sort' => $this->request->param('sort', 'created_at'),
                'order' => $this->request->param('order', 'desc')
            ];

            // 参数验证
            // TODO: 创建验证规则
            // $this->validate($params, 'Publish.taskList');

            // 调用模型获取任务列表
            $query = \app\model\PublishTask::where('user_id', $userId);

            // 状态筛选
            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            // 内容任务ID筛选
            if (!empty($params['content_task_id'])) {
                $query->where('content_task_id', $params['content_task_id']);
            }

            // 日期范围筛选
            if (!empty($params['start_date'])) {
                $query->whereTime('create_time', '>=', $params['start_date']);
            }
            if (!empty($params['end_date'])) {
                $query->whereTime('create_time', '<=', $params['end_date']);
            }

            // 平台筛选（需要JSON查询）
            if (!empty($params['platform'])) {
                $query->where('platforms', 'like', '%"' . $params['platform'] . '"%');
            }

            // 获取总数
            $total = $query->count();

            // 排序和分页
            $list = $query->order($params['sort'], $params['order'])
                ->page($params['page'], $params['limit'])
                ->select()
                ->toArray();

            Log::info('获取发布任务列表', [
                'user_id' => $userId,
                'params' => $params,
                'total' => $total
            ]);

            return $this->paginate(
                $list,
                $total,
                $params['page'],
                $params['limit'],
                '获取任务列表成功'
            );

        } catch (ValidateException $e) {
            return $this->validationError(['tasks' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取发布任务列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'merchant_id' => $merchantId ?? null
            ]);

            return $this->error($e->getMessage(), 400, 'get_task_list_failed');
        }
    }

    /**
     * 重试发布任务
     * POST /api/publish/task/{id}/retry
     *
     * 重新执行失败的发布任务
     * 仅支持状态为 FAILED 或 PARTIAL_SUCCESS 的任务
     *
     * 路径参数:
     * @param int $id 发布任务ID
     *
     * 请求参数:
     * {
     *     "platforms": ["DOUYIN", "KUAISHOU"]  // 可选，指定需要重试的平台，不传则重试所有失败的平台
     * }
     *
     * 响应数据:
     * {
     *     "task_id": 789,
     *     "status": "PROCESSING",
     *     "retry_count": 1,
     *     "platforms_count": 2,
     *     "message": "任务已重新提交"
     * }
     */
    public function retryTask($id)
    {
        try {
            $data = $this->request->post();

            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['task_id' => '任务ID不能为空']);
            }

            // 查询任务
            $task = \app\model\PublishTask::find($id);

            if (!$task) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            // 检查权限
            if ($task->user_id != $userId) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            // 检查状态
            if (!in_array($task->status, [\app\model\PublishTask::STATUS_FAILED, \app\model\PublishTask::STATUS_PARTIAL])) {
                return $this->error('任务状态不允许重试', 400, 'task_cannot_retry');
            }

            // 重置任务状态
            $task->reset();

            // 执行发布任务
            $result = $this->publishService->executePublishTask($task->id);

            if (!$result['success']) {
                return $this->error($result['error'], 400, 'retry_task_failed');
            }

            Log::info('重试发布任务', [
                'user_id' => $userId,
                'task_id' => $id,
                'status' => $result['status']
            ]);

            return $this->success([
                'task_id' => $result['task_id'],
                'status' => $result['status'],
                'message' => '任务已重新提交'
            ], '任务已重新提交');

        } catch (ValidateException $e) {
            return $this->validationError(['retry' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('重试发布任务失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'task_id' => $id,
                'data' => $data ?? []
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            if (strpos($e->getMessage(), '任务状态不允许重试') !== false) {
                return $this->error('任务状态不允许重试', 400, 'task_cannot_retry');
            }

            if (strpos($e->getMessage(), '无权访问') !== false) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            return $this->error($e->getMessage(), 400, 'retry_task_failed');
        }
    }

    /**
     * 更新定时发布任务
     * PUT /api/publish/task/{id}/schedule
     *
     * 修改定时发布任务的发布时间
     * 仅支持状态为 PENDING 的定时任务
     *
     * 路径参数:
     * @param int $id 发布任务ID
     *
     * 请求参数:
     * {
     *     "scheduled_time": "2024-12-31 18:00:00"  // 新的定时发布时间
     * }
     *
     * 响应数据:
     * {
     *     "task_id": 789,
     *     "scheduled_time": "2024-12-31 18:00:00",
     *     "message": "定时任务已更新"
     * }
     */
    public function updateScheduledTask($id)
    {
        try {
            $data = $this->request->put();

            // 获取用户ID
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['task_id' => '任务ID不能为空']);
            }

            if (empty($data['scheduled_time'])) {
                return $this->validationError(['scheduled_time' => '定时发布时间不能为空']);
            }

            // 查询任务
            $task = \app\model\PublishTask::find($id);

            if (!$task) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            // 检查权限
            if ($task->user_id != $userId) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            // 检查任务状态 - 只有PENDING状态才能修改
            if ($task->status !== \app\model\PublishTask::STATUS_PENDING) {
                return $this->error('只有待执行的任务可以修改', 400, 'task_cannot_update');
            }

            // 验证新的定时时间
            $newScheduledTime = strtotime($data['scheduled_time']);
            if ($newScheduledTime === false) {
                return $this->validationError(['scheduled_time' => '定时发布时间格式不正确']);
            }

            if ($newScheduledTime <= time()) {
                return $this->validationError(['scheduled_time' => '定时发布时间必须晚于当前时间']);
            }

            // 更新任务
            $task->scheduled_time = $data['scheduled_time'];
            $task->save();

            Log::info('更新定时发布任务', [
                'user_id' => $userId,
                'task_id' => $id,
                'old_time' => $task->getData('scheduled_time'),
                'new_time' => $data['scheduled_time']
            ]);

            return $this->success([
                'task_id' => $task->id,
                'scheduled_time' => $task->scheduled_time,
                'message' => '定时任务已更新'
            ], '定时任务已更新');

        } catch (\Exception $e) {
            Log::error('更新定时发布任务失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'task_id' => $id
            ]);

            return $this->error($e->getMessage(), 400, 'update_scheduled_task_failed');
        }
    }

    /**
     * 取消发布任务
     * POST /api/publish/task/{id}/cancel
     *
     * 取消待执行或执行中的发布任务
     * 仅支持状态为 PENDING 或 PROCESSING 的任务
     *
     * 路径参数:
     * @param int $id 发布任务ID
     *
     * 响应数据:
     * {
     *     "task_id": 789,
     *     "status": "CANCELLED",
     *     "message": "任务已取消"
     * }
     */
    public function cancelTask($id)
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['task_id' => '任务ID不能为空']);
            }

            // 调用发布服务取消任务
            $success = $this->publishService->cancelPublishTask((int)$id);

            if (!$success) {
                return $this->error('取消任务失败', 400, 'cancel_task_failed');
            }

            Log::info('取消发布任务', [
                'user_id' => $userId,
                'task_id' => $id
            ]);

            return $this->success([
                'task_id' => (int)$id,
                'message' => '任务已取消'
            ], '任务已取消');

        } catch (\Exception $e) {
            Log::error('取消发布任务失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'task_id' => $id
            ]);

            if (strpos($e->getMessage(), '任务未找到') !== false) {
                return $this->platformError('TASK_NOT_FOUND', [
                    'task_id' => $id
                ], 404);
            }

            if (strpos($e->getMessage(), '任务无法取消') !== false) {
                return $this->error('任务无法取消', 400, 'task_cannot_cancel');
            }

            if (strpos($e->getMessage(), '无权访问') !== false) {
                return $this->error('无权访问该任务', 403, 'access_denied');
            }

            return $this->error($e->getMessage(), 400, 'cancel_task_failed');
        }
    }

    /**
     * 获取平台账号列表
     * GET /api/publish/accounts
     *
     * 获取用户已授权的平台账号列表
     *
     * 查询参数:
     * - platform: 平台筛选，可选：DOUYIN/KUAISHOU/XIAOHONGSHU
     * - status: 状态筛选，可选：ACTIVE/EXPIRED/DISABLED
     *
     * 响应数据:
     * {
     *     "accounts": [
     *         {
     *             "id": 456,
     *             "platform": "DOUYIN",
     *             "platform_uid": "douyin_123",
     *             "platform_name": "用户昵称",
     *             "avatar": "头像URL",
     *             "follower_count": 1000,
     *             "status": "ACTIVE",
     *             "is_default": true,
     *             "authorized_at": "2024-01-01 12:00:00",
     *             "expires_at": "2024-07-01 12:00:00"
     *         }
     *     ]
     * }
     */
    public function accounts()
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取查询参数
            $platform = $this->request->param('platform', '');
            $status = $this->request->param('status', '');

            // 查询账号列表
            $query = PlatformAccount::where('user_id', $userId);

            // 平台筛选
            if (!empty($platform)) {
                $query->where('platform', strtoupper($platform));
            }

            // 状态筛选
            if (!empty($status)) {
                if ($status === 'ACTIVE') {
                    $query->where('status', PlatformAccount::STATUS_VALID)
                        ->where('expires_time', '>', date('Y-m-d H:i:s'));
                } elseif ($status === 'EXPIRED') {
                    $query->where('expires_time', '<=', date('Y-m-d H:i:s'));
                } elseif ($status === 'DISABLED') {
                    $query->where('status', PlatformAccount::STATUS_INVALID);
                }
            }

            $accounts = $query->order('create_time', 'desc')
                ->select()
                ->toArray();

            return $this->success([
                'accounts' => $accounts
            ], '获取账号列表成功');

        } catch (\Exception $e) {
            Log::error('获取平台账号列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null
            ]);

            return $this->error($e->getMessage(), 400, 'get_accounts_failed');
        }
    }

    /**
     * 删除平台账号
     * DELETE /api/publish/account/{id}
     *
     * 删除（解除授权）指定的平台账号
     *
     * 路径参数:
     * @param int $id 账号ID
     *
     * 响应数据:
     * {
     *     "message": "账号已删除"
     * }
     */
    public function deleteAccount($id)
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['account_id' => '账号ID不能为空']);
            }

            // 查询账号
            $account = PlatformAccount::find($id);

            if (!$account) {
                return $this->platformError('ACCOUNT_NOT_FOUND', [
                    'account_id' => $id
                ], 404);
            }

            // 检查权限
            if ($account->user_id != $userId) {
                return $this->error('无权访问该账号', 403, 'access_denied');
            }

            // 删除账号
            $account->delete();

            Log::info('删除平台账号', [
                'user_id' => $userId,
                'account_id' => $id,
                'platform' => $account->platform
            ]);

            return $this->success(null, '账号已删除');

        } catch (\Exception $e) {
            Log::error('删除平台账号失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'account_id' => $id
            ]);

            if (strpos($e->getMessage(), '账号未找到') !== false) {
                return $this->platformError('ACCOUNT_NOT_FOUND', [
                    'account_id' => $id
                ], 404);
            }

            if (strpos($e->getMessage(), '无权访问') !== false) {
                return $this->error('无权访问该账号', 403, 'access_denied');
            }

            return $this->error($e->getMessage(), 400, 'delete_account_failed');
        }
    }

    /**
     * 刷新平台账号token
     * POST /api/publish/account/{id}/refresh
     *
     * 刷新指定平台账号的访问令牌
     *
     * 路径参数:
     * @param int $id 账号ID
     *
     * 响应数据:
     * {
     *     "account_id": 123,
     *     "platform": "douyin",
     *     "expires_at": 1234567890,
     *     "message": "Token刷新成功"
     * }
     */
    public function refreshAccountToken($id)
    {
        try {
            // 获取用户ID（从JWT中间件解析）
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 参数验证
            if (empty($id)) {
                return $this->validationError(['account_id' => '账号ID不能为空']);
            }

            // 调用OAuth服务刷新token
            $result = $this->oauthService->refreshToken((int)$id);

            Log::info('刷新平台账号token成功', [
                'user_id' => $userId,
                'account_id' => $id,
                'platform' => $result['platform']
            ]);

            return $this->success($result, '令牌已刷新');

        } catch (\Exception $e) {
            Log::error('刷新平台账号token失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'account_id' => $id
            ]);

            if (strpos($e->getMessage(), '不存在') !== false) {
                return $this->platformError('ACCOUNT_NOT_FOUND', [
                    'account_id' => $id
                ], 404);
            }

            if (strpos($e->getMessage(), '不支持') !== false) {
                return $this->error('该平台不支持token刷新，请重新授权', 400, 'refresh_not_supported');
            }

            return $this->error($e->getMessage(), 400, 'refresh_token_failed');
        }
    }

    /**
     * 获取平台授权URL
     * GET /api/publish/oauth/url/{platform}
     *
     * 生成平台OAuth授权URL
     *
     * 路径参数:
     * @param string $platform 平台名称：douyin/xiaohongshu/kuaishou/weibo/bilibili
     *
     * 响应数据:
     * {
     *     "platform": "douyin",
     *     "platform_name": "抖音",
     *     "auth_url": "https://open.douyin.com/...",
     *     "tips": "授权后可发布视频到抖音..."
     * }
     */
    public function getPlatformAuthUrl($platform)
    {
        try {
            // 获取用户ID和商户ID
            $userId = $this->request->user_id ?? null;
            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            $merchantId = $this->request->merchant_id ?? 0;
            if (!$merchantId) {
                return $this->error('缺少商户ID', 400, 'missing_merchant_id');
            }

            // 验证平台参数
            $platform = strtolower($platform);
            $supportedPlatforms = ['douyin', 'xiaohongshu', 'kuaishou', 'weibo', 'bilibili'];

            if (!in_array($platform, $supportedPlatforms)) {
                return $this->error('不支持的平台类型', 400, 'unsupported_platform');
            }

            // 调用OAuth服务获取授权URL
            $result = $this->oauthService->getAuthUrl($merchantId, $platform);

            Log::info('生成平台授权URL', [
                'user_id' => $userId,
                'merchant_id' => $merchantId,
                'platform' => $platform
            ]);

            return $this->success($result, '获取授权URL成功');

        } catch (\Exception $e) {
            Log::error('获取平台授权URL失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'platform' => $platform ?? null
            ]);

            return $this->error($e->getMessage(), 400, 'get_auth_url_failed');
        }
    }
}