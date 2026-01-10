<?php
/**
 * 数据库管理脚本
 * 提供迁移、回滚、状态查看等功能的统一入口
 */

require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/rollback.php';

class DatabaseManager {
    private $migrationRunner;
    private $rollback;

    public function __construct() {
        $this->migrationRunner = new MigrationRunner();
        $this->rollback = new MigrationRollback();
    }

    /**
     * 显示帮助信息
     */
    public function showHelp() {
        echo "=== 小魔推数据库管理工具 ===\n\n";
        echo "用法: php db_manager.php [命令]\n\n";
        echo "可用命令:\n";
        echo "  migrate      - 执行数据库迁移\n";
        echo "  rollback     - 回滚最后一个批次\n";
        echo "  reset        - 完全重置数据库\n";
        echo "  status       - 显示迁移状态\n";
        echo "  test         - 测试数据库连接\n";
        echo "  help         - 显示此帮助信息\n\n";
        echo "示例:\n";
        echo "  php db_manager.php migrate   # 执行迁移\n";
        echo "  php db_manager.php status    # 查看状态\n";
        echo "  php db_manager.php rollback  # 回滚\n\n";
    }

    /**
     * 执行迁移
     */
    public function migrate() {
        try {
            echo "=== 执行数据库迁移 ===\n";

            $this->migrationRunner->initialize()
                                  ->ensureMigrationTable();

            $pending = $this->migrationRunner->getPendingMigrations();

            if (empty($pending)) {
                echo "没有待执行的迁移。\n";
                return true;
            }

            echo "发现 " . count($pending) . " 个待执行的迁移。\n";

            $success = $this->migrationRunner->runMigrations();

            if ($success) {
                $this->migrationRunner->verifyTables();
                echo "\n✓ 迁移执行完成！\n";
            } else {
                echo "\n✗ 迁移执行失败！\n";
                return false;
            }

            return true;

        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 回滚迁移
     */
    public function rollback() {
        try {
            echo "=== 回滚数据库迁移 ===\n";

            $this->rollback->initialize();

            $lastBatch = $this->rollback->getLastBatch();

            if ($lastBatch <= 0) {
                echo "没有可回滚的迁移。\n";
                return true;
            }

            echo "准备回滚批次 $lastBatch\n";

            $success = $this->rollback->rollbackBatch();

            if ($success) {
                echo "\n✓ 回滚完成！\n";
            } else {
                echo "\n✗ 回滚失败！\n";
                return false;
            }

            return true;

        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 重置数据库
     */
    public function reset() {
        try {
            echo "=== 重置数据库 ===\n";
            echo "警告: 这将删除所有表和数据！\n";

            $confirm = readline("确认要继续吗？输入 'yes' 继续: ");

            if (strtolower($confirm) !== 'yes') {
                echo "操作已取消。\n";
                return true;
            }

            $this->rollback->initialize();
            $this->rollback->resetDatabase();

            echo "\n✓ 数据库重置完成！\n";
            echo "提示: 运行 'php db_manager.php migrate' 重新创建表。\n";

            return true;

        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 显示状态
     */
    public function status() {
        try {
            echo "=== 数据库状态 ===\n";

            // 测试连接
            $connection = testDatabaseConnection();
            if (!$connection) {
                echo "数据库连接失败！\n";
                return false;
            }

            echo "数据库连接: ✓\n";
            echo "数据库名: " . $connection['config']['database'] . "\n";
            echo "表前缀: " . $connection['config']['prefix'] . "\n\n";

            // 初始化迁移器
            $this->migrationRunner->initialize();

            // 检查迁移表
            if (checkMigrationTable($connection['pdo'], $connection['config']['prefix'])) {
                echo "迁移记录表: ✓\n";
            } else {
                echo "迁移记录表: ✗ (需要执行迁移)\n";
            }

            // 显示迁移状态
            $this->migrationRunner->showStatus();

            return true;

        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * 测试数据库连接
     */
    public function test() {
        echo "=== 测试数据库连接 ===\n";

        $connection = testDatabaseConnection();

        if ($connection) {
            echo "\n✓ 数据库连接测试成功！\n";
            return true;
        } else {
            echo "\n✗ 数据库连接测试失败！\n";
            return false;
        }
    }

    /**
     * 交互式模式
     */
    public function interactive() {
        while (true) {
            echo "\n=== 小魔推数据库管理 ===\n";
            echo "1. 执行迁移\n";
            echo "2. 回滚迁移\n";
            echo "3. 查看状态\n";
            echo "4. 测试连接\n";
            echo "5. 重置数据库\n";
            echo "6. 退出\n";

            $choice = readline("请选择操作 (1-6): ");

            switch ($choice) {
                case '1':
                    $this->migrate();
                    break;
                case '2':
                    $this->rollback();
                    break;
                case '3':
                    $this->status();
                    break;
                case '4':
                    $this->test();
                    break;
                case '5':
                    $this->reset();
                    break;
                case '6':
                    echo "再见！\n";
                    return;
                default:
                    echo "无效的选择，请重试。\n";
            }

            readline("\n按回车键继续...");
        }
    }

    /**
     * 执行命令
     */
    public function run($command = null) {
        if ($command === null) {
            $this->interactive();
            return;
        }

        switch ($command) {
            case 'migrate':
                return $this->migrate();

            case 'rollback':
                return $this->rollback();

            case 'reset':
                return $this->reset();

            case 'status':
                return $this->status();

            case 'test':
                return $this->test();

            case 'help':
                $this->showHelp();
                return true;

            default:
                echo "未知命令: $command\n\n";
                $this->showHelp();
                return false;
        }
    }
}

// 如果直接运行此脚本
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $manager = new DatabaseManager();

    // 获取命令行参数
    $command = isset($argv[1]) ? $argv[1] : null;

    if ($command === null) {
        $manager->showHelp();
        echo "启动交互模式...\n";
        $manager->interactive();
    } else {
        $success = $manager->run($command);
        exit($success ? 0 : 1);
    }
}