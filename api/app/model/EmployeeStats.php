<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class EmployeeStats extends Model
{
    protected $name = 'employee_stats';

    protected $autoWriteTimestamp = true;

    public const PERIOD_TYPES = [
        'day'       => '今天',
        'week'      => '本周',
        'month'     => '本月',
        'quarter'   => '本季度',
        'half_year' => '近半年',
        'year'      => '今年',
    ];

    public const RANK_TYPES = [
        'high_creator'   => '高产创作者',
        'high_interact'  => '高互动创作者',
        'unpublished'    => '未发布员工',
        'publish_rank'   => '发布排行榜',
    ];

    public const TASK_TYPES = [
        'douyin_publish', 'kuaishou_publish', 'xiaohongshu_publish',
        'video_account_publish', 'moments', 'xiaohongshu_graphic',
        'dianping_review', 'dianping_checkin', 'meituan_checkin',
        'gaode_checkin', 'baidu_checkin', 'douyin_checkin',
        'douyin_follow', 'kuaishou_follow', 'xiaohongshu_follow',
        'video_account_follow', 'official_account_follow', 'wifi',
        'wechat_card', 'ctrip_review', 'ctrip_scenic', 'ctrip_note',
        'edaijia', 'dianping_note',
    ];

    protected $type = [
        'id'              => 'integer',
        'merchant_id'     => 'integer',
        'employee_id'     => 'integer',
        'store_id'        => 'integer',
        'target_count'    => 'integer',
        'completed_count' => 'integer',
        'exposure_count'  => 'integer',
        'like_count'      => 'integer',
        'publish_count'   => 'integer',
        'date'            => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];
}
