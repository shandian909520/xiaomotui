<?php
declare (strict_types = 1);

namespace app\service;

use app\model\NfcDevice;
use app\model\SceneConfig;
use app\model\ReviewAction;
use think\facade\Db;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 打卡点评服务(模块4)
 *
 * **合规约束**:
 *  - 不实现"自动好评",仅生成"评价灵感草稿"(明确提示用户修改)
 *  - 不调用各平台写入接口;跳转链接在商户端录入,服务仅返回跳转 URL
 *  - 任何自动发布/代写/代发动作一律不做
 *  - AI 文案必须带"请根据真实体验修改后发布"提示
 */
class ReviewService
{
    /**
     * 获取点评配置(从 SceneConfig.review_config)
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
        $merchantName = trim((string)($sceneConfig->store_name ?? ''));

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
            'enabled' => $enabled,
            'merchant_name' => $merchantName,
            'platforms' => $list,
            'insight_supported' => true,
            'compliance_tip'    => '请根据真实体验修改后发布,平台禁止代写代发。',
        ];
    }

    /**
     * 生成"评价灵感草稿"(合规:不写入平台)
     *
     * @param int    $deviceId
     * @param string $platform  DIANPING/MEITUAN/GAODE/BAIDU/DOUYIN
     * @param int    $count     期望条数(默认 3,最多 5)
     * @return array
     */
    public function generateDraft(int $deviceId, string $platform, int $count = 3): array
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        if ($count < 1)  $count = 1;
        if ($count > 5)  $count = 5;
        $platform = strtoupper($platform);
        if (!in_array($platform, [
            ReviewAction::PLATFORM_DIANPING,
            ReviewAction::PLATFORM_MEITUAN,
            ReviewAction::PLATFORM_GAODE,
            ReviewAction::PLATFORM_BAIDU,
            ReviewAction::PLATFORM_DOUYIN,
        ], true)) {
            throw new ValidateException('不支持的点评平台');
        }

        $sceneConfig = $this->getSceneConfig($device);
        $merchantName = trim((string)($sceneConfig->store_name ?? '该店'));

        // 调用 AI 服务生成"评价灵感"
        $candidates = $this->callAiForReviewDraft($merchantName, $platform, $count);

        // 强制追加合规提示
        $complianceTip = '⚠ 提示:请根据真实消费体验修改以上文案,平台禁止代写代发,违规将面临处罚。';

        // 埋点:进入草稿页
        $this->record($deviceId, $platform, ReviewAction::ACTION_VIEW, ['count' => $count]);

        return [
            'platform'       => $platform,
            'platform_name'  => ReviewAction::platformName($platform),
            'merchant_name'  => $merchantName,
            'drafts'         => $candidates,
            'compliance_tip' => $complianceTip,
            'disclaimer'     => 'AI 生成的文案仅供参考,不代表用户真实体验。',
        ];
    }

    /**
     * 记录点评动作(埋点)
     */
    public function recordAction(int $deviceId, string $platform, string $action, array $extra = []): bool
    {
        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }
        return $this->record($deviceId, $platform, $action, $extra);
    }

    protected function getSceneConfig(NfcDevice $device): ?SceneConfig
    {
        try {
            // table_id 列不在 NfcDevice 的 $schema 中(已存在但需读 getData 兜底),
            // 失败时退化为 merchant_id 维度查找
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
            return SceneConfig::where('merchant_id', $device->merchant_id)
                ->order('id', 'asc')
                ->find();
        } catch (\Throwable $e) {
            Log::warning('ReviewService: 场景配置加载失败', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 调用 AI 服务生成"评价灵感"
     * 失败时使用本地兜底模板
     */
    protected function callAiForReviewDraft(string $merchantName, string $platform, int $count): array
    {
        try {
            $wenxin = new WenxinService(config('ai.default', 'wenxin'));
            $scene = "某用户在【{$merchantName}】消费后的真实评价,口语化、第一人称";
            $style = '亲切自然';
            // WenxinService 标准字段为 scene/style/platform/category/requirements,
            // "请生成 N 条" 的提示放入 requirements,由 splitDraftText 自动拆分
            $reqParts = [];
            $reqParts[] = '请生成 ' . $count . ' 条候选评价文案,用换行分隔';
            $reqParts[] = '每条30-80字,真实自然,带有具体细节但不得编造菜品/价格';
            $reqParts[] = '不要输出任何"建议五星"或操纵好评的措辞';
            $result = $wenxin->generateText([
                'scene'     => $scene,
                'style'     => $style,
                'platform'  => strtoupper($platform),
                'category'  => '探店',
                'requirements' => implode(';', $reqParts),
            ]);
            $text = $result['text'] ?? '';
            $items = $this->splitDraftText($text, $count);
            if (!empty($items)) {
                return $items;
            }
        } catch (\Throwable $e) {
            Log::warning('AI 评价灵感生成失败,使用本地兜底', [
                'merchant' => $merchantName,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->localFallbackDrafts($merchantName, $platform, $count);
    }

    /**
     * 把 AI 返回的文本切成多条草稿
     */
    protected function splitDraftText(string $text, int $count): array
    {
        $text = trim($text);
        if ($text === '') return [];
        // 按换行或编号拆分
        $parts = preg_split("/\n|\\d+\\.\\s*|\\d+、/", $text) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), function ($s) {
            return mb_strlen($s) > 4;
        }));
        if (empty($parts)) {
            return [['index' => 0, 'content' => $text]];
        }
        $items = [];
        $i = 0;
        foreach ($parts as $p) {
            if ($i >= $count) break;
            $items[] = ['index' => $i, 'content' => $p];
            $i++;
        }
        // 不足 count 条,补切短句
        while (count($items) < $count && count($items) > 0) {
            $items[] = ['index' => count($items), 'content' => $items[count($items) - 1]['content']];
        }
        return $items;
    }

    /**
     * 本地兜底模板(不依赖 AI)
     */
    protected function localFallbackDrafts(string $merchantName, string $platform, int $count): array
    {
        $platformIntro = ReviewAction::platformName($platform);
        $tpl = [
            "来「{$merchantName}」消费了一次,{$platformIntro}上必须好评!整体体验超预期,服务态度也很好,推荐给朋友们。",
            "专程过来体验「{$merchantName}」,环境干净卫生,出品稳定。下次还会来,真实分享给同样在选店的朋友们。",
            "在「{$merchantName}」的这次消费整体满意,人均性价比不错,氛围适合家人朋友聚餐,分享给同样在找店的人。",
        ];
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'index'   => $i,
                'content' => $tpl[$i % count($tpl)],
            ];
        }
        return $items;
    }

    protected function record(int $deviceId, string $platform, string $action, array $extra = []): bool
    {
        try {
            $device = NfcDevice::find($deviceId);
            $userHash = $this->userHash();
            ReviewAction::create([
                'device_id'   => $deviceId,
                'merchant_id' => $device ? (int)$device->merchant_id : 0,
                'platform'    => $platform,
                'action'      => $action,
                'user_hash'   => $userHash,
                'ip'          => request()->ip(),
                'ua'          => substr((string)request()->header('User-Agent'), 0, 255),
                'extra_data'  => $extra,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('ReviewAction 记录失败', [
                'device_id' => $deviceId,
                'platform'  => $platform,
                'action'    => $action,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function userHash(): string
    {
        try {
            $ip = request()->ip() ?: '0.0.0.0';
            $ua = request()->header('User-Agent') ?: '';
            return md5($ip . '|' . $ua);
        } catch (\Throwable $e) {
            return md5((string)time());
        }
    }
}
