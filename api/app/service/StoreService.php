<?php
declare(strict_types=1);

namespace app\service;

use app\model\Store;
use app\model\StoreImportTask;
use think\facade\Db;
use think\facade\Log;

class StoreService
{
    public function getStoreList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = Store::where('merchant_id', $merchantId);

        if (!empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $keyword = '%' . addcslashes($filters['keyword'], '%_') . '%';
                $q->whereLike('name', $keyword)
                  ->whereOr('address', $keyword);
            });
        }
        if (isset($filters['table_sticker_status']) && $filters['table_sticker_status'] !== '') {
            $query->where('table_sticker_status', (int)$filters['table_sticker_status']);
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)
            ->order('update_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function getStoreDetail(int $id): ?array
    {
        $store = Store::find($id);
        if (!$store) {
            return null;
        }
        return $store->toArray();
    }

    public function updateStore(int $id, array $data): ?array
    {
        $store = Store::find($id);
        if (!$store) {
            return null;
        }

        $allowFields = [
            'name', 'address', 'phone', 'business_hours',
            'service_facilities', 'poi_id', 'poi_name', 'poi_platform',
            'decoration_config', 'qr_code_url', 'nfc_config_path',
            'table_sticker_status',
        ];
        foreach ($allowFields as $field) {
            if (array_key_exists($field, $data)) {
                $store->$field = $data[$field];
            }
        }
        $store->save();

        return $store->toArray();
    }

    public function batchImportStores(int $merchantId, array $data): array
    {
        $stores = $data['stores'] ?? [];
        if (empty($stores) || !is_array($stores)) {
            throw new \InvalidArgumentException('导入门店数据不能为空');
        }

        $task                      = new StoreImportTask();
        $task->merchant_id         = $merchantId;
        $task->import_type         = StoreImportTask::IMPORT_TYPE_STORE;
        $task->total_count         = count($stores);
        $task->success_count       = 0;
        $task->fail_count          = 0;
        $task->status              = 'processing';
        $task->file_url            = $data['file_url'] ?? null;
        $task->save();

        $failReasons = [];

        Db::startTrans();
        try {
            foreach ($stores as $index => $storeData) {
                try {
                    $store              = new Store();
                    $store->merchant_id = $merchantId;
                    $store->name        = $storeData['name'] ?? '';
                    $store->address     = $storeData['address'] ?? '';
                    $store->phone       = $storeData['phone'] ?? '';
                    $store->poi_id      = $storeData['poi_id'] ?? null;

                    if (!empty($storeData['service_facilities'])) {
                        $store->service_facilities = $storeData['service_facilities'];
                    }

                    $store->save();
                    $task->success_count++;
                } catch (\Exception $e) {
                    $task->fail_count++;
                    $failReasons[] = "第" . ($index + 1) . "条: " . $e->getMessage();
                }
            }

            $task->status       = 'completed';
            $task->fail_reason  = empty($failReasons) ? null : implode("\n", $failReasons);
            $task->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $task->status      = 'failed';
            $task->fail_reason = $e->getMessage();
            $task->save();
            throw $e;
        }

        return $task->toArray();
    }

    public function batchImportPoi(int $merchantId, array $data): array
    {
        $poiList = $data['poi_list'] ?? [];
        if (empty($poiList) || !is_array($poiList)) {
            throw new \InvalidArgumentException('导入POI数据不能为空');
        }

        $task                      = new StoreImportTask();
        $task->merchant_id         = $merchantId;
        $task->import_type         = StoreImportTask::IMPORT_TYPE_POI;
        $task->total_count         = count($poiList);
        $task->success_count       = 0;
        $task->fail_count          = 0;
        $task->status              = 'processing';
        $task->file_url            = $data['file_url'] ?? null;
        $task->save();

        $failReasons = [];

        Db::startTrans();
        try {
            foreach ($poiList as $index => $poiData) {
                try {
                    $store = Store::where('merchant_id', $merchantId)
                        ->where('id', $poiData['store_id'] ?? 0)
                        ->find();

                    if (!$store) {
                        throw new \RuntimeException('门店不存在');
                    }

                    $store->poi_id       = $poiData['poi_id'] ?? null;
                    $store->poi_name     = $poiData['poi_name'] ?? null;
                    $store->poi_platform = $poiData['poi_platform'] ?? null;
                    $store->save();

                    $task->success_count++;
                } catch (\Exception $e) {
                    $task->fail_count++;
                    $failReasons[] = "第" . ($index + 1) . "条: " . $e->getMessage();
                }
            }

            $task->status       = 'completed';
            $task->fail_reason  = empty($failReasons) ? null : implode("\n", $failReasons);
            $task->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $task->status      = 'failed';
            $task->fail_reason = $e->getMessage();
            $task->save();
            throw $e;
        }

        return $task->toArray();
    }

    public function getImportTaskStatus(int $taskId): ?array
    {
        $task = StoreImportTask::find($taskId);
        if (!$task) {
            return null;
        }
        return $task->toArray();
    }

    public function generateQrCode(int $storeId): ?array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return null;
        }

        $qrCodeUrl = $store->qr_code_url;
        if (empty($qrCodeUrl)) {
            $qrCodeUrl = '/store/qr/' . $store->merchant_id . '/' . $storeId;
            $store->qr_code_url = $qrCodeUrl;
            $store->save();
        }

        return [
            'store_id'    => $storeId,
            'qr_code_url' => $qrCodeUrl,
            'preview_url' => $qrCodeUrl . '?preview=1',
        ];
    }

    public function getNfcConfigPath(int $storeId): ?array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return null;
        }

        $nfcPath = $store->nfc_config_path;
        if (empty($nfcPath)) {
            $nfcPath = '/nfc/config/store/' . $storeId;
            $store->nfc_config_path = $nfcPath;
            $store->save();
        }

        return [
            'store_id'        => $storeId,
            'nfc_config_path' => $nfcPath,
        ];
    }

    public function updateDecoration(int $storeId, array $config): ?array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return null;
        }

        $store->decoration_config = $config;
        $store->save();

        return $store->toArray();
    }

    public function toggleTableSticker(int $storeId, int $status): ?array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return null;
        }

        $store->table_sticker_status = $status;
        $store->save();

        return $store->toArray();
    }
}
