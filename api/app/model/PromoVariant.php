<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 视频变体模型
 * @property int $id 变体ID
 * @property int $template_id 模板ID
 * @property int $merchant_id 商家ID
 * @property string $file_url 视频文件URL
 * @property int|null $file_size 文件大小
 * @property float|null $duration 时长(秒)
 * @property string|null $md5 文件MD5
 * @property array|null $params_json 去重参数
 * @property int $use_count 使用次数
 * @property int $status 状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoVariant extends Model
{
    protected $table = 'xmt_promo_variants';

    protected $pk = 'id';

    // 状态常量
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    // 轮换策略
    public const STRATEGY_ROUND_ROBIN = 'round_robin';  // 轮询
    public const STRATEGY_RANDOM = 'random';            // 随机
    public const STRATEGY_LEAST_USED = 'least_used';    // 最少使用

    // 默认去重参数范围
    public const DEFAULT_DEDUP_PARAMS = [
        'brightness' => ['min' => -0.05, 'max' => 0.05],     // 亮度调整范围
        'contrast' => ['min' => 0.95, 'max' => 1.05],        // 对比度调整范围
        'saturation' => ['min' => 0.95, 'max' => 1.05],      // 饱和度调整范围
        'noise_level' => ['min' => 0, 'max' => 2],           // 噪声级别
        'speed_variation' => ['min' => 0.98, 'max' => 1.02], // 速度变化范围
        'mirror_chance' => 0.1,                               // 镜像概率
        'crop_range' => ['min' => 0, 'max' => 0.02],         // 裁剪范围
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'template_id' => 'integer',
        'merchant_id' => 'integer',
        'file_size' => 'integer',
        'duration' => 'float',
        'params_json' => 'json',
        'use_count' => 'integer',
        'status' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // JSON字段
    protected $json = ['params_json'];

    // 允许批量赋值的字段
    protected $field = [
        'template_id', 'merchant_id', 'file_url', 'file_size',
        'duration', 'md5', 'params_json', 'use_count', 'status'
    ];

    /**
     * 状态文本映射
     */
    private static array $statusText = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED => '可用',
    ];

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
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
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(PromoTemplate::class, 'template_id');
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 查询作用域：按模板ID
     */
    public function scopeByTemplateId($query, int $templateId)
    {
        return $query->where('template_id', $templateId);
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
     * 启用变体
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用变体
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 记录使用
     */
    public function recordUse(): bool
    {
        $this->use_count++;
        return $this->save();
    }

    /**
     * 检查MD5是否已存在
     */
    public static function isMd5Exists(string $md5): bool
    {
        return static::where('md5', $md5)->count() > 0;
    }

    /**
     * 获取下一个可用变体（轮询策略）
     */
    public static function getNextRoundRobin(int $templateId): ?self
    {
        // 获取上次使用的变体ID（可从缓存中获取）
        $cacheKey = "promo_variant_last_used_{$templateId}";
        $lastId = cache($cacheKey, 0);

        // 查询下一个可用变体
        $variant = static::where('template_id', $templateId)
            ->where('status', self::STATUS_ENABLED)
            ->where('id', '>', $lastId)
            ->order('id', 'asc')
            ->find();

        // 如果没有找到，从头开始
        if (!$variant) {
            $variant = static::where('template_id', $templateId)
                ->where('status', self::STATUS_ENABLED)
                ->order('id', 'asc')
                ->find();
        }

        // 缓存当前ID
        if ($variant) {
            cache($cacheKey, $variant->id, 86400);
        }

        return $variant;
    }

    /**
     * 获取下一个可用变体（随机策略）
     */
    public static function getNextRandom(int $templateId): ?self
    {
        return static::where('template_id', $templateId)
            ->where('status', self::STATUS_ENABLED)
            ->orderRaw('RAND()')
            ->find();
    }

    /**
     * 获取下一个可用变体（最少使用策略）
     */
    public static function getNextLeastUsed(int $templateId): ?self
    {
        return static::where('template_id', $templateId)
            ->where('status', self::STATUS_ENABLED)
            ->order('use_count', 'asc')
            ->order('id', 'asc')
            ->find();
    }

    /**
     * 根据策略获取下一个可用变体
     */
    public static function getNextByStrategy(int $templateId, string $strategy = self::STRATEGY_ROUND_ROBIN): ?self
    {
        return match ($strategy) {
            self::STRATEGY_RANDOM => self::getNextRandom($templateId),
            self::STRATEGY_LEAST_USED => self::getNextLeastUsed($templateId),
            default => self::getNextRoundRobin($templateId),
        };
    }

    /**
     * 获取所有状态选项
     */
    public static function getStatusOptions(): array
    {
        return self::$statusText;
    }

    /**
     * 获取轮换策略选项
     */
    public static function getStrategyOptions(): array
    {
        return [
            self::STRATEGY_ROUND_ROBIN => '轮询',
            self::STRATEGY_RANDOM => '随机',
            self::STRATEGY_LEAST_USED => '最少使用',
        ];
    }

    /**
     * 统计模板的变体数量
     */
    public static function countByTemplate(int $templateId, int $status = -1): int
    {
        $query = static::where('template_id', $templateId);

        if ($status >= 0) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    /**
     * 批量更新状态
     */
    public static function batchUpdateStatus(array $ids, int $status): int
    {
        return static::whereIn('id', $ids)->update(['status' => $status]);
    }
}
