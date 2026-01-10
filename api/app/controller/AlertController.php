<?php
declare (strict_types = 1);

namespace app\controller;

use app\controller\BaseController;
use app\model\DeviceAlert;
use app\service\AlertService;
use app\service\NotificationService;
use app\service\AlertRuleService;
use think\exception\ValidateException;
use think\facade\Log;
use think\Request;

/**
 * 设备告警控制器
 */
class AlertController extends BaseController
{
    /**
     * 告警服务实例
     */
    protected AlertService $alertService;

    /**
     * 通知服务实例
     */
    protected NotificationService $notificationService;

    /**
     * 告警规则服务实例
     */
    protected AlertRuleService $alertRuleService;

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->alertService = new AlertService();
        $this->notificationService = new NotificationService();
        $this->alertRuleService = new AlertRuleService();
    }

    /**
     * 获取告警列表
     *
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'integer|>:0',
                'device_id' => 'integer|>:0',
                'alert_type' => 'in:offline,low_battery,response_timeout,device_error,signal_weak,temperature,heartbeat,trigger_failed',
                'alert_level' => 'in:low,medium,high,critical',
                'status' => 'in:pending,acknowledged,resolved,ignored',
                'start_date' => 'date',
                'end_date' => 'date',
                'page' => 'integer|>:0',
                'limit' => 'integer|between:1,100'
            ]);

            // 构建查询条件
            $where = [];

            if (isset($params['merchant_id'])) {
                $where['merchant_id'] = $params['merchant_id'];
            }

            if (isset($params['device_id'])) {
                $where['device_id'] = $params['device_id'];
            }

            if (isset($params['alert_type'])) {
                $where['alert_type'] = $params['alert_type'];
            }

            if (isset($params['alert_level'])) {
                $where['alert_level'] = $params['alert_level'];
            }

            if (isset($params['status'])) {
                $where['status'] = $params['status'];
            }

            // 时间范围查询
            if (isset($params['start_date']) && isset($params['end_date'])) {
                $where['create_time'] = ['between', [$params['start_date'] . ' 00:00:00', $params['end_date'] . ' 23:59:59']];
            } elseif (isset($params['start_date'])) {
                $where['create_time'] = ['>=', $params['start_date'] . ' 00:00:00'];
            } elseif (isset($params['end_date'])) {
                $where['create_time'] = ['<=', $params['end_date'] . ' 23:59:59'];
            }

            // 分页参数
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;

            // 查询告警列表
            $query = DeviceAlert::where($where)
                ->with(['device', 'merchant'])
                ->order('create_time', 'desc');

            $total = $query->count();
            $alerts = $query->page($page, $limit)->select()->toArray();

            // 格式化告警数据
            foreach ($alerts as &$alert) {
                $alert['alert_type_text'] = DeviceAlert::getAlertTypeTextAttr(null, $alert);
                $alert['alert_level_text'] = DeviceAlert::getAlertLevelTextAttr(null, $alert);
                $alert['status_text'] = DeviceAlert::getStatusTextAttr(null, $alert);
                $alert['level_color'] = DeviceAlert::getLevelColorAttr(null, $alert);
            }

            return $this->paginate($alerts, $total, $page, $limit, '告警列表获取成功');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('获取告警列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取告警列表失败');
        }
    }

    /**
     * 获取告警详情
     *
     * @param Request $request
     * @param int $id
     * @return \think\Response
     */
    public function read(Request $request, int $id)
    {
        try {
            $alert = DeviceAlert::with(['device', 'merchant', 'resolveUser'])->find($id);

            if (!$alert) {
                return $this->error('告警不存在', 404);
            }

            // 格式化告警数据
            $alertData = $alert->toArray();
            $alertData['alert_type_text'] = $alert->getAlertTypeTextAttr(null, $alert->getData());
            $alertData['alert_level_text'] = $alert->getAlertLevelTextAttr(null, $alert->getData());
            $alertData['status_text'] = $alert->getStatusTextAttr(null, $alert->getData());
            $alertData['level_color'] = $alert->getLevelColorAttr(null, $alert->getData());

            // 获取相关告警历史
            if ($alert->device_id) {
                $relatedAlerts = DeviceAlert::where('device_id', $alert->device_id)
                    ->where('id', '<>', $id)
                    ->order('create_time', 'desc')
                    ->limit(10)
                    ->select()
                    ->toArray();

                $alertData['related_alerts'] = $relatedAlerts;
            }

            return $this->success($alertData, '告警详情获取成功');

        } catch (\Exception $e) {
            Log::error('获取告警详情失败', ['alert_id' => $id, 'error' => $e->getMessage()]);
            return $this->error('获取告警详情失败');
        }
    }

    /**
     * 确认告警
     *
     * @param Request $request
     * @param int $id
     * @return \think\Response
     */
    public function acknowledge(Request $request, int $id)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'user_id' => 'require|integer|>:0'
            ]);

            $alert = DeviceAlert::find($id);
            if (!$alert) {
                return $this->error('告警不存在', 404);
            }

            $result = $alert->acknowledge($params['user_id']);

            if ($result) {
                Log::info('告警已确认', [
                    'alert_id' => $id,
                    'user_id' => $params['user_id']
                ]);
                return $this->success(['alert_id' => $id], '告警确认成功');
            } else {
                return $this->error('告警确认失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('告警确认失败', ['alert_id' => $id, 'error' => $e->getMessage()]);
            return $this->error('告警确认失败');
        }
    }

    /**
     * 解决告警
     *
     * @param Request $request
     * @param int $id
     * @return \think\Response
     */
    public function resolve(Request $request, int $id)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'user_id' => 'require|integer|>:0',
                'note' => 'max:1000'
            ]);

            $alert = DeviceAlert::find($id);
            if (!$alert) {
                return $this->error('告警不存在', 404);
            }

            $result = $alert->resolve($params['user_id'], $params['note'] ?? '');

            if ($result) {
                Log::info('告警已解决', [
                    'alert_id' => $id,
                    'user_id' => $params['user_id'],
                    'note' => $params['note'] ?? ''
                ]);
                return $this->success(['alert_id' => $id], '告警解决成功');
            } else {
                return $this->error('告警解决失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('告警解决失败', ['alert_id' => $id, 'error' => $e->getMessage()]);
            return $this->error('告警解决失败');
        }
    }

    /**
     * 忽略告警
     *
     * @param Request $request
     * @param int $id
     * @return \think\Response
     */
    public function ignore(Request $request, int $id)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'user_id' => 'require|integer|>:0',
                'note' => 'max:1000'
            ]);

            $alert = DeviceAlert::find($id);
            if (!$alert) {
                return $this->error('告警不存在', 404);
            }

            $result = $alert->ignore($params['user_id'], $params['note'] ?? '');

            if ($result) {
                Log::info('告警已忽略', [
                    'alert_id' => $id,
                    'user_id' => $params['user_id'],
                    'note' => $params['note'] ?? ''
                ]);
                return $this->success(['alert_id' => $id], '告警忽略成功');
            } else {
                return $this->error('告警忽略失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('告警忽略失败', ['alert_id' => $id, 'error' => $e->getMessage()]);
            return $this->error('告警忽略失败');
        }
    }

    /**
     * 批量处理告警
     *
     * @param Request $request
     * @return \think\Response
     */
    public function batchAction(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'alert_ids' => 'require|array',
                'action' => 'require|in:acknowledge,resolve,ignore',
                'user_id' => 'require|integer|>:0',
                'note' => 'max:1000'
            ]);

            $alertIds = $params['alert_ids'];
            $action = $params['action'];
            $userId = $params['user_id'];
            $note = $params['note'] ?? '';

            $results = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($alertIds as $alertId) {
                try {
                    $alert = DeviceAlert::find($alertId);
                    if (!$alert) {
                        $results[] = [
                            'alert_id' => $alertId,
                            'success' => false,
                            'message' => '告警不存在'
                        ];
                        $failCount++;
                        continue;
                    }

                    $result = false;
                    switch ($action) {
                        case 'acknowledge':
                            $result = $alert->acknowledge($userId);
                            break;
                        case 'resolve':
                            $result = $alert->resolve($userId, $note);
                            break;
                        case 'ignore':
                            $result = $alert->ignore($userId, $note);
                            break;
                    }

                    $results[] = [
                        'alert_id' => $alertId,
                        'success' => $result,
                        'message' => $result ? '处理成功' : '处理失败'
                    ];

                    if ($result) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'alert_id' => $alertId,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    $failCount++;
                }
            }

            Log::info('批量处理告警完成', [
                'action' => $action,
                'user_id' => $userId,
                'total' => count($alertIds),
                'success' => $successCount,
                'fail' => $failCount
            ]);

            return $this->batchResponse($results, sprintf('批量%s完成，成功%d个，失败%d个',
                $action === 'acknowledge' ? '确认' : ($action === 'resolve' ? '解决' : '忽略'),
                $successCount, $failCount));

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('批量处理告警失败', ['error' => $e->getMessage()]);
            return $this->error('批量处理告警失败');
        }
    }

    /**
     * 获取告警统计
     *
     * @param Request $request
     * @return \think\Response
     */
    public function stats(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'start_date' => 'date',
                'end_date' => 'date'
            ]);

            $merchantId = $params['merchant_id'];
            $startDate = $params['start_date'] ?? null;
            $endDate = $params['end_date'] ?? null;

            // 获取告警统计
            $stats = DeviceAlert::getAlertStats($merchantId, $startDate, $endDate);

            // 获取未解决告警数量
            $stats['unresolved_count'] = DeviceAlert::getUnresolvedCount($merchantId);

            return $this->success($stats, '告警统计获取成功');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('获取告警统计失败', ['error' => $e->getMessage()]);
            return $this->error('获取告警统计失败');
        }
    }

    /**
     * 手动检测设备告警
     *
     * @param Request $request
     * @return \think\Response
     */
    public function check(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'integer|>:0'
            ]);

            $merchantId = $params['merchant_id'] ?? null;

            // 执行告警检测
            $result = $this->alertService->batchCheckAlerts($merchantId);

            return $this->success($result, '设备告警检测完成');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('手动检测设备告警失败', ['error' => $e->getMessage()]);
            return $this->error('设备告警检测失败');
        }
    }

    /**
     * 获取告警规则列表
     *
     * @param Request $request
     * @return \think\Response
     */
    public function rules(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0'
            ]);

            $merchantId = $params['merchant_id'];

            // 获取所有告警规则
            $rules = $this->alertRuleService->getAllRules($merchantId);

            // 获取规则统计
            $stats = $this->alertRuleService->getRuleStats($merchantId);

            return $this->success([
                'rules' => $rules,
                'stats' => $stats
            ], '告警规则获取成功');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('获取告警规则失败', ['error' => $e->getMessage()]);
            return $this->error('获取告警规则失败');
        }
    }

    /**
     * 更新告警规则
     *
     * @param Request $request
     * @return \think\Response
     */
    public function updateRule(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'alert_type' => 'require|in:offline,low_battery,response_timeout,device_error,signal_weak,temperature,heartbeat,trigger_failed',
                'rule' => 'require|array'
            ]);

            $merchantId = $params['merchant_id'];
            $alertType = $params['alert_type'];
            $rule = $params['rule'];

            // 更新规则
            $result = $this->alertRuleService->setRule($merchantId, $alertType, $rule);

            if ($result) {
                return $this->success(['alert_type' => $alertType], '告警规则更新成功');
            } else {
                return $this->error('告警规则更新失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('更新告警规则失败', ['error' => $e->getMessage()]);
            return $this->error('更新告警规则失败');
        }
    }

    /**
     * 批量更新告警规则
     *
     * @param Request $request
     * @return \think\Response
     */
    public function updateBatchRules(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'rules' => 'require|array'
            ]);

            $merchantId = $params['merchant_id'];
            $rules = $params['rules'];

            // 批量更新规则
            $results = $this->alertRuleService->setBatchRules($merchantId, $rules);

            return $this->batchResponse($results, '批量更新告警规则完成');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('批量更新告警规则失败', ['error' => $e->getMessage()]);
            return $this->error('批量更新告警规则失败');
        }
    }

    /**
     * 重置告警规则
     *
     * @param Request $request
     * @return \think\Response
     */
    public function resetRule(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'alert_type' => 'in:offline,low_battery,response_timeout,device_error,signal_weak,temperature,heartbeat,trigger_failed'
            ]);

            $merchantId = $params['merchant_id'];
            $alertType = $params['alert_type'] ?? null;

            // 重置规则
            $result = $this->alertRuleService->resetRule($merchantId, $alertType);

            if ($result) {
                $message = $alertType ? '告警规则重置成功' : '所有告警规则重置成功';
                return $this->success(['alert_type' => $alertType], $message);
            } else {
                return $this->error('告警规则重置失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('重置告警规则失败', ['error' => $e->getMessage()]);
            return $this->error('重置告警规则失败');
        }
    }

    /**
     * 获取告警规则模板
     *
     * @param Request $request
     * @return \think\Response
     */
    public function ruleTemplates(Request $request)
    {
        try {
            $templates = $this->alertRuleService->getRuleTemplates();
            return $this->success($templates, '告警规则模板获取成功');

        } catch (\Exception $e) {
            Log::error('获取告警规则模板失败', ['error' => $e->getMessage()]);
            return $this->error('获取告警规则模板失败');
        }
    }

    /**
     * 应用告警规则模板
     *
     * @param Request $request
     * @return \think\Response
     */
    public function applyTemplate(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'template' => 'require|in:basic,strict,relaxed'
            ]);

            $merchantId = $params['merchant_id'];
            $template = $params['template'];

            // 应用模板
            $result = $this->alertRuleService->applyTemplate($merchantId, $template);

            if ($result) {
                return $this->success(['template' => $template], '告警规则模板应用成功');
            } else {
                return $this->error('告警规则模板应用失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('应用告警规则模板失败', ['error' => $e->getMessage()]);
            return $this->error('应用告警规则模板失败');
        }
    }

    /**
     * 获取系统通知列表
     *
     * @param Request $request
     * @return \think\Response
     */
    public function notifications(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'unread_only' => 'boolean'
            ]);

            $merchantId = $params['merchant_id'];
            $unreadOnly = $params['unread_only'] ?? false;

            $notifications = $this->notificationService->getSystemNotifications($merchantId, $unreadOnly);

            return $this->success($notifications, '系统通知获取成功');

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('获取系统通知失败', ['error' => $e->getMessage()]);
            return $this->error('获取系统通知失败');
        }
    }

    /**
     * 标记通知为已读
     *
     * @param Request $request
     * @return \think\Response
     */
    public function markAsRead(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0',
                'alert_id' => 'require|integer|>:0'
            ]);

            $merchantId = $params['merchant_id'];
            $alertId = $params['alert_id'];

            $result = $this->notificationService->markNotificationAsRead($merchantId, $alertId);

            if ($result) {
                return $this->success(['alert_id' => $alertId], '通知已标记为已读');
            } else {
                return $this->error('标记通知失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('标记通知为已读失败', ['error' => $e->getMessage()]);
            return $this->error('标记通知失败');
        }
    }

    /**
     * 清除已读通知
     *
     * @param Request $request
     * @return \think\Response
     */
    public function clearReadNotifications(Request $request)
    {
        try {
            $params = $request->param();

            // 验证请求参数
            $this->validate($params, [
                'merchant_id' => 'require|integer|>:0'
            ]);

            $merchantId = $params['merchant_id'];

            $result = $this->notificationService->clearReadNotifications($merchantId);

            if ($result) {
                return $this->success([], '已读通知清除成功');
            } else {
                return $this->error('清除已读通知失败');
            }

        } catch (ValidateException $e) {
            return $this->validationError(['message' => $e->getError()]);
        } catch (\Exception $e) {
            Log::error('清除已读通知失败', ['error' => $e->getMessage()]);
            return $this->error('清除已读通知失败');
        }
    }
}