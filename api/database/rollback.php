<?php
/**
 * 数据库迁移回滚脚本
 * 用于回滚数据库迁移操作
 */

require_once __DIR__ . '/test_connection.php';

class MigrationRollback {
    private $pdo;
    private $config;
    private $rollbackPath;
    private $logEnabled;

    public function __construct($rollbackPath = null) {
        $this->rollbackPath = $rollbackPath ?: __DIR__ . '/rollbacks';
        $this->logEnabled = true;

        // 确保回滚目录存在
        if (!is_dir($this->rollbackPath)) {
            mkdir($this->rollbackPath, 0755, true);
        }
    }

    /**
     * 初始化数据库连接
     */
    public function initialize() {
        $connection = testDatabaseConnection();
        if (!$connection) {
            throw new Exception('数据库连接初始化失败');
        }

        $this->pdo = $connection['pdo'];
        $this->config = $connection['config'];

        $this->log('数据库连接初始化成功');
        return $this;
    }

    /**
     * 生成回滚SQL文件
     */
    public function generateRollbackFiles() {
        $this->log('=== 生成回滚SQL文件 ===');

        $rollbacks = [
            '20250929223848_rollback_content_templates_table.sql' => "-- 回滚内容模板表\nDROP TABLE IF EXISTS `xmt_content_templates`;",
            '20250929222838_rollback_content_tasks_table.sql' => "-- 回滚内容任务表\nDROP TABLE IF EXISTS `xmt_content_tasks`;",
            '20250929221354_rollback_nfc_devices_table.sql' => "-- 回滚NFC设备表\nDROP TABLE IF EXISTS `xmt_nfc_devices`;",
            '20250929220835_rollback_merchants_table.sql' => "-- 回滚商家表\nDROP TABLE IF EXISTS `xmt_merchants`;",
            '20250929215341_rollback_users_table.sql' => "-- 回滚用户表\nDROP TABLE IF EXISTS `xmt_user`;",
            '20250929000000_rollback_migration_log_table.sql' => "-- 回滚迁移记录表\nDROP TABLE IF EXISTS `xmt_migration_log`;",
        ];

        foreach ($rollbacks as $filename => $sql) {
            $filepath = $this->rollbackPath . '/' . $filename;
            file_put_contents($filepath, $sql);
            $this->log("✓ 生成回滚文件: $filename");
        }

        $this->log('回滚文件生成完成');
        return $this;
    }

