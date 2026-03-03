<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 推广素材模型
 * @property int $id 素材ID
 * @property int $merchant_id 商家ID
 * @property string $type 素材类型
 * @property string $name 素材名称
 * @property string $file_url 文件URL
 * @property string|null $thumbnail_url 缩略图URL
 * @property float|null $duration 时长(秒)
 * @property int|null $file_size 文件大小
 * @property int|null $width 宽度
 * @property int|null $height 高度
 * @property int $sort_order 排序
 * @property int $status 状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoMaterial extends Model
{
    protected $table = 'xmt_promo_materials';

    protected $pk = 'id';

    // 素材类型常量
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_MUSIC = 'music';

    // 状态常量
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'duration' => 'float',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'status' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'type', 'name', 'file_url', 'thumbnail_url',
        'duration', 'file_size', 'width', 'height', 'sort_order', 'status'
    ];

    /**
     * 类型文本映射
     */
    private static array $typeText = [
        self::TYPE_IMAGE => '图片',
        self::TYPE_VIDEO => '视频',
        self::TYPE_MUSIC => '音乐',
    ];

    /**
     * 类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        return self::$typeText[$data['type']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return $data['status'] === self::STATUS_ENABLED ? '正常' : '禁用';
    }

    /**
     * 文件大小格式化获取器
     */
    public function getFileSizeFormatAttr($value, $data): string
    {
        if (empty($data['file_size'])) {
            return '-';
        }

        $bytes = (int)$data['file_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 时长格式化获取器
     */
    public function getDurationFormatAttr($value, $data): string
    {
        if (empty($data['duration'])) {
            return '-';
        }

        $duration = (float)$data['duration'];
        $minutes = (int)floor($duration / 60);
        $seconds = (int)($duration % 60);

        if ($minutes > 0) {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
        return sprintf('%ds', $seconds);
    }

    /**
     * 文件URL完整路径获取器
     */
    public function getFileUrlFullAttr($value, $data): string
    {
        if (empty($data['file_url'])) {
            return '';
        }

        if (strpos($data['file_url'], 'http') === 0) {
            return $data['file_url'];
        }

        return request()->domain() . $data['file_url'];
    }

    /**
     * 缩略图URL完整路径获取器
     */
    public function getThumbnailUrlFullAttr($value, $data): string
    {
        if (empty($data['thumbnail_url'])) {
            return '';
        }

        if (strpos($data['thumbnail_url'], 'http') === 0) {
            return $data['thumbnail_url'];
        }

        return request()->domain() . $data['thumbnail_url'];
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 查询作用域：按商家ID
     */
    public function scopeByMerchantId($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * 查询作用域：按类型
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * 查询作用域：正常状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 启用素材
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用素材
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 获取所有类型选项
     */
    public static function getTypeOptions(): array
    {
        return self::$typeText;
    }

    /**
     * 获取支持的文件扩展名
     */
    public static function getAllowedExtensions(string $type): array
    {
        return match ($type) {
            self::TYPE_IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::TYPE_VIDEO => ['mp4', 'mov', 'avi'],
            self::TYPE_MUSIC => ['mp3', 'wav', 'm4a'],
            default => [],
        };
    }

    /**
     * 获取文件大小限制（字节）
     */
    public static function getMaxFileSize(string $type): int
    {
        return match ($type) {
            self::TYPE_IMAGE => 10 * 1024 * 1024,   // 10MB
            self::TYPE_VIDEO => 100 * 1024 * 1024,  // 100MB
            self::TYPE_MUSIC => 20 * 1024 * 1024,   // 20MB
            default => 10 * 1024 * 1024,
        };
    }

    /**
     * 根据商家ID获取素材列表
     */
    public static function getByMerchantId(int $merchantId, ?string $type = null, int $status = self::STATUS_ENABLED): array
    {
        $query = static::where('merchant_id', $merchantId);

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($status >= 0) {
            $query->where('status', $status);
        }

        return $query->order('sort_order', 'asc')
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 根据类型获取素材数量统计
     */
    public static function getTypeCount(int $merchantId): array
    {
        $result = static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_ENABLED)
            ->field('type, count(*) as count')
            ->group('type')
            ->select()
            ->toArray();

        $countMap = [
            self::TYPE_IMAGE => 0,
            self::TYPE_VIDEO => 0,
            self::TYPE_MUSIC => 0,
        ];

        foreach ($result as $item) {
            $countMap[$item['type']] = (int)$item['count'];
        }

        return $countMap;
    }
}
