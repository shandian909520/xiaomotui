<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Table;
use app\model\DiningSession;
use app\model\SessionUser;
use app\model\ServiceCall;
use app\model\User;
use app\model\NfcDevice;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Db;

/**
 * 桌号绑定服务类
 *
 * 功能包括：
 * - 桌号绑定管理
 * - 用餐会话管理
 * - 服务呼叫功能
 * - 数据统计分析
 */
class TableService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'table_';

    /**
     * 会话缓存时间(秒)
     */
    const SESSION_CACHE_TTL = 7200; // 2小时

    /**
     * NFC设备触发桌号绑定
     * 用户通过NFC设备扫码/碰一碰后自动绑定到桌号
     *
     * @param string $deviceCode NFC设备编码
     * @param int $userId 用户ID
     * @return array
     * @throws \Exception
     */
    public function bindTableByDevice(string $deviceCode, int $userId): array
    {
        Db::startTrans();
        try {
            // 查找设备
            $device = NfcDevice::findByDeviceCode($deviceCode);
            if (!$device) {
                throw new ValidateException('NFC设备不存在');
            }

            // 检查设备是否已绑定桌台
            if (!$device->table_id) {
                throw new ValidateException('该NFC设备未绑定桌台');
            }

            // 查找桌台
            $table = Table::find($device->table_id);
            if (!$table) {
                throw new ValidateException('桌台不存在');
            }

            // 查找用户
            $user = User::find($userId);
            if (!$user) {
                throw new ValidateException('用户不存在');
            }

            // 检查桌台是否有进行中的会话
            $session = DiningSession::getCurrentSessionByTableId($table->id);

            if ($session) {
                // 如果已有会话，检查用户是否已经在会话中
                $sessionUser = SessionUser::findBySessionAndUser($session->id, $userId);

                if ($sessionUser && $sessionUser->isInSession()) {
                    // 用户已在会话中
                    Db::commit();

                    Log::info('用户重新扫描桌台', [
                        'user_id' => $userId,
                        'table_id' => $table->id,
                        'session_id' => $session->id
                    ]);

                    return [
                        'type' => 'existing_session',
                        'session_id' => $session->id,
                        'session_code' => $session->session_code,
                        'table_number' => $table->table_number,
                        'is_host' => $sessionUser->isHost(),
                        'guest_count' => $session->guest_count,
                        'start_time' => $session->start_time,
                        'message' => '欢迎回来！您已在该桌用餐中'
                    ];
                } else {
                    // 用户不在会话中，加入现有会话
                    if ($sessionUser) {
                        // 用户之前离开了，现在重新加入
                        $sessionUser->leave_time = null;
                        $sessionUser->join_time = date('Y-m-d H:i:s');
                        $sessionUser->save();
                    } else {
                        // 创建新的会话用户关联
                        $sessionUser = SessionUser::create([
                            'session_id' => $session->id,
                            'user_id' => $userId,
                            'is_host' => 0,
                            'join_time' => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 更新用餐人数
                    $activeUserCount = SessionUser::where('session_id', $session->id)
                        ->where('leave_time', null)
                        ->count();
                    $session->updateGuestCount($activeUserCount);

                    Db::commit();

                    Log::info('用户加入现有会话', [
                        'user_id' => $userId,
                        'table_id' => $table->id,
                        'session_id' => $session->id
                    ]);

                    return [
                        'type' => 'join_session',
                        'session_id' => $session->id,
                        'session_code' => $session->session_code,
                        'table_number' => $table->table_number,
                        'is_host' => false,
                        'guest_count' => $session->guest_count,
                        'start_time' => $session->start_time,
                        'message' => '已加入拼桌，祝您用餐愉快！'
                    ];
                }
            } else {
                // 没有进行中的会话，创建新会话
                $sessionCode = DiningSession::generateSessionCode();

                $session = DiningSession::create([
                    'merchant_id' => $device->merchant_id,
                    'table_id' => $table->id,
                    'device_id' => $device->id,
                    'session_code' => $sessionCode,
                    'status' => DiningSession::STATUS_ACTIVE,
                    'guest_count' => 1,
                    'start_time' => date('Y-m-d H:i:s')
                ]);

                // 创建会话用户关联（作为主用户）
                SessionUser::create([
                    'session_id' => $session->id,
                    'user_id' => $userId,
                    'is_host' => 1,
                    'join_time' => date('Y-m-d H:i:s')
                ]);

                // 更新桌台状态为使用中
                $table->setOccupied();

                Db::commit();

                Log::info('创建新用餐会话', [
                    'user_id' => $userId,
                    'table_id' => $table->id,
                    'session_id' => $session->id,
                    'session_code' => $sessionCode
                ]);

                return [
                    'type' => 'new_session',
                    'session_id' => $session->id,
                    'session_code' => $sessionCode,
                    'table_number' => $table->table_number,
                    'is_host' => true,
                    'guest_count' => 1,
                    'start_time' => $session->start_time,
                    'message' => '欢迎光临！已为您安排' . $table->table_number . '号桌'
                ];
            }

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('桌号绑定失败', [
                'device_code' => $deviceCode,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 创建服务呼叫
     *
     * @param int $sessionId 会话ID
     * @param int $userId 用户ID
     * @param string $callType 呼叫类型
     * @param string $description 描述
     * @param string $priority 优先级
     * @return array
     * @throws \Exception
     */
    public function createServiceCall(
        int $sessionId,
        int $userId,
        string $callType,
        string $description = '',
        string $priority = ServiceCall::PRIORITY_NORMAL
    ): array {
        try {
            // 查找会话
            $session = DiningSession::find($sessionId);
            if (!$session) {
                throw new ValidateException('用餐会话不存在');
            }

            if (!$session->isActive()) {
                throw new ValidateException('用餐会话已结束');
            }

            // 验证用户在会话中
            $sessionUser = SessionUser::findBySessionAndUser($sessionId, $userId);
            if (!$sessionUser || !$sessionUser->isInSession()) {
                throw new ValidateException('您不在该用餐会话中');
            }

            // 创建服务呼叫
            $serviceCall = ServiceCall::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'merchant_id' => $session->merchant_id,
                'table_id' => $session->table_id,
                'call_type' => $callType,
                'description' => $description,
                'priority' => $priority,
                'status' => ServiceCall::STATUS_PENDING
            ]);

            // 发送通知给商家
            $this->notifyMerchantServiceCall($serviceCall);

            Log::info('创建服务呼叫', [
                'call_id' => $serviceCall->id,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'call_type' => $callType
            ]);

            return [
                'call_id' => $serviceCall->id,
                'call_type' => $callType,
                'call_type_text' => $serviceCall->call_type_text,
                'description' => $description,
                'priority' => $priority,
                'priority_text' => $serviceCall->priority_text,
                'status' => $serviceCall->status,
                'status_text' => $serviceCall->status_text,
                'table_number' => $session->table->table_number,
                'create_time' => $serviceCall->create_time,
                'message' => '服务呼叫已发送，服务员将尽快为您服务'
            ];

        } catch (\Exception $e) {
            Log::error('创建服务呼叫失败', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'call_type' => $callType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 处理服务呼叫
     *
     * @param int $callId 呼叫ID
     * @param int $staffId 员工ID
     * @return array
     * @throws \Exception
     */
    public function processServiceCall(int $callId, int $staffId): array
    {
        try {
            $serviceCall = ServiceCall::find($callId);
            if (!$serviceCall) {
                throw new ValidateException('服务呼叫不存在');
            }

            if ($serviceCall->isCompleted() || $serviceCall->isCancelled()) {
                throw new ValidateException('服务呼叫已处理');
            }

            // 开始处理
            $serviceCall->startProcessing($staffId);

            // 发送通知给用户
            $this->notifyUserServiceProcessing($serviceCall);

            Log::info('开始处理服务呼叫', [
                'call_id' => $callId,
                'staff_id' => $staffId,
                'response_time' => $serviceCall->response_time
            ]);

            return [
                'call_id' => $serviceCall->id,
                'status' => $serviceCall->status,
                'status_text' => $serviceCall->status_text,
                'staff_id' => $staffId,
                'response_time' => $serviceCall->response_time,
                'message' => '已开始处理服务呼叫'
            ];

        } catch (\Exception $e) {
            Log::error('处理服务呼叫失败', [
                'call_id' => $callId,
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 完成服务呼叫
     *
     * @param int $callId 呼叫ID
     * @return array
     * @throws \Exception
     */
    public function completeServiceCall(int $callId): array
    {
        try {
            $serviceCall = ServiceCall::find($callId);
            if (!$serviceCall) {
                throw new ValidateException('服务呼叫不存在');
            }

            if ($serviceCall->isCompleted()) {
                throw new ValidateException('服务呼叫已完成');
            }

            // 完成处理
            $serviceCall->complete();

            // 发送通知给用户
            $this->notifyUserServiceCompleted($serviceCall);

            Log::info('完成服务呼叫', [
                'call_id' => $callId,
                'processing_duration' => $serviceCall->getProcessingDuration()
            ]);

            return [
                'call_id' => $serviceCall->id,
                'status' => $serviceCall->status,
                'status_text' => $serviceCall->status_text,
                'complete_time' => $serviceCall->complete_time,
                'processing_duration' => $serviceCall->getProcessingDuration(),
                'message' => '服务呼叫已完成'
            ];

        } catch (\Exception $e) {
            Log::error('完成服务呼叫失败', [
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 结束用餐会话
     *
     * @param int $sessionId 会话ID
     * @param int $userId 用户ID（可选，用于验证权限）
     * @return array
     * @throws \Exception
     */
    public function endDiningSession(int $sessionId, ?int $userId = null): array
    {
        Db::startTrans();
        try {
            $session = DiningSession::find($sessionId);
            if (!$session) {
                throw new ValidateException('用餐会话不存在');
            }

            if (!$session->isActive()) {
                throw new ValidateException('用餐会话已结束');
            }

            // 如果提供了用户ID，验证是否为主用户
            if ($userId !== null) {
                $sessionUser = SessionUser::findBySessionAndUser($sessionId, $userId);
                if (!$sessionUser || !$sessionUser->isHost()) {
                    throw new ValidateException('只有主用户可以结束会话');
                }
            }

            // 完成会话
            $session->complete();

            // 标记所有用户离开
            SessionUser::where('session_id', $sessionId)
                ->where('leave_time', null)
                ->update(['leave_time' => date('Y-m-d H:i:s')]);

            // 取消所有待处理的服务呼叫
            ServiceCall::where('session_id', $sessionId)
                ->whereIn('status', [ServiceCall::STATUS_PENDING, ServiceCall::STATUS_PROCESSING])
                ->update([
                    'status' => ServiceCall::STATUS_CANCELLED,
                    'complete_time' => date('Y-m-d H:i:s')
                ]);

            // 更新桌台状态为清理中
            $table = Table::find($session->table_id);
            if ($table) {
                $table->setCleaning();
            }

            Db::commit();

            Log::info('结束用餐会话', [
                'session_id' => $sessionId,
                'duration' => $session->duration,
                'guest_count' => $session->guest_count
            ]);

            return [
                'session_id' => $sessionId,
                'session_code' => $session->session_code,
                'status' => $session->status,
                'status_text' => $session->status_text,
                'duration' => $session->duration,
                'guest_count' => $session->guest_count,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'message' => '用餐会话已结束，感谢光临！'
            ];

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('结束用餐会话失败', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 用户离开会话
     *
     * @param int $sessionId 会话ID
     * @param int $userId 用户ID
     * @return array
     * @throws \Exception
     */
    public function leaveSession(int $sessionId, int $userId): array
    {
        Db::startTrans();
        try {
            $session = DiningSession::find($sessionId);
            if (!$session || !$session->isActive()) {
                throw new ValidateException('用餐会话不存在或已结束');
            }

            $sessionUser = SessionUser::findBySessionAndUser($sessionId, $userId);
            if (!$sessionUser || !$sessionUser->isInSession()) {
                throw new ValidateException('您不在该用餐会话中');
            }

            // 如果是主用户，需要转移主用户权限或结束会话
            if ($sessionUser->isHost()) {
                // 查找其他在座用户
                $otherUsers = SessionUser::where('session_id', $sessionId)
                    ->where('user_id', '<>', $userId)
                    ->where('leave_time', null)
                    ->select();

                if ($otherUsers->isEmpty()) {
                    // 没有其他用户，结束会话
                    Db::commit();
                    return $this->endDiningSession($sessionId);
                } else {
                    // 将第一个用户设为主用户
                    $newHost = $otherUsers[0];
                    $newHost->setAsHost();
                }
            }

            // 标记离开
            $sessionUser->leave();

            // 更新用餐人数
            $activeUserCount = SessionUser::where('session_id', $sessionId)
                ->where('leave_time', null)
                ->count();
            $session->updateGuestCount($activeUserCount);

            Db::commit();

            Log::info('用户离开会话', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'stay_duration' => $sessionUser->getStayDuration()
            ]);

            return [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'leave_time' => $sessionUser->leave_time,
                'stay_duration' => $sessionUser->getStayDuration(),
                'remaining_guests' => $session->guest_count,
                'message' => '您已离开用餐会话'
            ];

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('用户离开会话失败', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取会话详情
     *
     * @param int $sessionId 会话ID
     * @return array
     * @throws \Exception
     */
    public function getSessionDetail(int $sessionId): array
    {
        $session = DiningSession::find($sessionId);
        if (!$session) {
            throw new ValidateException('用餐会话不存在');
        }

        // 获取会话中的所有用户
        $sessionUsers = SessionUser::getUsersBySessionId($sessionId, true);
        $users = [];
        foreach ($sessionUsers as $sessionUser) {
            $user = User::find($sessionUser->user_id);
            if ($user) {
                $users[] = [
                    'user_id' => $user->id,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                    'is_host' => $sessionUser->isHost(),
                    'join_time' => $sessionUser->join_time
                ];
            }
        }

        // 获取服务呼叫记录
        $serviceCalls = ServiceCall::getCallsBySessionId($sessionId);
        $calls = [];
        foreach ($serviceCalls as $call) {
            $calls[] = [
                'call_id' => $call->id,
                'call_type' => $call->call_type,
                'call_type_text' => $call->call_type_text,
                'description' => $call->description,
                'status' => $call->status,
                'status_text' => $call->status_text,
                'priority' => $call->priority,
                'priority_text' => $call->priority_text,
                'response_time' => $call->response_time,
                'create_time' => $call->create_time,
                'complete_time' => $call->complete_time
            ];
        }

        $table = Table::find($session->table_id);

        return [
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'status_text' => $session->status_text,
            'table_number' => $table ? $table->table_number : '',
            'table_area' => $table ? $table->area : '',
            'guest_count' => $session->guest_count,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'duration' => $session->duration,
            'users' => $users,
            'service_calls' => $calls
        ];
    }

    /**
     * 获取商家待处理的服务呼叫列表
     *
     * @param int $merchantId 商家ID
     * @return array
     */
    public function getMerchantPendingCalls(int $merchantId): array
    {
        $calls = ServiceCall::getPendingCalls($merchantId);

        $result = [];
        foreach ($calls as $call) {
            $session = DiningSession::find($call->session_id);
            $table = Table::find($call->table_id);
            $user = User::find($call->user_id);

            $result[] = [
                'call_id' => $call->id,
                'call_type' => $call->call_type,
                'call_type_text' => $call->call_type_text,
                'description' => $call->description,
                'priority' => $call->priority,
                'priority_text' => $call->priority_text,
                'status' => $call->status,
                'status_text' => $call->status_text,
                'table_number' => $table ? $table->table_number : '',
                'table_area' => $table ? $table->area : '',
                'user_nickname' => $user ? $user->nickname : '未知用户',
                'create_time' => $call->create_time,
                'waiting_time' => time() - strtotime($call->create_time)
            ];
        }

        return $result;
    }

    /**
     * 获取桌台使用统计
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getTableUsageStats(int $merchantId, string $startDate, string $endDate): array
    {
        // 获取所有桌台
        $tables = Table::getByMerchantId($merchantId);
        $totalTables = count($tables);

        // 当前使用中的桌台
        $occupiedCount = Table::where('merchant_id', $merchantId)
            ->where('status', Table::STATUS_OCCUPIED)
            ->count();

        // 时间段内的会话统计
        $sessions = DiningSession::where('merchant_id', $merchantId)
            ->where('start_time', '>=', $startDate . ' 00:00:00')
            ->where('start_time', '<=', $endDate . ' 23:59:59')
            ->select();

        $totalSessions = count($sessions);
        $completedSessions = 0;
        $totalDuration = 0;
        $totalGuests = 0;

        foreach ($sessions as $session) {
            if ($session->isCompleted()) {
                $completedSessions++;
                $totalDuration += $session->duration ?? 0;
            }
            $totalGuests += $session->guest_count;
        }

        $avgDuration = $completedSessions > 0 ? round($totalDuration / $completedSessions, 2) : 0;
        $avgGuests = $totalSessions > 0 ? round($totalGuests / $totalSessions, 2) : 0;

        // 翻台率 = 总会话数 / 桌台数
        $turnoverRate = $totalTables > 0 ? round($totalSessions / $totalTables, 2) : 0;

        // 使用率 = 当前使用中桌台 / 总桌台
        $usageRate = $totalTables > 0 ? round(($occupiedCount / $totalTables) * 100, 2) : 0;

        return [
            'total_tables' => $totalTables,
            'occupied_tables' => $occupiedCount,
            'available_tables' => $totalTables - $occupiedCount,
            'usage_rate' => $usageRate . '%',
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'active_sessions' => $totalSessions - $completedSessions,
            'turnover_rate' => $turnoverRate,
            'avg_duration' => $avgDuration . '分钟',
            'avg_guests' => $avgGuests,
            'total_guests' => $totalGuests
        ];
    }

    /**
     * 获取服务呼叫统计
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public function getServiceCallStats(int $merchantId, string $startDate, string $endDate): array
    {
        $calls = ServiceCall::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->select();

        $totalCalls = count($calls);
        $completedCalls = 0;
        $totalResponseTime = 0;
        $callTypeStats = [];
        $priorityStats = [];

        foreach ($calls as $call) {
            // 统计完成数和响应时间
            if ($call->isCompleted()) {
                $completedCalls++;
                if ($call->response_time !== null) {
                    $totalResponseTime += $call->response_time;
                }
            }

            // 按类型统计
            $type = $call->call_type;
            if (!isset($callTypeStats[$type])) {
                $callTypeStats[$type] = ['count' => 0, 'text' => $call->call_type_text];
            }
            $callTypeStats[$type]['count']++;

            // 按优先级统计
            $priority = $call->priority;
            if (!isset($priorityStats[$priority])) {
                $priorityStats[$priority] = ['count' => 0, 'text' => $call->priority_text];
            }
            $priorityStats[$priority]['count']++;
        }

        $avgResponseTime = $completedCalls > 0 ? round($totalResponseTime / $completedCalls, 2) : 0;
        $completionRate = $totalCalls > 0 ? round(($completedCalls / $totalCalls) * 100, 2) : 0;

        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'pending_calls' => ServiceCall::where('merchant_id', $merchantId)
                ->where('status', ServiceCall::STATUS_PENDING)
                ->count(),
            'processing_calls' => ServiceCall::where('merchant_id', $merchantId)
                ->where('status', ServiceCall::STATUS_PROCESSING)
                ->count(),
            'completion_rate' => $completionRate . '%',
            'avg_response_time' => $avgResponseTime . '秒',
            'call_type_stats' => array_values($callTypeStats),
            'priority_stats' => array_values($priorityStats)
        ];
    }

    /**
     * 清理桌台（设置为可用状态）
     *
     * @param int $tableId 桌台ID
     * @return array
     * @throws \Exception
     */
    public function cleanTable(int $tableId): array
    {
        try {
            $table = Table::find($tableId);
            if (!$table) {
                throw new ValidateException('桌台不存在');
            }

            $table->setAvailable();

            Log::info('清理桌台完成', ['table_id' => $tableId, 'table_number' => $table->table_number]);

            return [
                'table_id' => $tableId,
                'table_number' => $table->table_number,
                'status' => $table->status,
                'status_text' => $table->status_text,
                'message' => '桌台已清理完成，可以接待新客户'
            ];

        } catch (\Exception $e) {
            Log::error('清理桌台失败', ['table_id' => $tableId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 通知商家有新的服务呼叫
     *
     * @param ServiceCall $serviceCall
     */
    protected function notifyMerchantServiceCall(ServiceCall $serviceCall): void
    {
        try {
            // 这里可以集成NotificationService发送实时通知
            // 例如：WebSocket推送、短信通知等
            Log::info('通知商家服务呼叫', [
                'call_id' => $serviceCall->id,
                'merchant_id' => $serviceCall->merchant_id,
                'call_type' => $serviceCall->call_type
            ]);
        } catch (\Exception $e) {
            Log::error('通知商家失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 通知用户服务正在处理中
     *
     * @param ServiceCall $serviceCall
     */
    protected function notifyUserServiceProcessing(ServiceCall $serviceCall): void
    {
        try {
            Log::info('通知用户服务处理中', [
                'call_id' => $serviceCall->id,
                'user_id' => $serviceCall->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('通知用户失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 通知用户服务已完成
     *
     * @param ServiceCall $serviceCall
     */
    protected function notifyUserServiceCompleted(ServiceCall $serviceCall): void
    {
        try {
            Log::info('通知用户服务完成', [
                'call_id' => $serviceCall->id,
                'user_id' => $serviceCall->user_id
            ]);
        } catch (\Exception $e) {
            Log::error('通知用户失败', ['error' => $e->getMessage()]);
        }
    }
}