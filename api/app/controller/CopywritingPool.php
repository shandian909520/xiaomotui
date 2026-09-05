<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\CopywritingPool as CopywritingPoolModel;
use app\service\CopywritingPoolService;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 文案池控制器(模块3 - 业务闭环: Agent C)
 *
 * 顾客端:
 *   GET  /api/copywriting/rotate?device_id=&scene=&rotate_token=  -> rotate
 * 商家后台(鉴权):
 *   GET  /api/copywriting/admin/list?device_id=&scene=
 *   POST /api/copywriting/admin/                {device_id,scene,content,weight,...}
 *   PUT  /api/copywriting/admin/:id              {content?,weight?,status?,sort?,scene?}
 *   DELETE /api/copywriting/admin/:id
 *   POST /api/copywriting/admin/batch-import    {device_id,scene,weight,lines:"A\nB\n..."}
 */
class CopywritingPool extends BaseController
{
    protected CopywritingPoolService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new CopywritingPoolService();
    }

    /**
     * 顾客端"换一批"公开接口
     *  - 首次调用 rotate_token 为空 → 单条 + 兜底
     *  - 携带 rotate_token → 防短时重复
     */
    public function rotate()
    {
        try {
            $deviceId    = (int)$this->request->param('device_id', 0);
            $scene       = (string)$this->request->param('scene', CopywritingPoolModel::SCENE_PUBLISH);
            $rotateToken = (string)$this->request->param('rotate_token', '');
            $useRotate   = (int)$this->request->param('rotate', 0) === 1;

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if (!in_array($scene, [
                CopywritingPoolModel::SCENE_PUBLISH,
                CopywritingPoolModel::SCENE_REVIEW,
                CopywritingPoolModel::SCENE_GROUPBUY,
            ], true)) {
                $scene = CopywritingPoolModel::SCENE_PUBLISH;
            }

            $payload = $useRotate
                ? $this->service->rotateCopywriting($deviceId, $rotateToken)
                : $this->service->prebuildPool($deviceId, $scene, 1);

            // rotateCopywriting 已经把 content/rotate_token 都返回了;prebuildPool 是数组
            if (is_array($payload) && isset($payload[0])) {
                $first = $payload[0];
                $data = [
                    'content'      => $first['content'] ?? '',
                    'content_id'   => $first['content_id'] ?? 0,
                    'rotate_token' => '',
                    'has_more'     => count($payload) > 1,
                    'source'       => 'pool',
                ];
            } else {
                $data = $payload;
            }
            $data['scene'] = $scene;
            return $this->success($data, '获取文案成功');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('文案轮播失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'rotate_copywriting_failed');
        }
    }

    /**
     * 商家后台列表
     */
    public function list()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $scene    = (string)$this->request->param('scene', '');
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $list = $this->service->getByDevice(
                $deviceId,
                $scene !== '' ? $scene : CopywritingPoolModel::SCENE_PUBLISH
            );
            return $this->success([
                'list'  => $list,
                'total' => count($list),
            ], '获取文案池成功');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('文案列表查询失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'list_copywriting_failed');
        }
    }

    /**
     * 商家后台新增
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            if (empty($data['scene'])) {
                $data['scene'] = CopywritingPoolModel::SCENE_PUBLISH;
            }
            $row = $this->service->add($data);
            return $this->success($row, '创建文案成功');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建文案失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_copywriting_failed');
        }
    }

    /**
     * 商家后台更新
     */
    public function update($id)
    {
        try {
            $row = $this->service->update((int)$id, $this->request->post());
            return $this->success($row, '更新文案成功');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新文案失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_copywriting_failed');
        }
    }

    /**
     * 商家后台删除
     */
    public function delete($id)
    {
        try {
            $ok = $this->service->delete((int)$id);
            return $this->success(['id' => (int)$id, 'deleted' => $ok], '删除文案成功');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('删除文案失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'delete_copywriting_failed');
        }
    }

    /**
     * 批量导入(每行一条)
     * POST { device_id, scene?, weight?, lines: "A\nB\nC" }
     */
    public function batchImport()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $scene    = (string)$this->request->param('scene', CopywritingPoolModel::SCENE_PUBLISH);
            $weight   = (int)$this->request->param('weight', 10);
            $raw      = (string)($this->request->post('lines', ''));

            if ($raw === '') {
                $raw = (string)($this->request->post('lines_text', ''));
            }
            $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            $result = $this->service->batchImport($deviceId, $lines, $scene, $weight);
            return $this->success($result, '批量导入完成');
        } catch (ValidateException $e) {
            return $this->validationError(['copywriting' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('批量导入文案失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'batch_import_copywriting_failed');
        }
    }
}
