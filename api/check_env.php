<?php
/**
 * 生产环境配置检查脚本
 * 用于验证 .env.production 配置是否完整和正确
 */

// 定义颜色输出
function colorOutput($text, $color = 'white') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];

    return $colors[$color] . $text . $colors['reset'];
}

// 检查配置文件是否存在
function checkFileExists($file) {
    if (!file_exists($file)) {
        echo colorOutput("✗ 配置文件不存在: $file\n", 'red');
        return false;
    }
    echo colorOutput("✓ 配置文件存在: $file\n", 'green');
    return true;
}

// 解析配置文件
function parseEnvFile($file) {
    if (!file_exists($file)) {
        return [];
    }

    $config = [];
    $currentSection = 'default';
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // 跳过注释
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // 检测section
        if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $currentSection = $matches[1];
            continue;
        }

        // 解析键值对
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 组合section和key
            $fullKey = $currentSection === 'default' ? $key : "$currentSection.$key";
            $config[$fullKey] = $value;
        }
    }

    return $config;
}

// 检查必需配置项
function checkRequiredConfigs($config) {
    $required = [
        // 数据库配置
        'DATABASE.HOSTNAME' => '数据库主机地址',
        'DATABASE.DATABASE' => '数据库名称',
        'DATABASE.USERNAME' => '数据库用户名',
        'DATABASE.PASSWORD' => '数据库密码',

        // Redis配置
        'REDIS.HOST' => 'Redis主机地址',

        // JWT配置
        'JWT.SECRET_KEY' => 'JWT密钥',

        // 微信配置
        'WECHAT.MINI_APP_ID' => '微信小程序AppID',
        'WECHAT.MINI_APP_SECRET' => '微信小程序Secret',

        // OSS配置
        'OSS.ACCESS_ID' => '阿里云OSS AccessID',
        'OSS.ACCESS_SECRET' => '阿里云OSS AccessSecret',
        'OSS.BUCKET' => 'OSS Bucket名称',

        // AI配置
        'AI.BAIDU_WENXIN_API_KEY' => '百度文心API Key',
        'AI.BAIDU_WENXIN_SECRET_KEY' => '百度文心Secret Key',
    ];

    $missing = [];
    $placeholder = [];

    echo "\n" . colorOutput("=== 检查必需配置项 ===\n", 'blue');

    foreach ($required as $key => $description) {
        if (!isset($config[$key]) || empty($config[$key])) {
            $missing[] = "$description ($key)";
            echo colorOutput("✗ 缺失: $description ($key)\n", 'red');
        } elseif (preg_match('/your_|your-/', $config[$key])) {
            $placeholder[] = "$description ($key)";
            echo colorOutput("⚠ 占位符未替换: $description ($key) = {$config[$key]}\n", 'yellow');
        } else {
            echo colorOutput("✓ 已配置: $description\n", 'green');
        }
    }

    return ['missing' => $missing, 'placeholder' => $placeholder];
}

// 检查安全配置
function checkSecurityConfigs($config) {
    echo "\n" . colorOutput("=== 检查安全配置 ===\n", 'blue');

    $issues = [];

    // 检查调试模式
    if (isset($config['default.APP_DEBUG']) && $config['default.APP_DEBUG'] === 'true') {
        echo colorOutput("✗ 生产环境不应启用调试模式\n", 'red');
        $issues[] = 'APP_DEBUG应设置为false';
    } else {
        echo colorOutput("✓ 调试模式已关闭\n", 'green');
    }

    // 检查JWT密钥强度
    if (isset($config['JWT.SECRET_KEY'])) {
        $key = $config['JWT.SECRET_KEY'];
        if (strlen($key) < 32) {
            echo colorOutput("✗ JWT密钥长度不足（当前: " . strlen($key) . "位，建议: ≥32位）\n", 'red');
            $issues[] = 'JWT密钥长度不足';
        } elseif (preg_match('/^[a-z0-9_-]+$/i', $key) && !preg_match('/[A-Z]/', $key)) {
            echo colorOutput("⚠ JWT密钥复杂度较低，建议包含大小写字母、数字和特殊字符\n", 'yellow');
        } else {
            echo colorOutput("✓ JWT密钥强度良好\n", 'green');
        }
    }

    // 检查HTTPS
    if (isset($config['SECURITY.FORCE_HTTPS']) && $config['SECURITY.FORCE_HTTPS'] === 'true') {
        echo colorOutput("✓ 已启用强制HTTPS\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用强制HTTPS (SECURITY.FORCE_HTTPS = true)\n", 'yellow');
    }

    // 检查HSTS
    if (isset($config['SECURITY.HSTS_ENABLED']) && $config['SECURITY.HSTS_ENABLED'] === 'true') {
        echo colorOutput("✓ 已启用HSTS\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用HSTS (SECURITY.HSTS_ENABLED = true)\n", 'yellow');
    }

    return $issues;
}

