<?php
declare(strict_types=1);

namespace app\controller;

use app\model\NfcDevice;
use app\model\ContentTask;
use app\model\PublishTask;
use app\model\ContentLibrary;
use app\model\Merchant;
use app\model\User;
use app\model\Material as MaterialModel;
use app\model\DeviceTrigger;
use think\fac\Db;
use think\facade\Log;
use think\Response;
use think\exception\HttpException;

/**
 * 兼容控制器 - 处理前端 /api/admin/* 路径的API调用
 * 将这些路径映射到现有的数据和控制器逻辑
 */
class AdminCompat extends BaseController
{
    /**
     * 控制器初始化：深度防御，确保 Auth 中间件已注入用户身份
     * 路由级 Auth 中间件已在 route/app.php 中应用，此处做防御性校验，
     * 防止未来路由配置变更后导致鉴权被绕过
     */
    protected function initialize(): void
    {
        $userId = $this->request->user_id ?? null;
        $userRole = $this->request->user_role ?? null;

        // Auth 中间件正常执行后必会注入 user_id；为空即视为鉴权未生效
        if ($userId === null && $userRole === null) {
            Log::warning('AdminCompat 鉴权防御拦截', [
                'path' => $this->request->pathinfo(),
                'ip'   => $this->request->ip(),
            ]);
            throw new HttpException(401, '未授权访问');
        }
    }

    // ==================== 视频 (video.js) ====================

