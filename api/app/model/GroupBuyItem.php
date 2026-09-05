<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 团购商品模型
 * @property int    $id
 * @property int    $merchant_id
 * @property int    $device_id
 * @property string $platform
 * @property string $title
 * @property string $image
 * @property string $price
 * @property string $original_price
 * @property int    $sales
 * @property string $jump_url
 * @property int    $sort
 * @property int    $status
 * @property string $start_time
 * @property string $end_time
 * @property string $create_time
 * @property string $update_time
 */
class GroupBuyItem extends Model
{
    protected $table = 'xmt_groupbuy_items';

    protected $pk = 'id';

    protected $schema = [
        'id'             => 'int',
        'merchant_id'    => 'int',
        'device_id'      => 'int',
        'platform'       => 'string',
        'title'          => 'string',
        'image'          => 'string',
        'price'          => 'float',
        'original_price' => 'float',
        'sales'          => 'int',
        'jump_url'       => 'string',
        'sort'           => 'int',
        'status'         => 'int',
        'start_time'     => 'datetime',
        'end_time'       => 'datetime',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $type = [
        'id'             => 'integer',
        'merchant_id'    => 'integer',
        'device_id'      => 'integer',
        'price'          => 'float',
        'original_price' => 'float',
        'sales'          => 'integer',
        'sort'           => 'integer',
        'status'         => 'integer',
        'start_time'     => 'datetime',
        'end_time'       => 'datetime',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $autoWriteTimestamp = true;

    public const STATUS_OFFLINE = 0;
    public const STATUS_ONLINE  = 1;

    /**
     * 折扣文本(如"8.5折"),用于前端展示
     */
    public function getDiscountTextAttr($value, $data): string
    {
        $price      = (float)($data['price'] ?? 0);
        $original   = (float)($data['original_price'] ?? 0);
        if ($original <= 0 || $price <= 0 || $price >= $original) {
            return '';
        }
        $rate = round(($price / $original) * 10, 1);
        return $rate . '折';
    }

    /**
     * 节省金额
     */
    public function getSaveAmountAttr($value, $data): float
    {
        $price    = (float)($data['price'] ?? 0);
        $original = (float)($data['original_price'] ?? 0);
        return max(0, $original - $price);
    }

    /**
     * 平台展示名
     */
    public function getPlatformNameAttr($value, $data): string
    {
        $map = [
            'MEITUAN' => '美团',
            'DOUYIN'  => '抖音团购',
            'ELEME'   => '饿了么',
            'CUSTOM'  => '其他',
        ];
        return $map[$data['platform'] ?? ''] ?? ($data['platform'] ?? '');
    }
}
