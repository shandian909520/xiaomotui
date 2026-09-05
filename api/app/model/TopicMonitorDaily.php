<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class TopicMonitorDaily extends Model
{
    protected $name = 'topic_monitor_daily';

    protected $autoWriteTimestamp = 'create_time';

    protected $type = [
        'id'                      => 'integer',
        'monitor_id'              => 'integer',
        'play_count'              => 'integer',
        'post_count'              => 'integer',
        'cumulative_play_count'   => 'integer',
        'cumulative_post_count'   => 'integer',
    ];

    protected $updateTime = false;
}
