<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\ClipProjectService;
use think\exception\ValidateException;
use think\facade\Log;

class ClipProject extends BaseController
{
    protected ClipProjectService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new ClipProjectService();
    }

    // ---- 工程管理 ----

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'mode'    => $this->request->param('mode', ''),
                'status'  => $this->request->param('status', ''),
                'keyword' => $this->request->param('keyword', ''),
                'page'    => (int)$this->request->param('page', 1),
                'limit'   => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getProjectList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取工程列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取剪辑工程列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_clip_project_list_failed');
        }
    }

    public function create()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->getPostData();

            $this->validate($data, [
                'name' => 'max:200',
                'mode' => 'in:auto,batch,storyboard',
            ], [
                'name.max'  => '工程名称不能超过200个字符',
                'mode.in'   => '模式值无效',
            ]);

            $data['user_id'] = $this->request->user_id ?? null;
            $result = $this->service->createProject($merchantId, $data);

            Log::info('创建剪辑工程成功', ['merchant_id' => $merchantId, 'project_id' => $result['id']]);

            return $this->success($result, '创建工程成功');
        } catch (ValidateException $e) {
            return $this->validationError(['project' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建剪辑工程失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_clip_project_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $result = $this->service->getProjectDetail($id);
            if (!$result) {
                return $this->error('工程不存在', 404, 'project_not_found');
            }

            return $this->success($result, '获取工程详情成功');
        } catch (\Exception $e) {
            Log::error('获取剪辑工程详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_clip_project_detail_failed');
        }
    }

    public function update()
    {
        try {
            $data = $this->getPostData();
            $id = (int)($data['id'] ?? $this->request->param('id', 0));
            if ($id <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $this->validate($data, [
                'name' => 'max:200',
                'mode' => 'in:auto,batch,storyboard',
            ]);

            $result = $this->service->updateProject($id, $data);
            if (!$result) {
                return $this->error('工程不存在', 404, 'project_not_found');
            }

            Log::info('更新剪辑工程成功', ['project_id' => $id]);
            return $this->success($result, '更新工程成功');
        } catch (ValidateException $e) {
            return $this->validationError(['project' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新剪辑工程失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_clip_project_failed');
        }
    }

    public function delete()
    {
        try {
            $data = $this->getPostData();
            $id = (int)($data['id'] ?? $this->request->param('id', 0));
            if ($id <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $result = $this->service->deleteProject($id);
            if (!$result) {
                return $this->error('工程不存在', 404, 'project_not_found');
            }

            Log::info('删除剪辑工程成功', ['project_id' => $id]);
            return $this->success([], '删除工程成功');
        } catch (\Exception $e) {
            Log::error('删除剪辑工程失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_clip_project_failed');
        }
    }

    public function saveAsTemplate()
    {
        try {
            $data = $this->getPostData();
            $projectId = (int)($data['project_id'] ?? $this->request->param('project_id', 0));
            if ($projectId <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $result = $this->service->saveAsTemplate($projectId);
            if (!$result) {
                return $this->error('工程不存在', 404, 'project_not_found');
            }

            Log::info('保存为模板成功', ['source_project_id' => $projectId]);
            return $this->success($result, '保存为模板成功');
        } catch (\Exception $e) {
            Log::error('保存为模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'save_as_template_failed');
        }
    }

    public function myTemplates()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getMyTemplates($merchantId);
            return $this->success($result, '获取模板列表成功');
        } catch (\Exception $e) {
            Log::error('获取我的模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_my_templates_failed');
        }
    }

    // ---- 分镜管理 ----

    public function shotList()
    {
        try {
            $projectId = (int)$this->getJsonParam('project_id', 0);
            if ($projectId <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $result = $this->service->getShots($projectId);
            return $this->success($result, '获取分镜列表成功');
        } catch (\Exception $e) {
            Log::error('获取分镜列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_shots_failed');
        }
    }

    public function addShot()
    {
        try {
            $data = $this->getPostData();
            $projectId = (int)($data['project_id'] ?? 0);
            if ($projectId <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $this->validate($data, [
                'duration' => 'float|>=:0.1',
            ], [
                'duration.float' => '时长必须是数字',
                'duration.>='    => '时长不能小于0.1秒',
            ]);

            $result = $this->service->addShot($projectId, $data);
            return $this->success($result, '添加分镜成功');
        } catch (ValidateException $e) {
            return $this->validationError(['shot' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('添加分镜失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'add_shot_failed');
        }
    }

    public function updateShot()
    {
        try {
            $data = $this->getPostData();
            $shotId = (int)($data['shot_id'] ?? 0);
            if ($shotId <= 0) {
                return $this->error('分镜ID不能为空', 400, 'shot_id_required');
            }

            $result = $this->service->updateShot($shotId, $data);
            if (!$result) {
                return $this->error('分镜不存在', 404, 'shot_not_found');
            }

            return $this->success($result, '更新分镜成功');
        } catch (\Exception $e) {
            Log::error('更新分镜失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_shot_failed');
        }
    }

    public function deleteShot()
    {
        try {
            $data = $this->getPostData();
            $shotId = (int)($data['shot_id'] ?? 0);
            if ($shotId <= 0) {
                return $this->error('分镜ID不能为空', 400, 'shot_id_required');
            }

            $result = $this->service->deleteShot($shotId);
            if (!$result) {
                return $this->error('分镜不存在', 404, 'shot_not_found');
            }

            return $this->success([], '删除分镜成功');
        } catch (\Exception $e) {
            Log::error('删除分镜失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_shot_failed');
        }
    }

    public function sortShots()
    {
        try {
            $data = $this->getPostData();
            $projectId = (int)($data['project_id'] ?? 0);
            if ($projectId <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $shotIds = $data['shot_ids'] ?? [];
            if (empty($shotIds) || !is_array($shotIds)) {
                return $this->error('排序数据不能为空', 400, 'shot_ids_required');
            }

            $this->service->sortShots($projectId, $shotIds);
            return $this->success([], '排序成功');
        } catch (\Exception $e) {
            Log::error('分镜排序失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'sort_shots_failed');
        }
    }

    // ---- 配置查询 ----

    public function voiceActors()
    {
        try {
            $result = $this->service->getVoiceActors();
            return $this->success($result, '获取配音演员成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_voice_actors_failed');
        }
    }

    public function transitions()
    {
        return $this->success($this->service->getTransitions(), '获取转场效果成功');
    }

    public function filters()
    {
        return $this->success($this->service->getFilters(), '获取滤镜列表成功');
    }

    public function aspectRatios()
    {
        return $this->success($this->service->getAspectRatios(), '获取画面比例成功');
    }

    public function frameRates()
    {
        return $this->success($this->service->getFrameRates(), '获取帧率成功');
    }

    // ---- 一键成片：AI生成分镜 ----

    public function generateAutoShots()
    {
        try {
            $data = $this->getPostData();

            $copyText = $data['copy_text'] ?? '';
            if (empty($copyText)) {
                return $this->error('文案内容不能为空', 400, 'copy_text_required');
            }

            $industry   = $data['industry'] ?? '';
            $materialIds = $data['material_ids'] ?? [];
            $options    = [
                'shot_duration'   => $data['shot_duration'] ?? 3,
                'transition_type' => $data['transition_type'] ?? 'fade',
            ];

            $result = $this->service->generateAutoShots($copyText, $industry, $materialIds, $options);

            return $this->success($result, '生成分镜成功');
        } catch (\Exception $e) {
            Log::error('AI生成分镜失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'generate_auto_shots_failed');
        }
    }

    // ---- 批量混剪 ----

    public function batchRemix()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->getPostData();

            $materialIds = $data['material_ids'] ?? [];
            if (count($materialIds) < 2) {
                return $this->error('至少需要选择2个素材', 400, 'material_ids_required');
            }

            $result = $this->service->batchRemix($merchantId, $data);

            Log::info('批量混剪任务创建成功', [
                'merchant_id' => $merchantId,
                'total'       => $result['total'],
            ]);

            return $this->success($result, '批量混剪任务已创建');
        } catch (\Exception $e) {
            Log::error('批量混剪失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'batch_remix_failed');
        }
    }

    // ---- 批量导出 ----

    public function batchExport()
    {
        try {
            $data = $this->getPostData();
            $projectIds = $data['project_ids'] ?? [];
            if (empty($projectIds) || !is_array($projectIds)) {
                return $this->error('工程ID列表不能为空', 400, 'project_ids_required');
            }

            $results = [];
            foreach ($projectIds as $pid) {
                $pid = intval($pid);
                if ($pid <= 0) continue;
                $res = $this->service->exportProject($pid);
                $results[] = $res;
            }

            return $this->success($results, '批量导出任务已提交');
        } catch (\Exception $e) {
            Log::error('批量导出失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'batch_export_failed');
        }
    }

    // ---- 导出 ----

    public function export()
    {
        try {
            $projectId = (int)$this->request->param('project_id', 0);
            if ($projectId <= 0) {
                return $this->error('工程ID不能为空', 400, 'project_id_required');
            }

            $result = $this->service->exportProject($projectId);
            if (!$result) {
                return $this->error('工程不存在', 404, 'project_not_found');
            }

            return $this->success($result, '开始导出');
        } catch (\Exception $e) {
            Log::error('导出剪辑工程失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'export_clip_project_failed');
        }
    }

    // ---- 辅助方法 ----

    private function getPostData(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json) && !empty($json)) {
                $post = $this->request->post() ?: [];
                $cached = array_merge($post, $json);
                return $cached;
            }
        }
        $cached = $this->request->post() ?: [];
        return $cached;
    }

    private function getJsonParam(string $key, $default = null)
    {
        $data = $this->getPostData();
        if (isset($data[$key])) return $data[$key];
        return $this->request->param($key, $default);
    }

    private function resolveMerchantId(): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        $userId     = $this->request->user_id ?? null;
        $userRole   = $this->request->user_role ?? '';

        if (!$merchantId && $userId) {
            $merchant = MerchantModel::where('user_id', $userId)->find();
            if ($merchant) {
                $merchantId = $merchant->id;
            }
        }

        if (!$merchantId && ($userRole === 'admin' || ($userId === 0))) {
            $merchantId = 1;
        }

        return $merchantId ? (int)$merchantId : null;
    }
}
