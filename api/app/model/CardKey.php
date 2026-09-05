<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 卡密模型
 * @property int $id
 * @property string $card_key 卡密编码
 * @property string $type 类型 store_quota/clip_power/storage/redpacket/version_upgrade
 * @property string $benefit_payload 权益负载JSON
 * @property int $status 0未使用 1已使用 2已过期
 * @property int $merchant_id 使用商家ID
 * @property string $used_at 使用时间
 * @property string $expire_at 过期时间
 * @property int $created_by 创建人
 * @property string $create_time
 * @property string $update_time
 */
class CardKey extends Model
{
    protected $table = 'xmt_card_keys';

    protected $pk = 'id';

    protected $schema = [
        'id'             => 'int',
        'card_key'       => 'string',
        'type'           => 'string',
        'benefit_payload'=> 'json',
        'status'         => 'int',
        'merchant_id'    => 'int',
        'used_at'        => 'datetime',
        'expire_at'      => 'datetime',
        'created_by'     => 'int',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id'             => 'integer',
        'benefit_payload'=> 'json',
        'status'         => 'integer',
        'merchant_id'    => 'integer',
        'used_at'        => 'datetime',
        'expire_at'      => 'datetime',
        'created_by'     => 'integer',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    const STATUS_UNUSED = 0;
    const STATUS_USED = 1;
    const STATUS_EXPIRED = 2;

    const TYPE_STORE_QUOTA = 'store_quota';
    const TYPE_CLIP_POWER = 'clip_power';
    const TYPE_STORAGE = 'storage';
    const TYPE_REDPACKET = 'redpacket';
    const TYPE_VERSION_UPGRADE = 'version_upgrade';

    protected static array $statusText = [
        self::STATUS_UNUSED  => '未使用',
        self::STATUS_USED    => '已使用',
        self::STATUS_EXPIRED => '已过期',
    ];

    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }
        return implode('-', $segments);
    }

    public static function findByKey(string $cardKey): ?self
    {
        return static::where('card_key', $cardKey)->find();
    }
}