// 检查性能配置
function checkPerformanceConfigs($config) {
    echo "\n" . colorOutput("=== 检查性能配置 ===\n", 'blue');

    // 检查数据库连接池
    $minConn = $config['DATABASE.POOL.MIN_CONNECTIONS'] ?? 0;
    $maxConn = $config['DATABASE.POOL.MAX_CONNECTIONS'] ?? 0;

    if ($minConn >= 10 && $maxConn >= 30) {
        echo colorOutput("✓ 数据库连接池配置合理 (Min: $minConn, Max: $maxConn)\n", 'green');
    } else {
        echo colorOutput("⚠ 数据库连接池可能不足 (Min: $minConn, Max: $maxConn)\n", 'yellow');
        echo colorOutput("  建议: MIN_CONNECTIONS ≥ 10, MAX_CONNECTIONS ≥ 30\n", 'yellow');
    }

    // 检查Redis连接池
    $redisMinConn = $config['REDIS.POOL.MIN_CONNECTIONS'] ?? 0;
    $redisMaxConn = $config['REDIS.POOL.MAX_CONNECTIONS'] ?? 0;

    if ($redisMinConn >= 5 && $redisMaxConn >= 20) {
        echo colorOutput("✓ Redis连接池配置合理 (Min: $redisMinConn, Max: $redisMaxConn)\n", 'green');
    } else {
        echo colorOutput("⚠ Redis连接池可能不足 (Min: $redisMinConn, Max: $redisMaxConn)\n", 'yellow');
        echo colorOutput("  建议: MIN_CONNECTIONS ≥ 5, MAX_CONNECTIONS ≥ 20\n", 'yellow');
    }

    // 检查持久连接
    if (isset($config['DATABASE.PERSISTENT']) && $config['DATABASE.PERSISTENT'] === 'true') {
        echo colorOutput("✓ 数据库已启用持久连接\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用数据库持久连接以提升性能\n", 'yellow');
    }

    if (isset($config['REDIS.PERSISTENT']) && $config['REDIS.PERSISTENT'] === 'true') {
        echo colorOutput("✓ Redis已启用持久连接\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用Redis持久连接以提升性能\n", 'yellow');
    }
}

// 检查监控配置
function checkMonitoringConfigs($config) {
    echo "\n" . colorOutput("=== 检查监控配置 ===\n", 'blue');

    // 检查数据库监控
    if (isset($config['MONITOR.DATABASE.ENABLED']) && $config['MONITOR.DATABASE.ENABLED'] === 'true') {
        echo colorOutput("✓ 数据库监控已启用\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用数据库监控\n", 'yellow');
    }

    // 检查Redis监控
    if (isset($config['MONITOR.REDIS.ENABLED']) && $config['MONITOR.REDIS.ENABLED'] === 'true') {
        echo colorOutput("✓ Redis监控已启用\n", 'green');
    } else {
        echo colorOutput("⚠ 建议启用Redis监控\n", 'yellow');
    }

    // 检查告警配置
    if (isset($config['MONITOR.ALERTS.ENABLED']) && $config['MONITOR.ALERTS.ENABLED'] === 'true') {
        echo colorOutput("✓ 告警已启用\n", 'green');

        // 检查告警渠道
        $channels = 0;
        if (isset($config['MONITOR.ALERTS.EMAIL.ENABLED']) && $config['MONITOR.ALERTS.EMAIL.ENABLED'] === 'true') {
            echo colorOutput("  ✓ 邮件告警已启用\n", 'green');
            $channels++;
        }
        if (isset($config['MONITOR.ALERTS.WECHAT.ENABLED']) && $config['MONITOR.ALERTS.WECHAT.ENABLED'] === 'true') {
            echo colorOutput("  ✓ 企业微信告警已启用\n", 'green');
            $channels++;
        }

        if ($channels === 0) {
            echo colorOutput("  ⚠ 未配置任何告警渠道\n", 'yellow');
        }
    } else {
        echo colorOutput("⚠ 建议启用告警功能\n", 'yellow');
    }
}

// 生成配置报告
function generateReport($config, $requiredCheck, $securityIssues) {
    echo "\n" . colorOutput("=== 配置检查报告 ===\n", 'blue');

    $totalIssues = count($requiredCheck['missing']) + count($requiredCheck['placeholder']) + count($securityIssues);

    if ($totalIssues === 0) {
        echo colorOutput("\n✓ 配置检查通过！所有必需配置项已正确设置。\n", 'green');
        return true;
    }

    echo colorOutput("\n发现 $totalIssues 个问题需要处理：\n", 'yellow');

    if (count($requiredCheck['missing']) > 0) {
        echo colorOutput("\n缺失的配置项:\n", 'red');
        foreach ($requiredCheck['missing'] as $item) {
            echo colorOutput("  - $item\n", 'red');
        }
    }

    if (count($requiredCheck['placeholder']) > 0) {
        echo colorOutput("\n未替换的占位符:\n", 'yellow');
        foreach ($requiredCheck['placeholder'] as $item) {
            echo colorOutput("  - $item\n", 'yellow');
        }
    }

    if (count($securityIssues) > 0) {
        echo colorOutput("\n安全问题:\n", 'red');
        foreach ($securityIssues as $issue) {
            echo colorOutput("  - $issue\n", 'red');
        }
    }

    echo colorOutput("\n请修复以上问题后再部署到生产环境。\n", 'yellow');
    return false;
}

// 主程序
function main() {
    echo colorOutput("
╔═══════════════════════════════════════════╗
║   小魔推生产环境配置检查工具              ║
╚═══════════════════════════════════════════╝
", 'blue');

    $envFile = __DIR__ . '/.env.production';

    // 检查文件是否存在
    if (!checkFileExists($envFile)) {
        echo colorOutput("\n请先创建 .env.production 文件。\n", 'red');
        echo "可以从 .env.example 复制:\n";
        echo "  cp .env.example .env.production\n";
        return 1;
    }

    // 解析配置文件
    echo "\n正在解析配置文件...\n";
    $config = parseEnvFile($envFile);
    echo colorOutput("✓ 配置文件解析完成，共 " . count($config) . " 个配置项\n", 'green');

    // 执行各项检查
    $requiredCheck = checkRequiredConfigs($config);
    $securityIssues = checkSecurityConfigs($config);
    checkPerformanceConfigs($config);
    checkMonitoringConfigs($config);

    // 生成报告
    $passed = generateReport($config, $requiredCheck, $securityIssues);

    echo "\n";
    echo colorOutput("检查完成！\n", 'blue');

    return $passed ? 0 : 1;
}

// 运行检查
exit(main());
