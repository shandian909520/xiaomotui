<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\SceneConfigService;
use app\model\SceneConfig as SceneConfigModel;
use app\model\Merchant as MerchantModel;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 场景配置矩阵控制器
 */
class SceneConfig extends BaseController
{
    protected SceneConfigService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new SceneConfigService();
    }

    /**
     * 配置矩阵列表
     * GET scene-config/list
     */
    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'store_name' => $this->request->param('store_name', ''),
                'status'     => $this->request->param('status', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date'   => $this->request->param('end_date', ''),
                'page'       => (int)$this->request->param('page', 1),
                'limit'      => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getConfigList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取配置矩阵列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取配置矩阵列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage(), 400, 'get_scene_config_list_failed');
        }
    }

    /**
     * 单门店详情
     * GET scene-config/detail
     */
    public function detail()
    {
        try {
            $storeId = (int)$this->request->param('store_id', 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $result = $this->service->getConfigDetail($storeId);
            if (!$result) {
                return $this->error('门店配置不存在', 404, 'config_not_found');
            }

            return $this->success($result, '获取门店配置详情成功');
        } catch (\Exception $e) {
            Log::error('获取门店配置详情失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), 400, 'get_scene_config_detail_failed');
        }
    }

    /**
     * 保存配置
     * POST scene-config/save
     */
    public function save()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();
            $storeId = (int)($data['store_id'] ?? 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $data['merchant_id'] = $merchantId;

            $this->validate($data, [
                'store_id'   => 'require|integer|>:0',
                'store_name' => 'max:100',
            ], [
                'store_id.require' => '门店ID不能为空',
                'store_id.integer' => '门店ID必须是整数',
                'store_id.>'       => '门店ID必须大于0',
                'store_name.max'   => '门店名称长度不能超过100个字符',
            ]);

            $result = $this->service->saveConfig($storeId, $data);

            Log::info('保存门店配置成功', [
                'merchant_id' => $merchantId,
                'store_id'    => $storeId,
            ]);

            return $this->success($result, '保存配置成功');
        } catch (ValidateException $e) {
            return $this->validationError(['config' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('保存门店配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage(), 400, 'save_scene_config_failed');
        }
    }

    /**
     * 批量保存
     * POST scene-config/batch-save
     */
    public function batchSave()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();
            $storeIds = $data['store_ids'] ?? [];
            if (empty($storeIds) || !is_array($storeIds)) {
                return $this->error('请选择要配置的门店', 400, 'store_ids_required');
            }

            $data['merchant_id'] = $merchantId;

            $result = $this->service->batchSaveConfig($storeIds, $data);

            Log::info('批量保存门店配置', [
                'merchant_id' => $merchantId,
                'store_count' => count($storeIds),
                'success'     => $result['success'],
                'failed'      => $result['failed'],
            ]);

            return $this->success($result, '批量保存配置完成');
        } catch (\Exception $e) {
            Log::error('批量保存门店配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage(), 400, 'batch_save_scene_config_failed');
        }
    }

    /**
     * 启停配置项
     * POST scene-config/toggle-status
     */
    public function toggleStatus()
    {
        try {
            $data = $this->request->post();

            $this->validate($data, [
                'store_id'    => 'require|integer|>:0',
                'config_key'  => 'require|max:50',
                'enabled'     => 'require|in:0,1',
            ], [
                'store_id.require'   => '门店ID不能为空',
                'config_key.require' => '配置项不能为空',
                'enabled.require'    => '启用状态不能为空',
                'enabled.in'         => '启用状态值无效',
            ]);

            $storeId = (int)$data['store_id'];
            $configKey = $data['config_key'];
            $enabled = (bool)(int)$data['enabled'];

            $result = $this->service->toggleStatus($storeId, $configKey, $enabled);
            if (!$result) {
                return $this->error('门店配置不存在', 404, 'config_not_found');
            }

            return $this->success($result, '操作成功');
        } catch (ValidateException $e) {
            return $this->validationError(['config' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('启停配置项失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), 400, 'toggle_scene_config_failed');
        }
    }

    /**
     * 平台列表
     * GET scene-config/platforms
     */
    public function platforms()
    {
        $platforms = $this->service->getPlatforms();
        $columns = $this->service->getConfigColumns();

        return $this->success([
            'platforms' => $platforms,
            'columns'   => $columns,
        ], '获取成功');
    }

    /**
     * 解析商家ID
     */
    private function resolveMerchantId(): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        $userId = $this->request->user_id ?? null;
        $userRole = $this->request->user_role ?? '';

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
