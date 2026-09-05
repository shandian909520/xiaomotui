<?php
declare (strict_types = 1);

namespace app\service;

use app\model\SceneConfig as SceneConfigModel;
use think\facade\Db;
use think\facade\Log;
use think\exception\ValidateException;

class SceneConfigService
{
    /**
     * 获取门店配置矩阵列表
     */
    public function getConfigList(int $merchantId, array $filters = []): array
    {
        $page = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = SceneConfigModel::where('merchant_id', $merchantId);

        if (!empty($filters['store_name'])) {
            $query->whereLike('store_name', '%' . addcslashes($filters['store_name'], '%_') . '%');
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = $query->count();

        $list = $query->page($page, $limit)
            ->order('create_time', 'desc')
            ->select();

        $items = [];
        foreach ($list as $config) {
            $items[] = $this->formatConfigRow($config);
        }

        return [
            'list'  => $items,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 获取单个门店配置详情
     */
    public function getConfigDetail(int $storeId): ?array
    {
        $config = SceneConfigModel::where('store_id', $storeId)->find();
        if (!$config) {
            return null;
        }
        return $this->formatConfigDetail($config);
    }

    /**
     * 保存单个门店配置
     */
    public function saveConfig(int $storeId, array $data): array
    {
        $config = SceneConfigModel::where('store_id', $storeId)->find();

        Db::startTrans();
        try {
            if (!$config) {
                $config = new SceneConfigModel();
                $config->store_id = $storeId;
                $config->merchant_id = $data['merchant_id'] ?? 0;
                $config->store_name = $data['store_name'] ?? '';
                $config->status = SceneConfigModel::STATUS_ACTIVE;
                $config->scan_enabled = 0;
            }

            $this->fillConfigData($config, $data);
            $config->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return $this->formatConfigDetail($config);
    }

    /**
     * 批量设置多门店配置
     */
    public function batchSaveConfig(array $storeIds, array $data): array
    {
        $results = ['success' => 0, 'failed' => 0, 'details' => []];

        Db::startTrans();
        try {
            foreach ($storeIds as $storeId) {
                try {
                    $config = SceneConfigModel::where('store_id', $storeId)->find();

                    if (!$config) {
                        $config = new SceneConfigModel();
                        $config->store_id = $storeId;
                        $config->merchant_id = $data['merchant_id'] ?? 0;
                        $config->store_name = $data['store_name'] ?? '';
                        $config->status = SceneConfigModel::STATUS_ACTIVE;
                        $config->scan_enabled = 0;
                    }

                    $this->fillConfigData($config, $data);
                    $config->save();

                    $results['success']++;
                    $results['details'][] = ['store_id' => $storeId, 'status' => 'ok'];
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = ['store_id' => $storeId, 'status' => 'fail', 'message' => $e->getMessage()];
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return $results;
    }

    /**
     * 启停某配置项
     */
    public function toggleStatus(int $storeId, string $configKey, bool $enabled): ?array
    {
        if (!in_array($configKey, SceneConfigModel::CONFIG_COLUMNS)) {
            throw new ValidateException('无效的配置项: ' . $configKey);
        }

        $config = SceneConfigModel::where('store_id', $storeId)->find();
        if (!$config) {
            return null;
        }

        if ($configKey === 'scan_enabled') {
            $config->scan_enabled = $enabled ? 1 : 0;
        } else {
            $currentVal = $config->$configKey;
            if (is_array($currentVal)) {
                $currentVal['enabled'] = $enabled;
            } else {
                $currentVal = ['enabled' => $enabled];
            }
            $config->$configKey = $currentVal;
        }

        $config->save();

        return $this->formatConfigDetail($config);
    }

    /**
     * 获取支持的平台列表
     */
    public function getPlatforms(): array
    {
        return SceneConfigModel::PLATFORMS;
    }

    /**
     * 获取矩阵配置项列表（列定义）
     */
    public function getConfigColumns(): array
    {
        $columns = [];
        foreach (SceneConfigModel::CONFIG_COLUMNS as $col) {
            $columns[] = [
                'key'   => $col,
                'label' => SceneConfigModel::CONFIG_LABELS[$col] ?? $col,
            ];
        }
        return $columns;
    }

    /**
     * 填充配置数据到模型
     */
    private function fillConfigData(SceneConfigModel $config, array $data): void
    {
        $jsonFields = [
            'platform_config', 'graphic_config', 'review_config',
            'checkin_config', 'follow_config', 'like_share_config',
            'groupbuy_config', 'wifi_config', 'wechat_card_config',
            'custom_link_config', 'edaijia_config', 'touch_config',
        ];

        if (isset($data['store_name'])) {
            $config->store_name = $data['store_name'];
        }
        if (isset($data['merchant_id'])) {
            $config->merchant_id = (int)$data['merchant_id'];
        }
        if (isset($data['status'])) {
            $config->status = (int)$data['status'];
        }
        if (isset($data['scan_enabled'])) {
            $config->scan_enabled = (int)$data['scan_enabled'];
        }

        foreach ($jsonFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                $config->$field = is_string($val) ? json_decode($val, true) : $val;
            }
        }
    }

    /**
     * 格式化列表行
     */
    private function formatConfigRow(SceneConfigModel $config): array
    {
        return [
            'id'            => $config->id,
            'store_id'      => $config->store_id,
            'store_name'    => $config->store_name,
            'status'        => $config->status,
            'status_text'   => $config->status_text,
            'scan_enabled'  => $config->scan_enabled ? true : false,
            'config_summary' => $config->getConfigSummary(),
            'create_time'   => $config->create_time,
            'update_time'   => $config->update_time,
        ];
    }

    /**
     * 格式化详情
     */
    private function formatConfigDetail(SceneConfigModel $config): array
    {
        return [
            'id'                   => $config->id,
            'merchant_id'          => $config->merchant_id,
            'store_id'             => $config->store_id,
            'store_name'           => $config->store_name,
            'status'               => $config->status,
            'status_text'          => $config->status_text,
            'scan_enabled'         => $config->scan_enabled ? true : false,
            'platform_config'      => $config->platform_config ?: null,
            'graphic_config'       => $config->graphic_config ?: null,
            'review_config'        => $config->review_config ?: null,
            'checkin_config'       => $config->checkin_config ?: null,
            'follow_config'        => $config->follow_config ?: null,
            'like_share_config'    => $config->like_share_config ?: null,
            'groupbuy_config'      => $config->groupbuy_config ?: null,
            'wifi_config'          => $config->wifi_config ?: null,
            'wechat_card_config'   => $config->wechat_card_config ?: null,
            'custom_link_config'   => $config->custom_link_config ?: null,
            'edaijia_config'       => $config->edaijia_config ?: null,
            'touch_config'         => $config->touch_config ?: null,
            'config_summary'       => $config->getConfigSummary(),
            'create_time'          => $config->create_time,
            'update_time'          => $config->update_time,
        ];
    }
}
