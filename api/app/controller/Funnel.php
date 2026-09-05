<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\FunnelService;
use think\facade\Log;

/**
 * 漏斗埋点控制器(Agent E)
 *
 * 顾客端(无需鉴权):
 *   POST /api/funnel/record     设备触发/页面打开/任务完成时调用
 *
 * 商家/平台后台(需鉴权):
 *   GET  /api/admin/funnel/funnel?device_id=X&date_from=...&date_to=...
 *   GET  /api/admin/funnel/daily?device_id=X&days=7
 *   GET  /api/admin/funnel/merchant?merchant_id=X&date_from=...&date_to=...
 */
class Funnel extends BaseController
{
    protected FunnelService $funnelService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->funnelService = new FunnelService();
    }

    /**
     * POST /api/funnel/record
     * 公开接口,H5 / uni-app 直接打点
     *  body: { device_id, user_hash, step, block, action, meta }
     */
    public function record()
    {
        try {
            $payload = $this->request->post();

            $deviceId = isset($payload['device_id']) ? (int)$payload['device_id'] : 0;
            $userHash = (string)($payload['user_hash'] ?? '');
            $step     = (string)($payload['step'] ?? '');
            $block    = (string)($payload['block'] ?? '');
            $action   = (string)($payload['action'] ?? '');
            $meta     = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

            // 兜底 user_hash: 用 IP + UA 哈希(匿名端)
            if ($userHash === '') {
                try {
                    $ip = request()->ip() ?: '0.0.0.0';
                    $ua = request()->header('user-agent') ?: '';
                    $userHash = md5($ip . '|' . $ua);
                } catch (\Throwable $e) {
                    $userHash = md5((string)time());
                }
            }

            if ($step === '') {
                return $this->validationError(['step' => 'step 不能为空']);
            }

            $ok = $this->funnelService->record(
                $deviceId > 0 ? $deviceId : null,
                $userHash,
                $step,
                $block,
                $action,
                $meta
            );

            return $this->success(['recorded' => $ok], $ok ? '埋点成功' : '埋点已忽略');
        } catch (\Throwable $e) {
            // 埋点永远不应阻塞主流程,失败 200 + recorded=false
            Log::warning('funnel record 接口异常', ['error' => $e->getMessage()]);
            return $this->success(['recorded' => false], '埋点失败已忽略');
        }
    }

    /**
     * GET /api/admin/funnel/funnel
     * 鉴权,商家后台用
     */
    public function funnel()
    {
        try {
            $deviceId  = (int)$this->request->param('device_id', 0);
            $dateFrom  = (string)$this->request->param('date_from', '');
            $dateTo    = (string)$this->request->param('date_to', '');

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }

            // 商家归属校验(防止越权)
            if (!$this->checkDeviceAccess($deviceId)) {
                return $this->error('无权访问该设备', 403, 'device_access_denied');
            }

            $from = $this->normalizeDate($dateFrom, '-7 days');
            $to   = $this->normalizeDate($dateTo, 'today');

            $data = $this->funnelService->funnel($deviceId, $from, $to);
            return $this->success($data, '获取漏斗数据成功');
        } catch (\Throwable $e) {
            Log::error('funnel 聚合失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'funnel_failed');
        }
    }

    /**
     * GET /api/admin/funnel/daily
     */
    public function dailyStat()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $days     = (int)$this->request->param('days', 7);

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if (!$this->checkDeviceAccess($deviceId)) {
                return $this->error('无权访问该设备', 403, 'device_access_denied');
            }

            $data = $this->funnelService->dailyStat($deviceId, max(1, min(90, $days)));
            return $this->success([
                'device_id' => $deviceId,
                'days'      => $days,
                'list'      => $data,
            ], '获取每日漏斗数据成功');
        } catch (\Throwable $e) {
            Log::error('funnel daily 失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'funnel_daily_failed');
        }
    }

    /**
     * GET /api/admin/funnel/merchant?merchant_id=X
     * dashboard 用,商家级总览
     */
    public function merchantFunnel()
    {
        try {
            $merchantId = (int)$this->request->param('merchant_id', 0);
            if ($merchantId <= 0) {
                // 默认取 resolveMerchantId()
                $merchantId = (int)($this->resolveMerchantId() ?? 0);
            }
            if ($merchantId <= 0) {
                return $this->validationError(['merchant_id' => '商家ID不能为空']);
            }

            $from = $this->normalizeDate((string)$this->request->param('date_from', ''), '-7 days');
            $to   = $this->normalizeDate((string)$this->request->param('date_to', ''), 'today');

            $counts = $this->funnelService->merchantFunnel($merchantId, $from, $to);

            // 组装 4 卡片标准结构(NFC触发 / H5落地 / 任务完成 / 加粉转化)
            $cards = [
                [
                    'key'   => 'nfc_trigger',
                    'title' => 'NFC 触发数',
                    'count' => (int)($counts['nfc_trigger'] ?? 0),
                ],
                [
                    'key'   => 'h5_enter',
                    'title' => 'H5 落地',
                    'count' => (int)($counts['h5_enter'] ?? 0),
                ],
                [
                    'key'   => 'task_complete',
                    'title' => '任务完成',
                    'count' => (int)($counts['task_complete'] ?? 0),
                ],
                [
                    'key'   => 'add_wechat',
                    'title' => '加粉转化',
                    'count' => (int)($counts['add_wechat'] ?? 0),
                ],
            ];

            return $this->success([
                'merchant_id' => $merchantId,
                'date_from'   => $from,
                'date_to'     => $to,
                'cards'       => $cards,
                'raw'         => $counts,
            ], '获取商家漏斗成功');
        } catch (\Throwable $e) {
            Log::error('funnel merchant 失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'funnel_merchant_failed');
        }
    }

    /**
     * 商家/平台管理员共用鉴权: 商家看自己的设备,平台 admin 看全部
     */
    protected function checkDeviceAccess(int $deviceId): bool
    {
        try {
            $userRole  = $this->request->user_role ?? '';
            $merchantId = (int)($this->request->merchant_id ?? 0);
            if ($userRole === 'admin' || $merchantId === 0) {
                return true; // 平台 admin / 超管
            }
            $row = \think\facade\Db::name('nfc_devices')
                ->where('id', $deviceId)
                ->value('merchant_id');
            return (int)$row === $merchantId;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function resolveMerchantId(): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        if (!$merchantId) {
            $userRole = $this->request->user_role ?? '';
            $userId   = $this->request->user_id ?? null;
            if ($userRole === 'admin' || $userId === 0) {
                $merchantId = (int)env('admin.default_merchant_id', 1);
            }
        }
        return $merchantId ? (int)$merchantId : null;
    }

    protected function normalizeDate(string $date, string $defaultRelative): string
    {
        $date = trim($date);
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        return date('Y-m-d', strtotime($defaultRelative));
    }
}