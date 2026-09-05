<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

class ClipShot extends Model
{
    protected $name = 'clip_shots';

    protected $autoWriteTimestamp = true;

    protected $updateTime = false;

    protected $type = [
        'id'              => 'integer',
        'project_id'      => 'integer',
        'sort_order'      => 'integer',
        'material_id'     => 'integer',
        'duration'        => 'float',
        'voice_actor_id'  => 'integer',
        'mute_original'   => 'integer',
        'create_time'     => 'datetime',
    ];

    protected $field = [
        'project_id', 'sort_order', 'material_id', 'material_type',
        'material_url', 'thumbnail_url', 'duration', 'subtitle',
        'voice_text', 'voice_actor_id', 'transition_type', 'filter_name',
        'mute_original', 'create_time',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClipProject::class, 'project_id');
    }
}
