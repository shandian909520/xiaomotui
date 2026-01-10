<?php
/**
 * 数据库连接测试脚本
 * 用于测试数据库连接配置是否正确
 */

// 加载环境变量（模拟）
function env($key, $default = null) {
    // 这里可以根据实际情况读取环境变量
    $env_values = [
        'database.type' => 'mysql',
        'database.hostname' => '127.0.0.1',
        'database.database' => 'xiaomotui',
        'database.username' => 'root',
        'database.password' => '',
        'database.hostport' => '3306',
        'database.charset' => 'utf8mb4',
        'database.collation' => 'utf8mb4_unicode_ci',
        'database.prefix' => 'xmt_',
    ];

    return $env_values[$key] ?? $default;
}

/**
 * 测试数据库连接
 */
function testDatabaseConnection() {
    try {
        // 数据库配置
        $config = [
            'host' => env('database.hostname', '127.0.0.1'),
            'port' => env('database.hostport', '3306'),
            'database' => env('database.database', 'xiaomotui'),
            'username' => env('database.username', 'root'),
            'password' => env('database.password', ''),
            'charset' => env('database.charset', 'utf8mb4'),
            'collation' => env('database.collation', 'utf8mb4_unicode_ci'),
            'prefix' => env('database.prefix', 'xmt_'),
        ];

        echo "正在测试数据库连接...\n";
        echo "主机: {$config['host']}:{$config['port']}\n";
        echo "数据库: {$config['database']}\n";
        echo "用户名: {$config['username']}\n";
        echo "字符集: {$config['charset']}\n";
        echo "表前缀: {$config['prefix']}\n";
        echo "-------------------\n";

        // 创建数据库连接
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}"
        ]);

        echo "✓ 数据库服务器连接成功\n";

        // 检查数据库是否存在
        $result = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'")->fetch();
        if ($result) {
            echo "✓ 数据库 '{$config['database']}' 存在\n";
        } else {
            echo "! 数据库 '{$config['database']}' 不存在，尝试创建...\n";
            $pdo->exec("CREATE DATABASE `{$config['database']}` CHARACTER SET {$config['charset']} COLLATE {$config['collation']}");
            echo "✓ 数据库 '{$config['database']}' 创建成功\n";
        }

        // 选择数据库
        $pdo->exec("USE `{$config['database']}`");
        echo "✓ 数据库选择成功\n";

        // 测试表前缀
        echo "✓ 表前缀配置: {$config['prefix']}\n";

        // 返回连接对象和配置
        return ['pdo' => $pdo, 'config' => $config];

    } catch (PDOException $e) {
        echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
        return false;
    } catch (Exception $e) {
        echo "✗ 错误: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * 检查迁移记录表是否存在
 */
function checkMigrationTable($pdo, $prefix) {
    try {
        $tableName = $prefix . 'migration_log';
        $result = $pdo->query("SHOW TABLES LIKE '$tableName'")->fetch();
        return (bool)$result;
    } catch (Exception $e) {
        return false;
    }
}

// 如果直接运行此脚本，则执行测试
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "=== 数据库连接测试 ===\n";
    $connection = testDatabaseConnection();

    if ($connection) {
        echo "\n=== 检查迁移表 ===\n";
        $hasMigrationTable = checkMigrationTable($connection['pdo'], $connection['config']['prefix']);

        if ($hasMigrationTable) {
            echo "✓ 迁移记录表已存在\n";
        } else {
            echo "! 迁移记录表不存在，需要创建\n";
        }

        echo "\n=== 测试完成 ===\n";
        echo "数据库连接正常，可以执行迁移操作\n";
    } else {
        echo "\n=== 测试失败 ===\n";
        echo "请检查数据库配置并确保数据库服务正在运行\n";
        exit(1);
    }
}