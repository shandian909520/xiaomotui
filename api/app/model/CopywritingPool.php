<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 设备文案池模型
 * @property int $id
 * @property int $device_id
 * @property int $merchant_id
 * @property string $scene
 * @property string $content
 * @property int $weight
 * @property int $status
 * @property int $sort
 * @property int $used_count
 * @property string $last_used_time
 * @property string $create_time
 * @property string $update_time
 */
class CopywritingPool extends Model
{
    protected $table = 'xmt_copywriting_pool';

    protected $pk = 'id';

    protected $schema = [
        'id'              => 'int',
        'device_id'       => 'int',
        'merchant_id'     => 'int',
        'scene'           => 'string',
        'content'         => 'string',
        'weight'          => 'int',
        'status'          => 'int',
        'sort'            => 'int',
        'used_count'      => 'int',
        'last_used_time'  => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    protected $type = [
        'id'             => 'integer',
        'device_id'      => 'integer',
        'merchant_id'    => 'integer',
        'weight'         => 'integer',
        'status'         => 'integer',
        'sort'           => 'integer',
        'used_count'     => 'integer',
        'last_used_time' => 'datetime',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $autoWriteTimestamp = true;

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    public const SCENE_PUBLISH  = 'publish';
    public const SCENE_REVIEW   = 'review';
    public const SCENE_GROUPBUY = 'groupbuy';

    /**
     * 查询某设备某场景的启用文案（按权重 + 排序）
     */
    public static function getActiveByDevice(int $deviceId, string $scene = self::SCENE_PUBLISH): \think\Collection
    {
        return static::where('device_id', $deviceId)
            ->where('scene', $scene)
            ->where('status', self::STATUS_ENABLED)
            ->order('sort', 'desc')
            ->order('weight', 'desc')
            ->order('id', 'asc')
            ->select();
    }
}
