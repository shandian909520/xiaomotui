<?php

// 数据库连接配置测试脚本

echo "=== 小磨推数据库连接配置测试 ===\n\n";

// 从.env文件加载配置
function loadEnvConfig($envFile = '.env') {
    $config = [];
    if (!file_exists($envFile)) {
        echo "错误: .env文件不存在\n";
        return $config;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $currentSection = '';

    foreach ($lines as $line) {
        $line = trim($line);

        // 跳过注释
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // 检查是否是节
        if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $currentSection = strtolower($matches[1]);
            continue;
        }

        // 解析键值对
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($currentSection) {
                $config[$currentSection][$key] = $value;
            } else {
                $config[$key] = $value;
            }
        }
    }

    return $config;
}

// 测试MySQL连接
function testMysqlConnection($config) {
    echo "1. 测试MySQL连接...\n";

    $dbConfig = $config['database'] ?? [];
    $host = $dbConfig['HOSTNAME'] ?? '127.0.0.1';
    $port = $dbConfig['HOSTPORT'] ?? '3306';
    $database = $dbConfig['DATABASE'] ?? 'xiaomotui';
    $username = $dbConfig['USERNAME'] ?? 'root';
    $password = $dbConfig['PASSWORD'] ?? '';
    $charset = $dbConfig['CHARSET'] ?? 'utf8mb4';
    $timeout = (int)($dbConfig['TIMEOUT'] ?? 30);

    echo "   主机: {$host}:{$port}\n";
    echo "   数据库: {$database}\n";
    echo "   用户名: {$username}\n";
    echo "   字符集: {$charset}\n";
    echo "   超时: {$timeout}秒\n";

    try {
        $startTime = microtime(true);

        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        $options = [
            PDO::ATTR_TIMEOUT => $timeout,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);

        $connectionTime = (microtime(true) - $startTime) * 1000;
        echo "   ✓ 连接成功! 耗时: " . round($connectionTime, 2) . "ms\n";

        // 测试选择数据库
        try {
            $pdo->exec("USE `{$database}`");
            echo "   ✓ 数据库 '{$database}' 存在并可访问\n";
        } catch (PDOException $e) {
            echo "   ⚠ 数据库 '{$database}' 不存在或无权访问: " . $e->getMessage() . "\n";
            echo "   尝试创建数据库...\n";

            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
                echo "   ✓ 数据库 '{$database}' 创建成功\n";
            } catch (PDOException $e) {
                echo "   ✗ 数据库创建失败: " . $e->getMessage() . "\n";
            }
        }

        // 测试基本查询
        $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_time");
        $result = $stmt->fetch();
        echo "   MySQL版本: {$result['version']}\n";
        echo "   当前时间: {$result['current_time']}\n";

        // 检查表前缀设置
        $prefix = $dbConfig['PREFIX'] ?? 'xmt_';
        echo "   表前缀: {$prefix}\n";

        echo "   ✓ MySQL连接测试完成\n\n";
        return true;

    } catch (PDOException $e) {
        echo "   ✗ MySQL连接失败: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// 测试Redis连接
function testRedisConnection($config) {
    echo "2. 测试Redis连接...\n";

    $redisConfig = $config['redis'] ?? [];
    $host = $redisConfig['HOST'] ?? '127.0.0.1';
    $port = (int)($redisConfig['PORT'] ?? 6379);
    $password = $redisConfig['PASSWORD'] ?? '';
    $database = (int)($redisConfig['SELECT'] ?? 0);
    $timeout = (float)($redisConfig['TIMEOUT'] ?? 5.0);
    $prefix = $redisConfig['PREFIX'] ?? 'xmt:';

    echo "   主机: {$host}:{$port}\n";
    echo "   数据库: {$database}\n";
    echo "   前缀: {$prefix}\n";
    echo "   超时: {$timeout}秒\n";

    if (!extension_loaded('redis')) {
        echo "   ✗ Redis PHP扩展未安装\n";

        // 尝试使用原生socket连接
        echo "   尝试使用socket连接...\n";

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            echo "   ✓ Redis服务器可访问 (socket连接成功)\n";
            fclose($socket);
            echo "   ⚠ 建议安装Redis PHP扩展以获得更好的性能\n\n";
            return false;
        } else {
            echo "   ✗ Redis服务器不可访问: {$errstr}\n\n";
            return false;
        }
    }

    try {
        $startTime = microtime(true);

        $redis = new Redis();

        // 连接配置
        $connected = $redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new Exception("无法连接到Redis服务器");
        }

        $connectionTime = (microtime(true) - $startTime) * 1000;
        echo "   ✓ 连接成功! 耗时: " . round($connectionTime, 2) . "ms\n";

        // 认证
        if (!empty($password)) {
            if (!$redis->auth($password)) {
                throw new Exception("Redis认证失败");
            }
            echo "   ✓ Redis认证成功\n";
        }

        // 选择数据库
        if (!$redis->select($database)) {
            throw new Exception("无法选择Redis数据库 {$database}");
        }
        echo "   ✓ 选择数据库 {$database} 成功\n";

        // 测试读写
        $testKey = $prefix . 'test_connection_' . time();
        $testValue = 'test_value_' . uniqid();

        if (!$redis->setex($testKey, 10, $testValue)) {
            throw new Exception("Redis写入测试失败");
        }

        $retrievedValue = $redis->get($testKey);
        if ($retrievedValue !== $testValue) {
            throw new Exception("Redis读取测试失败");
        }

        $redis->del($testKey);
        echo "   ✓ Redis读写测试成功\n";

        // 获取Redis信息
        $info = $redis->info();
        if (isset($info['redis_version'])) {
            echo "   Redis版本: {$info['redis_version']}\n";
        }
        if (isset($info['used_memory_human'])) {
            echo "   内存使用: {$info['used_memory_human']}\n";
        }
        if (isset($info['connected_clients'])) {
            echo "   连接客户端数: {$info['connected_clients']}\n";
        }

        $redis->close();
        echo "   ✓ Redis连接测试完成\n\n";
        return true;

    } catch (Exception $e) {
        echo "   ✗ Redis连接失败: " . $e->getMessage() . "\n\n";
        return false;
    }
}

