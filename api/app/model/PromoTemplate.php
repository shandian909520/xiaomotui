<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 视频模板模型
 * @property int $id 模板ID
 * @property int $merchant_id 商家ID
 * @property string $name 模板名称
 * @property string|null $description 模板描述
 * @property array|null $material_ids 素材ID列表
 * @property array|null $config 合成配置
 * @property int $status 状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoTemplate extends Model
{
    protected $table = 'xmt_promo_templates';

    protected $pk = 'id';

    // 状态常量
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    // 转场效果类型
    public const TRANSITION_NONE = 'none';
    public const TRANSITION_FADE = 'fade';
    public const TRANSITION_SLIDE = 'slide';
    public const TRANSITION_ZOOM = 'zoom';
    public const TRANSITION_WIPE = 'wipe';

    // 默认合成配置
    public const DEFAULT_CONFIG = [
        'duration_per_image' => 3,      // 每张图片时长(秒)
        'transition_type' => 'fade',    // 转场类型
        'transition_duration' => 0.5,   // 转场时长(秒)
        'resolution' => '1080p',        // 分辨率
        'fps' => 30,                    // 帧率
        'music_id' => null,             // 背景音乐ID
        'music_volume' => 0.5,          // 音乐音量(0-1)
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'material_ids' => 'json',
        'config' => 'json',
        'status' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // JSON字段
    protected $json = ['material_ids', 'config'];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'name', 'description', 'material_ids', 'config', 'status'
    ];

    /**
     * 状态文本映射
     */
    private static array $statusText = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED => '正常',
    ];

    /**
     * 转场效果文本映射
     */
    private static array $transitionText = [
        self::TRANSITION_NONE => '无转场',
        self::TRANSITION_FADE => '淡入淡出',
        self::TRANSITION_SLIDE => '滑动',
        self::TRANSITION_ZOOM => '缩放',
        self::TRANSITION_WIPE => '擦除',
    ];

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    /**
     * 配置获取器 - 确保返回完整配置
     */
    public function getConfigAttr($value, $data): array
    {
        $config = $value ?: [];
        return array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联变体
     */
    public function variants()
    {
        return $this->hasMany(PromoVariant::class, 'template_id');
    }

    /**
     * 关联素材
     */
    public function materials()
    {
        return $this->belongsToMany(
            PromoMaterial::class,
            'xmt_promo_template_materials',
            'material_id',
            'template_id'
        );
    }

    /**
     * 查询作用域：按商家ID
     */
    public function scopeByMerchantId($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
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
     * 启用模板
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用模板
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 获取素材列表
     */
    public function getMaterials(): array
    {
        $materialIds = $this->material_ids ?: [];
        if (empty($materialIds)) {
            return [];
        }

        return PromoMaterial::whereIn('id', $materialIds)
            ->where('status', PromoMaterial::STATUS_ENABLED)
            ->orderRaw('FIELD(id, ' . implode(',', $materialIds) . ')')
            ->select()
            ->toArray();
    }

    /**
     * 获取可用变体数量
     */
    public function getAvailableVariantCount(): int
    {
        return PromoVariant::where('template_id', $this->id)
            ->where('status', PromoVariant::STATUS_ENABLED)
            ->count();
    }

    /**
     * 获取所有转场效果选项
     */
    public static function getTransitionOptions(): array
    {
        return self::$transitionText;
    }

    /**
     * 获取所有状态选项
     */
    public static function getStatusOptions(): array
    {
        return self::$statusText;
    }

    /**
     * 获取分辨率选项
     */
    public static function getResolutionOptions(): array
    {
        return [
            '720p' => '1280x720',
            '1080p' => '1920x1080',
            '2k' => '2560x1440',
            '4k' => '3840x2160',
        ];
    }

    /**
     * 根据商家ID获取模板列表
     */
    public static function getByMerchantId(int $merchantId, int $status = self::STATUS_ENABLED): array
    {
        $query = static::where('merchant_id', $merchantId);

        if ($status >= 0) {
            $query->where('status', $status);
        }

        return $query->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 验证配置
     */
    public static function validateConfig(array $config): bool
    {
        // 验证每张图片时长
        if (isset($config['duration_per_image'])) {
            $duration = (float)$config['duration_per_image'];
            if ($duration < 1 || $duration > 30) {
                return false;
            }
        }

        // 验证转场时长
        if (isset($config['transition_duration'])) {
            $transDuration = (float)$config['transition_duration'];
            if ($transDuration < 0 || $transDuration > 3) {
                return false;
            }
        }

        // 验证帧率
        if (isset($config['fps'])) {
            $fps = (int)$config['fps'];
            if (!in_array($fps, [24, 25, 30, 60])) {
                return false;
            }
        }

        // 验证音乐音量
        if (isset($config['music_volume'])) {
            $volume = (float)$config['music_volume'];
            if ($volume < 0 || $volume > 1) {
                return false;
            }
        }

        return true;
    }
}
