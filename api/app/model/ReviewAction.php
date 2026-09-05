<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 打卡点评行为埋点模型
 * @property int    $id
 * @property int    $device_id
 * @property int    $merchant_id
 * @property string $platform
 * @property string $action
 * @property string $user_hash
 * @property int    $draft_index
 * @property string $ip
 * @property string $ua
 * @property array  $extra_data
 * @property string $created_at
 */
class ReviewAction extends Model
{
    protected $table = 'xmt_review_actions';

    protected $pk = 'id';

    protected $schema = [
        'id'          => 'int',
        'device_id'   => 'int',
        'merchant_id' => 'int',
        'platform'    => 'string',
        'action'      => 'string',
        'user_hash'   => 'string',
        'draft_index' => 'int',
        'ip'          => 'string',
        'ua'          => 'string',
        'extra_data'  => 'json',
        'created_at'  => 'datetime',
    ];

    protected $type = [
        'id'          => 'integer',
        'device_id'   => 'integer',
        'merchant_id' => 'integer',
        'draft_index' => 'integer',
        'extra_data'  => 'json',
        'created_at'  => 'datetime',
    ];

    /**
     * 不使用 ThinkPHP 自动时间戳，表中只有 created_at
     */
    protected $autoWriteTimestamp = false;

    public const ACTION_VIEW       = 'view';       // 进入点评页
    public const ACTION_DRAFT_COPY = 'draft_copy'; // 复制某条草稿
    public const ACTION_DRAFT_USED = 'draft_used'; // 标记草稿有效
    public const ACTION_JUMP       = 'jump';       // 跳转点评平台
    public const ACTION_FEEDBACK   = 'feedback';   // 反馈

    public const PLATFORM_DIANPING = 'DIANPING';
    public const PLATFORM_MEITUAN  = 'MEITUAN';
    public const PLATFORM_GAODE    = 'GAODE';
    public const PLATFORM_BAIDU    = 'BAIDU';
    public const PLATFORM_DOUYIN   = 'DOUYIN';

    /**
     * 平台中文名
     */
    public static function platformName(string $platform): string
    {
        $map = [
            self::PLATFORM_DIANPING => '大众点评',
            self::PLATFORM_MEITUAN  => '美团',
            self::PLATFORM_GAODE    => '高德地图',
            self::PLATFORM_BAIDU    => '百度地图',
            self::PLATFORM_DOUYIN   => '抖音',
        ];
        return $map[$platform] ?? $platform;
    }
}
