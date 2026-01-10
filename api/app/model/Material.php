<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 素材模型
 * @property int $id 素材ID
 * @property string $type 素材类型
 * @property string $name 素材名称
 * @property int $category_id 分类ID
 * @property string $file_url 文件URL
 * @property string $thumbnail_url 缩略图URL
 * @property int $file_size 文件大小
 * @property int $duration 时长
 * @property array $metadata 元数据
 * @property array $tags 标签
 * @property int $usage_count 使用次数
 * @property int $weight 推荐权重
 * @property int $status 状态
 * @property int $audit_status 审核状态
 * @property string $audit_message 审核信息
 * @property int $creator_id 创建者ID
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class Material extends Model
{
    protected $name = 'materials';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'type' => 'string',
        'name' => 'string',
        'category_id' => 'int',
        'file_url' => 'string',
        'thumbnail_url' => 'string',
        'file_size' => 'int',
        'duration' => 'int',
        'metadata' => 'json',
        'tags' => 'json',
        'usage_count' => 'int',
        'weight' => 'int',
        'status' => 'int',
        'audit_status' => 'int',
        'audit_message' => 'string',
        'creator_id' => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'category_id' => 'integer',
        'file_size' => 'integer',
        'duration' => 'integer',
        'metadata' => 'array',
        'tags' => 'array',
        'usage_count' => 'integer',
        'weight' => 'integer',
        'status' => 'integer',
        'audit_status' => 'integer',
        'creator_id' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['metadata', 'tags'];

    // 允许批量赋值的字段
    protected $field = [
        'type', 'name', 'category_id', 'file_url', 'thumbnail_url',
        'file_size', 'duration', 'metadata', 'tags', 'usage_count',
        'weight', 'status', 'audit_status', 'audit_message', 'creator_id'
    ];

    /**
     * 素材类型常量
     */
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_AUDIO = 'AUDIO';
    const TYPE_TRANSITION = 'TRANSITION';
    const TYPE_TEXT_TEMPLATE = 'TEXT_TEMPLATE';
    const TYPE_IMAGE = 'IMAGE';
    const TYPE_MUSIC = 'MUSIC';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用
    const STATUS_AUDITING = 2;  // 审核中

    /**
     * 审核状态常量
     */
    const AUDIT_PENDING = 0;    // 待审核
    const AUDIT_APPROVED = 1;   // 通过
    const AUDIT_REJECTED = 2;   // 拒绝

    /**
     * 类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_VIDEO => '视频片段',
            self::TYPE_AUDIO => '音效',
            self::TYPE_TRANSITION => '转场效果',
            self::TYPE_TEXT_TEMPLATE => '文案模板',
            self::TYPE_IMAGE => '图片素材',
            self::TYPE_MUSIC => '背景音乐'
        ];
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
            self::STATUS_AUDITING => '审核中'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 审核状态获取器
     */
    public function getAuditStatusTextAttr($value, $data): string
    {
        $auditStatus = [
            self::AUDIT_PENDING => '待审核',
            self::AUDIT_APPROVED => '审核通过',
            self::AUDIT_REJECTED => '审核拒绝'
        ];
        return $auditStatus[$data['audit_status']] ?? '未知';
    }

    /**
     * 时长格式化获取器
     */
    public function getDurationFormatAttr($value, $data): string
    {
        if (empty($data['duration'])) {
            return '-';
        }

        $duration = $data['duration'];
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * 文件大小格式化获取器
     */
    public function getFileSizeFormatAttr($value, $data): string
    {
        if (empty($data['file_size'])) {
            return '-';
        }

        $bytes = $data['file_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 增加使用次数
     */
    public function incrementUsageCount(): bool
    {
        $this->usage_count = $this->usage_count + 1;
        return $this->save();
    }

    /**
     * 更新权重
     */
    public function updateWeight(int $weight): bool
    {
        $this->weight = $weight;
        return $this->save();
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
     * 审核通过
     */
    public function approve(string $message = ''): bool
    {
        $this->audit_status = self::AUDIT_APPROVED;
        $this->status = self::STATUS_ENABLED;
        $this->audit_message = $message ?: '审核通过';
        return $this->save();
    }

    /**
     * 审核拒绝
     */
    public function reject(string $message): bool
    {
        $this->audit_status = self::AUDIT_REJECTED;
        $this->status = self::STATUS_DISABLED;
        $this->audit_message = $message;
        return $this->save();
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查审核是否通过
     */
    public function isApproved(): bool
    {
        return $this->audit_status === self::AUDIT_APPROVED;
    }

    /**
     * 分类关联
     */
    public function category()
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    /**
     * 创建者关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * 根据类型获取素材
     */
    public static function getByType(string $type, array $filters = []): array
    {
        $query = static::where('type', $type)
                      ->where('status', self::STATUS_ENABLED)
                      ->where('audit_status', self::AUDIT_APPROVED);

        // 分类过滤
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // 标签过滤
        if (!empty($filters['tags'])) {
            // JSON查询需要特殊处理
        }

        return $query->order('weight', 'desc')
                    ->order('usage_count', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取热门素材
     */
    public static function getPopular(int $limit = 10, string $type = null): array
    {
        $query = static::where('status', self::STATUS_ENABLED)
                      ->where('audit_status', self::AUDIT_APPROVED)
                      ->where('usage_count', '>', 0);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('weight', 'desc')
                    ->limit($limit)
                    ->select()
                    ->toArray();
    }

    /**
     * 搜索素材
     */
    public static function search(string $keyword, array $filters = []): array
    {
        $query = static::where('status', self::STATUS_ENABLED)
                      ->where('audit_status', self::AUDIT_APPROVED)
                      ->whereLike('name', "%{$keyword}%");

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->order('weight', 'desc')
                    ->order('usage_count', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取素材统计
     */
    public static function getMaterialStats(array $filters = []): array
    {
        $query = static::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $total = $query->count();
        $enabled = (clone $query)->where('status', self::STATUS_ENABLED)->count();
        $auditing = (clone $query)->where('status', self::STATUS_AUDITING)->count();
        $approved = (clone $query)->where('audit_status', self::AUDIT_APPROVED)->count();

        // 按类型统计
        $typeStats = static::field('type, count(*) as count')
                          ->group('type')
                          ->select()
                          ->toArray();

        $typeCount = [];
        foreach ($typeStats as $stat) {
            $typeCount[$stat['type']] = $stat['count'];
        }

        return [
            'total' => $total,
            'enabled' => $enabled,
            'auditing' => $auditing,
            'approved' => $approved,
            'type_count' => $typeCount,
            'total_usage' => static::sum('usage_count') ?: 0,
            'total_size' => static::sum('file_size') ?: 0
        ];
    }

    /**
     * 获取所有素材类型选项
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_VIDEO => '视频片段',
            self::TYPE_AUDIO => '音效',
            self::TYPE_TRANSITION => '转场效果',
            self::TYPE_TEXT_TEMPLATE => '文案模板',
            self::TYPE_IMAGE => '图片素材',
            self::TYPE_MUSIC => '背景音乐'
        ];
    }
}