<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\LotteryService;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Request;

/**
 * 大转盘抽奖控制器(模块6)
 * 顾客端入口:
 *   GET  /api/lottery/device?device_code=xx -> getLotteryByDevice
 *   POST /api/lottery/draw                  -> draw
 *   GET  /api/lottery/my-records            -> myRecords
 *
 * 设计:
 *   - 抽中通过 LotteryService.drawLottery 完成,概率算法在 Service 中
 *   - 抽奖机会 = NFC 触发记录(daily_limit 控制每天最大次数)
 */
class Lottery extends BaseController
{
    protected LotteryService $lotteryService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->lotteryService = new LotteryService();
    }

    /**
     * 根据设备编码获取该设备的有效活动
     */
    public function getLotteryByDevice()
    {
        try {
            $deviceCode = (string)$this->request->param('device_code', '');
            if ($deviceCode === '') {
                return $this->validationError(['device_code' => '设备编码不能为空']);
            }
            $device = \app\model\NfcDevice::findByCode($deviceCode);
            if (!$device) {
                return $this->platformError('LOTTERY_DEVICE_NOT_FOUND', ['device_code' => $deviceCode], 404);
            }
            $activity = $this->lotteryService->getActiveByDevice((int)$device->id);
            if (!$activity) {
                return $this->success(['enabled' => false], '暂无进行中的活动');
            }

            // 加载前 6 个奖项用于前端展示转盘
            $prizes = \app\model\LotteryPrize::where('activity_id', (int)$activity->id)
                ->where('status', \app\model\LotteryPrize::STATUS_ENABLED)
                ->order('sort', 'desc')
                ->order('id', 'asc')
                ->limit(6)
                ->select()
                ->toArray();

            return $this->success([
                'enabled'      => true,
                'activity_id'  => (int)$activity->id,
                'name'         => (string)$activity->name,
                'description'  => (string)($activity->description ?? ''),
                'start_at'     => $activity->start_at,
                'end_at'       => $activity->end_at,
                'daily_limit'  => (int)$activity->daily_limit,
                'cost_points'  => (int)$activity->cost_points,
                'prizes'       => array_map(function ($p) {
                    // 对前端隐藏真实库存(只展示"充足/紧张/已抢光")
                    $stock = (int)($p['stock'] ?? 0);
                    $statusText = $stock === 0 ? '已抢光' : ($stock < 5 ? '紧张' : '充足');
                    return [
                        'id'          => (int)$p['id'],
                        'name'        => (string)$p['name'],
                        'image'       => (string)($p['image'] ?? ''),
                        'prize_type'  => (string)$p['prize_type'],
                        'stock_status'=> $statusText,
                    ];
                }, $prizes),
                'rules_tip'    => '抽奖与 NFC 触发次数挂钩,详见商家活动规则。',
            ], '获取活动成功');
        } catch (ValidateException $e) {
            return $this->validationError(['lottery' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('获取抽奖活动失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_lottery_failed');
        }
    }

    /**
     * 抽奖
     */
    public function draw()
    {
        try {
            $payload = $this->request->post();
            $activityId = (int)($payload['activity_id'] ?? 0);
            $userHash   = (string)($payload['user_hash'] ?? '');

            if ($activityId <= 0) {
                return $this->validationError(['activity_id' => '活动ID不能为空']);
            }
            if ($userHash === '') {
                $userHash = $this->buildUserHash();
            }

            // 取出活动对应的设备ID(简化:从 POST device_id 取,缺省抛错)
            $deviceId = (int)($payload['device_id'] ?? 0);
            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }

            $result = $this->lotteryService->drawLottery($activityId, $userHash, $deviceId);
            return $this->success($result, $result['is_winning'] ? '恭喜中奖!' : '谢谢参与');
        } catch (ValidateException $e) {
            return $this->validationError(['draw' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('抽奖失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'draw_lottery_failed');
        }
    }

    /**
     * 我的中奖记录
     */
    public function myRecords()
    {
        try {
            $deviceId = (int)$this->request->param('device_id', 0);
            $userHash = (string)$this->request->param('user_hash', '');
            $limit    = (int)$this->request->param('limit', 20);

            if ($deviceId <= 0) {
                return $this->validationError(['device_id' => '设备ID不能为空']);
            }
            if ($userHash === '') {
                $userHash = $this->buildUserHash();
            }
            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;

            $list = $this->lotteryService->myRecords($deviceId, $userHash, $limit);
            return $this->success(['list' => $list, 'total' => count($list)], '获取记录成功');
        } catch (\Exception $e) {
            Log::error('获取我的抽奖记录失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_records_failed');
        }
    }

    protected function buildUserHash(): string
    {
        $ip = $this->request->ip() ?: '0.0.0.0';
        $ua = $this->request->header('User-Agent') ?: '';
        return md5($ip . '|' . $ua);
    }
}
