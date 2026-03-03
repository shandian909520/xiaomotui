<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Cache;
use think\facade\Db;

/**
 * IP黑名单管理服务
 */
class IpBlacklistService
{
    /**
     * @var array 配置
     */
    protected array $config;

    /**
     * @var string 黑名单缓存前缀
     */
    protected string $prefix;

    /**
     * @var string 黑名单表名
     */
    protected string $table;

    public function __construct()
    {
        $this->config = config('throttle.blacklist', []);
        $this->prefix = $this->config['key_prefix'] ?? 'throttle:blacklist:';
        $this->table = $this->config['table'] ?? 'ip_blacklist';
    }

    /**
     * 检查IP是否在黑名单中
     *
     * @param string $ip
     * @return array|null ['reason' => '', 'blocked_until' => timestamp]
     */
    public function isBlocked(string $ip): ?array
    {
        $cacheKey = $this->getCacheKey($ip);
        $blocked = Cache::get($cacheKey);

        if ($blocked) {
            return $blocked;
        }

        // 从数据库查询
        $record = Db::name($this->table)
            ->where('ip', $ip)
            ->where('status', 'active')
            ->where('blocked_until', '>', time())
            ->find();

        if ($record) {
            $data = [
                'reason' => $record['reason'] ?? '触发频率限制',
                'blocked_until' => $record['blocked_until'],
            ];

            // 回写到缓存
            $ttl = $record['blocked_until'] - time();
            Cache::set($cacheKey, $data, $ttl);

            return $data;
        }

        return null;
    }

