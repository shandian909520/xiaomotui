<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\StoreService;
use think\facade\Log;

class StoreManage extends BaseController
{
    protected StoreService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new StoreService();
    }

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'keyword'              => $this->request->param('keyword', ''),
                'table_sticker_status' => $this->request->param('table_sticker_status', ''),
                'page'                 => (int)$this->request->param('page', 1),
                'limit'                => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getStoreList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取门店列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取门店列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_store_list_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $result = $this->service->getStoreDetail($id);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            return $this->success($result, '获取门店详情成功');
        } catch (\Exception $e) {
            Log::error('获取门店详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_store_detail_failed');
        }
    }

    public function update()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $data   = $this->request->post();
            $result = $this->service->updateStore($id, $data);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            Log::info('更新门店成功', ['store_id' => $id]);
            return $this->success($result, '更新门店成功');
        } catch (\Exception $e) {
            Log::error('更新门店失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_store_failed');
        }
    }

    public function batchImport()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();

            $this->validate($data, [
                'stores' => 'require|array',
            ], [
                'stores.require' => '门店数据不能为空',
                'stores.array'   => '门店数据格式无效',
            ]);

            $result = $this->service->batchImportStores($merchantId, $data);

            Log::info('批量导入门店完成', [
                'merchant_id'    => $merchantId,
                'task_id'        => $result['id'],
                'success_count'  => $result['success_count'],
                'fail_count'     => $result['fail_count'],
            ]);

            return $this->success($result, '批量导入完成');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400, 'invalid_params');
        } catch (\Exception $e) {
            Log::error('批量导入门店失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'batch_import_store_failed');
        }
    }

    public function batchImportPoi()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();

            $this->validate($data, [
                'poi_list' => 'require|array',
            ], [
                'poi_list.require' => 'POI数据不能为空',
                'poi_list.array'   => 'POI数据格式无效',
            ]);

            $result = $this->service->batchImportPoi($merchantId, $data);

            Log::info('批量导入POI完成', [
                'merchant_id'   => $merchantId,
                'task_id'       => $result['id'],
                'success_count' => $result['success_count'],
                'fail_count'    => $result['fail_count'],
            ]);

            return $this->success($result, '批量导入POI完成');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400, 'invalid_params');
        } catch (\Exception $e) {
            Log::error('批量导入POI失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'batch_import_poi_failed');
        }
    }

    public function importStatus()
    {
        try {
            $taskId = (int)$this->request->param('task_id', 0);
            if ($taskId <= 0) {
                return $this->error('任务ID不能为空', 400, 'task_id_required');
            }

            $result = $this->service->getImportTaskStatus($taskId);
            if (!$result) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            return $this->success($result, '获取导入状态成功');
        } catch (\Exception $e) {
            Log::error('获取导入状态失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_import_status_failed');
        }
    }

    public function qrCode()
    {
        try {
            $storeId = (int)$this->request->param('store_id', 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $result = $this->service->generateQrCode($storeId);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            return $this->success($result, '获取二维码成功');
        } catch (\Exception $e) {
            Log::error('获取门店二维码失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_qr_code_failed');
        }
    }

    public function nfcPath()
    {
        try {
            $storeId = (int)$this->request->param('store_id', 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $result = $this->service->getNfcConfigPath($storeId);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            return $this->success($result, '获取NFC路径成功');
        } catch (\Exception $e) {
            Log::error('获取NFC路径失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_nfc_path_failed');
        }
    }

    public function decoration()
    {
        try {
            $storeId = (int)$this->request->post('store_id', 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $config = $this->request->post('config', []);
            if (empty($config)) {
                return $this->error('装修配置不能为空', 400, 'config_required');
            }

            $result = $this->service->updateDecoration($storeId, $config);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            Log::info('更新门店装修配置成功', ['store_id' => $storeId]);
            return $this->success($result, '更新装修配置成功');
        } catch (\Exception $e) {
            Log::error('更新门店装修配置失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_decoration_failed');
        }
    }

    public function tableSticker()
    {
        try {
            $storeId = (int)$this->request->post('store_id', 0);
            if ($storeId <= 0) {
                return $this->error('门店ID不能为空', 400, 'store_id_required');
            }

            $status = (int)$this->request->post('status', 0);

            $result = $this->service->toggleTableSticker($storeId, $status);
            if (!$result) {
                return $this->error('门店不存在', 404, 'store_not_found');
            }

            Log::info('更新桌贴状态成功', ['store_id' => $storeId, 'status' => $status]);
            return $this->success($result, '更新桌贴状态成功');
        } catch (\Exception $e) {
            Log::error('更新桌贴状态失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'toggle_table_sticker_failed');
        }
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
