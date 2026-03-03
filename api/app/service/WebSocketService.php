<?php
declare(strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Cache;

/**
 * WebSocket实时通知服务
 * 用于向客户端推送实时告警和状态更新
 */
class WebSocketService
{
    /**
     * WebSocket服务器配置
     */
    const WS_HOST = '127.0.0.1';
    const WS_PORT = 9501;

    /**
     * 通知类型
     */
    const TYPE_ALERT = 'alert';           // 告警通知
    const TYPE_STATUS = 'status';         // 状态更新
    const TYPE_DATA = 'data';             // 数据更新
    const TYPE_SYSTEM = 'system';         // 系统通知

    /**
     * 推送告警通知到指定商家
     *
     * @param int $merchantId 商家ID
     * @param array $alertData 告警数据
     * @return bool
     */
    public function pushAlert(int $merchantId, array $alertData): bool
    {
        try {
            $message = [
                'type' => self::TYPE_ALERT,
                'merchant_id' => $merchantId,
                'data' => $alertData,
                'timestamp' => time()
            ];

            return $this->pushToMerchant($merchantId, $message);

        } catch (\Exception $e) {
            Log::error('推送告警通知失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 推送设备状态更新
     *
     * @param int $merchantId 商家ID
     * @param int $deviceId 设备ID
     * @param string $status 状态
     * @return bool
     */
    public function pushDeviceStatus(int $merchantId, int $deviceId, string $status): bool
    {
        try {
            $message = [
                'type' => self::TYPE_STATUS,
                'merchant_id' => $merchantId,
                'data' => [
                    'device_id' => $deviceId,
                    'status' => $status,
                    'status_text' => $this->getStatusText($status)
                ],
                'timestamp' => time()
            ];

            return $this->pushToMerchant($merchantId, $message);

        } catch (\Exception $e) {
            Log::error('推送设备状态失败', [
                'merchant_id' => $merchantId,
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 推送数据更新
     *
     * @param int|null $merchantId 商家ID(null表示所有商家）
     * @param string $dataType 数据类型
     * @param array $data 数据
     * @return bool
     */
    public function pushDataUpdate(?int $merchantId, string $dataType, array $data): bool
    {
        try {
            $message = [
                'type' => self::TYPE_DATA,
                'merchant_id' => $merchantId,
                'data' => [
                    'data_type' => $dataType,
                    'content' => $data
                ],
                'timestamp' => time()
            ];

            if ($merchantId === null) {
                return $this->broadcast($message);
            } else {
                return $this->pushToMerchant($merchantId, $message);
            }

        } catch (\Exception $e) {
            Log::error('推送数据更新失败', [
                'merchant_id' => $merchantId,
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 推送系统通知
     *
     * @param int|null $merchantId 商家ID
     * @param string $title 标题
     * @param string $content 内容
     * @param string $level 级别
     * @return bool
     */
    public function pushSystemNotification(?int $merchantId, string $title, string $content, string $level = 'info'): bool
    {
        try {
            $message = [
                'type' => self::TYPE_SYSTEM,
                'merchant_id' => $merchantId,
                'data' => [
                    'title' => $title,
                    'content' => $content,
                    'level' => $level
                ],
                'timestamp' => time()
            ];

            if ($merchantId === null) {
                return $this->broadcast($message);
            } else {
                return $this->pushToMerchant($merchantId, $message);
            }

        } catch (\Exception $e) {
            Log::error('推送系统通知失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 推送消息到指定商家的在线客户端
     *
     * @param int $merchantId 商家ID
     * @param array $message 消息内容
     * @return bool
     */
    protected function pushToMerchant(int $merchantId, array $message): bool
    {
        try {
            // 获取商家的在线连接
            $connections = $this->getMerchantConnections($merchantId);

            if (empty($connections)) {
                Log::debug('商家无在线连接', ['merchant_id' => $merchantId]);
                return false;
            }

            // 推送到每个连接
            $successCount = 0;
            foreach ($connections as $conn) {
                if ($this->sendToConnection($conn, $message)) {
                    $successCount++;
                }
            }

            Log::info('推送消息完成', [
                'merchant_id' => $merchantId,
                'connections' => count($connections),
                'success' => $successCount
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error('pushToMerchant失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 广播消息到所有在线客户端
     *
     * @param array $message 消息内容
     * @return bool
     */
    protected function broadcast(array $message): bool
    {
        try {
            // 获取所有在线连接
            $allConnections = $this->getAllConnections();

            if (empty($allConnections)) {
                Log::debug('无在线连接');
                return false;
            }

            // 推送到所有连接
            $successCount = 0;
            foreach ($allConnections as $conn) {
                if ($this->sendToConnection($conn, $message)) {
                    $successCount++;
                }
            }

            Log::info('广播消息完成', [
                'total_connections' => count($allConnections),
                'success' => $successCount
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error('broadcast失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送消息到指定连接
     *
     * @param array $connection 连接信息
     * @param array $message 消息内容
     * @return bool
     */
    protected function sendToConnection(array $connection, array $message): bool
    {
        try {
            // 使用HTTP接口推送消息到WebSocket服务器
            $url = "http://" . self::WS_HOST . ":" . (self::WS_PORT + 1) . "/push";

            $payload = [
                'fd' => $connection['fd'] ?? null,
                'merchant_id' => $connection['merchant_id'] ?? null,
                'user_id' => $connection['user_id'] ?? null,
                'message' => $message
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: XMT-WebSocket-Client/1.0'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!empty($error) || $httpCode >= 400) {
                Log::warning('发送消息到连接失败', [
                    'fd' => $connection['fd'] ?? null,
                    'http_code' => $httpCode,
                    'error' => $error
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('sendToConnection失败', [
                'connection' => $connection,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取商家的在线连接
     *
     * @param int $merchantId 商家ID
     * @return array
     */
    protected function getMerchantConnections(int $merchantId): array
    {
        $cacheKey = "ws_connections:merchant:{$merchantId}";
        $connections = Cache::get($cacheKey, []);

        return is_array($connections) ? $connections : [];
    }

    /**
     * 获取所有在线连接
     *
     * @return array
     */
    protected function getAllConnections(): array
    {
        $cacheKey = 'ws_connections:all';
        $connections = Cache::get($cacheKey, []);

        return is_array($connections) ? $connections : [];
    }

    /**
     * 注册连接
     *
     * @param int $fd 连接ID
     * @param int $merchantId 商家ID
     * @param int|null $userId 用户ID
     * @return bool
     */
    public function registerConnection(int $fd, int $merchantId, ?int $userId = null): bool
    {
        try {
            $connection = [
                'fd' => $fd,
                'merchant_id' => $merchantId,
                'user_id' => $userId,
                'connect_time' => time(),
                'last_heartbeat' => time()
            ];

            // 添加到全局连接列表
            $allKey = 'ws_connections:all';
            $allConnections = $this->getAllConnections();
            $allConnections[] = $connection;
            Cache::set($allKey, $allConnections, 3600);

            // 添加到商家连接列表
            $merchantKey = "ws_connections:merchant:{$merchantId}";
            $merchantConnections = $this->getMerchantConnections($merchantId);
            $merchantConnections[] = $connection;
            Cache::set($merchantKey, $merchantConnections, 3600);

            Log::info('注册WebSocket连接', [
                'fd' => $fd,
                'merchant_id' => $merchantId,
                'user_id' => $userId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('注册连接失败', [
                'fd' => $fd,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 移除连接
     *
     * @param int $fd 连接ID
     * @return bool
     */
    public function removeConnection(int $fd): bool
    {
        try {
            // 从全局连接列表中移除
            $allKey = 'ws_connections:all';
            $allConnections = $this->getAllConnections();
            $allConnections = array_filter($allConnections, function($conn) use ($fd) {
                return ($conn['fd'] ?? null) !== $fd;
            });
            Cache::set($allKey, $allConnections, 3600);

            // 从商家连接列表中移除
            $removed = null;
            foreach ($allConnections as $conn) {
                $merchantId = $conn['merchant_id'] ?? null;
                if ($merchantId) {
                    $merchantKey = "ws_connections:merchant:{$merchantId}";
                    $merchantConnections = $this->getMerchantConnections($merchantId);
                    $merchantConnections = array_filter($merchantConnections, function($conn) use ($fd) {
                        return ($conn['fd'] ?? null) !== $fd;
                    });
                    Cache::set($merchantKey, $merchantConnections, 3600);
                }
            }

            Log::info('移除WebSocket连接', ['fd' => $fd]);

            return true;

        } catch (\Exception $e) {
            Log::error('移除连接失败', [
                'fd' => $fd,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 更新连接心跳时间
     *
     * @param int $fd 连接ID
     * @return bool
     */
    public function updateHeartbeat(int $fd): bool
    {
        try {
            $allConnections = $this->getAllConnections();

            foreach ($allConnections as &$conn) {
                if (($conn['fd'] ?? null) === $fd) {
                    $conn['last_heartbeat'] = time();
                    break;
                }
            }

            $allKey = 'ws_connections:all';
            Cache::set($allKey, $allConnections, 3600);

            return true;

        } catch (\Exception $e) {
            Log::error('更新心跳失败', [
                'fd' => $fd,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清理过期连接
     *
     * @param int $timeout 超时时间(秒)
     * @return int 清理数量
     */
    public function cleanupExpiredConnections(int $timeout = 180): int
    {
        try {
            $now = time();
            $allConnections = $this->getAllConnections();
            $expiredCount = 0;

            foreach ($allConnections as $conn) {
                $lastHeartbeat = $conn['last_heartbeat'] ?? $conn['connect_time'] ?? 0;
                if ($now - $lastHeartbeat > $timeout) {
                    $fd = $conn['fd'] ?? null;
                    if ($fd !== null) {
                        $this->removeConnection($fd);
                        $expiredCount++;
                    }
                }
            }

            if ($expiredCount > 0) {
                Log::info('清理过期连接', ['count' => $expiredCount]);
            }

            return $expiredCount;

        } catch (\Exception $e) {
            Log::error('清理过期连接失败', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 获取在线统计
     *
     * @return array
     */
    public function getOnlineStats(): array
    {
        $allConnections = $this->getAllConnections();

        $merchantStats = [];
        foreach ($allConnections as $conn) {
            $merchantId = $conn['merchant_id'] ?? null;
            if ($merchantId) {
                if (!isset($merchantStats[$merchantId])) {
                    $merchantStats[$merchantId] = 0;
                }
                $merchantStats[$merchantId]++;
            }
        }

        return [
            'total_connections' => count($allConnections),
            'merchant_count' => count($merchantStats),
            'merchant_stats' => $merchantStats,
            'timestamp' => time()
        ];
    }

    /**
     * 获取状态文本
     *
     * @param string $status
     * @return string
     */
    protected function getStatusText(string $status): string
    {
        $statusMap = [
            'online' => '在线',
            'offline' => '离线',
            'active' => '活跃',
            'inactive' => '非活跃'
        ];

        return $statusMap[$status] ?? $status;
    }

    /**
     * 推送批量告警
     *
     * @param array $alerts 告警列表
     * @return bool
     */
    public function pushBatchAlerts(array $alerts): bool
    {
        try {
            // 按商家分组
            $groupedAlerts = [];
            foreach ($alerts as $alert) {
                $merchantId = $alert['merchant_id'] ?? null;
                if ($merchantId) {
                    if (!isset($groupedAlerts[$merchantId])) {
                        $groupedAlerts[$merchantId] = [];
                    }
                    $groupedAlerts[$merchantId][] = $alert;
                }
            }

            // 推送到各商家
            $successCount = 0;
            foreach ($groupedAlerts as $merchantId => $merchantAlerts) {
                $message = [
                    'type' => self::TYPE_ALERT,
                    'merchant_id' => $merchantId,
                    'data' => [
                        'batch' => true,
                        'count' => count($merchantAlerts),
                        'alerts' => $merchantAlerts
                    ],
                    'timestamp' => time()
                ];

                if ($this->pushToMerchant($merchantId, $message)) {
                    $successCount++;
                }
            }

            Log::info('批量推送告警完成', [
                'total_merchants' => count($groupedAlerts),
                'success' => $successCount
            ]);

            return $successCount > 0;

        } catch (\Exception $e) {
            Log::error('批量推送告警失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
