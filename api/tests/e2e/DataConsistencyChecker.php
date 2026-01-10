<?php
declare(strict_types=1);

namespace tests\e2e;

use think\facade\Db;

/**
 * 数据一致性检查器
 * 验证数据完整性和一致性
 */
class DataConsistencyChecker
{
    private array $errors = [];
    private array $warnings = [];

    /**
     * 执行所有一致性检查
     *
     * @return array
     */
    public function checkAll(): array
    {
        echo "\n正在执行数据一致性检查...\n";

        $this->errors = [];
        $this->warnings = [];

        // 1. 外键完整性检查
        $this->checkForeignKeyIntegrity();

        // 2. 数据关联检查
        $this->checkDataLinkage();

        // 3. 孤儿记录检查
        $this->checkOrphanRecords();

        // 4. 统计数据准确性检查
        $this->checkStatisticsAccuracy();

        // 5. 时间戳一致性检查
        $this->checkTimestampConsistency();

        return [
            'passed' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'total_checks' => 5,
            'failed_checks' => count($this->errors),
        ];
    }

    /**
     * 检查外键完整性
     */
    private function checkForeignKeyIntegrity(): void
    {
        echo "  [1/5] 检查外键完整性...\n";

        // 检查 content_tasks.user_id 存在于 users
        $invalidUserIds = Db::query("
            SELECT ct.id, ct.user_id
            FROM content_tasks ct
            LEFT JOIN users u ON ct.user_id = u.id
            WHERE u.id IS NULL AND ct.user_id IS NOT NULL
        ");

        if (!empty($invalidUserIds)) {
            $this->errors[] = [
                'check' => 'foreign_key_integrity',
                'table' => 'content_tasks',
                'field' => 'user_id',
                'message' => '发现 ' . count($invalidUserIds) . ' 条内容任务引用了不存在的用户',
                'details' => $invalidUserIds,
            ];
        }

        // 检查 content_tasks.merchant_id 存在于 merchants
        $invalidMerchantIds = Db::query("
            SELECT ct.id, ct.merchant_id
            FROM content_tasks ct
            LEFT JOIN merchants m ON ct.merchant_id = m.id
            WHERE m.id IS NULL AND ct.merchant_id IS NOT NULL
        ");

        if (!empty($invalidMerchantIds)) {
            $this->errors[] = [
                'check' => 'foreign_key_integrity',
                'table' => 'content_tasks',
                'field' => 'merchant_id',
                'message' => '发现 ' . count($invalidMerchantIds) . ' 条内容任务引用了不存在的商家',
                'details' => $invalidMerchantIds,
            ];
        }

        // 检查 content_tasks.device_id 存在于 nfc_devices
        $invalidDeviceIds = Db::query("
            SELECT ct.id, ct.device_id
            FROM content_tasks ct
            LEFT JOIN nfc_devices nd ON ct.device_id = nd.id
            WHERE nd.id IS NULL AND ct.device_id IS NOT NULL
        ");

        if (!empty($invalidDeviceIds)) {
            $this->errors[] = [
                'check' => 'foreign_key_integrity',
                'table' => 'content_tasks',
                'field' => 'device_id',
                'message' => '发现 ' . count($invalidDeviceIds) . ' 条内容任务引用了不存在的设备',
                'details' => $invalidDeviceIds,
            ];
        }

        // 检查 device_triggers.device_id 存在于 nfc_devices
        $invalidTriggerDeviceIds = Db::query("
            SELECT dt.id, dt.device_id
            FROM device_triggers dt
            LEFT JOIN nfc_devices nd ON dt.device_id = nd.id
            WHERE nd.id IS NULL AND dt.device_id IS NOT NULL
        ");

        if (!empty($invalidTriggerDeviceIds)) {
            $this->errors[] = [
                'check' => 'foreign_key_integrity',
                'table' => 'device_triggers',
                'field' => 'device_id',
                'message' => '发现 ' . count($invalidTriggerDeviceIds) . ' 条设备触发记录引用了不存在的设备',
                'details' => $invalidTriggerDeviceIds,
            ];
        }

        if (empty($this->errors)) {
            echo "    ✓ 外键完整性检查通过\n";
        } else {
            echo "    ✗ 外键完整性检查发现 " . count($this->errors) . " 个问题\n";
        }
    }

    /**
     * 检查数据关联
     */
    private function checkDataLinkage(): void
    {
        echo "  [2/5] 检查数据关联...\n";

        // 检查 device_triggers → content_tasks 的关联
        // 成功的触发应该创建内容任务
        $triggersWithoutTasks = Db::query("
            SELECT dt.id, dt.device_id, dt.user_id, dt.create_time
            FROM device_triggers dt
            LEFT JOIN content_tasks ct ON dt.device_id = ct.device_id
                AND dt.user_id = ct.user_id
                AND dt.create_time <= ct.create_time
                AND DATE(dt.create_time) = DATE(ct.create_time)
            WHERE dt.trigger_status = 'SUCCESS'
                AND dt.trigger_mode IN ('VIDEO', 'MENU')
                AND ct.id IS NULL
                AND dt.create_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");

        if (!empty($triggersWithoutTasks)) {
            $this->warnings[] = [
                'check' => 'data_linkage',
                'message' => '发现 ' . count($triggersWithoutTasks) . ' 条成功的设备触发没有对应的内容任务',
                'details' => $triggersWithoutTasks,
            ];
        }

        // 检查同一流程中的 user_id 一致性
        $inconsistentUserIds = Db::query("
            SELECT dt.id as trigger_id, dt.user_id as trigger_user_id,
                   ct.id as task_id, ct.user_id as task_user_id
            FROM device_triggers dt
            INNER JOIN content_tasks ct ON dt.device_id = ct.device_id
                AND DATE(dt.create_time) = DATE(ct.create_time)
            WHERE dt.user_id != ct.user_id
                AND dt.create_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");

        if (!empty($inconsistentUserIds)) {
            $this->errors[] = [
                'check' => 'data_linkage',
                'message' => '发现 ' . count($inconsistentUserIds) . ' 条记录的用户ID不一致',
                'details' => $inconsistentUserIds,
            ];
        }

        if (empty($this->errors)) {
            echo "    ✓ 数据关联检查通过\n";
        } else {
            echo "    ✗ 数据关联检查发现 " . count(array_filter($this->errors, fn($e) => $e['check'] === 'data_linkage')) . " 个问题\n";
        }
    }

    /**
     * 检查孤儿记录
     */
    private function checkOrphanRecords(): void
    {
        echo "  [3/5] 检查孤儿记录...\n";

        // 检查没有用户的内容任务
        $tasksWithoutUsers = Db::table('content_tasks')
            ->whereNull('user_id')
            ->count();

        if ($tasksWithoutUsers > 0) {
            $this->errors[] = [
                'check' => 'orphan_records',
                'table' => 'content_tasks',
                'message' => '发现 ' . $tasksWithoutUsers . ' 条没有用户的内容任务',
            ];
        }

        // 检查没有设备的触发记录
        $triggersWithoutDevices = Db::table('device_triggers')
            ->whereNull('device_id')
            ->count();

        if ($triggersWithoutDevices > 0) {
            $this->errors[] = [
                'check' => 'orphan_records',
                'table' => 'device_triggers',
                'message' => '发现 ' . $triggersWithoutDevices . ' 条没有设备的触发记录',
            ];
        }

        // 检查没有商家的设备
        $devicesWithoutMerchants = Db::table('nfc_devices')
            ->whereNull('merchant_id')
            ->count();

        if ($devicesWithoutMerchants > 0) {
            $this->warnings[] = [
                'check' => 'orphan_records',
                'table' => 'nfc_devices',
                'message' => '发现 ' . $devicesWithoutMerchants . ' 个没有商家的设备',
            ];
        }

        if (empty($this->errors)) {
            echo "    ✓ 孤儿记录检查通过\n";
        } else {
            echo "    ✗ 孤儿记录检查发现 " . count(array_filter($this->errors, fn($e) => $e['check'] === 'orphan_records')) . " 个问题\n";
        }
    }

    /**
     * 检查统计数据准确性
     */
    private function checkStatisticsAccuracy(): void
    {
        echo "  [4/5] 检查统计数据准确性...\n";

        // 检查设备触发统计
        $devices = Db::table('nfc_devices')
            ->where('device_code', 'like', 'E2E_TEST_%')
            ->select();

        foreach ($devices as $device) {
            // 计算实际触发次数
            $actualTriggers = Db::table('device_triggers')
                ->where('device_id', $device['id'])
                ->count();

            // 如果设备有统计字段，验证准确性
            if (isset($device['trigger_count']) && $device['trigger_count'] != $actualTriggers) {
                $this->warnings[] = [
                    'check' => 'statistics_accuracy',
                    'table' => 'nfc_devices',
                    'device_id' => $device['id'],
                    'message' => "设备触发统计不准确: 记录显示 {$device['trigger_count']}, 实际 {$actualTriggers}",
                ];
            }
        }

        // 检查内容任务统计
        $users = Db::table('users')
            ->where('openid', 'like', 'test_e2e_%')
            ->select();

        foreach ($users as $user) {
            $actualTasks = Db::table('content_tasks')
                ->where('user_id', $user['id'])
                ->count();

            // 如果用户有统计字段，验证准确性
            if (isset($user['task_count']) && $user['task_count'] != $actualTasks) {
                $this->warnings[] = [
                    'check' => 'statistics_accuracy',
                    'table' => 'users',
                    'user_id' => $user['id'],
                    'message' => "用户任务统计不准确: 记录显示 {$user['task_count']}, 实际 {$actualTasks}",
                ];
            }
        }

        echo "    ✓ 统计数据准确性检查完成\n";
    }

    /**
     * 检查时间戳一致性
     */
    private function checkTimestampConsistency(): void
    {
        echo "  [5/5] 检查时间戳一致性...\n";

        // 检查 create_time <= update_time
        $inconsistentTimestamps = Db::query("
            SELECT 'content_tasks' as table_name, id, create_time, update_time
            FROM content_tasks
            WHERE create_time > update_time
            UNION ALL
            SELECT 'device_triggers' as table_name, id, create_time, update_time
            FROM device_triggers
            WHERE create_time > update_time
        ");

        if (!empty($inconsistentTimestamps)) {
            $this->errors[] = [
                'check' => 'timestamp_consistency',
                'message' => '发现 ' . count($inconsistentTimestamps) . ' 条记录的创建时间晚于更新时间',
                'details' => $inconsistentTimestamps,
            ];
        }

        // 检查完成时间的合理性
        $invalidCompleteTimes = Db::query("
            SELECT id, create_time, complete_time
            FROM content_tasks
            WHERE status = 'COMPLETED'
                AND complete_time IS NOT NULL
                AND complete_time < create_time
        ");

        if (!empty($invalidCompleteTimes)) {
            $this->errors[] = [
                'check' => 'timestamp_consistency',
                'message' => '发现 ' . count($invalidCompleteTimes) . ' 条任务的完成时间早于创建时间',
                'details' => $invalidCompleteTimes,
            ];
        }

        // 检查时间顺序: trigger_time < task_create_time < task_complete_time
        $invalidSequence = Db::query("
            SELECT dt.id as trigger_id, dt.create_time as trigger_time,
                   ct.id as task_id, ct.create_time as task_create_time, ct.complete_time
            FROM device_triggers dt
            INNER JOIN content_tasks ct ON dt.device_id = ct.device_id
                AND dt.user_id = ct.user_id
                AND DATE(dt.create_time) = DATE(ct.create_time)
            WHERE dt.create_time > ct.create_time
        ");

        if (!empty($invalidSequence)) {
            $this->errors[] = [
                'check' => 'timestamp_consistency',
                'message' => '发现 ' . count($invalidSequence) . ' 条记录的时间顺序不正确',
                'details' => $invalidSequence,
            ];
        }

        if (empty($this->errors)) {
            echo "    ✓ 时间戳一致性检查通过\n";
        } else {
            echo "    ✗ 时间戳一致性检查发现 " . count(array_filter($this->errors, fn($e) => $e['check'] === 'timestamp_consistency')) . " 个问题\n";
        }
    }

    /**
     * 验证特定触发的数据链路
     *
     * @param int $triggerId
     * @return array
     */
    public function verifyTriggerDataChain(int $triggerId): array
    {
        $issues = [];

        // 获取触发记录
        $trigger = Db::table('device_triggers')->find($triggerId);
        if (!$trigger) {
            return ['error' => '触发记录不存在'];
        }

        // 验证设备存在
        $device = Db::table('nfc_devices')->find($trigger['device_id']);
        if (!$device) {
            $issues[] = '设备不存在';
        }

        // 验证用户存在
        $user = Db::table('users')->find($trigger['user_id']);
        if (!$user) {
            $issues[] = '用户不存在';
        }

        // 如果是内容生成类型的触发，验证内容任务
        if (in_array($trigger['trigger_mode'], ['VIDEO', 'MENU', 'IMAGE'])) {
            $task = Db::table('content_tasks')
                ->where('device_id', $trigger['device_id'])
                ->where('user_id', $trigger['user_id'])
                ->where('create_time', '>=', $trigger['create_time'])
                ->order('create_time', 'asc')
                ->find();

            if (!$task) {
                $issues[] = '未找到对应的内容任务';
            } else {
                // 验证用户ID一致
                if ($task['user_id'] != $trigger['user_id']) {
                    $issues[] = '内容任务的用户ID与触发记录不一致';
                }

                // 验证时间顺序
                if ($task['create_time'] < $trigger['create_time']) {
                    $issues[] = '内容任务的创建时间早于触发时间';
                }
            }
        }

        return [
            'trigger_id' => $triggerId,
            'valid' => empty($issues),
            'issues' => $issues,
            'details' => [
                'trigger' => $trigger,
                'device' => $device ?? null,
                'user' => $user ?? null,
                'task' => $task ?? null,
            ],
        ];
    }

    /**
     * 获取错误列表
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取警告列表
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * 是否有错误
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
