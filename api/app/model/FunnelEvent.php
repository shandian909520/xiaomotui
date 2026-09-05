<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 漏斗埋点事件模型(Agent E)
 * 对应表: xmt_funnel_event
 *
 * @property int    $id
 * @property int    $device_id
 * @property int    $merchant_id
 * @property string $user_hash
 * @property string $step
 * @property string $block
 * @property string $action
 * @property array  $meta
 * @property string $created_at
 */
class FunnelEvent extends Model
{
    protected $table = 'xmt_funnel_event';

    protected $pk = 'id';

    protected $schema = [
        'id'          => 'int',
        'device_id'   => 'int',
        'merchant_id' => 'int',
        'user_hash'   => 'string',
        'step'        => 'string',
        'block'       => 'string',
        'action'      => 'string',
        'meta'        => 'json',
        'created_at'  => 'datetime',
    ];

    protected $type = [
        'id'          => 'integer',
        'device_id'   => 'integer',
        'merchant_id' => 'integer',
        'meta'        => 'json',
        'created_at'  => 'datetime',
    ];

    protected $autoWriteTimestamp = false;

    /**
     * 漏斗标准 step 枚举(防止乱填,调用方可选校验)
     */
    public const STEP_NFC_TRIGGER    = 'nfc_trigger';
    public const STEP_H5_ENTER       = 'h5_enter';
    public const STEP_HUB_VIEW       = 'hub_view';
    public const STEP_TASK_START     = 'task_start';
    public const STEP_TASK_COMPLETE  = 'task_complete';
    public const STEP_REWARD_CLAIM   = 'reward_claim';
    public const STEP_WIFI_CONNECT   = 'wifi_connect';
    public const STEP_CONTACT_COPY   = 'contact_copy';
    public const STEP_ADD_WECHAT     = 'add_wechat';
    public const STEP_ADD_QQ         = 'add_qq';
    public const STEP_ADD_WEWORK     = 'add_wework';
    public const STEP_REVIEW_POST    = 'review_post';
    public const STEP_LOTTERY_DRAW   = 'lottery_draw';
    public const STEP_COUPON_CLAIM   = 'coupon_claim';

    /**
     * 标准漏斗顺序(聚合 funnel 时按此顺序输出)
     * h5_enter → task_start → task_complete → reward_claim → add_wechat(转化底部)
     */
    public const FUNNEL_ORDER = [
        self::STEP_H5_ENTER,
        self::STEP_HUB_VIEW,
        self::STEP_TASK_START,
        self::STEP_TASK_COMPLETE,
        self::STEP_REWARD_CLAIM,
        self::STEP_ADD_WECHAT,
        self::STEP_ADD_QQ,
        self::STEP_ADD_WEWORK,
    ];

    /**
     * 商家漏斗视角: 仅算 5 个核心阶段
     */
    public const FUNNEL_ORDER_SIMPLE = [
        self::STEP_H5_ENTER,
        self::STEP_TASK_START,
        self::STEP_TASK_COMPLETE,
        self::STEP_REWARD_CLAIM,
        self::STEP_ADD_WECHAT,
    ];

    /**
     * 按设备 + 时间窗口汇总每天每 step 的事件数
     *
     * @param int      $deviceId
     * @param string|null $dateFrom Y-m-d
     * @param string|null $dateTo   Y-m-d
     * @return array
     */
    public static function aggregateByStep(int $deviceId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = static::where('device_id', $deviceId);
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

        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['step']] = (int)$r['cnt'];
        }
        return $map;
    }

    /**
     * 按日期 + 设备聚合每日事件数(给 dailyStat 用)
     *
     * @param int    $deviceId
     * @param int    $days     回溯天数
     * @return array [{date:'YYYY-MM-DD', total:N, by_step:{step:cnt}}, ...]
     */
    public static function dailyStat(int $deviceId, int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to   = date('Y-m-d');

        $rows = static::where('device_id', $deviceId)
            ->where('created_at', '>=', $from . ' 00:00:00')
            ->where('created_at', '<=', $to . ' 23:59:59')
            ->field('DATE(created_at) AS d, step, COUNT(*) AS cnt')
            ->group('d, step')
            ->select()
            ->toArray();

        // 初始化 7 天空骨架
        $skeleton = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days'));
            $skeleton[$date] = [
                'date'    => $date,
                'total'   => 0,
                'by_step' => [],
            ];
        }

        foreach ($rows as $r) {
            $d  = (string)$r['d'];
            $st = (string)$r['step'];
            $cn = (int)$r['cnt'];
            if (!isset($skeleton[$d])) {
                continue;
            }
            $skeleton[$d]['by_step'][$st] = $cn;
            $skeleton[$d]['total'] += $cn;
        }
        return array_values($skeleton);
    }
}