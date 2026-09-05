<?php
declare(strict_types=1);

namespace app\service;

use app\model\Merchant as MerchantModel;
use app\model\MerchantBenefit;
use app\model\NfcDevice;
use app\model\ClipProject;
use app\model\ContentTask;
use think\facade\Db;

class DashboardService
{
    public function getFlowSteps(int $merchantId): array
    {
        $merchant = MerchantModel::find($merchantId);
        $hasStore = $merchant !== null;

        $hasContent = ContentTask::where('merchant_id', $merchantId)->count() > 0;

        $hasVideo = ClipProject::where('merchant_id', $merchantId)
            ->where('status', 'completed')
            ->count() > 0;

        $hasProduct = ClipProject::where('merchant_id', $merchantId)
            ->where('status', 'completed')
            ->count() >= 1;

        return [
            'steps' => [
                [
                    'key'        => 'create-store',
                    'step'       => 1,
                    'title'      => '创建门店',
                    'no'         => '01',
                    'icon'       => '🏠',
                    'desc'       => '完善门店基础信息',
                    'completed'  => $hasStore,
                ],
                [
                    'key'        => 'create-content',
                    'step'       => 2,
                    'title'      => '灵感创作',
                    'no'         => '02',
                    'icon'       => '✨',
                    'desc'       => 'AI生成营销文案',
                    'completed'  => $hasContent,
                ],
                [
                    'key'        => 'create-video',
                    'step'       => 3,
                    'title'      => '视频创作',
                    'no'         => '03',
                    'icon'       => '🎬',
                    'desc'       => '剪辑生成推广视频',
                    'completed'  => $hasVideo,
                ],
                [
                    'key'        => 'publish',
                    'step'       => 4,
                    'title'      => '成品库与配置',
                    'no'         => '04',
                    'icon'       => '📦',
                    'desc'       => '管理成品内容并发布',
                    'completed'  => $hasProduct,
                ],
            ],
        ];
    }

    public function getDataStats(int $merchantId, array $filters): array
    {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate   = $filters['end_date'] ?? date('Y-m-d');
        $storeId   = $filters['store_id'] ?? 0;

        $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('m/d', strtotime($startDate . " +{$i} days"));
        }

        $colors = ['#5573dc', '#7ac66b', '#f1b332', '#e96d6d', '#a855f7'];
        $tints = ['rgba(85,115,220,0.08)', 'rgba(122,198,107,0.08)', 'rgba(241,179,50,0.08)', 'rgba(233,109,109,0.08)', 'rgba(168,85,247,0.08)'];
        $icons = ['📺', '📝', '⭐', '👤', '🔧'];

        $groupsData = [
            [
                'name'  => '短视频平台',
                'items' => [
                    ['label' => '抖音发布', 'value' => rand(10, 100)],
                    ['label' => '快手发布', 'value' => rand(5, 50)],
                    ['label' => '视频生成', 'value' => rand(20, 80)],
                ],
            ],
            [
                'name'  => '图文发布',
                'items' => [
                    ['label' => '小红书发布', 'value' => rand(5, 40)],
                    ['label' => '图文生成', 'value' => rand(15, 60)],
                ],
            ],
            [
                'name'  => '点评文案',
                'items' => [
                    ['label' => '点评评价', 'value' => rand(3, 30)],
                    ['label' => '美团评价', 'value' => rand(3, 25)],
                ],
            ],
            [
                'name'  => '关注账号',
                'items' => [
                    ['label' => '抖音关注', 'value' => rand(10, 80)],
                    ['label' => '快手关注', 'value' => rand(5, 40)],
                ],
            ],
            [
                'name'  => '工具及其他',
                'items' => [
                    ['label' => 'NFC触发', 'value' => rand(20, 120)],
                    ['label' => '优惠券核销', 'value' => rand(5, 35)],
                ],
            ],
        ];

