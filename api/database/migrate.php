<?php
/**
 * 数据库迁移执行脚本
 * 用于执行数据库迁移文件并跟踪迁移状态
 */

require_once __DIR__ . '/test_connection.php';

class MigrationRunner {
    private $pdo;
    private $config;
    private $migrationPath;
    private $logEnabled;

    public function __construct($migrationPath = null) {
        $this->migrationPath = $migrationPath ?: __DIR__ . '/migrations';
        $this->logEnabled = true;
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
     * 确保迁移记录表存在
     */
    public function ensureMigrationTable() {
        $tableName = $this->config['prefix'] . 'migration_log';

        if (!checkMigrationTable($this->pdo, $this->config['prefix'])) {
            $this->log('创建迁移记录表...');

            $migrationTableSql = __DIR__ . '/migrations/20250929000000_create_migration_log_table.sql';
            if (file_exists($migrationTableSql)) {
                $this->executeSqlFile($migrationTableSql, false); // 不记录到迁移表
                $this->log('迁移记录表创建成功');
            } else {
                // 如果文件不存在，直接创建表
                $sql = "
                CREATE TABLE `$tableName` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
                  `migration_name` varchar(255) NOT NULL COMMENT '迁移文件名',
                  `batch` int(11) NOT NULL COMMENT '批次号',
                  `executed_at` datetime NOT NULL COMMENT '执行时间',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `migration_name` (`migration_name`),
                  KEY `batch` (`batch`),
                  KEY `executed_at` (`executed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录表'
                ";

                $this->pdo->exec($sql);
                $this->log('迁移记录表创建成功');
            }
        } else {
            $this->log('迁移记录表已存在');
        }

        return $this;
    }

    /**
     * 获取所有迁移文件
     */
    public function getMigrationFiles() {
        if (!is_dir($this->migrationPath)) {
            throw new Exception("迁移目录不存在: {$this->migrationPath}");
        }

        $files = glob($this->migrationPath . '/*.sql');

        // 按文件名排序（时间戳排序）
        usort($files, function($a, $b) {
            return basename($a) <=> basename($b);
        });

        return $files;
    }

    /**
     * 获取已执行的迁移
     */
    public function getExecutedMigrations() {
        $tableName = $this->config['prefix'] . 'migration_log';

        try {
            $stmt = $this->pdo->query("SELECT migration_name FROM `$tableName` ORDER BY executed_at");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 获取待执行的迁移
     */
    public function getPendingMigrations() {
        $allFiles = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();

        $pending = [];
        foreach ($allFiles as $file) {
            $fileName = basename($file);
            if (!in_array($fileName, $executed)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    /**
     * 执行单个SQL文件
     */
    public function executeSqlFile($filePath, $recordMigration = true) {
        if (!file_exists($filePath)) {
            throw new Exception("迁移文件不存在: $filePath");
        }

        $fileName = basename($filePath);
        $this->log("执行迁移: $fileName");

        $sql = file_get_contents($filePath);
        if (empty(trim($sql))) {
            $this->log("跳过空文件: $fileName");
            return true;
        }

        try {
            // 分割SQL语句并分类
            $statements = $this->splitSqlStatements($sql);

            // 分类为DDL和DML语句
            $ddlStatements = [];
            $dmlStatements = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;

                // 检查是否为DDL语句
                $upperStatement = strtoupper(substr($statement, 0, 10));
                if (strpos($upperStatement, 'CREATE') !== false ||
                    strpos($upperStatement, 'ALTER') !== false ||
                    strpos($upperStatement, 'DROP') !== false ||
                    strpos($upperStatement, 'TRUNCATE') !== false) {
                    $ddlStatements[] = $statement;
                } else {
                    $dmlStatements[] = $statement;
                }
            }

            // 先执行DDL语句(DDL会自动提交,不需要事务)
            foreach ($ddlStatements as $statement) {
                $this->pdo->exec($statement);
            }

            // 再执行DML语句(使用事务)
            if (!empty($dmlStatements)) {
                $this->pdo->beginTransaction();
                try {
                    foreach ($dmlStatements as $statement) {
                        $this->pdo->exec($statement);
                    }
                    // 记录迁移(在事务中)
                    if ($recordMigration) {
                        $this->recordMigration($fileName);
                    }
                    $this->pdo->commit();
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw $e;
                }
            } else {
                // 如果只有DDL语句,单独记录迁移
                if ($recordMigration) {
                    $this->recordMigration($fileName);
                }
            }

            $this->log("✓ 迁移执行成功: $fileName");
            return true;

        } catch (Exception $e) {
            $this->log("✗ 迁移执行失败: $fileName - " . $e->getMessage());
            throw $e;
        }
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
     * 记录迁移执行
     */
    private function recordMigration($fileName) {
        $tableName = $this->config['prefix'] . 'migration_log';
        $batch = $this->getNextBatch();

        $stmt = $this->pdo->prepare("
            INSERT INTO `$tableName` (migration_name, batch, executed_at)
            VALUES (?, ?, NOW())
        ");

        $stmt->execute([$fileName, $batch]);
    }

    /**
     * 获取下一个批次号
     */
    private function getNextBatch() {
        $tableName = $this->config['prefix'] . 'migration_log';

        $stmt = $this->pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `$tableName`");
        return $stmt->fetchColumn();
    }

    /**
     * 执行所有待执行的迁移
     */
    public function runMigrations() {
        $this->log('=== 开始执行数据库迁移 ===');

        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            $this->log('没有待执行的迁移文件');
            return true;
        }

        $this->log('发现 ' . count($pending) . ' 个待执行的迁移文件:');
        foreach ($pending as $file) {
            $this->log('  - ' . basename($file));
        }

        $this->log('');

        $successCount = 0;
        $failureCount = 0;

        foreach ($pending as $file) {
            try {
                $this->executeSqlFile($file);
                $successCount++;
            } catch (Exception $e) {
                $failureCount++;
                $this->log("迁移失败，停止执行: " . $e->getMessage());
                break;
            }
        }

        $this->log('');
        $this->log('=== 迁移执行完成 ===');
        $this->log("成功: $successCount 个");
        $this->log("失败: $failureCount 个");

        return $failureCount === 0;
    }

    /**
     * 验证数据库表结构
     */
    public function verifyTables() {
        $this->log('=== 验证数据库表结构 ===');

        $expectedTables = [
            'migration_log',
            'user',
            'merchants',
            'nfc_devices',
            'content_tasks',
            'content_templates'
        ];

        $prefix = $this->config['prefix'];
        $allTablesExist = true;

        foreach ($expectedTables as $table) {
            $fullTableName = $prefix . $table;
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$fullTableName'");
            $exists = $stmt->fetch();

            if ($exists) {
                $this->log("✓ 表 '$fullTableName' 存在");

                // 获取表结构信息
                $stmt = $this->pdo->query("DESCRIBE `$fullTableName`");
                $columns = $stmt->fetchAll();
                $this->log("  - 字段数: " . count($columns));

                // 检查字符集
                $stmt = $this->pdo->query("
                    SELECT TABLE_COLLATION
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$fullTableName'
                ");
                $collation = $stmt->fetchColumn();
                $this->log("  - 字符集: $collation");

            } else {
                $this->log("✗ 表 '$fullTableName' 不存在");
                $allTablesExist = false;
            }
        }

        if ($allTablesExist) {
            $this->log('✓ 所有预期表都已创建成功');
        } else {
            $this->log('✗ 部分表创建失败');
        }

        return $allTablesExist;
    }

    /**
     * 显示迁移状态
     */
    public function showStatus() {
        $this->log('=== 迁移状态 ===');

        $all = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        $pending = $this->getPendingMigrations();

        $this->log('总迁移文件: ' . count($all));
        $this->log('已执行: ' . count($executed));
        $this->log('待执行: ' . count($pending));

        if (!empty($executed)) {
            $this->log('');
            $this->log('已执行的迁移:');
            foreach ($executed as $migration) {
                $this->log("  ✓ $migration");
            }
        }

        if (!empty($pending)) {
            $this->log('');
            $this->log('待执行的迁移:');
            foreach ($pending as $file) {
                $this->log("  - " . basename($file));
            }
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

// 如果直接运行此脚本，则执行迁移
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $runner = new MigrationRunner();
        $runner->initialize()
               ->ensureMigrationTable()
               ->showStatus();

        echo "\n";
        
        // 检查命令行参数是否有 -y 或 --yes
        $autoConfirm = false;
        global $argv;
        if (isset($argv) && (in_array('-y', $argv) || in_array('--yes', $argv))) {
            $autoConfirm = true;
        }

        if ($autoConfirm) {
            echo "自动确认执行迁移...\n";
            $input = 'y';
        } else {
            $input = readline("是否执行待执行的迁移? (y/n): ");
        }

        if (strtolower($input) === 'y' || strtolower($input) === 'yes') {
            $success = $runner->runMigrations();

            if ($success) {
                echo "\n";
                $runner->verifyTables();
                echo "\n所有迁移执行完成！\n";
            } else {
                echo "\n迁移执行过程中出现错误！\n";
                exit(1);
            }
        } else {
            echo "迁移执行已取消。\n";
        }

    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        exit(1);
    }
}