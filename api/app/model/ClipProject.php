<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\relation\HasMany;

class ClipProject extends Model
{
    protected $name = 'clip_projects';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'            => 'integer',
        'merchant_id'   => 'integer',
        'user_id'       => 'integer',
        'config'        => 'json',
        'duration'      => 'integer',
        'template_id'   => 'integer',
        'is_template'   => 'integer',
        'create_time'   => 'datetime',
        'update_time'   => 'datetime',
    ];

    protected $json = ['config'];

    protected $field = [
        'merchant_id', 'user_id', 'name', 'mode', 'config',
        'status', 'video_url', 'duration', 'template_id',
        'is_template', 'create_time', 'update_time',
    ];

    public function shots(): HasMany
    {
        return $this->hasMany(ClipShot::class, 'project_id')->order('sort_order', 'asc');
    }

    public function getModeTextAttr($value, $data): string
    {
        return match ($data['mode'] ?? '') {
            'auto'       => '一键成片',
            'batch'      => '批量混剪',
            'storyboard' => '分镜剪辑',
            default      => '未知',
        };
    }

    public function getStatusTextAttr($value, $data): string
    {
        return match ($data['status'] ?? '') {
            'draft'     => '草稿',
            'completed' => '已完成',
            'exporting' => '导出中',
            'failed'    => '导出失败',
            default     => '未知',
        };
    }
}
