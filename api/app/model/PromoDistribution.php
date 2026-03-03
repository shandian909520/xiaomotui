<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 推广分发记录模型
 * @property int $id 记录ID
 * @property int $campaign_id 活动ID
 * @property int $device_id 设备ID
 * @property int $variant_id 变体ID
 * @property string|null $user_openid 用户OpenID
 * @property string|null $platform 发布平台
 * @property string $status 状态
 * @property int|null $reward_coupon_user_id 发放的优惠券记录ID
 * @property string|null $client_ip 客户端IP
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoDistribution extends Model
{
    protected $table = 'xmt_promo_distributions';

    protected $pk = 'id';

    // 状态常量
    public const STATUS_PENDING = 'pending';       // 待下载
    public const STATUS_DOWNLOADED = 'downloaded'; // 已下载
    public const STATUS_PUBLISHED = 'published';   // 已发布
    public const STATUS_REWARDED = 'rewarded';     // 已发放奖励

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'campaign_id' => 'integer',
        'device_id' => 'integer',
        'variant_id' => 'integer',
        'reward_coupon_user_id' => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'campaign_id', 'device_id', 'variant_id', 'user_openid',
        'platform', 'status', 'reward_coupon_user_id', 'client_ip'
    ];

    /**
     * 状态文本映射
     */
    private static array $statusText = [
        self::STATUS_PENDING => '待下载',
        self::STATUS_DOWNLOADED => '已下载',
        self::STATUS_PUBLISHED => '已发布',
        self::STATUS_REWARDED => '已发放奖励',
    ];

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    /**
     * 关联活动
     */
    public function campaign()
    {
        return $this->belongsTo(PromoCampaign::class, 'campaign_id');
    }

    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 关联变体
     */
    public function variant()
    {
        return $this->belongsTo(PromoVariant::class, 'variant_id');
    }

    /**
     * 查询作用域：按活动ID
     */
    public function scopeByCampaignId($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * 查询作用域：按设备ID
     */
    public function scopeByDeviceId($query, int $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * 查询作用域：按用户OpenID
     */
    public function scopeByUserOpenid($query, string $openid)
    {
        return $query->where('user_openid', $openid);
    }

    /**
     * 查询作用域：按状态
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 更新状态为已下载
     */
    public function markAsDownloaded(): bool
    {
        $this->status = self::STATUS_DOWNLOADED;
        return $this->save();
    }

    /**
     * 更新状态为已发布
     */
    public function markAsPublished(string $platform): bool
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->platform = $platform;
        return $this->save();
    }

    /**
     * 更新状态为已发放奖励
     */
    public function markAsRewarded(int $couponUserId): bool
    {
        $this->status = self::STATUS_REWARDED;
        $this->reward_coupon_user_id = $couponUserId;
        return $this->save();
    }

    /**
     * 创建分发记录
     */
    public static function createDistribution(
        int $campaignId,
        int $deviceId,
        int $variantId,
        ?string $openid = null,
        ?string $clientIp = null
    ): ?self {
        $record = new static();
        $record->campaign_id = $campaignId;
        $record->device_id = $deviceId;
        $record->variant_id = $variantId;
        $record->user_openid = $openid;
        $record->client_ip = $clientIp;
        $record->status = self::STATUS_PENDING;

        if ($record->save()) {
            return $record;
        }

        return null;
    }

    /**
     * 获取活动统计
     */
    public static function getCampaignStats(int $campaignId): array
    {
        $result = static::where('campaign_id', $campaignId)
            ->field([
                'COUNT(*) as total',
                'SUM(CASE WHEN status = "' . self::STATUS_PENDING . '" THEN 1 ELSE 0 END) as pending_count',
                'SUM(CASE WHEN status = "' . self::STATUS_DOWNLOADED . '" THEN 1 ELSE 0 END) as downloaded_count',
                'SUM(CASE WHEN status = "' . self::STATUS_PUBLISHED . '" THEN 1 ELSE 0 END) as published_count',
                'SUM(CASE WHEN status = "' . self::STATUS_REWARDED . '" THEN 1 ELSE 0 END) as rewarded_count',
            ])
            ->find();

        return [
            'total' => (int)($result->total ?? 0),
            'pending' => (int)($result->pending_count ?? 0),
            'downloaded' => (int)($result->downloaded_count ?? 0),
            'published' => (int)($result->published_count ?? 0),
            'rewarded' => (int)($result->rewarded_count ?? 0),
        ];
    }

    /**
     * 获取设备统计
     */
    public static function getDeviceStats(int $deviceId): array
    {
        $result = static::where('device_id', $deviceId)
            ->field([
                'COUNT(*) as total',
                'SUM(CASE WHEN status = "' . self::STATUS_PUBLISHED . '" THEN 1 ELSE 0 END) as published_count',
                'SUM(CASE WHEN status = "' . self::STATUS_REWARDED . '" THEN 1 ELSE 0 END) as rewarded_count',
            ])
            ->find();

        return [
            'total' => (int)($result->total ?? 0),
            'published' => (int)($result->published_count ?? 0),
            'rewarded' => (int)($result->rewarded_count ?? 0),
        ];
    }

    /**
     * 获取平台分布统计
     */
    public static function getPlatformStats(int $campaignId): array
    {
        $result = static::where('campaign_id', $campaignId)
            ->where('platform', '<>', null)
            ->field('platform, COUNT(*) as count')
            ->group('platform')
            ->select()
            ->toArray();

        $stats = [];
        foreach ($result as $item) {
            $stats[$item['platform']] = (int)$item['count'];
        }

        return $stats;
    }
}
