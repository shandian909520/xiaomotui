<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 碰一碰任务包配置模型
 * @property int $id
 * @property int $merchant_id 商家ID
 * @property int|null $device_id 绑定设备ID
 * @property string $bundle_name 任务包名称
 * @property string $title 落地页标题
 * @property string|null $subtitle 副标题
 * @property string|null $cover 封面图
 * @property string $completion_rule 完成规则 ALL/ANY/COUNT
 * @property int|null $completion_count COUNT规则所需数
 * @property string $reward_type 奖励类型
 * @property array|null $reward_config 奖励配置
 * @property array|null $lander_config 落地页配置
 * @property int $expire_hours 有效时长
 * @property int $status 状态
 * @property string $create_time
 * @property string $update_time
 */
class TaskBundle extends Model
{
    public const RULE_ALL = 'ALL';
    public const RULE_ANY = 'ANY';
    public const RULE_COUNT = 'COUNT';

    public const REWARD_REDPACKET = 'redpacket';
    public const REWARD_COUPON = 'coupon';
    public const REWARD_POINTS = 'points';
    public const REWARD_NONE = 'none';

    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    protected $table = 'xmt_task_bundles';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'              => 'integer',
        'merchant_id'     => 'integer',
        'device_id'       => 'integer',
        'completion_count'=> 'integer',
        'expire_hours'    => 'integer',
        'status'          => 'integer',
    ];

    protected $json = ['reward_config', 'lander_config'];
    protected $jsonAssoc = true;

    protected $field = [
        'merchant_id', 'device_id', 'bundle_name', 'title', 'subtitle', 'cover',
        'completion_rule', 'completion_count', 'reward_type', 'reward_config',
        'lander_config', 'expire_hours', 'status',
    ];

    /**
     * 查找设备启用的任务包（设备专属优先，其次商家默认包）
     */
    public static function findActiveForDevice(int $deviceId, int $merchantId): ?self
    {
        $bundle = self::where('device_id', $deviceId)
            ->where('status', self::STATUS_ENABLED)
            ->order('id', 'desc')
            ->find();
        if ($bundle) {
            return $bundle;
        }
        return self::where('merchant_id', $merchantId)
            ->whereNull('device_id')
            ->where('status', self::STATUS_ENABLED)
            ->order('id', 'desc')
            ->find();
    }

    /**
     * 获取任务包内动作列表（按排序）
     */
    public function getActions(): array
    {
        return TaskAction::where('bundle_id', $this->id)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }
}
