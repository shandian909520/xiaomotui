<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;

/**
 * 数据库健康检查命令
 */
class DatabaseHealthCheck extends Command
{
    protected function configure()
    {
        $this->setName('health:database')
            ->setDescription('数据库健康检查');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('<info>开始数据库健康检查...</info>');

        $results = [];
        $allHealthy = true;

        // 检查MySQL连接
        $mysqlResult = $this->checkMysqlConnection();
        $results['mysql'] = $mysqlResult;
        if (!$mysqlResult['healthy']) {
            $allHealthy = false;
        }

        // 检查Redis连接
        $redisResult = $this->checkRedisConnection();
        $results['redis'] = $redisResult;
        if (!$redisResult['healthy']) {
            $allHealthy = false;
        }

        // 检查数据库性能
        $performanceResult = $this->checkDatabasePerformance();
        $results['performance'] = $performanceResult;
        if (!$performanceResult['healthy']) {
            $allHealthy = false;
        }

        // 输出检查结果
        $this->outputResults($output, $results, $allHealthy);

        // 记录检查结果
        $this->logResults($results, $allHealthy);

        // 保存监控数据
        $this->saveMonitoringData($results);

        return $allHealthy ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * 检查MySQL连接
     */
    private function checkMysqlConnection(): array
    {
        $result = [
            'healthy' => false,
            'message' => '',
            'metrics' => [],
            'timestamp' => time()
        ];

        try {
            $startTime = microtime(true);

            // 测试连接
            $connection = Db::connect();
            $connection->query('SELECT 1');

            $connectionTime = (microtime(true) - $startTime) * 1000;

            // 获取连接信息
            $connectionInfo = $connection->query('SHOW STATUS LIKE "Threads_connected"');
            $maxConnections = $connection->query('SHOW VARIABLES LIKE "max_connections"');

            $threadsConnected = isset($connectionInfo[0]['Value']) ? (int)$connectionInfo[0]['Value'] : 0;
            $maxConnectionsValue = isset($maxConnections[0]['Value']) ? (int)$maxConnections[0]['Value'] : 0;

            $connectionUsageRate = $maxConnectionsValue > 0 ? ($threadsConnected / $maxConnectionsValue) * 100 : 0;

            $result['healthy'] = true;
            $result['message'] = 'MySQL连接正常';
            $result['metrics'] = [
                'connection_time_ms' => round($connectionTime, 2),
                'threads_connected' => $threadsConnected,
                'max_connections' => $maxConnectionsValue,
                'connection_usage_rate' => round($connectionUsageRate, 2)
            ];

            // 检查连接时间是否超过阈值
            $threshold = Config::get('monitor.database.connection_timeout_threshold', 10) * 1000;
            if ($connectionTime > $threshold) {
                $result['healthy'] = false;
                $result['message'] = "MySQL连接时间过长: {$connectionTime}ms";
            }

            // 检查连接使用率
            $usageThreshold = Config::get('monitor.database.pool.max_connections_warning', 0.9) * 100;
            if ($connectionUsageRate > $usageThreshold) {
                $result['healthy'] = false;
                $result['message'] = "MySQL连接使用率过高: {$connectionUsageRate}%";
            }

        } catch (\Exception $e) {
            $result['message'] = 'MySQL连接失败: ' . $e->getMessage();
            Log::error('MySQL健康检查失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $result;
    }

    /**
     * 检查Redis连接
     */
    private function checkRedisConnection(): array
    {
        $result = [
            'healthy' => false,
            'message' => '',
            'metrics' => [],
            'timestamp' => time()
        ];

        try {
            $startTime = microtime(true);

            // 测试Redis连接
            $redis = Cache::store('redis');
            $testKey = 'health_check_' . time();
            $redis->set($testKey, 'test', 10);
            $value = $redis->get($testKey);
            $redis->delete($testKey);

            $connectionTime = (microtime(true) - $startTime) * 1000;

            if ($value === 'test') {
                // 获取Redis信息
                $redisHandler = $redis->handler();
                if (method_exists($redisHandler, 'info')) {
                    $info = $redisHandler->info();

                    $result['healthy'] = true;
                    $result['message'] = 'Redis连接正常';
                    $result['metrics'] = [
                        'connection_time_ms' => round($connectionTime, 2),
                        'connected_clients' => isset($info['connected_clients']) ? (int)$info['connected_clients'] : 0,
                        'used_memory' => isset($info['used_memory']) ? (int)$info['used_memory'] : 0,
                        'used_memory_human' => isset($info['used_memory_human']) ? $info['used_memory_human'] : 'N/A',
                        'redis_version' => isset($info['redis_version']) ? $info['redis_version'] : 'N/A'
                    ];

                    // 检查连接时间
                    $threshold = Config::get('monitor.redis.connection_timeout_threshold', 5) * 1000;
                    if ($connectionTime > $threshold) {
                        $result['healthy'] = false;
                        $result['message'] = "Redis连接时间过长: {$connectionTime}ms";
                    }

                    // 检查内存使用
                    $memoryThreshold = Config::get('monitor.redis.memory_warning_threshold', 1073741824);
                    if (isset($info['used_memory']) && $info['used_memory'] > $memoryThreshold) {
                        $result['healthy'] = false;
                        $result['message'] = "Redis内存使用过高: " . $info['used_memory_human'];
                    }
                } else {
                    $result['healthy'] = true;
                    $result['message'] = 'Redis连接正常（无法获取详细信息）';
                    $result['metrics']['connection_time_ms'] = round($connectionTime, 2);
                }
            } else {
                $result['message'] = 'Redis读写测试失败';
            }

        } catch (\Exception $e) {
            $result['message'] = 'Redis连接失败: ' . $e->getMessage();
            Log::error('Redis健康检查失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $result;
    }

    /**
     * 检查数据库性能
     */
    private function checkDatabasePerformance(): array
    {
        $result = [
            'healthy' => true,
            'message' => '数据库性能正常',
            'metrics' => [],
            'timestamp' => time()
        ];

        try {
            $connection = Db::connect();

            // 检查慢查询
            $slowQueries = $connection->query('SHOW STATUS LIKE "Slow_queries"');
            $queries = $connection->query('SHOW STATUS LIKE "Queries"');

            $slowQueryCount = isset($slowQueries[0]['Value']) ? (int)$slowQueries[0]['Value'] : 0;
            $totalQueries = isset($queries[0]['Value']) ? (int)$queries[0]['Value'] : 1;

            $slowQueryRate = ($slowQueryCount / $totalQueries) * 100;

            // 检查表锁
            $tableLocks = $connection->query('SHOW STATUS LIKE "Table_locks_waited"');
            $tableLockWaited = isset($tableLocks[0]['Value']) ? (int)$tableLocks[0]['Value'] : 0;

            $result['metrics'] = [
                'slow_queries' => $slowQueryCount,
                'total_queries' => $totalQueries,
                'slow_query_rate' => round($slowQueryRate, 4),
                'table_locks_waited' => $tableLockWaited
            ];

            // 检查慢查询率阈值
            $slowQueryThreshold = Config::get('monitor.database.slow_query_threshold', 2);
            if ($slowQueryRate > $slowQueryThreshold) {
                $result['healthy'] = false;
                $result['message'] = "慢查询率过高: {$slowQueryRate}%";
            }

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['message'] = '数据库性能检查失败: ' . $e->getMessage();
            Log::error('数据库性能检查失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $result;
    }

    /**
     * 输出检查结果
     */
    private function outputResults(Output $output, array $results, bool $allHealthy): void
    {
        $output->writeln('');
        $output->writeln('=== 健康检查结果 ===');

        foreach ($results as $component => $result) {
            $status = $result['healthy'] ? '<info>✓</info>' : '<error>✗</error>';
            $output->writeln("{$status} {$component}: {$result['message']}");

            if (!empty($result['metrics'])) {
                foreach ($result['metrics'] as $key => $value) {
                    $output->writeln("  - {$key}: {$value}");
                }
            }
            $output->writeln('');
        }

        $overallStatus = $allHealthy ? '<info>所有组件健康</info>' : '<error>发现健康问题</error>';
        $output->writeln("总体状态: {$overallStatus}");
    }

    /**
     * 记录检查结果
     */
    private function logResults(array $results, bool $allHealthy): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_healthy' => $allHealthy,
            'results' => $results
        ];

        if ($allHealthy) {
            Log::info('数据库健康检查通过', $logData);
        } else {
            Log::error('数据库健康检查发现问题', $logData);

            // 如果启用了告警，发送告警
            if (Config::get('monitor.alerts.enabled', true)) {
                $this->sendAlert($results);
            }
        }
    }

    /**
     * 保存监控数据
     */
    private function saveMonitoringData(array $results): void
    {
        try {
            $storageType = Config::get('monitor.storage.type', 'redis');
            $timestamp = time();

            $monitoringData = [
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'results' => $results
            ];

            switch ($storageType) {
                case 'redis':
                    $keyPrefix = Config::get('monitor.storage.redis.key_prefix', 'monitor:');
                    $key = $keyPrefix . 'health_check:' . date('Y-m-d', $timestamp);
                    $retentionTime = Config::get('monitor.storage.retention_time', 86400 * 7);

                    Cache::store('redis')->setex($key, $retentionTime, json_encode($monitoringData));
                    break;

                case 'file':
                    $path = Config::get('monitor.storage.file.path', runtime_path('monitor'));
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }

                    $filename = $path . DIRECTORY_SEPARATOR . 'health_check_' . date('Y-m-d') . '.json';
                    file_put_contents($filename, json_encode($monitoringData) . "\n", FILE_APPEND | LOCK_EX);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('监控数据保存失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 发送告警
     */
    private function sendAlert(array $results): void
    {
        try {
            $alertChannels = Config::get('monitor.alerts.channels', []);

            // 检查告警频率限制
            $rateLimit = Config::get('monitor.alerts.rate_limit', 300);
            $cacheKey = 'alert_rate_limit:database_health';

            if (Cache::has($cacheKey)) {
                return; // 在频率限制时间内，不发送告警
            }

            Cache::set($cacheKey, time(), $rateLimit);

            $alertMessage = "数据库健康检查发现问题：\n";
            foreach ($results as $component => $result) {
                if (!$result['healthy']) {
                    $alertMessage .= "- {$component}: {$result['message']}\n";
                }
            }

            // 邮件告警
            if ($alertChannels['email']['enabled'] ?? false) {
                // 这里可以集成邮件发送功能
                Log::info('发送邮件告警', ['message' => $alertMessage]);
            }

            // 日志告警
            if ($alertChannels['log']['enabled'] ?? true) {
                $level = $alertChannels['log']['level'] ?? 'error';
                Log::$level($alertMessage);
            }

        } catch (\Exception $e) {
            Log::error('告警发送失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}