<?php
declare(strict_types=1);

namespace app\controller;

use app\model\NfcDevice;
use app\model\Merchant;
use app\model\DeviceTrigger;
use app\service\NfcService;
use think\Request;
use think\response\Json;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Db;

/**
 * 设备管理控制器
 * 商家后台设备管理功能
 */
class DeviceManage extends BaseController
{
    /**
     * NFC服务实例
     * @var NfcService
     */
    protected NfcService $nfcService;

    /**
     * 中间件配置
     * @var array
     */
    protected array $middleware = [
        'app\middleware\JwtAuth' => ['except' => []],
    ];

    /**
     * 构造函数
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->nfcService = app(NfcService::class);
    }

    /**
     * 获取设备列表
     * GET /api/merchant/device/list
     *
     * @param Request $request
     * @return Json
     */
    public function index(Request $request): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $page = (int)$request->param('page', 1);
            $limit = (int)$request->param('limit', 20);
            $status = $request->param('status');
            $keyword = $request->param('keyword', '');
            $type = $request->param('type');
            $triggerMode = $request->param('trigger_mode');

            // 构建查询
            $query = NfcDevice::query();
            
            if ($merchantId > 0) {
                $query->where('merchant_id', $merchantId);
            }

            // 状态筛选
            if ($status !== null && $status !== '') {
                $query->where('status', $status);
            }

            // 类型筛选
            if ($type) {
                $query->where('type', $type);
            }

            // 触发模式筛选
            if ($triggerMode) {
                $query->where('trigger_mode', $triggerMode);
            }

