<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AiStaffRole extends Model
{
    protected $name = 'ai_staff_roles';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'             => 'integer',
        'task_types'     => 'json',
        'is_hot'         => 'integer',
        'free_count'     => 'integer',
        'used_count'     => 'integer',
        'sort_order'     => 'integer',
        'status'         => 'integer',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $json = ['task_types'];

    protected $field = [
        'group_name', 'role_name', 'nickname', 'avatar_url',
        'description', 'task_types', 'prompt_template',
        'is_hot', 'free_count', 'used_count', 'sort_order', 'status',
    ];

    const GROUP_CONTENT    = '内容文案组';
    const GROUP_VISUAL     = '视觉设计组';
    const GROUP_STORE      = '门店运营组';
    const GROUP_REPUTATION = '口碑管理组';

    public static function getGroups(): array
    {
        return [
            self::GROUP_CONTENT,
            self::GROUP_VISUAL,
            self::GROUP_STORE,
            self::GROUP_REPUTATION,
        ];
    }

    public function getStatusTextAttr($value, $data): string
    {
        return ($data['status'] ?? 0) ? '启用' : '禁用';
    }

    public function getRemainingCountAttr($value, $data): int
    {
        return max(0, ($data['free_count'] ?? 0) - ($data['used_count'] ?? 0));
    }
}
