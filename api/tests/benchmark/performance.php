<?php
/**
 * 性能基准测试主入口
 * Performance Benchmark Main Entry Point
 *
 * 执行完整的性能基准测试套件，包括：
 * 1. API响应时间测试
 * 2. 并发负载测试
 * 3. 内存使用测试
 * 4. 数据库性能测试
 *
 * 使用方法:
 * php performance.php [options]
 *
 * Options:
 *   --skip-login        跳过登录（仅测试无需认证的接口）
 *   --skip-db           跳过数据库性能测试
 *   --skip-memory       跳过内存测试
 *   --skip-concurrent   跳过并发测试
 *   --quick             快速测试（减少迭代次数）
 *   --help              显示帮助信息
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 设置内存限制
ini_set('memory_limit', '512M');

// 加载配置文件
$config = require __DIR__ . '/config.php';

// 加载性能测试类
require_once __DIR__ . '/PerformanceBenchmark.php';

use tests\benchmark\PerformanceBenchmark;

// 解析命令行参数
$options = parseCommandLineOptions($argv);

// 显示帮助信息
if (isset($options['help'])) {
    displayHelp();
    exit(0);
}

// 应用快速测试模式
if (isset($options['quick'])) {
    applyQuickMode($config);
}

// 应用跳过选项
applySkipOptions($config, $options);

// 显示测试信息
displayTestInfo($config, $options);

try {
    // 创建性能测试实例
    $benchmark = new PerformanceBenchmark($config);

    echo "\n";
    echo str_repeat("=", 80) . "\n";
    echo "开始执行性能基准测试\n";
    echo "Starting Performance Benchmark Tests\n";
    echo str_repeat("=", 80) . "\n";
    echo "\n";

    // 步骤1: 登录获取Token（如果需要）
    if (!isset($options['skip-login'])) {
        echo "[步骤 1/5] 用户登录认证...\n";
        $loginSuccess = $benchmark->login();

        if (!$loginSuccess) {
            echo "\n警告: 登录失败，将跳过需要认证的API测试\n";
            echo "Warning: Login failed, authenticated API tests will be skipped\n\n";
        }
    } else {
        echo "[步骤 1/5] 跳过登录（仅测试公开接口）\n";
    }

    // 步骤2: API响应时间测试
    echo "\n[步骤 2/5] 测试API响应时间...\n";
    $benchmark->testApiResponseTime();

    // 步骤3: 并发负载测试
    if (!isset($options['skip-concurrent'])) {
        echo "\n[步骤 3/5] 测试并发负载...\n";
        $benchmark->testConcurrentLoad();
    } else {
        echo "\n[步骤 3/5] 跳过并发测试\n";
    }

    // 步骤4: 内存使用测试
    if (!isset($options['skip-memory'])) {
        echo "\n[步骤 4/5] 测试内存使用...\n";
        $benchmark->testMemoryUsage();
    } else {
        echo "\n[步骤 4/5] 跳过内存测试\n";
    }

    // 步骤5: 数据库性能测试
    if (!isset($options['skip-db'])) {
        echo "\n[步骤 5/5] 测试数据库性能...\n";
        $benchmark->testDatabasePerformance();
    } else {
        echo "\n[步骤 5/5] 跳过数据库测试\n";
    }

    // 生成性能报告
    echo "\n[完成] 生成性能报告...\n";
    $benchmark->generateReport();

    echo "\n";
    echo str_repeat("=", 80) . "\n";
    echo "性能基准测试完成！\n";
    echo "Performance Benchmark Tests Completed!\n";
    echo str_repeat("=", 80) . "\n";
    echo "\n";

    // 退出码（0表示成功）
    exit(0);

} catch (Exception $e) {
    echo "\n";
    echo str_repeat("=", 80) . "\n";
    echo "错误: 测试执行失败\n";
    echo "Error: Test execution failed\n";
    echo str_repeat("=", 80) . "\n";
    echo "\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n";
    echo "堆栈跟踪:\n";
    echo $e->getTraceAsString() . "\n";
    echo "\n";

    // 退出码（1表示失败）
    exit(1);
}

/**
 * 解析命令行参数
 *
 * @param array $argv 命令行参数
 * @return array 解析后的选项
 */
function parseCommandLineOptions($argv)
{
    $options = [];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $option = substr($arg, 2);
            $options[$option] = true;
        }
    }

    return $options;
}

/**
 * 应用快速测试模式
 *
 * @param array &$config 配置数组（引用）
 */
function applyQuickMode(&$config)
{
    echo "启用快速测试模式（减少迭代次数）\n\n";

    // 减少并发测试级别
    $config['load_test_levels'] = [
        'light' => 10,
        'normal' => 50,
        'medium' => 100,
    ];

    // 减少数据库测试迭代次数
    $config['database_tests']['iterations'] = 20;

    // 减少内存泄漏检测迭代次数
    $config['memory_tests']['leak_detection_iterations'] = 20;
}

/**
 * 应用跳过选项
 *
 * @param array &$config 配置数组（引用）
 * @param array $options 命令行选项
 */
function applySkipOptions(&$config, $options)
{
    if (isset($options['skip-db'])) {
        $config['database_tests']['enabled'] = false;
    }

    if (isset($options['skip-memory'])) {
        $config['memory_tests']['enabled'] = false;
    }
}

/**
 * 显示帮助信息
 */
function displayHelp()
{
    echo <<<HELP

性能基准测试工具
Performance Benchmark Tool

使用方法:
  php performance.php [options]

选项:
  --skip-login        跳过登录（仅测试无需认证的接口）
  --skip-db           跳过数据库性能测试
  --skip-memory       跳过内存测试
  --skip-concurrent   跳过并发测试
  --quick             快速测试模式（减少迭代次数）
  --help              显示此帮助信息

示例:
  # 完整测试
  php performance.php

  # 快速测试
  php performance.php --quick

  # 跳过数据库测试
  php performance.php --skip-db

  # 仅测试公开API（不登录）
  php performance.php --skip-login

  # 组合选项
  php performance.php --quick --skip-db --skip-memory

性能目标:
  - NFC响应时间: < 1秒
  - AI内容生成: < 30秒
  - 视频处理: < 60秒
  - 并发设备: 1000+
  - API响应: < 500ms
  - 数据库查询: < 100ms

HELP;
}

/**
 * 显示测试信息
 *
 * @param array $config 配置信息
 * @param array $options 命令行选项
 */
function displayTestInfo($config, $options)
{
    echo "\n";
    echo "测试配置信息:\n";
    echo str_repeat("-", 80) . "\n";
    echo "基础URL: " . $config['base_url'] . "\n";
    echo "测试环境: " . $config['environment'] . "\n";
    echo "数据库测试: " . ($config['database_tests']['enabled'] ? '启用' : '禁用') . "\n";
    echo "内存测试: " . ($config['memory_tests']['enabled'] ? '启用' : '禁用') . "\n";
    echo "并发测试: " . (isset($options['skip-concurrent']) ? '禁用' : '启用') . "\n";
    echo "登录认证: " . (isset($options['skip-login']) ? '禁用' : '启用') . "\n";

    if (isset($options['quick'])) {
        echo "测试模式: 快速模式\n";
    } else {
        echo "测试模式: 完整模式\n";
    }

    echo str_repeat("-", 80) . "\n";
}