            // 关键字搜索
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('device_name', "%{$keyword}%")
                      ->whereOr('device_code', 'like', "%{$keyword}%")
                      ->whereOr('location', 'like', "%{$keyword}%");
                });
            }

            // 排序
            $orderBy = $request->param('order_by', 'create_time');
            $orderDir = $request->param('order_dir', 'desc');
            $query->order($orderBy, $orderDir);

            // 分页查询
            $total = $query->count();
            $list = $query->page($page, $limit)->select();

            // 添加额外信息
            $list = $list->map(function ($device) {
                $data = $device->toArray();
                $data['status_text'] = $device->status_text;
                $data['type_text'] = $device->type_text;
                $data['trigger_mode_text'] = $device->trigger_mode_text;
                $data['is_online'] = $device->isOnline();
                $data['battery_status'] = $device->battery_status;
                return $data;
            });

            return $this->paginate($list->toArray(), $total, $page, $limit, '获取设备列表成功');

        } catch (\Exception $e) {
            Log::error('获取设备列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取设备列表失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取设备详情
     * GET /api/merchant/device/:id
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function read(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->with(['template', 'merchant'])
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $data = $device->toArray();
            $data['status_text'] = $device->status_text;
            $data['type_text'] = $device->type_text;
            $data['trigger_mode_text'] = $device->trigger_mode_text;
            $data['is_online'] = $device->isOnline();
            $data['battery_status'] = $device->battery_status;
            $data['is_low_battery'] = $device->isLowBattery();

            return $this->success($data, '获取设备详情成功');

        } catch (\Exception $e) {
            Log::error('获取设备详情失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('获取设备详情失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 创建设备
     * POST /api/merchant/device/create
     *
     * @param Request $request
     * @return Json
     */
    public function create(Request $request): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $data = $request->param();

            // 验证数据
            $this->validate($data, [
                'device_code|设备编码' => 'require|max:32',
                'device_name|设备名称' => 'require|max:100',
                'type|设备类型' => 'require|in:TABLE,WALL,COUNTER,ENTRANCE',
                'trigger_mode|触发模式' => 'require|in:VIDEO,COUPON,WIFI,CONTACT,MENU,GROUP_BUY,PROMO',
                'location|设备位置' => 'max:100',
                'template_id|模板ID' => 'integer',
                'redirect_url|跳转链接' => 'url|max:255',
                'wifi_ssid|WiFi名称' => 'max:50',
                'wifi_password|WiFi密码' => 'max:50',
            ]);

            // 检查设备编码是否已存在
            $exists = NfcDevice::where('device_code', $data['device_code'])->find();
            if ($exists) {
                return $this->error('设备编码已存在', 400);
            }

            // 添加商家ID
            $data['merchant_id'] = $merchantId;

            // 设置默认状态
            if (!isset($data['status'])) {
                $data['status'] = NfcDevice::STATUS_OFFLINE;
            }

            // 创建设备
            $device = NfcDevice::create($data);

            Log::info('创建设备成功', [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'merchant_id' => $merchantId
            ]);

            return $this->success($device, '创建设备成功', 201);

        } catch (ValidateException $e) {
            return $this->error($e->getError(), 400);
        } catch (\Exception $e) {
            Log::error('创建设备失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('创建设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新设备
     * PUT /api/merchant/device/:id/update
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function update(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $data = $request->param();

            // 验证数据
            if (isset($data['device_name'])) {
                $this->validate($data, ['device_name|设备名称' => 'max:100']);
            }
            if (isset($data['type'])) {
                $this->validate($data, ['type|设备类型' => 'in:TABLE,WALL,COUNTER,ENTRANCE']);
            }
            if (isset($data['trigger_mode'])) {
                $this->validate($data, ['trigger_mode|触发模式' => 'in:VIDEO,COUPON,WIFI,CONTACT,MENU,GROUP_BUY,PROMO']);
            }

            // 不允许修改的字段
            unset($data['device_code'], $data['merchant_id'], $data['id'], $data['create_time']);

            // 更新设备
            $device->save($data);

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($device->device_code);

            Log::info('更新设备成功', [
                'device_id' => $id,
                'merchant_id' => $merchantId,
                'updated_fields' => array_keys($data)
            ]);

            return $this->success($device, '更新设备成功');

        } catch (ValidateException $e) {
            return $this->error($e->getError(), 400);
        } catch (\Exception $e) {
            Log::error('更新设备失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('更新设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除设备
     * DELETE /api/merchant/device/:id/delete
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function delete(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            // 检查是否可以删除（如果有关联数据，可能需要额外检查）
            $deviceCode = $device->device_code;

            // 删除设备
            $device->delete();

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($deviceCode);

            Log::info('删除设备成功', [
                'device_id' => $id,
                'device_code' => $deviceCode,
                'merchant_id' => $merchantId
            ]);

            return $this->success(null, '删除设备成功');

        } catch (\Exception $e) {
            Log::error('删除设备失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('删除设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 绑定设备
     * POST /api/merchant/device/:id/bind
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function bind(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            // 检查设备是否存在且未被其他商家绑定
            $device = NfcDevice::where('id', $id)->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            if ($device->merchant_id && $device->merchant_id != $merchantId) {
                return $this->error('设备已被其他商家绑定', 400);
            }

            if ($device->merchant_id == $merchantId) {
                return $this->error('设备已绑定到当前商家', 400);
            }

            // 绑定设备
            $device->merchant_id = $merchantId;
            $device->save();

            Log::info('绑定设备成功', [
                'device_id' => $id,
                'merchant_id' => $merchantId
            ]);

            return $this->success($device, '绑定设备成功');

        } catch (\Exception $e) {
            Log::error('绑定设备失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('绑定设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 解绑设备
     * POST /api/merchant/device/:id/unbind
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function unbind(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            // 解绑设备
            $device->merchant_id = 0;
            $device->status = NfcDevice::STATUS_OFFLINE;
            $device->save();

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($device->device_code);

            Log::info('解绑设备成功', [
                'device_id' => $id,
                'merchant_id' => $merchantId
            ]);

            return $this->success(null, '解绑设备成功');

        } catch (\Exception $e) {
            Log::error('解绑设备失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('解绑设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新设备状态
     * PUT /api/merchant/device/:id/status
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function updateStatus(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $status = (int)$request->param('status');

            // 验证状态值
            if (!in_array($status, [NfcDevice::STATUS_OFFLINE, NfcDevice::STATUS_ONLINE, NfcDevice::STATUS_MAINTENANCE])) {
                return $this->error('无效的状态值', 400);
            }

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $oldStatus = $device->status;
            $device->setDeviceStatus($status);

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($device->device_code);

            Log::info('更新设备状态成功', [
                'device_id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $status
            ]);

            return $this->success([
                'id' => $device->id,
                'status' => $device->status,
                'status_text' => $device->status_text
            ], '更新设备状态成功');

        } catch (\Exception $e) {
            Log::error('更新设备状态失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('更新设备状态失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新设备配置
     * PUT /api/merchant/device/:id/config
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function updateConfig(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $data = $request->param();

            // 允许更新的配置字段
            $allowedFields = [
                'template_id', 'redirect_url', 'wifi_ssid',
                'wifi_password', 'trigger_mode', 'group_buy_config',
                'promo_video_id', 'promo_copywriting', 'promo_tags', 'promo_reward_coupon_id'
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (empty($updateData)) {
                return $this->error('没有需要更新的配置', 400);
            }

            // 更新配置
            $device->save($updateData);

            // 清除设备配置缓存
            $this->nfcService->clearConfigCache($device->device_code);

            Log::info('更新设备配置成功', [
                'device_id' => $id,
                'updated_fields' => array_keys($updateData)
            ]);

            return $this->success($device, '更新设备配置成功');

        } catch (\Exception $e) {
            Log::error('更新设备配置失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('更新设备配置失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取设备状态
     * GET /api/merchant/device/:id/status
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function getStatus(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $statusData = [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->device_name,
                'status' => $device->status,
                'status_text' => $device->status_text,
                'is_online' => $device->isOnline(),
                'battery_level' => $device->battery_level,
                'battery_status' => $device->battery_status,
                'is_low_battery' => $device->isLowBattery(),
                'last_heartbeat' => $device->last_heartbeat,
                'update_time' => $device->update_time,
            ];

            return $this->success($statusData, '获取设备状态成功');

        } catch (\Exception $e) {
            Log::error('获取设备状态失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('获取设备状态失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取设备统计数据
     * GET /api/merchant/device/:id/statistics
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function statistics(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $startDate = $request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->param('end_date', date('Y-m-d'));

            // 触发统计
            $triggerStats = DeviceTrigger::where('device_id', $id)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field([
                    'COUNT(*) as total_count',
                    'SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count',
                    'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count',
                    'AVG(response_time) as avg_response_time',
                    'MAX(response_time) as max_response_time',
                    'MIN(response_time) as min_response_time'
                ])
                ->find();

            // 按触发模式统计
            $modeStats = DeviceTrigger::where('device_id', $id)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', 'success')
                ->field('trigger_mode, COUNT(*) as count')
                ->group('trigger_mode')
                ->select();

            // 按日期统计
            $dailyStats = DeviceTrigger::where('device_id', $id)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field([
                    'DATE(trigger_time) as date',
                    'COUNT(*) as total',
                    'SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success',
                    'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'
                ])
                ->group('DATE(trigger_time)')
                ->order('date', 'asc')
                ->select();

            $stats = [
                'device_info' => [
                    'id' => $device->id,
                    'device_code' => $device->device_code,
                    'device_name' => $device->device_name,
                ],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_triggers' => (int)($triggerStats['total_count'] ?? 0),
                    'success_count' => (int)($triggerStats['success_count'] ?? 0),
                    'failed_count' => (int)($triggerStats['failed_count'] ?? 0),
                    'success_rate' => $triggerStats['total_count'] > 0
                        ? round(($triggerStats['success_count'] / $triggerStats['total_count']) * 100, 2)
                        : 0,
                    'avg_response_time' => round((float)($triggerStats['avg_response_time'] ?? 0), 2),
                    'max_response_time' => (int)($triggerStats['max_response_time'] ?? 0),
                    'min_response_time' => (int)($triggerStats['min_response_time'] ?? 0),
                ],
                'by_mode' => $modeStats->toArray(),
                'daily_stats' => $dailyStats->toArray(),
            ];

            return $this->success($stats, '获取设备统计数据成功');

        } catch (\Exception $e) {
            Log::error('获取设备统计数据失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('获取设备统计数据失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取设备触发历史
     * GET /api/merchant/device/:id/triggers
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function getTriggerHistory(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            // 验证设备所有权
            if (!$this->verifyDeviceOwnership($id)) {
                return $this->error('设备不存在或无权访问', 403);
            }

            $page = (int)$request->param('page', 1);
            $limit = (int)$request->param('limit', 20);
            $status = $request->param('status');
            $triggerMode = $request->param('trigger_mode');

            $query = DeviceTrigger::where('device_id', $id);

            if ($status) {
                $query->where('status', $status);
            }

            if ($triggerMode) {
                $query->where('trigger_mode', $triggerMode);
            }

            $total = $query->count();
            $list = $query->order('trigger_time', 'desc')
                ->page($page, $limit)
                ->select();

            return $this->paginate($list->toArray(), $total, $page, $limit, '获取触发历史成功');

        } catch (\Exception $e) {
            Log::error('获取触发历史失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('获取触发历史失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 设备健康检查
     * GET /api/merchant/device/:id/health
     *
     * @param Request $request
     * @param int $id
     * @return Json
     */
    public function checkHealth(Request $request, int $id): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();

            $device = NfcDevice::where('id', $id)
                ->where('merchant_id', $merchantId)
                ->find();

            if (!$device) {
                return $this->error('设备不存在', 404);
            }

            $healthIssues = [];
            $healthScore = 100;

            // 检查在线状态
            if (!$device->isOnline()) {
                $healthIssues[] = '设备离线';
                $healthScore -= 30;
            }

            // 检查电池电量
            if ($device->isLowBattery()) {
                $healthIssues[] = '电池电量过低';
                $healthScore -= 20;
            }

            // 检查心跳时间
            if ($device->last_heartbeat) {
                $lastHeartbeat = strtotime($device->last_heartbeat);
                $timeSinceHeartbeat = time() - $lastHeartbeat;

                if ($timeSinceHeartbeat > 3600) { // 超过1小时
                    $healthIssues[] = '长时间无心跳';
                    $healthScore -= 15;
                }
            }

            // 检查最近触发失败率
            $recentTriggers = DeviceTrigger::where('device_id', $id)
                ->where('trigger_time', '>', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->field([
                    'COUNT(*) as total',
                    'SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'
                ])
                ->find();

            if ($recentTriggers['total'] > 0) {
                $failRate = ($recentTriggers['failed'] / $recentTriggers['total']) * 100;
                if ($failRate > 10) {
                    $healthIssues[] = '触发失败率过高';
                    $healthScore -= 25;
                }
            }

            $healthStatus = $healthScore >= 80 ? 'healthy' : ($healthScore >= 60 ? 'warning' : 'critical');

            $healthData = [
                'device_id' => $device->id,
                'device_code' => $device->device_code,
                'device_name' => $device->device_name,
                'health_status' => $healthStatus,
                'health_score' => max(0, $healthScore),
                'issues' => $healthIssues,
                'checks' => [
                    'is_online' => $device->isOnline(),
                    'battery_level' => $device->battery_level,
                    'is_low_battery' => $device->isLowBattery(),
                    'last_heartbeat' => $device->last_heartbeat,
                    'recent_fail_rate' => isset($recentTriggers['total']) && $recentTriggers['total'] > 0
                        ? round(($recentTriggers['failed'] / $recentTriggers['total']) * 100, 2)
                        : 0,
                ],
                'check_time' => date('Y-m-d H:i:s'),
            ];

            return $this->success($healthData, '设备健康检查完成');

        } catch (\Exception $e) {
            Log::error('设备健康检查失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->error('设备健康检查失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量更新设备
     * POST /api/merchant/device/batch/update
     *
     * @param Request $request
     * @return Json
     */
    public function batchUpdate(Request $request): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $deviceIds = $request->param('device_ids', []);
            $updateData = $request->param('data', []);

            if (empty($deviceIds) || !is_array($deviceIds)) {
                return $this->error('请选择要更新的设备', 400);
            }

            if (empty($updateData)) {
                return $this->error('请提供更新数据', 400);
            }

            // 只允许更新特定字段
            $allowedFields = ['status', 'template_id', 'trigger_mode', 'location'];
            $updateData = array_intersect_key($updateData, array_flip($allowedFields));

            if (empty($updateData)) {
                return $this->error('没有可更新的字段', 400);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            Db::startTrans();
            try {
                foreach ($deviceIds as $deviceId) {
                    $device = NfcDevice::where('id', $deviceId)
                        ->where('merchant_id', $merchantId)
                        ->find();

                    if ($device) {
                        $device->save($updateData);
                        $this->nfcService->clearConfigCache($device->device_code);
                        $results['success'][] = $deviceId;
                    } else {
                        $results['failed'][] = [
                            'device_id' => $deviceId,
                            'reason' => '设备不存在或无权访问'
                        ];
                    }
                }

                Db::commit();

                Log::info('批量更新设备成功', [
                    'merchant_id' => $merchantId,
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed'])
                ]);

                return $this->batchResponse($results, '批量更新完成');

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('批量更新设备失败', [
                'error' => $e->getMessage()
            ]);
            return $this->error('批量更新设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量删除设备
     * POST /api/merchant/device/batch/delete
     *
     * @param Request $request
     * @return Json
     */
    public function batchDelete(Request $request): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $deviceIds = $request->param('device_ids', []);

            if (empty($deviceIds) || !is_array($deviceIds)) {
                return $this->error('请选择要删除的设备', 400);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            Db::startTrans();
            try {
                foreach ($deviceIds as $deviceId) {
                    $device = NfcDevice::where('id', $deviceId)
                        ->where('merchant_id', $merchantId)
                        ->find();

                    if ($device) {
                        $deviceCode = $device->device_code;
                        $device->delete();
                        $this->nfcService->clearConfigCache($deviceCode);
                        $results['success'][] = $deviceId;
                    } else {
                        $results['failed'][] = [
                            'device_id' => $deviceId,
                            'reason' => '设备不存在或无权访问'
                        ];
                    }
                }

                Db::commit();

                Log::info('批量删除设备成功', [
                    'merchant_id' => $merchantId,
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed'])
                ]);

                return $this->batchResponse($results, '批量删除完成');

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('批量删除设备失败', [
                'error' => $e->getMessage()
            ]);
            return $this->error('批量删除设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量启用设备
     * POST /api/merchant/device/batch/enable
     *
     * @param Request $request
     * @return Json
     */
    public function batchEnable(Request $request): Json
    {
        return $this->batchUpdateStatus($request, NfcDevice::STATUS_ONLINE, '批量启用');
    }

    /**
     * 批量禁用设备
     * POST /api/merchant/device/batch/disable
     *
     * @param Request $request
     * @return Json
     */
    public function batchDisable(Request $request): Json
    {
        return $this->batchUpdateStatus($request, NfcDevice::STATUS_OFFLINE, '批量禁用');
    }

    /**
     * 批量更新设备状态（内部方法）
     *
     * @param Request $request
     * @param int $status
     * @param string $action
     * @return Json
     */
    protected function batchUpdateStatus(Request $request, int $status, string $action): Json
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $deviceIds = $request->param('device_ids', []);

            if (empty($deviceIds) || !is_array($deviceIds)) {
                return $this->error('请选择要操作的设备', 400);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            Db::startTrans();
            try {
                foreach ($deviceIds as $deviceId) {
                    $device = NfcDevice::where('id', $deviceId)
                        ->where('merchant_id', $merchantId)
                        ->find();

                    if ($device) {
                        $device->setDeviceStatus($status);
                        $this->nfcService->clearConfigCache($device->device_code);
                        $results['success'][] = $deviceId;
                    } else {
                        $results['failed'][] = [
                            'device_id' => $deviceId,
                            'reason' => '设备不存在或无权访问'
                        ];
                    }
                }

                Db::commit();

                Log::info($action . '设备成功', [
                    'merchant_id' => $merchantId,
                    'status' => $status,
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed'])
                ]);

                return $this->batchResponse($results, $action . '完成');

            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error($action . '设备失败', [
                'error' => $e->getMessage()
            ]);
            return $this->error($action . '设备失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取当前用户的商家ID
     *
     * @return int
     * @throws \Exception
     */
    protected function getUserMerchantId(): int
    {
        // 如果是管理员，允许返回0 (表示系统级访问)
        if (($this->request->user_role ?? '') === 'admin') {
             // 如果参数指定了 merchant_id，则返回指定的（用于管理员查看特定商家的设备）
             $paramMerchantId = $this->request->param('merchant_id');
             if ($paramMerchantId) {
                 return (int)$paramMerchantId;
             }
             return 0; 
        }

        $userId = $this->request->getUserId();

        // 优先从JWT中获取merchant_id
        $merchantId = $this->request->getMerchantId();

        if ($merchantId > 0) {
            return $merchantId;
        }

        // 如果JWT中没有，从数据库查询
        $merchant = Merchant::where('user_id', $userId)->find();

        if (!$merchant) {
            throw new \Exception('商家信息不存在');
        }

        return $merchant->id;
    }

    /**
     * 验证设备所有权
     *
     * @param int $deviceId
     * @return bool
     */
    protected function verifyDeviceOwnership(int $deviceId): bool
    {
        try {
            $merchantId = $this->getUserMerchantId();
            $device = NfcDevice::where('id', $deviceId)
                ->where('merchant_id', $merchantId)
                ->find();

            return $device !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}