    /**
     * 获取最后一个批次
     */
    public function getLastBatch() {
        $tableName = $this->config['prefix'] . 'migration_log';

        try {
            $stmt = $this->pdo->query("SELECT MAX(batch) FROM `$tableName`");
            return $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 获取指定批次的迁移
     */
    public function getMigrationsByBatch($batch) {
        $tableName = $this->config['prefix'] . 'migration_log';

        try {
            $stmt = $this->pdo->prepare("
                SELECT migration_name, executed_at
                FROM `$tableName`
                WHERE batch = ?
                ORDER BY executed_at DESC
            ");
            $stmt->execute([$batch]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 回滚指定批次
     */
    public function rollbackBatch($batch = null) {
        if ($batch === null) {
            $batch = $this->getLastBatch();
        }

        if ($batch <= 0) {
            $this->log('没有可回滚的迁移');
            return true;
        }

        $this->log("=== 回滚批次 $batch ===");

        $migrations = $this->getMigrationsByBatch($batch);

        if (empty($migrations)) {
            $this->log("批次 $batch 中没有找到迁移记录");
            return true;
        }

        $this->log('准备回滚以下迁移:');
        foreach ($migrations as $migration) {
            $this->log("  - {$migration['migration_name']} (执行于 {$migration['executed_at']})");
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($migrations as $migration) {
            try {
                $this->rollbackSingleMigration($migration['migration_name']);
                $successCount++;
            } catch (Exception $e) {
                $failureCount++;
                $this->log("回滚失败: " . $e->getMessage());
                break;
            }
        }

        $this->log('');
        $this->log('=== 回滚完成 ===');
        $this->log("成功: $successCount 个");
        $this->log("失败: $failureCount 个");

        return $failureCount === 0;
    }

    /**
     * 回滚单个迁移
     */
    public function rollbackSingleMigration($migrationName) {
        $this->log("回滚迁移: $migrationName");

        // 生成回滚文件名
        $rollbackName = str_replace('create_', 'rollback_', $migrationName);
        $rollbackFile = $this->rollbackPath . '/' . $rollbackName;

        try {
            // 开始事务
            $this->pdo->beginTransaction();

            // 如果存在专门的回滚文件，执行它
            if (file_exists($rollbackFile)) {
                $sql = file_get_contents($rollbackFile);
                $statements = $this->splitSqlStatements($sql);

                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $this->pdo->exec($statement);
                    }
                }
            } else {
                // 否则，根据迁移名称自动生成回滚操作
                $this->autoGenerateRollback($migrationName);
            }

            // 从迁移记录中删除
            $this->removeMigrationRecord($migrationName);

            // 提交事务
            $this->pdo->commit();

            $this->log("✓ 回滚成功: $migrationName");
            return true;

        } catch (Exception $e) {
            // 回滚事务
            $this->pdo->rollBack();
            $this->log("✗ 回滚失败: $migrationName - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 自动生成回滚操作
     */
    private function autoGenerateRollback($migrationName) {
        // 根据迁移名称推断表名
        $tableMappings = [
            'create_users_table' => 'xmt_user',
            'create_merchants_table' => 'xmt_merchants',
            'create_nfc_devices_table' => 'xmt_nfc_devices',
            'create_content_tasks_table' => 'xmt_content_tasks',
            'create_content_templates_table' => 'xmt_content_templates',
            'create_migration_log_table' => 'xmt_migration_log',
        ];

        foreach ($tableMappings as $pattern => $tableName) {
            if (strpos($migrationName, $pattern) !== false) {
                $this->log("  自动删除表: $tableName");
                $this->pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                return;
            }
        }

        throw new Exception("无法自动生成回滚操作: $migrationName");
    }

    /**
     * 从迁移记录中删除
     */
    private function removeMigrationRecord($migrationName) {
        $tableName = $this->config['prefix'] . 'migration_log';

        $stmt = $this->pdo->prepare("DELETE FROM `$tableName` WHERE migration_name = ?");
        $stmt->execute([$migrationName]);
    }

    /**
     * 分割SQL语句
     */
    private function splitSqlStatements($sql) {
        // 移除注释
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // 按分号分割语句
        $statements = explode(';', $sql);

        return array_filter(array_map('trim', $statements));
    }

    /**
     * 完全重置数据库
     */
    public function resetDatabase() {
        $this->log('=== 完全重置数据库 ===');

        $tables = [
            'xmt_content_templates',
            'xmt_content_tasks',
            'xmt_nfc_devices',
            'xmt_merchants',
            'xmt_user',
            'xmt_migration_log',
        ];

        try {
            // 禁用外键检查
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
                $this->log("✓ 删除表: $table");
            }

            // 重新启用外键检查
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $this->log('数据库重置完成');
            return true;

        } catch (Exception $e) {
            $this->log("重置失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 显示回滚状态
     */
    public function showStatus() {
        $this->log('=== 回滚状态 ===');

        $lastBatch = $this->getLastBatch();
        $this->log("最后批次: $lastBatch");

        if ($lastBatch > 0) {
            $migrations = $this->getMigrationsByBatch($lastBatch);
            $this->log("当前批次迁移数: " . count($migrations));

            $this->log('当前批次迁移:');
            foreach ($migrations as $migration) {
                $this->log("  - {$migration['migration_name']} (执行于 {$migration['executed_at']})");
            }
        } else {
            $this->log('没有已执行的迁移');
        }
    }

    /**
     * 日志输出
     */
    private function log($message) {
        if ($this->logEnabled) {
            echo date('Y-m-d H:i:s') . " - $message\n";
        }
    }

    /**
     * 禁用日志
     */
    public function disableLogging() {
        $this->logEnabled = false;
        return $this;
    }

    /**
     * 启用日志
     */
    public function enableLogging() {
        $this->logEnabled = true;
        return $this;
    }
}

// 如果直接运行此脚本，则执行回滚
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $rollback = new MigrationRollback();
        $rollback->initialize()
                 ->generateRollbackFiles()
                 ->showStatus();

        echo "\n";
        echo "回滚选项:\n";
        echo "1. 回滚最后一个批次\n";
        echo "2. 完全重置数据库\n";
        echo "3. 退出\n";

        $input = readline("请选择操作 (1-3): ");

        switch ($input) {
            case '1':
                $success = $rollback->rollbackBatch();
                if ($success) {
                    echo "\n最后批次回滚完成！\n";
                } else {
                    echo "\n回滚过程中出现错误！\n";
                    exit(1);
                }
                break;

            case '2':
                $confirm = readline("确认要完全重置数据库吗？这将删除所有表！(yes/no): ");
                if (strtolower($confirm) === 'yes') {
                    $rollback->resetDatabase();
                    echo "\n数据库重置完成！\n";
                } else {
                    echo "操作已取消。\n";
                }
                break;

            case '3':
                echo "退出。\n";
                break;

            default:
                echo "无效的选择。\n";
                exit(1);
        }

    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        exit(1);
    }
}