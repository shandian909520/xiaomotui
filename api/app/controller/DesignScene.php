<?php
declare(strict_types=1);

namespace app\controller;

use app\service\DesignSceneService;
use think\facade\Log;

class DesignScene extends BaseController
{
    protected DesignSceneService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new DesignSceneService();
    }

    public function list()
    {
        try {
            $filters = [
                'keyword' => $this->request->param('keyword', ''),
            ];

            $result = $this->service->getSceneList($filters);
            return $this->success($result, '获取场景列表成功');
        } catch (\Exception $e) {
            Log::error('获取场景列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_scene_list_failed');
        }
    }

    public function detail()
    {
        try {
            $sceneKey = $this->request->param('scene_key', '');
            if (empty($sceneKey)) {
                return $this->error('场景标识不能为空', 400, 'scene_key_required');
            }

            $result = $this->service->getSceneDetail($sceneKey);
            if (!$result) {
                return $this->error('场景不存在', 404, 'scene_not_found');
            }

            return $this->success($result, '获取场景详情成功');
        } catch (\Exception $e) {
            Log::error('获取场景详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_scene_detail_failed');
        }
    }

    public function templates()
    {
        try {
            $sceneKey = $this->request->param('scene_key', '');
            if (empty($sceneKey)) {
                return $this->error('场景标识不能为空', 400, 'scene_key_required');
            }

            $filters = [
                'page'  => (int)$this->request->param('page', 1),
                'limit' => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getSceneTemplates($sceneKey, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取场景模板成功'
            );
        } catch (\Exception $e) {
            Log::error('获取场景模板失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_scene_templates_failed');
        }
    }

    public function preview()
    {
        try {
            $data = $this->request->post();

            if (empty($data['scene_key'])) {
                return $this->error('场景标识不能为空', 400, 'scene_key_required');
            }
            if (empty($data['template_id'])) {
                return $this->error('模板ID不能为空', 400, 'template_id_required');
            }

            $result = $this->service->previewDesign($data);
            return $this->success($result, '预览设计成功');
        } catch (\Exception $e) {
            Log::error('预览设计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'preview_design_failed');
        }
    }

    public function generate()
    {
        try {
            $data = $this->request->post();

            if (empty($data['scene_key'])) {
                return $this->error('场景标识不能为空', 400, 'scene_key_required');
            }
            if (empty($data['template_id'])) {
                return $this->error('模板ID不能为空', 400, 'template_id_required');
            }

            $result = $this->service->generateDesign($data);
            return $this->success($result, '生成设计成功');
        } catch (\Exception $e) {
            Log::error('生成设计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'generate_design_failed');
        }
    }
}
