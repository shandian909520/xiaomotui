<?php
declare(strict_types=1);

namespace app\service;

use app\model\PromoCampaign;
use app\model\PromoCampaignDevice;
use app\model\PromoDistribution;
use app\model\NfcDevice;
use app\model\PromoVariant;
use think\facade\Db;
use think\facade\Log;

/**
 * 推广活动服务
 */
class PromoCampaignService
{
    /**
     * 创建活动
     */
    public function create(int $merchantId, array $data): array
    {
        try {
            Db::startTrans();

            $campaign = new PromoCampaign();
            $campaign->merchant_id = $merchantId;
            $campaign->name = $data['name'];
            $campaign->description = $data['description'] ?? '';
            $campaign->variant_ids = $data['variant_ids'] ?? [];
            $campaign->copywriting = $data['copywriting'] ?? '';
            $campaign->tags = $data['tags'] ?? [];
            $campaign->reward_coupon_id = $data['reward_coupon_id'] ?? null;
            $campaign->platforms = $data['platforms'] ?? [];
            $campaign->status = $data['status'] ?? PromoCampaign::STATUS_ENABLED;
            $campaign->start_time = $data['start_time'] ?? null;
            $campaign->end_time = $data['end_time'] ?? null;

            if (!$campaign->save()) {
                Db::rollback();
                return [
                    'success' => false,
                    'message' => '创建活动失败'
                ];
            }

            // 如果有设备ID，绑定设备
            if (!empty($data['device_ids'])) {
                $bindResult = PromoCampaignDevice::bindDevices($campaign->id, $data['device_ids']);
                if (!empty($bindResult['failed'])) {
                    Log::warning('部分设备绑定失败', [
                        'campaign_id' => $campaign->id,
                        'failed' => $bindResult['failed']
                    ]);
                }
            }

            Db::commit();

            return [
                'success' => true,
                'message' => '创建活动成功',
                'campaign_id' => $campaign->id
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建活动异常', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '创建活动失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新活动
     */
    public function update(int $campaignId, ?int $merchantId, array $data, bool $isAdmin = false): array
    {
        try {
            $campaign = PromoCampaign::find($campaignId);

            if (!$campaign) {
                return [
                    'success' => false,
                    'message' => '活动不存在'
                ];
            }

            if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
                return [
                    'success' => false,
                    'message' => '无权修改此活动'
                ];
            }

            // 更新字段
            if (isset($data['name'])) {
                $campaign->name = $data['name'];
            }
            if (isset($data['description'])) {
                $campaign->description = $data['description'];
            }
            if (isset($data['variant_ids'])) {
                $campaign->variant_ids = $data['variant_ids'];
            }
            if (isset($data['copywriting'])) {
                $campaign->copywriting = $data['copywriting'];
            }
            if (isset($data['tags'])) {
                $campaign->tags = $data['tags'];
            }
            if (isset($data['reward_coupon_id'])) {
                $campaign->reward_coupon_id = $data['reward_coupon_id'];
            }
            if (isset($data['platforms'])) {
                $campaign->platforms = $data['platforms'];
            }
            if (isset($data['status'])) {
                $campaign->status = $data['status'];
            }
            if (isset($data['start_time'])) {
                $campaign->start_time = $data['start_time'];
            }
            if (isset($data['end_time'])) {
                $campaign->end_time = $data['end_time'];
            }

            if (!$campaign->save()) {
                return [
                    'success' => false,
                    'message' => '更新活动失败'
                ];
            }

            return [
                'success' => true,
                'message' => '更新活动成功'
            ];
        } catch (\Exception $e) {
            Log::error('更新活动异常', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '更新活动失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 删除活动
     */
    public function delete(int $campaignId, ?int $merchantId, bool $isAdmin = false): array
    {
        try {
            Db::startTrans();

            $campaign = PromoCampaign::find($campaignId);

            if (!$campaign) {
                Db::rollback();
                return [
                    'success' => false,
                    'message' => '活动不存在'
                ];
            }

            if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
                Db::rollback();
                return [
                    'success' => false,
                    'message' => '无权删除此活动'
                ];
            }

            // 解绑所有设备
            PromoCampaignDevice::unbindAllDevices($campaignId);

            // 删除活动
            $campaign->delete();

            Db::commit();

            return [
                'success' => true,
                'message' => '删除活动成功'
            ];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('删除活动异常', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '删除活动失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 绑定设备
     */
    public function bindDevices(int $campaignId, ?int $merchantId, array $deviceIds, bool $isAdmin = false): array
    {
        try {
            $campaign = PromoCampaign::find($campaignId);

            if (!$campaign) {
                return [
                    'success' => false,
                    'message' => '活动不存在'
                ];
            }

            if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
                return [
                    'success' => false,
                    'message' => '无权操作此活动'
                ];
            }

            // 验证设备是否属于当前商家（admin可绑定任意设备）
            if (!$isAdmin && $merchantId) {
                $validDeviceIds = NfcDevice::where('merchant_id', $merchantId)
                    ->where('id', 'in', $deviceIds)
                    ->column('id');

                $invalidIds = array_diff($deviceIds, $validDeviceIds);
                if (!empty($invalidIds)) {
                    return [
                        'success' => false,
                        'message' => '部分设备不存在或不属于当前商家',
                        'invalid_ids' => $invalidIds
                    ];
                }
            }

            $result = PromoCampaignDevice::bindDevices($campaignId, $deviceIds);

            return [
                'success' => true,
                'message' => sprintf('成功绑定 %d 个设备', count($result['bound'])),
                'bound' => $result['bound'],
                'failed' => $result['failed']
            ];
        } catch (\Exception $e) {
            Log::error('绑定设备异常', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '绑定设备失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 解绑设备
     */
    public function unbindDevice(int $campaignId, int $deviceId, ?int $merchantId, bool $isAdmin = false): array
    {
        try {
            $campaign = PromoCampaign::find($campaignId);

            if (!$campaign) {
                return [
                    'success' => false,
                    'message' => '活动不存在'
                ];
            }

            if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
                return [
                    'success' => false,
                    'message' => '无权操作此活动'
                ];
            }

            $result = PromoCampaignDevice::unbindDevice($campaignId, $deviceId);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '解绑设备成功'
                ];
            }

            return [
                'success' => false,
                'message' => '解绑设备失败，设备未绑定到此活动'
            ];
        } catch (\Exception $e) {
            Log::error('解绑设备异常', [
                'campaign_id' => $campaignId,
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '解绑设备失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取活动列表
     */
    public function getList(?int $merchantId, array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 20);
        $status = $params['status'] ?? -1;

        $query = PromoCampaign::when($merchantId, function($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        });

        if ($status >= 0) {
            $query->where('status', $status);
        }

        // 搜索
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->whereOr('description', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        // 补充设备数量
        foreach ($list as &$item) {
            $item['device_count'] = PromoCampaignDevice::where('campaign_id', $item['id'])->count();
        }

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize
        ];
    }

    /**
     * 获取活动详情
     */
    public function getDetail(int $campaignId, ?int $merchantId, bool $isAdmin = false): ?array
    {
        $campaign = PromoCampaign::find($campaignId);

        if (!$campaign) {
            return null;
        }

        if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
            return null;
        }

        $data = $campaign->toArray();

        // 获取绑定的设备
        $deviceIds = PromoCampaignDevice::getCampaignDeviceIds($campaignId);
        $data['device_ids'] = $deviceIds;
        $data['devices'] = [];

        if (!empty($deviceIds)) {
            $devices = NfcDevice::where('id', 'in', $deviceIds)
                ->field('id, device_code, device_name, location, status')
                ->select()
                ->toArray();
            $data['devices'] = $devices;
        }

        // 获取变体信息
        if (!empty($data['variant_ids'])) {
            $variants = PromoVariant::where('id', 'in', $data['variant_ids'])
                ->field('id, file_url, duration, status')
                ->select()
                ->toArray();
            $data['variants'] = $variants;
        } else {
            $data['variants'] = [];
        }

        return $data;
    }

    /**
     * 获取活动统计
     */
    public function getStats(int $campaignId, ?int $merchantId, bool $isAdmin = false): array
    {
        $campaign = PromoCampaign::find($campaignId);

        if (!$campaign) {
            return [
                'success' => false,
                'message' => '活动不存在'
            ];
        }

        if (!$isAdmin && $campaign->merchant_id !== $merchantId) {
            return [
                'success' => false,
                'message' => '活动不存在'
            ];
        }

        // 基础统计
        $distributionStats = PromoDistribution::getCampaignStats($campaignId);

        // 设备数量
        $deviceCount = PromoCampaignDevice::where('campaign_id', $campaignId)->count();

        // 变体数量
        $variantCount = count($campaign->variant_ids ?? []);

        // 平台分布
        $platformStats = PromoDistribution::getPlatformStats($campaignId);

        return [
            'success' => true,
            'data' => [
                'campaign_id' => $campaignId,
                'device_count' => $deviceCount,
                'variant_count' => $variantCount,
                'distribution' => $distributionStats,
                'platforms' => $platformStats
            ]
        ];
    }

    /**
     * 获取可选的设备列表（未被绑定的）
     */
    public function getAvailableDevices(?int $merchantId, ?int $excludeCampaignId = null, bool $isAdmin = false): array
    {
        $query = NfcDevice::when($merchantId, function($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->where('status', '<>', NfcDevice::STATUS_MAINTENANCE);

        // 排除已绑定的设备
        $boundDeviceIds = PromoCampaignDevice::where('campaign_id', '<>', $excludeCampaignId ?? 0)
            ->column('device_id');

        if (!empty($boundDeviceIds)) {
            $query->where('id', 'not in', $boundDeviceIds);
        }

        return $query->field('id, device_code, device_name, location, status')
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }
}
