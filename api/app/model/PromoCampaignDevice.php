<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 活动设备关联模型
 * @property int $id ID
 * @property int $campaign_id 活动ID
 * @property int $device_id 设备ID
 * @property string $create_time 创建时间
 */
class PromoCampaignDevice extends Model
{
    protected $table = 'xmt_promo_campaign_devices';

    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'campaign_id' => 'integer',
        'device_id' => 'integer',
        'create_time' => 'datetime',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'campaign_id', 'device_id'
    ];

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
     * 检查设备是否已被绑定
     */
    public static function isDeviceBound(int $deviceId, ?int $excludeCampaignId = null): bool
    {
        $query = static::where('device_id', $deviceId);

        if ($excludeCampaignId !== null) {
            $query->where('campaign_id', '<>', $excludeCampaignId);
        }

        return $query->count() > 0;
    }

    /**
     * 获取设备绑定的活动ID
     */
    public static function getDeviceCampaignId(int $deviceId): ?int
    {
        $record = static::where('device_id', $deviceId)->find();
        return $record ? $record->campaign_id : null;
    }

    /**
     * 获取活动的设备ID列表
     */
    public static function getCampaignDeviceIds(int $campaignId): array
    {
        return static::where('campaign_id', $campaignId)
            ->column('device_id');
    }

    /**
     * 批量绑定设备
     */
    public static function bindDevices(int $campaignId, array $deviceIds): array
    {
        $boundIds = [];
        $failedIds = [];

        foreach ($deviceIds as $deviceId) {
            // 检查是否已被其他活动绑定
            if (static::isDeviceBound($deviceId, $campaignId)) {
                $failedIds[] = [
                    'device_id' => $deviceId,
                    'reason' => '设备已被其他活动绑定'
                ];
                continue;
            }

            // 检查是否已绑定当前活动
            $exists = static::where('campaign_id', $campaignId)
                ->where('device_id', $deviceId)
                ->find();

            if ($exists) {
                $boundIds[] = $deviceId;
                continue;
            }

            // 创建绑定
            $record = new static();
            $record->campaign_id = $campaignId;
            $record->device_id = $deviceId;

            if ($record->save()) {
                $boundIds[] = $deviceId;
            } else {
                $failedIds[] = [
                    'device_id' => $deviceId,
                    'reason' => '绑定失败'
                ];
            }
        }

        return [
            'bound' => $boundIds,
            'failed' => $failedIds
        ];
    }

    /**
     * 解绑设备
     */
    public static function unbindDevice(int $campaignId, int $deviceId): bool
    {
        return static::where('campaign_id', $campaignId)
            ->where('device_id', $deviceId)
            ->delete() > 0;
    }

    /**
     * 解绑活动所有设备
     */
    public static function unbindAllDevices(int $campaignId): int
    {
        return static::where('campaign_id', $campaignId)->delete();
    }
}