    /**
     * GET /api/admin/video/tasks
     */
    public function videoTasks(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $status = $this->request->param('status', '');

            $query = ContentTask::where('type', 'VIDEO');
            if ($status !== '') {
                $query->where('status', $status);
            }

            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取视频任务列表失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/video/tasks
     */
    public function createVideoTask(): Response
    {
        try {
            $data = $this->request->post();
            $data['type'] = 'VIDEO';
            $task = ContentTask::create($data);
            return $this->success($task->toArray(), '创建视频任务成功');
        } catch (\Exception $e) {
            return $this->error('创建视频任务失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/video/tasks/:id
     */
    public function videoTaskDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $task = ContentTask::find($id);
            if (!$task) {
                return $this->error('任务不存在', 404);
            }
            return $this->success($task->toArray());
        } catch (\Exception $e) {
            return $this->error('获取视频任务详情失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/video/tasks/:id/retry
     */
    public function retryVideoTask(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $task = ContentTask::find($id);
            if (!$task) {
                return $this->error('任务不存在', 404);
            }
            $task->status = ContentTask::STATUS_PENDING;
            $task->save();
            return $this->success(null, '任务已重试');
        } catch (\Exception $e) {
            return $this->error('重试任务失败: ' . $e->getMessage());
        }
    }

    // ==================== 门店 (stores.js) ====================

    /**
     * GET /api/admin/stores
     */
    public function stores(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $keyword = $this->request->param('keyword', '');

            $query = Merchant::where('status', '>=', 0);
            if ($keyword !== '') {
                $query->whereLike('name', "%{$keyword}%");
            }

            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->field('id, name, category, address, phone, status, create_time')
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取门店列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/stores/simple
     */
    public function storesSimple(): Response
    {
        try {
            $list = Merchant::where('status', 1)
                ->field('id, name')
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            return $this->success(['list' => $list, 'total' => count($list)]);
        } catch (\Exception $e) {
            return $this->error('获取门店列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/stores/:id
     */
    public function storeDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $store = Merchant::find($id);
            if (!$store) {
                return $this->error('门店不存在', 404);
            }
            return $this->success($store->toArray());
        } catch (\Exception $e) {
            return $this->error('获取门店详情失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/stores
     */
    public function createStore(): Response
    {
        try {
            $data = $this->request->post();
            $store = Merchant::create($data);
            return $this->success($store->toArray(), '创建门店成功');
        } catch (\Exception $e) {
            return $this->error('创建门店失败: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/admin/stores/:id
     */
    public function updateStore(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->put();
            $store = Merchant::find($id);
            if (!$store) {
                return $this->error('门店不存在', 404);
            }
            $store->save($data);
            return $this->success($store->toArray(), '更新门店成功');
        } catch (\Exception $e) {
            return $this->error('更新门店失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/stores/:id
     */
    public function deleteStore(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $store = Merchant::find($id);
            if (!$store) {
                return $this->error('门店不存在', 404);
            }
            $store->delete();
            return $this->success(null, '删除门店成功');
        } catch (\Exception $e) {
            return $this->error('删除门店失败: ' . $e->getMessage());
        }
    }

    // ==================== 任务 (tasks.js) ====================

    /**
     * GET /api/admin/tasks
     */
    public function tasks(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $status = $this->request->param('status', '');
            $type = $this->request->param('type', '');

            $query = ContentTask::order('create_time', 'desc');
            if ($status !== '') {
                $query->where('status', $status);
            }
            if ($type !== '') {
                $query->where('type', $type);
            }

            $total = (clone $query)->count();
            $list = $query->page($page, $pageSize)
                ->append(['status_text'])
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取任务列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/tasks/:id
     */
    public function taskDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $task = ContentTask::find($id);
            if (!$task) {
                return $this->error('任务不存在', 404);
            }
            return $this->success($task->toArray());
        } catch (\Exception $e) {
            return $this->error('获取任务详情失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/tasks/:id/retry
     */
    public function retryTask(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $task = ContentTask::find($id);
            if (!$task) {
                return $this->error('任务不存在', 404);
            }
            $task->status = ContentTask::STATUS_PENDING;
            $task->save();
            return $this->success(null, '任务已重试');
        } catch (\Exception $e) {
            return $this->error('重试任务失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/tasks/:id/cancel
     */
    public function cancelTask(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $task = ContentTask::find($id);
            if (!$task) {
                return $this->error('任务不存在', 404);
            }
            $task->status = ContentTask::STATUS_CANCELLED ?? 'cancelled';
            $task->save();
            return $this->success(null, '任务已取消');
        } catch (\Exception $e) {
            return $this->error('取消任务失败: ' . $e->getMessage());
        }
    }

    // ==================== 成品库 (library.js) ====================

    /**
     * GET /api/admin/library/videos
     */
    public function libraryVideos(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = ContentLibrary::where('library_type', 'video')->where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取视频库失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/library/images
     */
    public function libraryImages(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = ContentLibrary::where('library_type', 'graphic')->where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取图文库失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/library/topics
     */
    public function libraryTopics(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = ContentLibrary::where('library_type', 'topic')->where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取话题库失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/library/stores
     */
    public function libraryStores(): Response
    {
        try {
            $list = Merchant::where('status', 1)
                ->field('id, name')
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            return $this->success(['list' => $list, 'total' => count($list)]);
        } catch (\Exception $e) {
            return $this->error('获取门店列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/library/platforms
     */
    public function libraryPlatforms(): Response
    {
        $platforms = [
            ['value' => 'douyin', 'label' => '抖音'],
            ['value' => 'kuaishou', 'label' => '快手'],
            ['value' => 'xiaohongshu', 'label' => '小红书'],
            ['value' => 'shipinhao', 'label' => '视频号'],
            ['value' => 'bilibili', 'label' => 'B站'],
        ];
        return $this->success($platforms);
    }

    /**
     * DELETE /api/admin/library/videos/:id
     */
    public function deleteLibraryVideo(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $item = ContentLibrary::where('id', $id)->where('library_type', 'video')->find();
            if (!$item) {
                return $this->error('记录不存在', 404);
            }
            $item->delete();
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/library/images/:id
     */
    public function deleteLibraryImage(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $item = ContentLibrary::where('id', $id)->where('library_type', 'graphic')->find();
            if (!$item) {
                return $this->error('记录不存在', 404);
            }
            $item->delete();
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/library/topics/:id
     */
    public function deleteLibraryTopic(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $item = ContentLibrary::where('id', $id)->where('library_type', 'topic')->find();
            if (!$item) {
                return $this->error('记录不存在', 404);
            }
            $item->delete();
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败: ' . $e->getMessage());
        }
    }

    // ==================== 监控 (monitor.js) ====================

    /**
     * GET /api/admin/monitor/topics
     */
    public function monitorTopics(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = \app\model\TopicMonitor::order('create_time', 'desc');
            $total = (clone $query)->count();
            $list = $query->page($page, $pageSize)->select()->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取话题列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/monitor/topics/:id
     */
    public function monitorTopicDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $topic = \app\model\TopicMonitor::find($id);
            if (!$topic) {
                return $this->error('话题不存在', 404);
            }
            return $this->success($topic->toArray());
        } catch (\Exception $e) {
            return $this->error('获取话题详情失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/monitor/topics/:id/trend
     */
    public function monitorTopicTrend(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            return $this->success(['dates' => [], 'values' => []]);
        } catch (\Exception $e) {
            return $this->error('获取话题趋势失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/monitor/topics/:id/export
     */
    public function monitorTopicExport(): Response
    {
        try {
            $merchantId = (int)$this->request->param('merchant_id', 0);
            if ($merchantId <= 0) {
                return $this->error('商家ID不能为空');
            }

            $filters = [
                'platform' => $this->request->param('platform', ''),
                'status' => $this->request->param('status', ''),
                'keyword' => $this->request->param('keyword', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date' => $this->request->param('end_date', ''),
            ];

            $service = new \app\service\TopicMonitorService();
            return $service->exportTopics($merchantId, $filters);
        } catch (\Exception $e) {
            return $this->error('导出话题数据失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/monitor/platforms
     */
    public function monitorPlatforms(): Response
    {
        $platforms = [
            ['value' => 'douyin', 'label' => '抖音'],
            ['value' => 'kuaishou', 'label' => '快手'],
            ['value' => 'xiaohongshu', 'label' => '小红书'],
            ['value' => 'weibo', 'label' => '微博'],
            ['value' => 'bilibili', 'label' => 'B站'],
        ];
        return $this->success($platforms);
    }

    // ==================== 物料设计 (design.js) ====================

    /**
     * GET /api/admin/design/materials
     */
    public function designMaterials(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = MaterialModel::where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取物料模板列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/design/materials/:id
     */
    public function designMaterialDetail(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $item = MaterialModel::find($id);
            if (!$item) {
                return $this->error('物料不存在', 404);
            }
            return $this->success($item->toArray());
        } catch (\Exception $e) {
            return $this->error('获取物料详情失败: ' . $e->getMessage());
        }
    }

    // ==================== 素材管理 (materials.js) ====================

    /**
     * GET /api/admin/materials
     */
    public function materials(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);
            $type = $this->request->param('type', '');
            $keyword = $this->request->param('keyword', '');

            $query = MaterialModel::where('status', '>=', 0);
            if ($type !== '') {
                $query->where('type', $type);
            }
            if ($keyword !== '') {
                $query->whereLike('name', "%{$keyword}%");
            }

            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取素材列表失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/materials/upload
     */
    public function materialsUpload(): Response
    {
        try {
            $upload = new \app\controller\Upload($this->app);
            return $upload->image();
        } catch (\Exception $e) {
            return $this->error('上传素材失败: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/admin/materials/:id
     */
    public function materialsUpdate(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->put();
            $item = MaterialModel::find($id);
            if (!$item) {
                return $this->error('素材不存在', 404);
            }
            $item->save($data);
            return $this->success($item->toArray(), '更新成功');
        } catch (\Exception $e) {
            return $this->error('更新素材失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/materials/:id
     */
    public function materialsDelete(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $item = MaterialModel::find($id);
            if (!$item) {
                return $this->error('素材不存在', 404);
            }
            $item->delete();
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除素材失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/materials/batch
     */
    public function materialsBatchDelete(): Response
    {
        try {
            $ids = $this->request->post('ids/a', []);
            if (empty($ids)) {
                return $this->error('请选择要删除的素材');
            }
            MaterialModel::destroy($ids);
            return $this->success(null, '批量删除成功');
        } catch (\Exception $e) {
            return $this->error('批量删除失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/materials/storage
     */
    public function materialsStorage(): Response
    {
        return $this->success([
            'total' => 0,
            'used' => 0,
            'available' => 0,
            'unit' => 'MB',
        ]);
    }

    // ==================== 活动 (activity.js) ====================

    /**
     * GET /api/admin/activity/scenes
     */
    public function activityScenes(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = \app\model\SceneConfig::where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取场景列表失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/activity/scenes
     */
    public function createActivityScene(): Response
    {
        try {
            $data = $this->request->post();
            $scene = \app\model\SceneConfig::create($data);
            return $this->success($scene->toArray(), '创建场景成功');
        } catch (\Exception $e) {
            return $this->error('创建场景失败: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/admin/activity/scenes/:id
     */
    public function updateActivityScene(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $data = $this->request->put();
            $scene = \app\model\SceneConfig::find($id);
            if (!$scene) {
                return $this->error('场景不存在', 404);
            }
            $scene->save($data);
            return $this->success($scene->toArray(), '更新场景成功');
        } catch (\Exception $e) {
            return $this->error('更新场景失败: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/admin/activity/scenes/:id
     */
    public function deleteActivityScene(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $scene = \app\model\SceneConfig::find($id);
            if (!$scene) {
                return $this->error('场景不存在', 404);
            }
            $scene->delete();
            return $this->success(null, '删除场景成功');
        } catch (\Exception $e) {
            return $this->error('删除场景失败: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/admin/activity/scenes/:id/toggle
     */
    public function toggleActivityScene(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $enabled = $this->request->put('enabled');
            $scene = \app\model\SceneConfig::find($id);
            if (!$scene) {
                return $this->error('场景不存在', 404);
            }
            $scene->status = $enabled ? 1 : 0;
            $scene->save();
            return $this->success(null, '切换状态成功');
        } catch (\Exception $e) {
            return $this->error('切换状态失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/activity/redpackets
     */
    public function activityRedpackets(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = \app\model\RedpacketActivity::order('create_time', 'desc');
            $total = (clone $query)->count();
            $list = $query->page($page, $pageSize)->select()->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取红包列表失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/activity/redpackets/balance
     */
    public function redpacketBalance(): Response
    {
        try {
            $merchantId = (int)$this->request->param('merchant_id', 0);
            if ($merchantId <= 0) {
                return $this->error('商家ID不能为空');
            }

            $service = new \app\service\RedpacketActivityService();
            $overview = $service->getBalanceOverview($merchantId);

            return $this->success([
                'total_balance'     => $overview['budget_total'],
                'frozen_balance'    => $overview['frozen_amount'] ?? 0,
                'available_balance' => $overview['remaining_budget'],
                'actual_consumed'   => $overview['actual_consumed'],
                'activity_count'    => $overview['activity_count'],
            ]);
        } catch (\Exception $e) {
            return $this->error('获取红包余额失败: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admin/activity/redpackets/send
     */
    public function redpacketSend(): Response
    {
        try {
            $data = $this->request->post();
            $openid = $data['openid'] ?? '';
            $activityId = (int)($data['activity_id'] ?? 0);
            $merchantId = (int)($data['merchant_id'] ?? 0);

            if (empty($openid)) {
                return $this->error('用户openid不能为空');
            }
            if ($activityId <= 0) {
                return $this->error('活动ID不能为空');
            }

            Db::startTrans();
            try {
                $activity = Db::name('redpacket_activities')->where('id', $activityId)->lock(true)->find();
                if (!$activity) {
                    Db::rollback();
                    return $this->error('活动不存在');
                }
                if (($activity['status'] ?? '') !== \app\model\RedpacketActivity::STATUS_ACTIVE) {
                    Db::rollback();
                    return $this->error('活动未启用');
                }

                $rules = is_string($activity['rule_config'] ?? '') ? json_decode($activity['rule_config'], true) : ($activity['rule_config'] ?? []);

                // 概率控制
                $probability = $rules['probability'] ?? 1.0;
                if (mt_rand() / mt_getrandmax() > $probability) {
                    Db::rollback();
                    return $this->error('未中奖，请再接再厉');
                }

                $minAmount = $rules['min_amount'] ?? 0.30;
                $maxAmount = $rules['max_amount'] ?? 1.00;
                $minFen = (int)round($minAmount * 100);
                $maxFen = (int)round($maxAmount * 100);
                $amountFen = mt_rand($minFen, $maxFen);
                $amountYuan = $amountFen / 100;

                $remaining = ($activity['budget_amount'] ?? 0) - ($activity['consumed_amount'] ?? 0);
                if ($remaining < $amountYuan) {
                    Db::rollback();
                    return $this->error('活动预算余额不足');
                }

                $dailyLimit = $rules['daily_limit'] ?? 100;
                $todaySent = Db::name('redpacket_send_logs')
                    ->where('activity_id', $activityId)
                    ->where('status', 'SUCCESS')
                    ->whereTime('create_time', 'today')
                    ->count();
                if ($todaySent >= $dailyLimit) {
                    Db::rollback();
                    return $this->error('今日发放已达上限');
                }

                // 用户每日领取限制
                $perUserLimit = $rules['per_user_limit'] ?? 3;
                $userTodayCount = Db::name('redpacket_send_logs')
                    ->where('openid', $openid)
                    ->where('activity_id', $activityId)
                    ->where('status', 'SUCCESS')
                    ->whereTime('create_time', 'today')
                    ->count();
                if ($userTodayCount >= $perUserLimit) {
                    Db::rollback();
                    return $this->error('您今日领取次数已达上限');
                }

                // 预扣减预算，提交事务释放行锁
                Db::name('redpacket_activities')->where('id', $activityId)->inc('consumed_amount', $amountYuan)->update();
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return $this->error('红包发送失败: ' . $e->getMessage());
            }

            // 事务外调用微信API，避免行锁长时间持有
            $redpacketService = new \app\service\WechatRedpacketService();
            $result = $redpacketService->sendRedpacket(
                $openid,
                $amountFen,
                $merchantId ?: ($activity['merchant_id'] ?? 0),
                $activityId,
                ($activity['activity_name'] ?? '') ?: '扫码领红包'
            );

            if (!$result['success']) {
                // 微信发送失败，回滚预扣减的预算
                Db::name('redpacket_activities')->where('id', $activityId)->dec('consumed_amount', $amountYuan)->update();
                return $this->error($result['message']);
            }

            return $this->success($result['data'], '红包发送成功');
        } catch (\Exception $e) {
            return $this->error('红包发送失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/activity/redpackets/rules
     */
    public function redpacketRules(): Response
    {
        $rules = \think\facade\Config::get('redpacket.rules', []);
        return $this->success($rules);
    }

    /**
     * POST /api/admin/activity/redpackets/rules
     */
    public function redpacketSetRules(): Response
    {
        try {
            $activityId = (int)$this->request->post('activity_id', 0);
            if ($activityId <= 0) {
                return $this->error('活动ID不能为空');
            }

            $activity = \app\model\RedpacketActivity::find($activityId);
            if (!$activity) {
                return $this->error('活动不存在');
            }

            $rules = [
                'min_amount'     => (float)$this->request->post('min_amount', 0.30),
                'max_amount'     => (float)$this->request->post('max_amount', 1.00),
                'probability'    => (float)$this->request->post('probability', 0.3),
                'daily_limit'    => (int)$this->request->post('daily_limit', 100),
                'per_user_limit' => (int)$this->request->post('per_user_limit', 3),
            ];

            $limits = \think\facade\Config::get('redpacket.limits', []);
            if ($rules['min_amount'] < ($limits['min_amount'] ?? 0.01)) {
                return $this->error('最低金额不能小于系统限制');
            }
            if ($rules['max_amount'] > ($limits['max_amount'] ?? 200)) {
                return $this->error('最高金额不能超过系统限制');
            }
            if ($rules['min_amount'] >= $rules['max_amount']) {
                return $this->error('最低金额必须小于最高金额');
            }
            if ($rules['probability'] < 0 || $rules['probability'] > 1) {
                return $this->error('概率必须在0-1之间');
            }

            $activity->rule_config = $rules;
            $activity->save();

            return $this->success($rules, '规则设置成功');
        } catch (\Exception $e) {
            return $this->error('规则设置失败: ' . $e->getMessage());
        }
    }

    // ==================== 模板 (video.js getTemplateList) ====================

    /**
     * GET /api/admin/templates
     */
    public function templates(): Response
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $pageSize = (int)$this->request->param('page_size', 20);

            $query = \app\model\SceneConfig::where('status', '>=', 0);
            $total = (clone $query)->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            return $this->error('获取模板列表失败: ' . $e->getMessage());
        }
    }
}
