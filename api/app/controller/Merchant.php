<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\model\NfcDevice;
use app\model\ContentTask;
use app\model\ContentTemplate;
use app\model\DeviceAlert;
use app\model\PublishTask;
use app\model\DeviceTrigger;
use app\model\Coupon;
use app\model\CouponUser;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Db;

/**
 * 商家管理控制器
 * 提供商家信息管理、数据统计、设备管理等API接口
 */
class Merchant extends BaseController
{
    /**
     * 控制器初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
    }

    /**
     * 获取商家信息
     * GET /api/merchant/info
     *
     * @return \think\Response
     */
    public function info()
    {
        try {
            // 从JWT中间件获取商家ID
            $merchantId = $this->request->merchant_id ?? null;

            if (!$merchantId) {
                // 如果没有merchant_id，尝试通过user_id获取
                $userId = $this->request->user_id ?? null;
                if ($userId === null) {
                    return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
                }

                // 通过用户ID获取商家信息
                $merchant = MerchantModel::where('user_id', $userId)->find();
            } else {
                $merchant = MerchantModel::find($merchantId);
            }

            if (!$merchant) {
                return $this->error('商家不存在', 404, 'merchant_not_found');
            }

            // 返回商家信息
            $merchantInfo = [
                'id' => $merchant->id,
                'user_id' => $merchant->user_id,
                'name' => $merchant->name,
                'category' => $merchant->category,
                'address' => $merchant->address,
                'longitude' => $merchant->longitude,
                'latitude' => $merchant->latitude,
                'phone' => $merchant->phone,
                'description' => $merchant->description,
                'logo' => $merchant->logo,
                'logo_url' => $merchant->logo_url,
                'business_hours' => $merchant->business_hours,
                'business_hours_text' => $merchant->business_hours_text,
                'status' => $merchant->status,
                'status_text' => $merchant->status_text,
                'create_time' => $merchant->create_time,
                'update_time' => $merchant->update_time,
            ];

            return $this->success($merchantInfo, '获取商家信息成功');

        } catch (\Exception $e) {
            Log::error('获取商家信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_merchant_info_failed');
        }
    }

