<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class VoiceActor extends Model
{
    protected $name = 'voice_actors';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'          => 'integer',
        'is_default'  => 'integer',
        'sort_order'  => 'integer',
        'status'      => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    protected $field = [
        'name', 'gender', 'style', 'sample_url', 'voice_id',
        'language', 'is_default', 'sort_order', 'status',
    ];

    public function getGenderTextAttr($value, $data): string
    {
        return ($data['gender'] ?? '') === 'male' ? '男声' : '女声';
    }
}
