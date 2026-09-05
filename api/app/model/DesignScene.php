<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class DesignScene extends Model
{
    protected $name = 'design_scenes';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'             => 'integer',
        'template_count' => 'integer',
        'sort_order'     => 'integer',
        'status'         => 'integer',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $field = [
        'scene_key', 'scene_name', 'icon', 'description',
        'template_count', 'sort_order', 'status',
    ];

    const STATUS_DISABLED = 0;
    const STATUS_ACTIVE   = 1;

    public static function getTemplates(string $sceneKey, int $page = 1, int $limit = 20): array
    {
        $templates = [
            'table_sticker' => [
                ['name' => '圆形桌贴', 'thumbnail' => '/static/design/table_sticker_1.png', 'size' => '10x10cm'],
                ['name' => '方形桌贴', 'thumbnail' => '/static/design/table_sticker_2.png', 'size' => '8x8cm'],
                ['name' => '长条桌贴', 'thumbnail' => '/static/design/table_sticker_3.png', 'size' => '15x5cm'],
                ['name' => '异形桌贴', 'thumbnail' => '/static/design/table_sticker_4.png', 'size' => '12x12cm'],
                ['name' => '迷你桌贴', 'thumbnail' => '/static/design/table_sticker_5.png', 'size' => '5x5cm'],
            ],
            'badge' => [
                ['name' => '横式工牌', 'thumbnail' => '/static/design/badge_1.png', 'size' => '9x5.5cm'],
                ['name' => '竖式工牌', 'thumbnail' => '/static/design/badge_2.png', 'size' => '5.5x9cm'],
                ['name' => '圆形工牌', 'thumbnail' => '/static/design/badge_3.png', 'size' => '6cm直径'],
            ],
            'roll_up' => [
                ['name' => '标准易拉宝', 'thumbnail' => '/static/design/roll_up_1.png', 'size' => '80x200cm'],
                ['name' => '宽幅易拉宝', 'thumbnail' => '/static/design/roll_up_2.png', 'size' => '120x200cm'],
            ],
            'poster' => [
                ['name' => 'A4海报', 'thumbnail' => '/static/design/poster_1.png', 'size' => '21x29.7cm'],
                ['name' => 'A3海报', 'thumbnail' => '/static/design/poster_2.png', 'size' => '29.7x42cm'],
                ['name' => '促销海报', 'thumbnail' => '/static/design/poster_3.png', 'size' => '40x60cm'],
                ['name' => '节日海报', 'thumbnail' => '/static/design/poster_4.png', 'size' => '42x57cm'],
            ],
        ];

        $sceneTemplates = $templates[$sceneKey] ?? [];

        foreach ($sceneTemplates as &$tpl) {
            $tpl['id'] = md5($sceneKey . '_' . $tpl['name']);
            $tpl['scene_key'] = $sceneKey;
            $tpl['format'] = 'PNG';
            $tpl['dpi'] = 300;
        }
        unset($tpl);

        $total = count($sceneTemplates);
        $offset = ($page - 1) * $limit;
        $list = array_slice($sceneTemplates, $offset, $limit);

        return compact('list', 'total', 'page', 'limit');
    }
}
