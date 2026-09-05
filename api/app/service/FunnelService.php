<?php
declare (strict_types = 1);

namespace app\service;

use app\model\FunnelEvent;
use think\facade\Log;

/**
 * 漏斗埋点服务(Agent E)
 *
 * 三类操作:
 *   1) record():    异步埋点写入,失败只 warn 不抛错
 *   2) funnel():    按设备 + 时间窗口聚合漏斗各 step 计数
 *   3) dailyStat(): 按设备 + 日期范围返回每日每 step 计数
 *
 * 设计要点:
 *   - 写入失败不影响主流程(H5 拉起 / 任务派发等)
 *   - meta 字段统一 json_encode,空时存 NULL
 *   - funnel 输出按 FUNNEL_ORDER_SIMPLE 顺序返回,保证前端表格对齐
 */
class FunnelService
{
    /**
     * 异步埋点写入
     *
     * @param int|null $deviceId
     * @param string   $userHash  md5(ip+ua) 形式,可匿名
     * @param string   $step
     * @param string   $block
     * @param string   $action
     * @param array    $meta
     * @return bool
     */
    public function record(?int $deviceId, string $userHash, string $step, string $block = '', string $action = '', array $meta = []): bool
    {
        try {
            if ($step === '') {
                return false;
            }
            if ($userHash === '') {
                $userHash = 'anon_' . substr(md5(uniqid('', true)), 0, 16);
            }

            // merchant_id 推断
            $merchantId = 0;
            if ($deviceId && $deviceId > 0) {
                try {
                    $row = \think\facade\Db::name('nfc_devices')
                        ->where('id', $deviceId)
                        ->value('merchant_id');
                    $merchantId = (int)$row;
                } catch (\Throwable $e) {
                    $merchantId = 0;
                }
            }

            $payload = [
                'device_id'   => $deviceId ? (int)$deviceId : null,
                'merchant_id' => $merchantId > 0 ? $merchantId : null,
                'user_hash'   => substr($userHash, 0, 32),
                'step'        => substr($step, 0, 32),
                'block'       => substr($block, 0, 32),
                'action'      => substr($action, 0, 32),
                'meta'        => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            // 直接 insert,失败仅记录日志(埋点不应阻塞主流程)
            FunnelEvent::create($payload);
            return true;
        } catch (\Throwable $e) {
            Log::warning('funnel record failed', [
                'device_id' => $deviceId,
                'step'      => $step,
                'block'     => $block,
                'action'    => $action,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 漏斗聚合(给商家后台 dashboard / 设备详情页用)
     *
     * @param int         $deviceId
     * @param string|null $dateFrom Y-m-d
     * @param string|null $dateTo   Y-m-d
     * @return array [
     *     'steps' => [
     *         ['step'=>'h5_enter', 'count'=>123, 'rate'=>1.0],
     *         ['step'=>'task_start', 'count'=>80, 'rate'=>0.65],
     *         ...
     *     ],
     *     'top_count' => 123,
     *     'device_id' => 1,
     *     'date_from' => '2026-09-01',
     *     'date_to'   => '2026-09-04',
     * ]
     */
    public function funnel(int $deviceId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $counts = FunnelEvent::aggregateByStep($deviceId, $dateFrom, $dateTo);

        // 顶部 = 进入数(漏斗顶端)
        $topCount = (int)($counts[FunnelEvent::STEP_H5_ENTER] ?? 0);
        if ($topCount <= 0) {
            // 兼容老数据 / 缺埋点的情况: 退一步用最大计数
            $topCount = !empty($counts) ? max($counts) : 0;
        }

        $steps = [];
        foreach (FunnelEvent::FUNNEL_ORDER_SIMPLE as $st) {
            $cnt = (int)($counts[$st] ?? 0);
            $rate = $topCount > 0 ? round($cnt / $topCount, 4) : 0.0;
            $steps[] = [
                'step'  => $st,
                'count' => $cnt,
                'rate'  => $rate,
            ];
        }
        return [
            'steps'     => $steps,
            'top_count' => $topCount,
            'device_id' => $deviceId,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];
    }

    /**
     * 每日统计
     *
     * @param int $deviceId
     * @param int $days
     * @return array
     */
    public function dailyStat(int $deviceId, int $days = 7): array
    {
        return FunnelEvent::dailyStat($deviceId, $days);
    }

    /**
     * 商家级漏斗(汇总商家名下所有设备)
     * 给商家后台 dashboard 卡片: NFC 触发 / H5 落地 / 任务完成 / 加粉转化
     *
     * @param int         $merchantId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array { nfc_trigger, h5_enter, task_complete, add_wechat, ... }
     */
    public function merchantFunnel(int $merchantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = \think\facade\Db::name('funnel_event')->where('merchant_id', $merchantId);
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        $rows = $query->field('step, COUNT(*) AS cnt')
            ->group('step')
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['step']] = (int)$r['cnt'];
        }
        return $out;
    }
}