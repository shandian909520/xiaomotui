<?php
declare(strict_types=1);

namespace app\service;

use app\model\TopicMonitor;
use app\model\TopicMonitorDaily;
use think\facade\Db;
use think\facade\Log;
use think\Response;

class TopicMonitorService
{
    public function getMonitorList(int $merchantId, array $filters): array
    {
        $page  = (int)($filters['page'] ?? 1);
        $limit = (int)($filters['limit'] ?? 20);

        $query = TopicMonitor::where('merchant_id', $merchantId);

        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $query->whereLike('topic_keyword', '%' . addcslashes($filters['keyword'], '%_') . '%');
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)
            ->order('update_time', 'desc')
            ->select()
            ->toArray();

        return compact('list', 'total', 'page', 'limit');
    }

    public function addMonitor(int $merchantId, array $data): array
    {
        $platform     = $data['platform'] ?? '';
        $topicKeyword = trim($data['topic_keyword'] ?? '');

        if (!in_array($platform, [TopicMonitor::PLATFORM_DOUYIN, TopicMonitor::PLATFORM_KUAISHOU], true)) {
            throw new \InvalidArgumentException('平台参数无效');
        }
        if ($topicKeyword === '') {
            throw new \InvalidArgumentException('话题关键词不能为空');
        }

        $exists = TopicMonitor::where('merchant_id', $merchantId)
            ->where('platform', $platform)
            ->where('topic_keyword', $topicKeyword)
            ->where('status', 1)
            ->find();

        if ($exists) {
            throw new \RuntimeException('该话题已在监控中');
        }

        $monitor                      = new TopicMonitor();
        $monitor->merchant_id         = $merchantId;
        $monitor->platform            = $platform;
        $monitor->topic_keyword       = $topicKeyword;
        $monitor->topic_url           = $data['topic_url'] ?? null;
        $monitor->total_play_count    = 0;
        $monitor->total_post_count    = 0;
        $monitor->yesterday_play_count = 0;
        $monitor->yesterday_post_count = 0;
        $monitor->status              = 1;
        $monitor->save();

        return $monitor->toArray();
    }

    public function getMonitorDetail(int $id): ?array
    {
        $monitor = TopicMonitor::with(['dailySnapshots' => function ($query) {
            $query->limit(30);
        }])->find($id);

        if (!$monitor) {
            return null;
        }

        return $monitor->toArray();
    }

    public function cancelMonitor(int $id): bool
    {
        $monitor = TopicMonitor::find($id);
        if (!$monitor) {
            return false;
        }

        $monitor->status = 0;
        $monitor->save();
        return true;
    }

    public function getDailyTrend(int $monitorId, array $dateRange): array
    {
        $startDate = $dateRange['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate   = $dateRange['end_date'] ?? date('Y-m-d');

        $list = TopicMonitorDaily::where('monitor_id', $monitorId)
            ->whereBetween('date', [$startDate, $endDate])
            ->order('date', 'asc')
            ->select()
            ->toArray();

        return $list;
    }

    public function syncDailyData(): int
    {
        $monitors = TopicMonitor::where('status', 1)->select();
        $synced   = 0;

        foreach ($monitors as $monitor) {
            try {
                $yesterday = date('Y-m-d', strtotime('-1 day'));

                $daily = TopicMonitorDaily::where('monitor_id', $monitor->id)
                    ->where('date', $yesterday)
                    ->find();

                if (!$daily) {
                    $daily                      = new TopicMonitorDaily();
                    $daily->monitor_id          = $monitor->id;
                    $daily->date                = $yesterday;
                }

                // @todo 临时实现：使用模拟数据，待对接平台API后替换为真实数据采集
                // 此数据为模拟生成，不代表真实平台数据
                $newPlayCount = rand(100, 5000);
                $newPostCount = rand(10, 200);
                $isSimulated = true;

                $daily->play_count            = $newPlayCount;
                $daily->post_count            = $newPostCount;
                $daily->cumulative_play_count = $monitor->total_play_count + $newPlayCount;
                $daily->cumulative_post_count = $monitor->total_post_count + $newPostCount;
                $daily->is_simulated          = $isSimulated;
                $daily->save();

                $monitor->yesterday_play_count = $newPlayCount;
                $monitor->yesterday_post_count = $newPostCount;
                $monitor->total_play_count     = $daily->cumulative_play_count;
                $monitor->total_post_count     = $daily->cumulative_post_count;
                $monitor->last_sync_time       = date('Y-m-d H:i:s');
                $monitor->save();

                $synced++;
            } catch (\Exception $e) {
                Log::error('话题监控数据同步失败', [
                    'monitor_id' => $monitor->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    /**
     * 导出话题数据为 CSV
     *
     * @param int $merchantId 商家ID
     * @param array $filters 筛选条件
     * @return Response
     */
    public function exportTopics(int $merchantId, array $filters = []): Response
    {
        $query = TopicMonitor::where('merchant_id', $merchantId);

        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        }
        if (!empty($filters['keyword'])) {
            $query->whereLike('topic_keyword', '%' . addcslashes($filters['keyword'], '%_') . '%');
        }
        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date'] . ' 23:59:59');
        }

        // 限制最大导出数量
        $query->limit(10000);

        $filename = "topic_monitor_{$merchantId}_" . date('YmdHis') . '.csv';

        $response = new Response();
        $response->header([
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        $output = fopen('php://temp', 'r+');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            '话题名称',
            '所属平台',
            '状态',
            '总播放量',
            '总发布数',
            '昨日播放量',
            '昨日发布数',
            '数据来源',
            '话题链接',
            '最近同步时间',
            '监控开始时间',
            '最近更新时间',
        ]);

        $query->chunk(200, function ($monitors) use ($output) {
            foreach ($monitors as $monitor) {
                $platformText = match ($monitor->platform) {
                    TopicMonitor::PLATFORM_DOUYIN => '抖音',
                    TopicMonitor::PLATFORM_KUAISHOU => '快手',
                    default => $monitor->platform,
                };
                $statusText = match ((int)$monitor->status) {
                    1 => '监控中',
                    0 => '已取消',
                    default => '未知',
                };

                fputcsv($output, [
                    $monitor->topic_keyword,
                    $platformText,
                    $statusText,
                    $monitor->total_play_count,
                    $monitor->total_post_count,
                    $monitor->yesterday_play_count,
                    $monitor->yesterday_post_count,
                    !empty($monitor->getData('is_simulated')) ? '模拟数据（待对接平台API）' : '平台数据',
                    $monitor->topic_url ?: '',
                    $monitor->last_sync_time ?: '',
                    $monitor->create_time,
                    $monitor->update_time,
                ]);
            }
        });

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $response->content($content);

        return $response;
    }
}