    /**
     * 创建商家
     * POST /api/merchant/create
     *
     * @return \think\Response
     */
    public function create()
    {
        try {
            $data = $this->request->post();
            $userId = $this->request->user_id ?? null;

            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 检查用户是否已有商家
            $existingMerchant = MerchantModel::where('user_id', $userId)->find();
            if ($existingMerchant) {
                return $this->error('该用户已创建商家', 400, 'merchant_already_exists');
            }

            // 数据验证
            $this->validate($data, [
                'name' => 'require|max:100',
                'category' => 'require|max:50',
                'address' => 'require|max:255',
                'longitude' => 'float|between:-180,180',
                'latitude' => 'float|between:-90,90',
                'phone' => 'max:20',
                'description' => 'max:1000',
                'logo' => 'max:255',
            ]);

            // 创建商家
            $merchant = new MerchantModel();
            $merchant->user_id = $userId;
            $merchant->name = $data['name'];
            $merchant->category = $data['category'];
            $merchant->address = $data['address'];
            $merchant->longitude = $data['longitude'] ?? null;
            $merchant->latitude = $data['latitude'] ?? null;
            $merchant->phone = $data['phone'] ?? '';
            $merchant->description = $data['description'] ?? '';
            $merchant->logo = $data['logo'] ?? '';
            $merchant->business_hours = $data['business_hours'] ?? null;
            $merchant->status = MerchantModel::STATUS_UNDER_REVIEW; // 默认审核中
            $merchant->save();

            Log::info('创建商家成功', [
                'merchant_id' => $merchant->id,
                'user_id' => $userId,
                'name' => $merchant->name
            ]);

            return $this->success([
                'merchant_id' => $merchant->id,
                'status' => $merchant->status,
                'status_text' => $merchant->status_text
            ], '创建商家成功');

        } catch (ValidateException $e) {
            return $this->validationError(['merchant' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建商家失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'create_merchant_failed');
        }
    }

    /**
     * 更新商家信息
     * PUT /api/merchant/update
     *
     * @return \think\Response
     */
    public function update()
    {
        try {
            $data = $this->request->post();
            $merchantId = $data['merchant_id'] ?? ($this->request->merchant_id ?? null);
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId !== null) {
                // 通过用户ID获取商家ID
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家ID', 400, 'merchant_id_required');
            }

            $merchant = MerchantModel::find($merchantId);
            if (!$merchant) {
                return $this->error('商家不存在', 404, 'merchant_not_found');
            }

            // 验证权限
            if ($userId && $merchant->user_id != $userId) {
                return $this->error('无权操作此商家', 403, 'merchant_access_denied');
            }

            // 数据验证
            $this->validate($data, [
                'name' => 'max:100',
                'category' => 'max:50',
                'address' => 'max:255',
                'longitude' => 'float|between:-180,180',
                'latitude' => 'float|between:-90,90',
                'phone' => 'max:20',
                'description' => 'max:1000',
                'logo' => 'max:255',
            ]);

            // 更新商家信息
            if (isset($data['name'])) $merchant->name = $data['name'];
            if (isset($data['category'])) $merchant->category = $data['category'];
            if (isset($data['address'])) $merchant->address = $data['address'];
            if (isset($data['longitude'])) $merchant->longitude = $data['longitude'];
            if (isset($data['latitude'])) $merchant->latitude = $data['latitude'];
            if (isset($data['phone'])) $merchant->phone = $data['phone'];
            if (isset($data['description'])) $merchant->description = $data['description'];
            if (isset($data['logo'])) $merchant->logo = $data['logo'];
            if (isset($data['business_hours'])) $merchant->business_hours = $data['business_hours'];

            $merchant->save();

            Log::info('更新商家信息成功', [
                'merchant_id' => $merchant->id,
                'user_id' => $userId
            ]);

            return $this->success(['merchant_id' => $merchant->id], '更新商家信息成功');

        } catch (ValidateException $e) {
            return $this->validationError(['merchant' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('更新商家信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'update_merchant_failed');
        }
    }

    /**
     * 上传商家logo
     * POST /api/merchant/uploadLogo
     *
     * @return \think\Response
     */
    public function uploadLogo()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                // 通过用户ID获取商家ID
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家ID', 400, 'merchant_id_required');
            }

            $merchant = MerchantModel::find($merchantId);
            if (!$merchant) {
                return $this->error('商家不存在', 404, 'merchant_not_found');
            }

            // 验证权限
            if ($userId && $merchant->user_id != $userId) {
                return $this->error('无权操作此商家', 403, 'merchant_access_denied');
            }

            // 获取上传文件
            $file = $this->request->file('logo');
            if (!$file) {
                return $this->error('请上传Logo文件', 400, 'logo_file_required');
            }

            // 验证文件
            $this->validate(['logo' => $file], [
                'logo' => 'require|image|fileSize:2097152|fileExt:jpg,jpeg,png,gif'
            ], [
                'logo.require' => '请上传Logo文件',
                'logo.image' => 'Logo必须是图片文件',
                'logo.fileSize' => 'Logo文件大小不能超过2MB',
                'logo.fileExt' => 'Logo文件格式仅支持jpg,jpeg,png,gif'
            ]);

            // 保存文件
            $savename = \think\facade\Filesystem::disk('public')->putFile('merchant/logo', $file);
            $logoUrl = '/storage/' . $savename;

            // 更新商家logo
            $merchant->logo = $logoUrl;
            $merchant->save();

            Log::info('上传商家Logo成功', [
                'merchant_id' => $merchant->id,
                'logo_url' => $logoUrl
            ]);

            return $this->success([
                'logo' => $logoUrl,
                'logo_url' => $merchant->logo_url
            ], '上传Logo成功');

        } catch (ValidateException $e) {
            return $this->validationError(['logo' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('上传商家Logo失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'upload_logo_failed');
        }
    }

    /**
     * 获取商家数据概览
     * GET /api/merchant/dashboard
     *
     * @return \think\Response
     */
    public function dashboard()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId !== null) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取今天日期范围
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');

            // 设备统计
            $deviceCount = NfcDevice::where('merchant_id', $merchantId)->count();
            $onlineDeviceCount = NfcDevice::where('merchant_id', $merchantId)
                ->where('status', NfcDevice::STATUS_ONLINE)
                ->count();

            // 今日触发次数
            $todayTriggerCount = DeviceTrigger::where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$todayStart, $todayEnd])
                ->count();

