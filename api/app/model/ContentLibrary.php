<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 内容库模型
 * @property int $id
 * @property int $merchant_id 商家ID
 * @property string $library_type 库类型
 * @property string $name 库名称
 * @property int $max_use_count 最多使用次数
 * @property int $total_count 总数量
 * @property int $used_count 已使用次数
 * @property int $remaining_count 剩余次数
 * @property string|null $warning_email 预警邮箱
 * @property int $status 状态
 * @property string $create_time
 * @property string $update_time
 */
class ContentLibrary extends Model
{
    protected $table = 'xmt_content_libraries';
    protected $pk = 'id';

    public const TYPE_VIDEO = 'video';
    public const TYPE_GRAPHIC = 'graphic';
    public const TYPE_IMAGE = 'image';
    public const TYPE_TEXT = 'text';
    public const TYPE_TOPIC = 'topic';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'max_use_count' => 'integer',
        'total_count' => 'integer',
        'used_count' => 'integer',
        'remaining_count' => 'integer',
        'status' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ContentLibraryItem::class, 'library_id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function scopeByMerchantId($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('library_type', $type);
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_VIDEO => '视频库',
            self::TYPE_GRAPHIC => '图文库',
            self::TYPE_IMAGE => '图片库',
            self::TYPE_TEXT => '文案库',
            self::TYPE_TOPIC => '话题库',
        ];
    }
}
