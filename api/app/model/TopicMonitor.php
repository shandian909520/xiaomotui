<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\relation\HasMany;

class TopicMonitor extends Model
{
    public const PLATFORM_DOUYIN = 'douyin';
    public const PLATFORM_KUAISHOU = 'kuaishou';

    protected $name = 'topic_monitors';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'                    => 'integer',
        'merchant_id'           => 'integer',
        'total_play_count'      => 'integer',
        'total_post_count'      => 'integer',
        'yesterday_play_count'  => 'integer',
        'yesterday_post_count'  => 'integer',
        'status'                => 'integer',
        'create_time'           => 'datetime',
        'update_time'           => 'datetime',
    ];

    public function dailySnapshots(): HasMany
    {
        return $this->hasMany(TopicMonitorDaily::class, 'monitor_id')->order('date', 'desc');
    }

    public function getStatusTextAttr($value, $data): string
    {
        return match ($data['status'] ?? 0) {
            1 => '监控中',
            0 => '已取消',
            default => '未知',
        };
    }

    public function getPlatformTextAttr($value, $data): string
    {
        return match ($data['platform'] ?? '') {
            self::PLATFORM_DOUYIN   => '抖音',
            self::PLATFORM_KUAISHOU => '快手',
            default                 => '未知',
        };
    }
}