            // 今日内容生成量
            $todayContentCount = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$todayStart, $todayEnd])
                ->count();

            // 今日发布量
            $todayPublishCount = PublishTask::alias('pt')
                ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                ->where('ct.merchant_id', $merchantId)
                ->where('pt.publish_time', 'between', [$todayStart, $todayEnd])
                ->where('pt.status', 'in', [PublishTask::STATUS_COMPLETED, PublishTask::STATUS_PARTIAL])
                ->count();

            // 设备状态分布
            $deviceStatus = [
                'online' => $onlineDeviceCount,
                'offline' => NfcDevice::where('merchant_id', $merchantId)
                    ->where('status', NfcDevice::STATUS_OFFLINE)
                    ->count(),
                'maintenance' => NfcDevice::where('merchant_id', $merchantId)
                    ->where('status', NfcDevice::STATUS_MAINTENANCE)
                    ->count(),
            ];

            // 最近触发记录（最近10条）
            $recentTriggers = DeviceTrigger::where('merchant_id', $merchantId)
                ->order('trigger_time', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            // 最近生成内容（最近10条）
            $recentContents = ContentTask::where('merchant_id', $merchantId)
                ->order('create_time', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            // 未解决的告警
            $alerts = DeviceAlert::where('merchant_id', $merchantId)
                ->where('status', 'in', [DeviceAlert::STATUS_PENDING, DeviceAlert::STATUS_ACKNOWLEDGED])
                ->order('trigger_time', 'desc')
                ->limit(5)
                ->select()
                ->toArray();

            $dashboardData = [
                'overview' => [
                    'device_count' => $deviceCount,
                    'online_device_count' => $onlineDeviceCount,
                    'today_trigger_count' => $todayTriggerCount,
                    'today_content_count' => $todayContentCount,
                    'today_publish_count' => $todayPublishCount,
                ],
                'device_status' => $deviceStatus,
                'recent_triggers' => $recentTriggers,
                'recent_contents' => $recentContents,
                'alerts' => $alerts,
            ];

            return $this->success($dashboardData, '获取数据概览成功');

        } catch (\Exception $e) {
            Log::error('获取商家数据概览失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_dashboard_failed');
        }
    }

    /**
     * 获取商家统计数据
     * GET /api/merchant/statistics
     *
     * @return \think\Response
     */
    public function statistics()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId !== null) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取查询参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));
            $metricType = $this->request->param('metric_type', 'all'); // all, trigger, content, publish

            $statistics = [];

            // 触发次数统计
            if ($metricType == 'all' || $metricType == 'trigger') {
                $triggerStats = DeviceTrigger::where('merchant_id', $merchantId)
                    ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->field('DATE(trigger_time) as date, COUNT(*) as count')
                    ->group('date')
                    ->order('date')
                    ->select()
                    ->toArray();

                $statistics['trigger'] = $triggerStats;
            }

            // 内容生成统计
            if ($metricType == 'all' || $metricType == 'content') {
                $contentStats = ContentTask::where('merchant_id', $merchantId)
                    ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->field('DATE(create_time) as date, COUNT(*) as total,
                            SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = "FAILED" THEN 1 ELSE 0 END) as failed')
                    ->group('date')
                    ->order('date')
                    ->select()
                    ->toArray();

                $statistics['content'] = $contentStats;
            }

            // 发布统计
            if ($metricType == 'all' || $metricType == 'publish') {
                $publishStats = Db::table('publish_tasks')
                    ->alias('pt')
                    ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                    ->where('ct.merchant_id', $merchantId)
                    ->where('pt.publish_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->field('DATE(pt.publish_time) as date, COUNT(*) as total,
                            SUM(CASE WHEN pt.status = "COMPLETED" THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN pt.status = "PARTIAL" THEN 1 ELSE 0 END) as partial,
                            SUM(CASE WHEN pt.status = "FAILED" THEN 1 ELSE 0 END) as failed')
                    ->group('date')
                    ->order('date')
                    ->select()
                    ->toArray();

                $statistics['publish'] = $publishStats;
            }

            return $this->success($statistics, '获取统计数据成功');

        } catch (\Exception $e) {
            Log::error('获取商家统计数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_statistics_failed');
        }
    }

    /**
     * 获取商家设备列表
     * GET /api/merchant/devices
     *
     * @return \think\Response
     */
    public function devices()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');
            $type = $this->request->param('type', '');

            // 构建查询条件
            $where = ['merchant_id' => $merchantId];
            if ($status !== '') {
                $where['status'] = (int)$status;
            }
            if ($type !== '') {
                $where['type'] = $type;
            }

            // 查询设备列表
            $devices = NfcDevice::where($where)
                ->page($page, $limit)
                ->order('create_time', 'desc')
                ->select();

            $total = NfcDevice::where($where)->count();

            // 转换为数组格式
            $deviceList = [];
            foreach ($devices as $device) {
                $deviceList[] = [
                    'id' => $device->id,
                    'device_code' => $device->device_code,
                    'device_name' => $device->device_name,
                    'type' => $device->type,
                    'type_text' => $device->type_text,
                    'trigger_mode' => $device->trigger_mode,
                    'trigger_mode_text' => $device->trigger_mode_text,
                    'status' => $device->status,
                    'status_text' => $device->status_text,
                    'battery_level' => $device->battery_level,
                    'battery_status' => $device->battery_status,
                    'is_online' => $device->is_online,
                    'last_heartbeat' => $device->last_heartbeat,
                    'location' => $device->location,
                    'create_time' => $device->create_time,
                ];
            }

            return $this->paginate($deviceList, $total, $page, $limit, '获取设备列表成功');

        } catch (\Exception $e) {
            Log::error('获取商家设备列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_devices_failed');
        }
    }

    /**
     * 获取设备统计
     * GET /api/merchant/deviceStats
     *
     * @return \think\Response
     */
    public function deviceStats()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $total = NfcDevice::where('merchant_id', $merchantId)->count();
            $online = NfcDevice::where('merchant_id', $merchantId)
                ->where('status', NfcDevice::STATUS_ONLINE)
                ->count();
            $offline = NfcDevice::where('merchant_id', $merchantId)
                ->where('status', NfcDevice::STATUS_OFFLINE)
                ->count();
            $maintenance = NfcDevice::where('merchant_id', $merchantId)
                ->where('status', NfcDevice::STATUS_MAINTENANCE)
                ->count();

            // 按类型统计
            $typeStats = NfcDevice::where('merchant_id', $merchantId)
                ->field('type, COUNT(*) as count')
                ->group('type')
                ->select()
                ->toArray();

            // 按触发模式统计
            $triggerModeStats = NfcDevice::where('merchant_id', $merchantId)
                ->field('trigger_mode, COUNT(*) as count')
                ->group('trigger_mode')
                ->select()
                ->toArray();

            // 低电量设备数
            $lowBatteryCount = NfcDevice::where('merchant_id', $merchantId)
                ->where('battery_level', '<=', 20)
                ->where('battery_level', '>', 0)
                ->count();

            $stats = [
                'total' => $total,
                'online' => $online,
                'offline' => $offline,
                'maintenance' => $maintenance,
                'low_battery' => $lowBatteryCount,
                'online_rate' => $total > 0 ? round($online / $total * 100, 2) : 0,
                'type_stats' => $typeStats,
                'trigger_mode_stats' => $triggerModeStats,
            ];

            return $this->success($stats, '获取设备统计成功');

        } catch (\Exception $e) {
            Log::error('获取设备统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_device_stats_failed');
        }
    }

    /**
     * 获取设备告警
     * GET /api/merchant/deviceAlerts
     *
     * @return \think\Response
     */
    public function deviceAlerts()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');

            // 构建查询条件
            $where = ['merchant_id' => $merchantId];
            if ($status !== '') {
                $where['status'] = $status;
            }

            // 查询告警列表
            $alerts = DeviceAlert::where($where)
                ->page($page, $limit)
                ->order('trigger_time', 'desc')
                ->select();

            $total = DeviceAlert::where($where)->count();

            // 转换为数组格式
            $alertList = [];
            foreach ($alerts as $alert) {
                $alertList[] = [
                    'id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_code' => $alert->device_code,
                    'alert_type' => $alert->alert_type,
                    'alert_type_text' => $alert->alert_type_text,
                    'alert_level' => $alert->alert_level,
                    'alert_level_text' => $alert->alert_level_text,
                    'level_color' => $alert->level_color,
                    'alert_title' => $alert->alert_title,
                    'alert_message' => $alert->alert_message,
                    'status' => $alert->status,
                    'status_text' => $alert->status_text,
                    'trigger_time' => $alert->trigger_time,
                    'resolve_time' => $alert->resolve_time,
                    'resolve_note' => $alert->resolve_note,
                ];
            }

            return $this->paginate($alertList, $total, $page, $limit, '获取设备告警成功');

        } catch (\Exception $e) {
            Log::error('获取设备告警失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_device_alerts_failed');
        }
    }

    /**
     * 获取商家内容模板
     * GET /api/merchant/templates
     *
     * @return \think\Response
     */
    public function templates()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId !== null) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $type = $this->request->param('type', '');
            $category = $this->request->param('category', '');

            // 构建查询条件 - 包含商家自己的模板和系统公开模板
            $query = ContentTemplate::where('status', ContentTemplate::STATUS_ENABLED)
                ->where(function($query) use ($merchantId) {
                    $query->whereNull('merchant_id')
                          ->whereOr('merchant_id', $merchantId);
                });

            if ($type !== '') {
                $query->where('type', $type);
            }
            if ($category !== '') {
                $query->where('category', $category);
            }

            // 查询模板列表
            $templates = $query->page($page, $limit)
                ->order('usage_count', 'desc')
                ->order('create_time', 'desc')
                ->select();

            $total = $query->count();

            // 转换为数组格式
            $templateList = [];
            foreach ($templates as $template) {
                $templateList[] = [
                    'id' => $template->id,
                    'merchant_id' => $template->merchant_id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'type_text' => $template->type_text,
                    'category' => $template->category,
                    'style' => $template->style,
                    'content' => $template->content,
                    'preview_url' => $template->preview_url,
                    'usage_count' => $template->usage_count,
                    'is_public' => $template->is_public,
                    'is_public_text' => $template->is_public_text,
                    'template_source' => $template->template_source,
                    'create_time' => $template->create_time,
                ];
            }

            return $this->paginate($templateList, $total, $page, $limit, '获取内容模板成功');

        } catch (\Exception $e) {
            Log::error('获取商家内容模板失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_templates_failed');
        }
    }

    /**
     * 获取内容生成统计
     * GET /api/merchant/contentStats
     *
     * @return \think\Response
     */
    public function contentStats()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取日期参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));

            // 总体统计
            $total = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $completed = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->count();

            $failed = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', ContentTask::STATUS_FAILED)
                ->count();

            // 按类型统计
            $typeStats = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('type, COUNT(*) as count')
                ->group('type')
                ->select()
                ->toArray();

            // 平均生成时间
            $avgGenerationTime = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->avg('generation_time');

            $stats = [
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'processing' => ContentTask::where('merchant_id', $merchantId)
                    ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->where('status', ContentTask::STATUS_PROCESSING)
                    ->count(),
                'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
                'type_stats' => $typeStats,
                'avg_generation_time' => round($avgGenerationTime ?? 0, 2),
            ];

            return $this->success($stats, '获取内容生成统计成功');

        } catch (\Exception $e) {
            Log::error('获取内容生成统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_content_stats_failed');
        }
    }

    /**
     * 获取触发次数报表
     * GET /api/merchant/triggerReport
     *
     * @return \think\Response
     */
    public function triggerReport()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));
            $deviceId = $this->request->param('device_id', null);

            // 构建查询
            $query = DeviceTrigger::where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }

            // 按天统计
            $dailyStats = $query->field('DATE(trigger_time) as date, COUNT(*) as count')
                ->group('date')
                ->order('date')
                ->select()
                ->toArray();

            // 按设备统计
            $deviceStats = DeviceTrigger::where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('device_id, device_code, COUNT(*) as count')
                ->group('device_id')
                ->order('count', 'desc')
                ->select()
                ->toArray();

            // 总触发次数
            $totalTriggers = array_sum(array_column($dailyStats, 'count'));

            $report = [
                'total_triggers' => $totalTriggers,
                'daily_stats' => $dailyStats,
                'device_stats' => $deviceStats,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            return $this->success($report, '获取触发次数报表成功');

        } catch (\Exception $e) {
            Log::error('获取触发次数报表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_trigger_report_failed');
        }
    }

    /**
     * 获取生成成功率报表
     * GET /api/merchant/successRateReport
     *
     * @return \think\Response
     */
    public function successRateReport()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));

            // 按天统计成功率
            $dailyStats = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('DATE(create_time) as date, COUNT(*) as total,
                        SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = "FAILED" THEN 1 ELSE 0 END) as failed')
                ->group('date')
                ->order('date')
                ->select()
                ->toArray();

            // 计算每天的成功率
            foreach ($dailyStats as &$stat) {
                $stat['success_rate'] = $stat['total'] > 0
                    ? round($stat['completed'] / $stat['total'] * 100, 2)
                    : 0;
            }

            // 总体统计
            $totalTasks = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $completedTasks = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->count();

            $overallSuccessRate = $totalTasks > 0
                ? round($completedTasks / $totalTasks * 100, 2)
                : 0;

            $report = [
                'overall_success_rate' => $overallSuccessRate,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'daily_stats' => $dailyStats,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            return $this->success($report, '获取生成成功率报表成功');

        } catch (\Exception $e) {
            Log::error('获取生成成功率报表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_success_rate_report_failed');
        }
    }

    /**
     * 获取分发效果报表
     * GET /api/merchant/publishReport
     *
     * @return \think\Response
     */
    public function publishReport()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));
            $platform = $this->request->param('platform', '');

            // 按天统计发布数据
            $dailyStats = Db::table('publish_tasks')
                ->alias('pt')
                ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                ->where('ct.merchant_id', $merchantId)
                ->where('pt.publish_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->field('DATE(pt.publish_time) as date, COUNT(*) as total,
                        SUM(CASE WHEN pt.status = "COMPLETED" THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN pt.status = "PARTIAL" THEN 1 ELSE 0 END) as partial,
                        SUM(CASE WHEN pt.status = "FAILED" THEN 1 ELSE 0 END) as failed')
                ->group('date')
                ->order('date')
                ->select()
                ->toArray();

            // 计算每天的成功率
            foreach ($dailyStats as &$stat) {
                $successCount = $stat['completed'] + $stat['partial'];
                $stat['success_rate'] = $stat['total'] > 0
                    ? round($successCount / $stat['total'] * 100, 2)
                    : 0;
            }

            // 总体统计
            $totalPublish = Db::table('publish_tasks')
                ->alias('pt')
                ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                ->where('ct.merchant_id', $merchantId)
                ->where('pt.publish_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $successfulPublish = Db::table('publish_tasks')
                ->alias('pt')
                ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                ->where('ct.merchant_id', $merchantId)
                ->where('pt.publish_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('pt.status', 'in', [PublishTask::STATUS_COMPLETED, PublishTask::STATUS_PARTIAL])
                ->count();

            $overallSuccessRate = $totalPublish > 0
                ? round($successfulPublish / $totalPublish * 100, 2)
                : 0;

            $report = [
                'overall_success_rate' => $overallSuccessRate,
                'total_publish' => $totalPublish,
                'successful_publish' => $successfulPublish,
                'daily_stats' => $dailyStats,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            return $this->success($report, '获取分发效果报表成功');

        } catch (\Exception $e) {
            Log::error('获取分发效果报表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_publish_report_failed');
        }
    }

    /**
     * 获取转化数据报表
     * GET /api/merchant/conversionReport
     *
     * @return \think\Response
     */
    public function conversionReport()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            $userId = $this->request->user_id ?? null;

            if (!$merchantId && $userId) {
                $merchant = MerchantModel::where('user_id', $userId)->find();
                if ($merchant) {
                    $merchantId = $merchant->id;
                }
            }

            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            // 获取参数
            $startDate = $this->request->param('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->param('end_date', date('Y-m-d'));

            // 触发到内容生成的转化率
            $triggerCount = DeviceTrigger::where('merchant_id', $merchantId)
                ->where('trigger_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            $contentCount = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->count();

            $triggerToContentRate = $triggerCount > 0
                ? round($contentCount / $triggerCount * 100, 2)
                : 0;

            // 内容生成到发布的转化率
            $publishCount = Db::table('publish_tasks')
                ->alias('pt')
                ->join('content_tasks ct', 'pt.content_task_id = ct.id')
                ->where('ct.merchant_id', $merchantId)
                ->where('pt.publish_time', 'between', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('pt.status', 'in', [PublishTask::STATUS_COMPLETED, PublishTask::STATUS_PARTIAL])
                ->count();

            $contentToPublishRate = $contentCount > 0
                ? round($publishCount / $contentCount * 100, 2)
                : 0;

            // 整体转化率（触发到发布）
            $overallConversionRate = $triggerCount > 0
                ? round($publishCount / $triggerCount * 100, 2)
                : 0;

            $report = [
                'trigger_count' => $triggerCount,
                'content_count' => $contentCount,
                'publish_count' => $publishCount,
                'trigger_to_content_rate' => $triggerToContentRate,
                'content_to_publish_rate' => $contentToPublishRate,
                'overall_conversion_rate' => $overallConversionRate,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            return $this->success($report, '获取转化数据报表成功');

        } catch (\Exception $e) {
            Log::error('获取转化数据报表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_conversion_report_failed');
        }
    }

    /**
     * 获取商家列表（管理员）
     * GET /api/merchant/list
     *
     * @return \think\Response
     */
    public function list()
    {
        try {
            // 验证管理员权限
            // $userRole = $this->request->user_role ?? ''; // 假设中间件设置了user_role
            // if ($userRole !== 'admin') {
            //    return $this->error('无权访问', 403);
            // }

            // 获取分页参数
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');
            $category = $this->request->param('category', '');
            $keyword = $this->request->param('keyword', '');

            // 构建查询条件
            $query = MerchantModel::query();

            if ($status !== '') {
                $query->where('status', (int)$status);
            }
            if ($category !== '') {
                $query->where('category', $category);
            }
            if ($keyword !== '') {
                $query->where(function($query) use ($keyword) {
                    $query->whereLike('name', "%{$keyword}%")
                          ->whereOr('phone', 'like', "%{$keyword}%")
                          ->whereOr('address', 'like', "%{$keyword}%");
                });
            }

            // 查询商家列表
            $merchants = $query->page($page, $limit)
                ->order('create_time', 'desc')
                ->select();

            $total = $query->count();

            // 转换为数组格式
            $merchantList = [];
            foreach ($merchants as $merchant) {
                $merchantList[] = [
                    'id' => $merchant->id,
                    'user_id' => $merchant->user_id,
                    'name' => $merchant->name,
                    'category' => $merchant->category,
                    'address' => $merchant->address,
                    'phone' => $merchant->phone,
                    'logo_url' => $merchant->logo_url,
                    'status' => $merchant->status,
                    'status_text' => $merchant->status_text,
                    'create_time' => $merchant->create_time,
                    'update_time' => $merchant->update_time,
                ];
            }

            return $this->paginate($merchantList, $total, $page, $limit, '获取商家列表成功');

        } catch (\Exception $e) {
            Log::error('获取商家列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error($e->getMessage(), 400, 'get_merchant_list_failed');
        }
    }

    /**
     * 获取单个商家详情（管理员）
     * GET /api/merchant/:id
     *
     * @param int $id 商家ID
     * @return \think\Response
     */
    public function read($id)
    {
        try {
            $merchant = MerchantModel::find($id);
            if (!$merchant) {
                return $this->error('商家不存在', 404, 'merchant_not_found');
            }

            $merchantInfo = [
                'id' => $merchant->id,
                'user_id' => $merchant->user_id,
                'name' => $merchant->name,
                'category' => $merchant->category,
                'address' => $merchant->address,
                'longitude' => $merchant->longitude,
                'latitude' => $merchant->latitude,
                'phone' => $merchant->phone,
                'description' => $merchant->description,
                'logo' => $merchant->logo,
                'logo_url' => $merchant->logo_url,
                'business_hours' => $merchant->business_hours,
                'business_hours_text' => $merchant->business_hours_text,
                'status' => $merchant->status,
                'status_text' => $merchant->status_text,
                'reject_reason' => $merchant->reject_reason, // 返回拒绝原因
                'create_time' => $merchant->create_time,
                'update_time' => $merchant->update_time,
            ];

            return $this->success($merchantInfo, '获取商家详情成功');
        } catch (\Exception $e) {
            return $this->error('获取商家详情失败: ' . $e->getMessage());
        }
    }

    /**
     * 审核通过
     * POST /api/merchant/:id/approve
     */
    public function approve($id)
    {
        try {
            $merchant = MerchantModel::find($id);
            if (!$merchant) {
                return $this->error('商家不存在', 404);
            }

            $merchant->status = 1; // 假设 1 是已审核/正常
            $merchant->reject_reason = null; // 清空拒绝原因
            $merchant->save();

            Log::info('审核通过商家', ['merchant_id' => $id]);

            return $this->success([], '审核通过成功');
        } catch (\Exception $e) {
            return $this->error('审核失败: ' . $e->getMessage());
        }
    }

    /**
     * 审核拒绝
     * POST /api/merchant/:id/reject
     */
    public function reject($id)
    {
        try {
            $reason = $this->request->param('reason');
            if (empty($reason)) {
                return $this->error('请输入拒绝原因', 400);
            }

            $merchant = MerchantModel::find($id);
            if (!$merchant) {
                return $this->error('商家不存在', 404);
            }

            $merchant->status = 2; // 假设 2 是审核拒绝/禁用
            $merchant->reject_reason = $reason;
            $merchant->save();

            Log::info('审核拒绝商家', ['merchant_id' => $id, 'reason' => $reason]);

            return $this->success([], '操作成功');
        } catch (\Exception $e) {
            return $this->error('操作失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取优惠券列表
     * GET /api/merchant/coupon/list
     */
    public function couponList()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            if (!$merchantId && isset($this->request->user_id)) {
                $merchant = MerchantModel::where('user_id', $this->request->user_id)->find();
                $merchantId = $merchant ? $merchant->id : null;
            }
            
            if (!$merchantId) {
                Log::error('couponList: 缺少商家认证信息', ['user_id' => $this->request->user_id ?? 'null']);
                return $this->error('缺少商家认证信息', 401);
            }

            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $status = $this->request->param('status', '');

            $query = Coupon::where('merchant_id', $merchantId);

            if ($status !== '') {
                $query->where('status', (int)$status);
            }

            $list = $query->order('create_time', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);

            return $this->success($list, '获取优惠券列表成功');
        } catch (\Exception $e) {
            Log::error('couponList: Exception', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('获取优惠券列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建优惠券
     * POST /api/merchant/coupon/create
     */
    public function createCoupon()
    {
        try {
            $merchantId = $this->request->merchant_id ?? null;
            if (!$merchantId && isset($this->request->user_id)) {
                $merchant = MerchantModel::where('user_id', $this->request->user_id)->find();
                $merchantId = $merchant ? $merchant->id : null;
            }
            
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401);
            }

            $data = $this->request->post();
            
            // 简单验证
            $validate = [
                'name' => 'require|max:50',
                'type' => 'require|in:DISCOUNT,FULL_REDUCE,FREE_SHIPPING',
                'value' => 'require|float',
                'total_count' => 'require|integer|min:1',
                'start_time' => 'require|date',
                'end_time' => 'require|date',
            ];
            
            $this->validate($data, $validate);

            $coupon = new Coupon();
            $coupon->merchant_id = $merchantId;
            $coupon->name = $data['name'];
            $coupon->type = $data['type'];
            $coupon->value = $data['value'];
            $coupon->min_amount = $data['min_amount'] ?? 0;
            $coupon->total_count = $data['total_count'];
            $coupon->per_user_limit = $data['per_user_limit'] ?? 1;
            $coupon->valid_days = $data['valid_days'] ?? 30;
            
            // Handle date format (timestamp or string)
            $startTime = $data['start_time'];
            $endTime = $data['end_time'];
            
            Log::info('createCoupon dates raw', ['start' => $startTime, 'end' => $endTime, 'is_numeric_start' => is_numeric($startTime)]);

            if (is_numeric($startTime)) {
                $startTime = date('Y-m-d H:i:s', (int)$startTime);
            }
            if (is_numeric($endTime)) {
                $endTime = date('Y-m-d H:i:s', (int)$endTime);
            }
            
            Log::info('createCoupon dates processed', ['start' => $startTime, 'end' => $endTime]);

            $coupon->start_time = $startTime;
            $coupon->end_time = $endTime;
            
            $coupon->status = Coupon::STATUS_ENABLED;
            $coupon->save();

            return $this->success($coupon, '创建优惠券成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('创建优惠券失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新优惠券
     * PUT /api/merchant/coupon/:id
     */
    public function updateCoupon($id)
    {
        try {
            $coupon = Coupon::find($id);
            if (!$coupon) {
                return $this->error('优惠券不存在', 404);
            }
            
            $data = $this->request->put();
            $coupon->save($data);

            return $this->success($coupon, '更新成功');
        } catch (\Exception $e) {
            return $this->error('更新失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除优惠券
     * DELETE /api/merchant/coupon/:id
     */
    public function deleteCoupon($id)
    {
        try {
            $coupon = Coupon::find($id);
            if (!$coupon) {
                return $this->error('优惠券不存在', 404);
            }
            
            $coupon->delete();
            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            return $this->error('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 优惠券使用记录
     * GET /api/merchant/coupon/:id/usage
     */
    public function couponUsage($id)
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);

            $list = CouponUser::where('coupon_id', $id)
                ->order('create_time', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);

            return $this->success($list, '获取使用记录成功');
        } catch (\Exception $e) {
            return $this->error('获取使用记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取NFC触发记录
     * GET /api/merchant/nfc/trigger-records
     */
    public function getTriggerRecords()
    {
        try {
            $page = (int)$this->request->param('page', 1);
            $limit = (int)$this->request->param('limit', 20);
            $deviceId = $this->request->param('device_id');

            $merchantId = $this->request->merchantId ?? 0;

            $query = \app\model\DeviceTrigger::where('merchant_id', $merchantId)
                ->with(['device' => function($query) {
                    $query->field('id,name,device_code');
                }]);

            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }

            $list = $query->order('trigger_time', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);

            return $this->success($list, '获取触发记录成功');
        } catch (\Exception $e) {
            return $this->error('获取触发记录失败: ' . $e->getMessage());
        }
    }

}
