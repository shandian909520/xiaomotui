<?php
declare(strict_types=1);

namespace app\service;

use app\model\DesignScene;

class DesignSceneService
{
    public function getSceneList(array $filters): array
    {
        $query = DesignScene::where('status', DesignScene::STATUS_ACTIVE);

        if (!empty($filters['keyword'])) {
            $query->whereLike('scene_name', '%' . addcslashes($filters['keyword'], '%_') . '%');
        }

        return $query->order('sort_order', 'asc')
            ->select()
            ->toArray();
    }

    public function getSceneDetail(string $sceneKey): ?array
    {
        $scene = DesignScene::where('scene_key', $sceneKey)
            ->where('status', DesignScene::STATUS_ACTIVE)
            ->find();

        if (!$scene) {
            return null;
        }

        $data = $scene->toArray();
        $data['config_fields'] = $this->getConfigFields($sceneKey);

        return $data;
    }

    public function getSceneTemplates(string $sceneKey, array $filters): array
    {
        $scene = DesignScene::where('scene_key', $sceneKey)
            ->where('status', DesignScene::STATUS_ACTIVE)
            ->find();

        if (!$scene) {
            return ['list' => [], 'total' => 0, 'page' => 1, 'limit' => 20];
        }

        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        return DesignScene::getTemplates($sceneKey, $page, $limit);
    }

    public function previewDesign(array $data): array
    {
        $sceneKey = $data['scene_key'] ?? '';
        $templateId = $data['template_id'] ?? '';

        return [
            'preview_id'   => md5($sceneKey . $templateId . time()),
            'scene_key'    => $sceneKey,
            'template_id'  => $templateId,
            'preview_url'  => '/static/design/preview/' . $sceneKey . '_' . $templateId . '.png',
            'bindings'     => [
                'qr_code_url'  => $data['qr_code_url'] ?? '',
                'store_name'   => $data['store_name'] ?? '',
                'activity_name' => $data['activity_name'] ?? '',
            ],
            'dimensions'   => $data['dimensions'] ?? [],
            'create_time'  => date('Y-m-d H:i:s'),
        ];
    }

    public function generateDesign(array $data): array
    {
        $sceneKey   = $data['scene_key'] ?? '';
        $templateId = $data['template_id'] ?? '';
        $format     = $data['format'] ?? 'PNG';

        return [
            'task_id'      => 'design_' . uniqid(),
            'scene_key'    => $sceneKey,
            'template_id'  => $templateId,
            'format'       => $format,
            'status'       => 'processing',
            'download_url' => '/static/design/download/' . $sceneKey . '_' . $templateId . '.' . strtolower($format),
            'bindings'     => [
                'qr_code_url'   => $data['qr_code_url'] ?? '',
                'store_name'    => $data['store_name'] ?? '',
                'activity_name' => $data['activity_name'] ?? '',
            ],
            'create_time'  => date('Y-m-d H:i:s'),
        ];
    }

    private function getConfigFields(string $sceneKey): array
    {
        $common = [
            ['key' => 'qr_code_url', 'name' => '二维码链接', 'type' => 'text', 'required' => true],
            ['key' => 'store_name', 'name' => '门店名称', 'type' => 'text', 'required' => true],
        ];

        $extra = match ($sceneKey) {
            'table_sticker' => [
                ['key' => 'table_number', 'name' => '桌号', 'type' => 'text', 'required' => false],
            ],
            'badge' => [
                ['key' => 'staff_name', 'name' => '员工姓名', 'type' => 'text', 'required' => true],
                ['key' => 'staff_title', 'name' => '职位', 'type' => 'text', 'required' => false],
            ],
            'poster', 'roll_up', 'display_stand' => [
                ['key' => 'activity_name', 'name' => '活动名称', 'type' => 'text', 'required' => true],
                ['key' => 'activity_desc', 'name' => '活动描述', 'type' => 'textarea', 'required' => false],
                ['key' => 'price', 'name' => '价格信息', 'type' => 'text', 'required' => false],
            ],
            'member_card' => [
                ['key' => 'card_type', 'name' => '卡类型', 'type' => 'select', 'options' => ['会员卡', '礼品卡', '储值卡'], 'required' => true],
                ['key' => 'card_number', 'name' => '卡号', 'type' => 'text', 'required' => false],
            ],
            default => [
                ['key' => 'activity_name', 'name' => '活动名称', 'type' => 'text', 'required' => false],
            ],
        };

        return array_merge($common, $extra);
    }
}