// 检查PHP环境
function checkPhpEnvironment() {
    echo "3. 检查PHP环境...\n";

    echo "   PHP版本: " . PHP_VERSION . "\n";

    // 检查必需的扩展
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
    $optionalExtensions = ['redis', 'gd', 'curl', 'fileinfo'];

    echo "   必需扩展:\n";
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $status = $loaded ? '✓' : '✗';
        echo "     {$status} {$ext}\n";
    }

    echo "   可选扩展:\n";
    foreach ($optionalExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $status = $loaded ? '✓' : '⚠';
        echo "     {$status} {$ext}\n";
    }

    echo "   ✓ PHP环境检查完成\n\n";
}

// 显示配置摘要
function showConfigSummary($config) {
    echo "4. 配置摘要...\n";

    $dbConfig = $config['database'] ?? [];
    $redisConfig = $config['redis'] ?? [];

    echo "   数据库配置:\n";
    echo "     类型: " . ($dbConfig['TYPE'] ?? 'mysql') . "\n";
    echo "     主机: " . ($dbConfig['HOSTNAME'] ?? '127.0.0.1') . ":" . ($dbConfig['HOSTPORT'] ?? '3306') . "\n";
    echo "     数据库: " . ($dbConfig['DATABASE'] ?? 'xiaomotui') . "\n";
    echo "     字符集: " . ($dbConfig['CHARSET'] ?? 'utf8mb4') . "\n";
    echo "     表前缀: " . ($dbConfig['PREFIX'] ?? 'xmt_') . "\n";
    echo "     调试模式: " . ($dbConfig['DEBUG'] ?? 'true') . "\n";

    echo "   Redis配置:\n";
    echo "     主机: " . ($redisConfig['HOST'] ?? '127.0.0.1') . ":" . ($redisConfig['PORT'] ?? '6379') . "\n";
    echo "     数据库: " . ($redisConfig['SELECT'] ?? '0') . "\n";
    echo "     前缀: " . ($redisConfig['PREFIX'] ?? 'xmt:') . "\n";
    echo "     超时: " . ($redisConfig['TIMEOUT'] ?? '5.0') . "秒\n";

    echo "   缓存配置:\n";
    echo "     驱动: " . ($config['cache']['DRIVER'] ?? 'redis') . "\n";

    echo "   队列配置:\n";
    echo "     驱动: " . ($config['queue']['DRIVER'] ?? 'redis') . "\n";

    echo "\n";
}

// 主程序
try {
    // 加载配置
    $config = loadEnvConfig('.env');
    if (empty($config)) {
        echo "无法加载配置文件，退出测试\n";
        exit(1);
    }

    // 检查环境
    checkPhpEnvironment();

    // 显示配置
    showConfigSummary($config);

    // 测试连接
    $mysqlOk = testMysqlConnection($config);
    $redisOk = testRedisConnection($config);

    // 总结
    echo "=== 测试结果摘要 ===\n";
    echo "MySQL连接: " . ($mysqlOk ? "✓ 成功" : "✗ 失败") . "\n";
    echo "Redis连接: " . ($redisOk ? "✓ 成功" : "✗ 失败") . "\n";

    if ($mysqlOk && $redisOk) {
        echo "\n🎉 所有数据库连接测试通过！配置正确。\n";
        exit(0);
    } else {
        echo "\n❌ 部分连接测试失败，请检查配置和服务状态。\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "测试过程中发生错误: " . $e->getMessage() . "\n";
    exit(1);
}