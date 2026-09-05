<?php
declare (strict_types = 1);

namespace app\controller;

use app\action\ActionRegistry;
use app\model\TaskAction;
use app\model\TaskBundle as TaskBundleModel;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 碰一碰任务包管理控制器（商家后台）
 * 任务包 CRUD + 动作管理 + 插件元信息
 */
class TaskBundle extends BaseController
{
    /**
     * 任务包分页列表
     * GET /api/task/bundle/list
     */
    public function index()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $page  = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');

            $query = TaskBundleModel::where('merchant_id', $merchantId);
            if ($status !== '') {
                $query->where('status', (int)$status);
            }
            $total = (clone $query)->count();
            $list  = $query->page($page, $limit)
                ->order('id', 'desc')
                ->select()
                ->toArray();

            return $this->paginate($list, $total, $page, $limit, '获取任务包列表成功');
        } catch (\Exception $e) {
            Log::error('获取任务包列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_bundle_list_failed');
        }
    }

    /**
     * 任务包详情（含动作列表）
     * GET /api/task/bundle/:id
     */
    public function read()
    {
        try {
            $bundle = $this->findOwnedBundle((int)$this->request->param('id', 0));
            return $this->success([
                'bundle'  => $bundle->toArray(),
                'actions' => $bundle->getActions(),
            ], '获取任务包详情成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'task_bundle_read_failed');
        }
    }

    /**
     * 创建任务包（含 actions 数组一次写入）
     * POST /api/task/bundle/create
     */
    public function save()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $data = $this->request->post();

            $this->validateBundle($data);

            $bundle = TaskBundleModel::create([
                'merchant_id'      => $merchantId,
                'device_id'        => !empty($data['device_id']) ? (int)$data['device_id'] : null,
                'bundle_name'      => $data['bundle_name'],
                'title'            => $data['title'],
                'subtitle'         => $data['subtitle'] ?? null,
                'cover'            => $data['cover'] ?? null,
                'completion_rule'  => $data['completion_rule'] ?? TaskBundleModel::RULE_ALL,
                'completion_count' => !empty($data['completion_count']) ? (int)$data['completion_count'] : null,
                'reward_type'      => $data['reward_type'] ?? TaskBundleModel::REWARD_NONE,
                'reward_config'    => $data['reward_config'] ?? [],
                'lander_config'    => $data['lander_config'] ?? [],
                'expire_hours'     => (int)($data['expire_hours'] ?? 24),
                'status'           => (int)($data['status'] ?? TaskBundleModel::STATUS_ENABLED),
            ]);

            if (!empty($data['actions']) && is_array($data['actions'])) {
                $this->saveActions((int)$bundle->id, $data['actions']);
            }

            return $this->success(['id' => $bundle->id], '任务包创建成功');
        } catch (ValidateException $e) {
            return $this->validationError(['bundle' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建任务包失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_bundle_create_failed');
        }
    }

    /**
     * 更新任务包基本信息
     * PUT /api/task/bundle/:id/update
     */
    public function update()
    {
        try {
            $bundle = $this->findOwnedBundle((int)$this->request->param('id', 0));
            $data = $this->request->post();

            $this->validateBundle($data, true);

            $bundle->bundle_name      = $data['bundle_name'] ?? $bundle->bundle_name;
            $bundle->title            = $data['title'] ?? $bundle->title;
            $bundle->subtitle         = array_key_exists('subtitle', $data) ? $data['subtitle'] : $bundle->subtitle;
            $bundle->cover            = array_key_exists('cover', $data) ? $data['cover'] : $bundle->cover;
            $bundle->device_id        = array_key_exists('device_id', $data) ? (!empty($data['device_id']) ? (int)$data['device_id'] : null) : $bundle->device_id;
            $bundle->completion_rule  = $data['completion_rule'] ?? $bundle->completion_rule;
            $bundle->completion_count = array_key_exists('completion_count', $data) ? (!empty($data['completion_count']) ? (int)$data['completion_count'] : null) : $bundle->completion_count;
            $bundle->reward_type      = $data['reward_type'] ?? $bundle->reward_type;
            $bundle->reward_config    = array_key_exists('reward_config', $data) ? ($data['reward_config'] ?? []) : $bundle->reward_config;
            $bundle->lander_config    = array_key_exists('lander_config', $data) ? ($data['lander_config'] ?? []) : $bundle->lander_config;
            $bundle->expire_hours     = isset($data['expire_hours']) ? (int)$data['expire_hours'] : $bundle->expire_hours;
            if (isset($data['status'])) {
                $bundle->status = (int)$data['status'];
            }
            $bundle->save();

            // 全量覆盖动作（传 actions 即替换）
            if (array_key_exists('actions', $data) && is_array($data['actions'])) {
                TaskAction::where('bundle_id', $bundle->id)->delete();
                $this->saveActions((int)$bundle->id, $data['actions']);
            }

            return $this->success(['id' => $bundle->id], '任务包更新成功');
        } catch (ValidateException $e) {
            return $this->validationError(['bundle' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新任务包失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_bundle_update_failed');
        }
    }

    /**
     * 删除/停用任务包
     * DELETE /api/task/bundle/:id/delete
     */
    public function delete()
    {
        try {
            $bundle = $this->findOwnedBundle((int)$this->request->param('id', 0));
            // 软删：置停用状态（历史实例仍可追溯）
            $bundle->status = TaskBundleModel::STATUS_DISABLED;
            $bundle->save();
            return $this->success(['id' => $bundle->id], '任务包已停用');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'task_bundle_delete_failed');
        }
    }

    /**
     * 新增动作
     * POST /api/task/bundle/:id/action
     */
    public function addAction()
    {
        try {
            $bundle = $this->findOwnedBundle((int)$this->request->param('id', 0));
            $actionData = $this->request->post('action', $this->request->post());
            $this->saveActions((int)$bundle->id, [$actionData]);
            return $this->success(null, '动作添加成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'task_action_add_failed');
        }
    }

    /**
     * 更新动作
     * PUT /api/task/bundle/action/:action_id/update
     */
    public function updateAction()
    {
        try {
            $action = $this->findOwnedAction((int)$this->request->param('action_id', 0));
            $data = $this->request->post();
            if (isset($data['action_name'])) $action->action_name = $data['action_name'];
            if (isset($data['action_icon'])) $action->action_icon = $data['action_icon'];
            if (array_key_exists('action_config', $data)) $action->action_config = $data['action_config'] ?? [];
            if (isset($data['sort_order'])) $action->sort_order = (int)$data['sort_order'];
            if (isset($data['required'])) $action->required = (int)$data['required'];
            $action->save();
            return $this->success(null, '动作更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'task_action_update_failed');
        }
    }

    /**
     * 删除动作
     * DELETE /api/task/bundle/action/:action_id/delete
     */
    public function deleteAction()
    {
        try {
            $action = $this->findOwnedAction((int)$this->request->param('action_id', 0));
            $action->delete();
            return $this->success(null, '动作删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'task_action_delete_failed');
        }
    }

    /**
     * 可用插件元信息（后台动态渲染配置表单用）
     * GET /api/task/bundle/plugins
     */
    public function plugins()
    {
        return $this->success(array_values(ActionRegistry::allMeta()), '获取插件列表成功');
    }

    /**
     * 凭证审核队列（管理端）
     * GET /api/task/proof/list
     */
    public function proofList()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $page  = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $auditStatus = strtoupper((string)$this->request->param('audit_status', ''));

            $query = \app\model\TaskProof::where('merchant_id', $merchantId);
            if ($auditStatus !== '') {
                $query->where('audit_status', $auditStatus);
            }
            $total = (clone $query)->count();
            $list  = $query->page($page, $limit)
                ->order('id', 'desc')
                ->select()
                ->toArray();

            // 附加动作名/任务包标题便于展示
            $actionIds = array_values(array_unique(array_column($list, 'action_id')));
            $instanceIds = array_values(array_unique(array_column($list, 'task_instance_id')));
            $actions = $actionIds ? TaskAction::whereIn('id', $actionIds)->column('action_name,bundle_id', 'id') : [];
            $instances = $instanceIds ? \app\model\TaskInstance::whereIn('id', $instanceIds)->column('bundle_id', 'id') : [];
            $bundleIds = array_values(array_unique(array_filter(array_map(
                fn($a) => $actions[$a]['bundle_id'] ?? null,
                $actionIds
            ))));
            $bundles = $bundleIds ? TaskBundleModel::whereIn('id', $bundleIds)->column('title,bundle_name', 'id') : [];

            foreach ($list as &$row) {
                $aid = $row['action_id'];
                $row['action_name'] = $actions[$aid]['action_name'] ?? '';
                $bid = $actions[$aid]['bundle_id'] ?? ($instances[$row['task_instance_id']]['bundle_id'] ?? 0);
                $row['bundle_title'] = $bundles[$bid]['title'] ?? $bundles[$bid]['bundle_name'] ?? '';
            }
            unset($row);

            return $this->paginate($list, $total, $page, $limit, '获取凭证列表成功');
        } catch (\Exception $e) {
            Log::error('获取凭证列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'proof_list_failed');
        }
    }

    /**
     * 凭证审核（管理端）
     * POST /api/task/proof/:id/audit  body: {result: approved|rejected, remark}
     */
    public function proofAudit()
    {
        try {
            $proofId = (int)$this->request->param('id', 0);
            $result  = strtolower((string)$this->request->post('result', ''));
            $remark  = (string)$this->request->post('remark', '');

            if (!in_array($result, ['approved', 'rejected'], true)) {
                return $this->validationError(['result' => '审核结果只能是 approved 或 rejected']);
            }
            if ($result === 'rejected' && $remark === '') {
                return $this->validationError(['remark' => '驳回必须填写备注']);
            }

            $proof = \app\model\TaskProof::find($proofId);
            if (!$proof) {
                return json(['code' => 404, 'message' => '凭证不存在', 'data' => null, 'error' => 'proof_not_found', 'timestamp' => time()])->code(200);
            }

            $auditorId = $this->request->admin_id ?? $this->request->merchant_id ?? 0;
            $verify = new \app\service\TaskVerifyService();
            $ok = $verify->auditProof(
                $proofId,
                strtoupper($result),
                (int)$auditorId,
                $remark
            );

            return $this->success(['completed' => $ok], $result === 'approved' ? '已通过' : '已驳回');
        } catch (\Exception $e) {
            Log::error('凭证审核失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'proof_audit_failed');
        }
    }

    /**
     * 用户任务实例列表（管理端）
     * GET /api/task/instance/list
     */
    public function instanceList()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $page  = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = strtoupper((string)$this->request->param('status', ''));
            $bundleId = (int)$this->request->param('bundle_id', 0);

            $query = \app\model\TaskInstance::where('merchant_id', $merchantId);
            if ($status !== '') {
                $query->where('status', $status);
            }
            if ($bundleId > 0) {
                $query->where('bundle_id', $bundleId);
            }
            $total = (clone $query)->count();
            $list  = $query->page($page, $limit)
                ->order('id', 'desc')
                ->select()
                ->toArray();

            // 附加任务包标题
            $bundleIds = array_values(array_unique(array_column($list, 'bundle_id')));
            $bundles = $bundleIds ? TaskBundleModel::whereIn('id', $bundleIds)->column('title', 'id') : [];
            foreach ($list as &$row) {
                $row['bundle_title'] = $bundles[$row['bundle_id']] ?? '';
            }
            unset($row);

            return $this->paginate($list, $total, $page, $limit, '获取用户任务列表成功');
        } catch (\Exception $e) {
            Log::error('获取用户任务列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'instance_list_failed');
        }
    }

    /**
     * 写入动作列表
     */
    protected function saveActions(int $bundleId, array $actions): void
    {
        foreach ($actions as $i => $aData) {
            if (empty($aData['plugin_key']) || !ActionRegistry::has((string)$aData['plugin_key'])) {
                throw new ValidateException('第' . ($i + 1) . '个动作的插件未注册');
            }
            TaskAction::create([
                'bundle_id'     => $bundleId,
                'plugin_key'    => (string)$aData['plugin_key'],
                'sort_order'    => (int)($aData['sort_order'] ?? $i),
                'action_name'   => (string)($aData['action_name'] ?? ActionRegistry::get((string)$aData['plugin_key'])->meta()['name']),
                'action_icon'   => $aData['action_icon'] ?? null,
                'action_config' => $aData['action_config'] ?? [],
                'required'      => (int)($aData['required'] ?? 1),
            ]);
        }
    }

    /**
     * 校验任务包基础数据
     */
    protected function validateBundle(array $data, bool $partial = false): void
    {
        $rules = [];
        if (!$partial || isset($data['bundle_name'])) $rules['bundle_name'] = 'require|max:100';
        if (!$partial || isset($data['title'])) $rules['title'] = 'require|max:200';
        if (isset($data['completion_rule'])) {
            $rules['completion_rule'] = 'require|in:ALL,ANY,COUNT';
        }
        if (isset($data['reward_type'])) {
            $rules['reward_type'] = 'require|in:redpacket,coupon,points,none';
        }
        if (isset($data['expire_hours'])) {
            $rules['expire_hours'] = 'integer|>=:1';
        }
        if (!empty($rules)) {
            $this->validate($data, $rules);
        }
        // COUNT 规则必须带数量
        if (($data['completion_rule'] ?? '') === TaskBundleModel::RULE_COUNT && empty($data['completion_count'])) {
            throw new ValidateException('COUNT 规则必须配置完成数量');
        }
    }

    /**
     * 查找当前商家名下的任务包
     */
    protected function findOwnedBundle(int $id): TaskBundleModel
    {
        $merchantId = $this->resolveMerchantId();
        $bundle = TaskBundleModel::where('id', $id)
            ->where('merchant_id', $merchantId)
            ->find();
        if (!$bundle) {
            throw new ValidateException('任务包不存在');
        }
        return $bundle;
    }

    /**
     * 查找当前商家名下的动作（经任务包归属校验）
     */
    protected function findOwnedAction(int $actionId): TaskAction
    {
        $action = TaskAction::find($actionId);
        if (!$action) {
            throw new ValidateException('动作不存在');
        }
        $this->findOwnedBundle((int)$action->bundle_id);
        return $action;
    }

    /**
     * 解析商家ID（与 Nfc 控制器一致：JWT 中间件注入，admin 回退默认商家）
     */
    protected function resolveMerchantId(): int
    {
        $merchantId = $this->request->merchant_id ?? null;
        if (!$merchantId) {
            $userRole = $this->request->user_role ?? '';
            $userId   = $this->request->user_id ?? null;
            if ($userRole === 'admin' || $userId === 0) {
                $merchantId = (int)env('admin.default_merchant_id', 1);
            }
        }
        if (!$merchantId) {
            throw new ValidateException('缺少商家认证信息');
        }
        return (int)$merchantId;
    }
}
