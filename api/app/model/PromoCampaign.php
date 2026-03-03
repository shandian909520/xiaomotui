<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 推广活动模型
 * @property int $id 活动ID
 * @property int $merchant_id 商家ID
 * @property string $name 活动名称
 * @property string|null $description 活动描述
 * @property array|null $variant_ids 关联的变体ID列表
 * @property string|null $copywriting 推广文案
 * @property array|null $tags 话题标签
 * @property int|null $reward_coupon_id 奖励优惠券ID
 * @property array|null $platforms 目标平台
 * @property int $status 状态
 * @property string|null $start_time 开始时间
 * @property string|null $end_time 结束时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoCampaign extends Model
{
    protected $table = 'xmt_promo_campaigns';

    protected $pk = 'id';

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
        'variant_ids' => 'json',
        'tags' => 'json',
        'platforms' => 'json',
        'reward_coupon_id' => 'integer',
        'status' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'name', 'description', 'variant_ids', 'copywriting',
        'tags', 'reward_coupon_id', 'platforms', 'status', 'start_time', 'end_time'
    ];

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return $data['status'] === self::STATUS_ENABLED ? '启用' : '禁用';
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联活动设备
     */
    public function campaignDevices()
    {
        return $this->hasMany(PromoCampaignDevice::class, 'campaign_id');
    }

    /**
     * 关联设备（多对多）
     */
    public function devices()
    {
        return $this->belongsToMany(
            NfcDevice::class,
            PromoCampaignDevice::class,
            'device_id',
            'campaign_id'
        );
    }

    /**
     * 关联分发记录
     */
    public function distributions()
    {
        return $this->hasMany(PromoDistribution::class, 'campaign_id');
    }

    /**
     * 关联奖励优惠券
     */
    public function rewardCoupon()
    {
        return $this->belongsTo(Coupon::class, 'reward_coupon_id');
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
     * 查询作用域：进行中的活动
     */
    public function scopeActive($query)
    {
        $now = date('Y-m-d H:i:s');
        return $query->where('status', self::STATUS_ENABLED)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_time')
                    ->whereOr('start_time', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')
                    ->whereOr('end_time', '>=', $now);
            });
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查是否在进行中
     */
    public function isActive(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $now = time();

        if ($this->start_time) {
            $startTime = strtotime($this->start_time);
            if ($now < $startTime) {
                return false;
            }
        }

        if ($this->end_time) {
            $endTime = strtotime($this->end_time);
            if ($now > $endTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * 启用活动
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用活动
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 获取绑定的设备列表
     */
    public function getDevices(): array
    {
        return $this->devices()->select()->toArray();
    }

    /**
     * 获取变体列表
     */
    public function getVariants(): array
    {
        $variantIds = $this->variant_ids ?? [];
        if (empty($variantIds)) {
            return [];
        }

        return PromoVariant::where('id', 'in', $variantIds)
            ->where('status', PromoVariant::STATUS_ENABLED)
            ->select()
            ->toArray();
    }

    /**
     * 获取可用的变体（轮询算法）
     */
    public function getNextVariant(): ?PromoVariant
    {
        $variantIds = $this->variant_ids ?? [];
        if (empty($variantIds)) {
            return null;
        }

        // 获取使用次数最少的变体
        $variant = PromoVariant::where('id', 'in', $variantIds)
            ->where('status', PromoVariant::STATUS_ENABLED)
            ->order('use_count', 'asc')
            ->find();

        return $variant;
    }

    /**
     * 根据商家ID获取活动列表
     */
    public static function getByMerchantId(int $merchantId, int $status = -1): array
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
     * 获取设备的当前活动
     */
    public static function getDeviceCampaign(int $deviceId): ?self
    {
        $campaignDevice = PromoCampaignDevice::where('device_id', $deviceId)->find();

        if (!$campaignDevice) {
            return null;
        }

        $campaign = static::find($campaignDevice->campaign_id);

        if (!$campaign || !$campaign->isActive()) {
            return null;
        }

        return $campaign;
    }

    /**
     * 统计活动数量
     */
    public static function getCount(int $merchantId, int $status = -1): int
    {
        $query = static::where('merchant_id', $merchantId);

        if ($status >= 0) {
            $query->where('status', $status);
        }

        return $query->count();
    }
}
