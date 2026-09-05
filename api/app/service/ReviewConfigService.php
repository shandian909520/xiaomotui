<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\ReviewDraftTemplate;
use app\model\SceneConfig;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 商家点评配置服务(Agent C)
 *
 * - 商家配置存于 SceneConfig.review_config(JSON 列)
 *   {
 *     "enabled": true,
 *     "ai_draft_enabled": true,
 *     "default_count": 3,
 *     "platforms": [{"key":"DIANPING","name":"大众点评","jump_url":"...","icon":"..."}, ...]
 *   }
 * - 模板存于 xmt_review_draft_templates 表,按 merchant_id + platform 维度
 * - 平台默认模板(merchant_id=0)始终可用
 */
class ReviewConfigService
{
    /**
     * 读取商家点评配置(从 SceneConfig.review_config)
     * 兼容未配置 store_id 的设备
     */
    public function getConfig(int $deviceId): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        $sceneConfig = $this->getSceneConfig($device);
        $config = is_array($sceneConfig->review_config ?? null) ? $sceneConfig->review_config : [];
        $enabled = !empty($config['enabled']);
        $platforms = $config['platforms'] ?? [];
        $list = [];
        if (is_array($platforms)) {
            foreach ($platforms as $item) {
                if (!is_array($item)) continue;
                $list[] = [
                    'key'      => $item['key'] ?? '',
                    'name'     => $item['name'] ?? \app\model\ReviewAction::platformName((string)($item['key'] ?? '')),
                    'jump_url' => $item['jump_url'] ?? '',
                    'icon'     => $item['icon'] ?? '',
                ];
            }
        }
        return [
            'enabled'           => $enabled,
            'ai_draft_enabled'  => (bool)($config['ai_draft_enabled'] ?? true),
            'default_count'     => (int)($config['default_count'] ?? 3),
            'platforms'         => $list,
            'merchant_name'     => trim((string)($sceneConfig->store_name ?? '')),
        ];
    }

    /**
     * 更新商家点评配置(写入 SceneConfig.review_config)
     *
     * POST { device_id, enabled?, ai_draft_enabled?, default_count?, platforms:[{key,name,jump_url,icon}] }
     */
    public function updateConfig(int $deviceId, array $payload): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        $sceneConfig = $this->getSceneConfig($device, true);
        if (!$sceneConfig) {
            throw new ValidateException('请先在场景配置中初始化门店配置');
        }
        $current = is_array($sceneConfig->review_config ?? null) ? $sceneConfig->review_config : [];

        if (array_key_exists('enabled', $payload)) {
            $current['enabled'] = (bool)$payload['enabled'];
        }
        if (array_key_exists('ai_draft_enabled', $payload)) {
            $current['ai_draft_enabled'] = (bool)$payload['ai_draft_enabled'];
        }
        if (array_key_exists('default_count', $payload)) {
            $count = (int)$payload['default_count'];
            $current['default_count'] = max(1, min(5, $count));
        }
        if (array_key_exists('platforms', $payload) && is_array($payload['platforms'])) {
            $cleaned = [];
            foreach ($payload['platforms'] as $item) {
                if (!is_array($item) || empty($item['key'])) continue;
                $cleaned[] = [
                    'key'      => (string)$item['key'],
                    'name'     => (string)($item['name'] ?? \app\model\ReviewAction::platformName((string)$item['key'])),
                    'jump_url' => (string)($item['jump_url'] ?? ''),
                    'icon'     => (string)($item['icon'] ?? ''),
                ];
            }
            $current['platforms'] = $cleaned;
        }

        $sceneConfig->review_config = $current;
        $sceneConfig->save();

        return $this->getConfig($deviceId);
    }

    /**
     * 商家草稿模板列表
     * GET { device_id, platform, scope?: "merchant"|"all"(默认 all,含平台默认) }
     */
    public function getDraftTemplates(int $deviceId, string $platform = '', string $scope = 'all'): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        $merchantId = (int)$device->merchant_id;

        $query = ReviewDraftTemplate::where('status', ReviewDraftTemplate::STATUS_ENABLED);
        if ($platform !== '') {
            $query = $query->where('platform', $platform);
        }
        if ($scope === 'merchant') {
            $query = $query->where('merchant_id', $merchantId);
        } else {
            $query = $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId)->whereOr('merchant_id', 0);
            });
        }
        $list = $query->order('sort', 'desc')
            ->order('weight', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $list;
    }

    /**
     * 新增商家模板
     * POST { device_id, platform, title, prompt, style?, weight?, sort?, status? }
     */
    public function addDraftTemplate(array $payload): array
    {
        $this->validateTemplate($payload, false);
        $device = NfcDevice::find((int)$payload['device_id']);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        $row = ReviewDraftTemplate::create([
            'merchant_id' => (int)$device->merchant_id,
            'platform'    => (string)$payload['platform'],
            'scene_key'   => (string)($payload['scene_key'] ?? 'default'),
            'title'       => (string)$payload['title'],
            'prompt'      => (string)$payload['prompt'],
            'style'       => (string)($payload['style'] ?? '亲切自然'),
            'weight'      => max(1, (int)($payload['weight'] ?? 10)),
            'status'      => isset($payload['status']) ? (int)$payload['status'] : ReviewDraftTemplate::STATUS_ENABLED,
            'sort'        => (int)($payload['sort'] ?? 0),
        ]);
        return $row->toArray();
    }

    /**
     * 删除商家模板
     */
    public function deleteDraftTemplate(int $id, int $merchantId): bool
    {
        $row = ReviewDraftTemplate::find($id);
        if (!$row) {
            throw new ValidateException('模板不存在');
        }
        // 平台默认模板不允许商家删
        if ((int)$row->merchant_id === 0) {
            throw new ValidateException('平台默认模板不可删除');
        }
        if ($merchantId > 0 && (int)$row->merchant_id !== $merchantId) {
            throw new ValidateException('无权删除他人模板');
        }
        return (bool)$row->delete();
    }

    protected function validateTemplate(array $payload, bool $partial): void
    {
        if (!$partial || array_key_exists('device_id', $payload)) {
            if (empty($payload['device_id']) || (int)$payload['device_id'] <= 0) {
                throw new ValidateException('设备ID不合法');
            }
        }
        if (!$partial || array_key_exists('platform', $payload)) {
            if (!isset(ReviewDraftTemplate::PLATFORMS[$payload['platform'] ?? ''])) {
                throw new ValidateException('平台不合法');
            }
        }
        if (!$partial || array_key_exists('title', $payload)) {
            $t = trim((string)($payload['title'] ?? ''));
            if ($t === '') {
                throw new ValidateException('模板标题不能为空');
            }
            if (mb_strlen($t) > 100) {
                throw new ValidateException('模板标题不能超过100字');
            }
        }
        if (!$partial || array_key_exists('prompt', $payload)) {
            $p = trim((string)($payload['prompt'] ?? ''));
            if ($p === '') {
                throw new ValidateException('模板提示词不能为空');
            }
            if (mb_strlen($p) > 1000) {
                throw new ValidateException('模板提示词不能超过1000字');
            }
        }
    }

    /**
     * 获取场景配置
     *  - $createIfMissing=true 时,若 store 维度未找到,则按 merchant_id 维度查找或新建
     */
    protected function getSceneConfig(NfcDevice $device, bool $createIfMissing = false): ?SceneConfig
    {
        try {
            $storeId = null;
            try {
                $storeId = $device->getData('table_id');
            } catch (\Throwable $e) {
                $storeId = null;
            }
            if (empty($storeId)) {
                $storeId = \think\facade\Db::name('nfc_devices')
                    ->where('id', (int)$device->id)
                    ->value('table_id');
            }
            if (!empty($storeId)) {
                $cfg = SceneConfig::where('store_id', (int)$storeId)->find();
                if ($cfg) return $cfg;
            }
            $cfg = SceneConfig::where('merchant_id', $device->merchant_id)
                ->order('id', 'asc')
                ->find();
            if ($cfg) return $cfg;
            if ($createIfMissing) {
                return SceneConfig::create([
                    'merchant_id' => (int)$device->merchant_id,
                    'store_id'    => (int)($storeId ?? 0),
                    'store_name'  => '',
                    'status'      => SceneConfig::STATUS_ACTIVE,
                ]);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('ReviewConfigService: 场景配置加载失败', [
                'device_id' => $device->id,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }
}