    /**
     * 将IP加入黑名单
     *
     * @param string $ip IP地址
     * @param string $reason 封禁原因
     * @param int $duration 封禁时长（分钟）
     * @param bool $permanent 是否永久封禁
     * @return bool
     */
    public function block(string $ip, string $reason = '手动封禁', int $duration = 1440, bool $permanent = false): bool
    {
        $cacheKey = $this->getCacheKey($ip);

        if ($permanent) {
            $blockedUntil = 0; // 0表示永久
            $ttl = 31536000; // 缓存一年
        } else {
            $blockedUntil = time() + ($duration * 60);
            $ttl = $duration * 60;
        }

        // 写入缓存
        Cache::set($cacheKey, [
            'reason' => $reason,
            'blocked_until' => $blockedUntil,
        ], $ttl);

        // 写入数据库
        try {
            $exists = Db::name($this->table)
                ->where('ip', $ip)
                ->find();

            if ($exists) {
                Db::name($this->table)
                    ->where('ip', $ip)
                    ->update([
                        'status' => 'active',
                        'reason' => $reason,
                        'blocked_until' => $blockedUntil,
                        'updated_at' => time(),
                    ]);
            } else {
                Db::name($this->table)->insert([
                    'ip' => $ip,
                    'status' => 'active',
                    'reason' => $reason,
                    'blocked_until' => $blockedUntil,
                    'blocked_at' => time(),
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
            }
        } catch (\Exception $e) {
            // 数据库写入失败不影响缓存
            trace('IP黑名单数据库写入失败: ' . $e->getMessage(), 'error');
        }

        return true;
    }

    /**
     * 自动封禁IP（触发频率限制时）
     *
     * @param string $ip
     * @param int $violationCount 违规次数
     * @return bool
     */
    public function autoBlock(string $ip, int $violationCount = 1): bool
    {
        $threshold = $this->config['auto_block_threshold'] ?? 5;
        $duration = $this->config['auto_block_duration'] ?? 1440;

        // 检查违规次数
        $violationKey = $this->prefix . 'violation:' . $ip;
        $count = Cache::get($violationKey, 0) + 1;
        Cache::set($violationKey, $count, 3600); // 违规计数1小时

        if ($count >= $threshold) {
            // 达到阈值，自动封禁
            $this->block($ip, "触发频率限制 {$count} 次", $duration);
            Cache::delete($violationKey); // 清空违规计数
            return true;
        }

        return false;
    }

    /**
     * 从黑名单移除IP
     *
     * @param string $ip
     * @return bool
     */
    public function unblock(string $ip): bool
    {
        $cacheKey = $this->getCacheKey($ip);

        // 删除缓存
        Cache::delete($cacheKey);

        // 更新数据库状态
        try {
            Db::name($this->table)
                ->where('ip', $ip)
                ->update([
                    'status' => 'inactive',
                    'updated_at' => time(),
                ]);
        } catch (\Exception $e) {
            trace('IP黑名单数据库更新失败: ' . $e->getMessage(), 'error');
        }

        return true;
    }

    /**
     * 获取黑名单列表
     *
     * @param int $page
     * @param int $pageSize
     * @param string|null $status
     * @return array
     */
    public function getList(int $page = 1, int $pageSize = 20, ?string $status = null): array
    {
        $query = Db::name($this->table);

        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query
            ->order('created_at', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        // 转换时间戳
        foreach ($list as &$item) {
            $item['blocked_at_formatted'] = $item['blocked_at'] ? date('Y-m-d H:i:s', $item['blocked_at']) : '';
            $item['blocked_until_formatted'] = $item['blocked_until'] ? date('Y-m-d H:i:s', $item['blocked_until']) : '永久';
        }

        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'list' => $list,
        ];
    }

    /**
     * 清空黑名单
     *
     * @param bool $clearAll 是否清空所有（包括永久封禁）
     * @return int 清空数量
     */
    public function clear(bool $clearAll = false): int
    {
        $query = Db::name($this->table);

        if (!$clearAll) {
            $query->where('status', 'active')->where('blocked_until', '>', 0);
        }

        $count = $query->count();

        Db::name($this->table)
            ->where('status', 'active')
            ->update(['status' => 'inactive', 'updated_at' => time()]);

        // 清空所有缓存
        $cacheKeys = Cache::get($this->prefix . 'keys', []);
        foreach ($cacheKeys as $key) {
            Cache::delete($key);
        }
        Cache::delete($this->prefix . 'keys');

        return $count;
    }

    /**
     * 获取IP访问统计
     *
     * @param string $ip
     * @return array
     */
    public function getIpStats(string $ip): array
    {
        $stats = [
            'ip' => $ip,
            'is_blocked' => false,
            'block_info' => null,
            'violation_count' => 0,
            'request_count_today' => 0,
        ];

        // 检查是否被封禁
        $blocked = $this->isBlocked($ip);
        if ($blocked) {
            $stats['is_blocked'] = true;
            $stats['block_info'] = $blocked;
        }

        // 违规次数
        $violationKey = $this->prefix . 'violation:' . $ip;
        $stats['violation_count'] = Cache::get($violationKey, 0);

        // 今天的请求次数（从日志统计，这里简化处理）
        $requestKey = $this->prefix . 'requests:' . date('Ymd') . ':' . $ip;
        $stats['request_count_today'] = Cache::get($requestKey, 0);

        return $stats;
    }

    /**
     * 批量添加IP到黑名单
     *
     * @param array $ips
     * @param string $reason
     * @param int $duration
     * @return array
     */
    public function batchBlock(array $ips, string $reason = '批量封禁', int $duration = 1440): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $this->block($ip, $reason, $duration);
                $results['success'][] = $ip;
            } else {
                $results['failed'][] = [
                    'ip' => $ip,
                    'reason' => '无效的IP地址',
                ];
            }
        }

        return $results;
    }

    /**
     * 批量移除IP
     *
     * @param array $ips
     * @return array
     */
    public function batchUnblock(array $ips): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($ips as $ip) {
            if ($this->unblock($ip)) {
                $results['success'][] = $ip;
            } else {
                $results['failed'][] = $ip;
            }
        }

        return $results;
    }

    /**
     * 导出黑名单
     *
     * @param string|null $status
     * @return array
     */
    public function export(?string $status = null): array
    {
        $query = Db::name($this->table);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->order('created_at', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取缓存Key
     *
     * @param string $ip
     * @return string
     */
    protected function getCacheKey(string $ip): string
    {
        return $this->prefix . $ip;
    }

    /**
     * 记录请求次数（用于统计）
     *
     * @param string $ip
     * @return void
     */
    public function recordRequest(string $ip): void
    {
        $requestKey = $this->prefix . 'requests:' . date('Ymd') . ':' . $ip;
        Cache::inc($requestKey);
        Cache::expire($requestKey, 86400 * 2); // 保留2天
    }
}
