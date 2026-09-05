<?php
declare(strict_types=1);

namespace app\controller;

use app\service\AiStaffService;
use think\facade\Log;
use think\Response;

class AiStaff extends BaseController
{
    protected array $middleware = [
        'app\middleware\JwtAuth'
    ];

    protected AiStaffService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new AiStaffService();
    }

    /**
     * GET ai-staff/groups - 分组列表
     */
    public function groups(): Response
    {
        try {
            $groups = $this->service->getStaffGroups();
            return $this->success($groups, '获取分组列表成功');
        } catch (\Exception $e) {
            Log::error('获取员工分组失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET ai-staff/list - 员工列表
     */
    public function list(): Response
    {
        try {
            $filters = [
                'group_name' => $this->request->param('group_name', ''),
                'is_hot'     => $this->request->param('is_hot', ''),
                'keyword'    => $this->request->param('keyword', ''),
                'page'       => (int)$this->request->param('page', 1),
                'limit'      => (int)$this->request->param('limit', 20),
            ];
            $result = $this->service->getStaffList($filters);
            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取员工列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取员工列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET ai-staff/detail - 员工详情
     */
    public function detail(): Response
    {
        try {
            $id = (int)$this->request->get('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }
            $result = $this->service->getStaffDetail($id);
            return $this->success($result, '获取员工详情成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST ai-staff/create - 创建
     */
    public function create(): Response
    {
        try {
            $data = $this->request->post([
                'group_name', 'role_name', 'nickname', 'avatar_url',
                'description', 'task_types', 'prompt_template',
                'is_hot', 'free_count', 'sort_order', 'status',
            ]);

            if (empty($data['group_name']) || empty($data['role_name']) || empty($data['nickname'])) {
                return $this->error('分组、角色名称、昵称不能为空');
            }

            // task_types 支持JSON字符串
            if (is_string($data['task_types'] ?? null)) {
                $data['task_types'] = json_decode($data['task_types'], true) ?? [];
            }

            $result = $this->service->createStaffRole($data);
            return $this->success($result, '创建员工成功');
        } catch (\Exception $e) {
            Log::error('创建员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * PUT ai-staff/update - 更新
     */
    public function update(): Response
    {
        try {
            $id = (int)$this->request->put('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }

            $data = $this->request->put([
                'group_name', 'role_name', 'nickname', 'avatar_url',
                'description', 'task_types', 'prompt_template',
                'is_hot', 'free_count', 'sort_order', 'status',
            ]);

            if (is_string($data['task_types'] ?? null)) {
                $data['task_types'] = json_decode($data['task_types'], true) ?? [];
            }

            // 移除空值
            $data = array_filter($data, fn($v) => $v !== null && $v !== '');

            $result = $this->service->updateStaffRole($id, $data);
            return $this->success($result, '更新员工成功');
        } catch (\Exception $e) {
            Log::error('更新员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * DELETE ai-staff/delete - 删除
     */
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }
            $this->service->deleteStaffRole($id);
            return $this->success([], '删除员工成功');
        } catch (\Exception $e) {
            Log::error('删除员工失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST ai-staff/assign - 安排工作
     */
    public function assign(): Response
    {
        try {
            $jsonInput = file_get_contents('php://input');
            $jsonParams = !empty($jsonInput) ? json_decode($jsonInput, true) : [];

            $data = array_merge(
                $this->request->post(['staff_id', 'task_type', 'prompt', 'provider', 'image_url', 'duration', 'style']),
                $jsonParams
            );

            $staffId = (int)($data['staff_id'] ?? 0);
            if ($staffId <= 0) {
                return $this->error('员工ID不能为空');
            }
            if (empty($data['task_type'])) {
                return $this->error('任务类型不能为空');
            }

            $result = $this->service->assignWork($staffId, $data);
            return $this->success($result, '任务执行成功');
        } catch (\Exception $e) {
            Log::error('安排工作失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET ai-staff/usage - 使用统计
     */
    public function usage(): Response
    {
        try {
            $id = (int)$this->request->get('id', 0);
            if ($id <= 0) {
                return $this->error('员工ID不能为空');
            }
            $result = $this->service->getStaffUsage($id);
            return $this->success($result, '获取使用统计成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
