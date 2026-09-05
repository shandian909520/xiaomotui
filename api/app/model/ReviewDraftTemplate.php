<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * AI 评价灵感草稿模板(Agent C)
 * @property int $id
 * @property int $merchant_id
 * @property string $platform
 * @property string $scene_key
 * @property string $title
 * @property string $prompt
 * @property string $style
 * @property int $weight
 * @property int $status
 * @property int $sort
 * @property string $create_time
 * @property string $update_time
 */
class ReviewDraftTemplate extends Model
{
    protected $table = 'xmt_review_draft_templates';
    protected $pk    = 'id';

    protected $schema = [
        'id'          => 'int',
        'merchant_id' => 'int',
        'platform'    => 'string',
        'scene_key'   => 'string',
        'title'       => 'string',
        'prompt'      => 'string',
        'style'       => 'string',
        'weight'      => 'int',
        'status'      => 'int',
        'sort'        => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime         = 'create_time';
    protected $updateTime         = 'update_time';

    protected $type = [
        'id'          => 'integer',
        'merchant_id' => 'integer',
        'weight'      => 'integer',
        'status'      => 'integer',
        'sort'        => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    public const PLATFORMS = [
        'DIANPING' => '大众点评',
        'MEITUAN'  => '美团',
        'GAODE'    => '高德地图',
        'BAIDU'    => '百度地图',
        'DOUYIN'   => '抖音',
    ];
}