        $metricGroups = [];
        foreach ($groupsData as $i => $g) {
            $metricGroups[] = [
                'title' => $g['name'],
                'icon'  => $icons[$i] ?? '📊',
                'color' => $colors[$i] ?? $colors[0],
                'tint'  => $tints[$i] ?? $tints[0],
                'items' => $g['items'],
            ];
        }

        $trendDates = array_slice($dates, -7);
        $paths = [];
        for ($line = 0; $line < 3; $line++) {
            $points = [];
            for ($i = 0; $i < 7; $i++) {
                $x = $i * 180;
                $y = 150 - rand(20, 130);
                $points[] = ($i === 0 ? 'M' : 'L') . "$x,$y";
            }
            $paths[] = implode(' ', $points);
        }

        $stores = MerchantModel::where('id', '>', 0)->limit(10)->select()->toArray();
        $storeList = array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'] ?? $s['store_name'] ?? '门店'], $stores);

        return [
            'metricGroups' => $metricGroups,
            'dates'        => $trendDates,
            'paths'        => $paths,
            'stores'       => $storeList,
        ];
    }

    public function getConsumptionOverview(int $merchantId): array
    {
        $benefit = MerchantBenefit::where('merchant_id', $merchantId)->find();

        $videoCount = ClipProject::where('merchant_id', $merchantId)->count();
        $storeCount = MerchantModel::where('id', $merchantId)->count();

        $totalPower = $benefit ? $benefit->clip_power : 100;
        $remainPower = $benefit ? max(0, $benefit->clip_power - $videoCount * 10) : max(0, 100 - $videoCount * 10);
        $storage = $benefit ? $benefit->storage : 1073741824;
        $storageUsed = rand(100, 500) * 1024 * 1024;
        $redpacketBalance = $benefit ? $benefit->redpacket_balance : 100;

        return [
            'items' => [
                ['label' => '视频数量', 'value' => $videoCount . ' 个', 'percent' => min(100, $videoCount * 5), 'color' => '#5573dc'],
                ['label' => '剪辑算力', 'value' => $remainPower . '/' . $totalPower, 'percent' => $totalPower > 0 ? round($remainPower / $totalPower * 100) : 0, 'color' => '#7ac66b'],
                ['label' => '门店数', 'value' => $storeCount . ' 家', 'percent' => min(100, $storeCount * 20), 'color' => '#f1b332'],
                ['label' => '存储空间', 'value' => $this->formatBytes($storageUsed) . '/' . $this->formatBytes($storage), 'percent' => $storage > 0 ? round($storageUsed / $storage * 100) : 0, 'color' => '#e96d6d'],
                ['label' => '红包余额', 'value' => '¥' . number_format($redpacketBalance, 2), 'percent' => min(100, $redpacketBalance), 'color' => '#a855f7'],
                ['label' => '版本', 'value' => $benefit ? (MerchantBenefit::getVersionTextMap()[$benefit->version_type] ?? '基础版') : '基础版'],
            ],
        ];
    }

    public function getQuickEntries(int $merchantId): array
    {
        $recentStaff = Db::table('xmt_ai_staff_roles')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->limit(4)
            ->select()
            ->toArray();

        $avatarBgs = ['#6366f1', '#ec4899', '#f59e0b', '#10b981'];
        $avatarChars = ['郑', '红', '素', '文'];

        return [
            'list' => array_map(function ($i) use ($recentStaff, $avatarBgs, $avatarChars) {
                $staff = $recentStaff[$i] ?? null;
                return [
                    'id'        => $staff ? $staff['id'] : ($i + 1),
                    'nickname'  => $staff ? $staff['nickname'] : '员工' . ($i + 1),
                    'role'      => $staff ? $staff['role_name'] : 'AI助手',
                    'avatar'    => $avatarChars[$i] ?? 'AI',
                    'avatarBg'  => $avatarBgs[$i] ?? '#6366f1',
                    'is_hot'    => $staff ? ($staff['is_hot'] ?? 0) : 0,
                ];
            }, array_keys($recentStaff ?: [0, 1, 2, 3])),
        ];
    }

    public function getMerchantQrCode(int $merchantId): array
    {
        return [
            'url' => '',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
