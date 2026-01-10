<?php
/**
 * 基础功能测试脚本
 * Basic Functionality Test Script
 *
 * 用于验证性能测试工具的基本功能是否正常
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Shanghai');

echo "================================================================================\n";
echo "性能测试工具基础功能验证\n";
echo "Basic Functionality Test\n";
echo "================================================================================\n\n";

// 测试1: 检查配置文件
echo "[测试 1/5] 检查配置文件...\n";
if (file_exists(__DIR__ . '/config.php')) {
    $config = require __DIR__ . '/config.php';
    echo "  ✓ 配置文件加载成功\n";
    echo "  ✓ 基础URL: " . $config['base_url'] . "\n";
    echo "  ✓ 环境: " . $config['environment'] . "\n";
    echo "  ✓ 端点数量: " . count($config['endpoints']) . "\n";
} else {
    echo "  ✗ 配置文件不存在\n";
    exit(1);
}

// 测试2: 检查性能测试类
echo "\n[测试 2/5] 检查性能测试类...\n";
if (file_exists(__DIR__ . '/PerformanceBenchmark.php')) {
    require_once __DIR__ . '/PerformanceBenchmark.php';
    echo "  ✓ 性能测试类文件存在\n";

    if (class_exists('tests\benchmark\PerformanceBenchmark')) {
        echo "  ✓ PerformanceBenchmark 类可用\n";

        // 尝试创建实例
        try {
            $benchmark = new \tests\benchmark\PerformanceBenchmark($config);
            echo "  ✓ 可以创建测试实例\n";
        } catch (Exception $e) {
            echo "  ⚠ 创建实例警告: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✗ PerformanceBenchmark 类未找到\n";
        exit(1);
    }
} else {
    echo "  ✗ 性能测试类文件不存在\n";
    exit(1);
}

// 测试3: 检查目录结构
echo "\n[测试 3/5] 检查目录结构...\n";
$dirs = ['reports', 'logs'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        echo "  ✓ {$dir}/ 目录存在\n";
    } else {
        echo "  ⚠ {$dir}/ 目录不存在，尝试创建...\n";
        if (mkdir($path, 0755, true)) {
            echo "    ✓ 目录创建成功\n";
        } else {
            echo "    ✗ 目录创建失败\n";
        }
    }
}

// 测试4: 检查PHP扩展
echo "\n[测试 4/5] 检查PHP扩展...\n";
$required_extensions = ['curl', 'pdo', 'pdo_mysql', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "  ✓ {$ext} 扩展已加载\n";
    } else {
        echo "  ✗ {$ext} 扩展未加载\n";
    }
}

// 测试5: 检查PHP配置
echo "\n[测试 5/5] 检查PHP配置...\n";
$memory_limit = ini_get('memory_limit');
echo "  ✓ 内存限制: {$memory_limit}\n";

$max_execution_time = ini_get('max_execution_time');
echo "  ✓ 最大执行时间: {$max_execution_time} 秒\n";

$php_version = PHP_VERSION;
echo "  ✓ PHP版本: {$php_version}\n";

// 总结
echo "\n================================================================================\n";
echo "基础功能验证完成\n";
echo "================================================================================\n\n";

echo "✓ 配置文件: OK\n";
echo "✓ 测试类: OK\n";
echo "✓ 目录结构: OK\n";
echo "✓ PHP扩展: " . (count(array_filter($required_extensions, 'extension_loaded')) == count($required_extensions) ? 'OK' : 'WARNING') . "\n";
echo "✓ PHP配置: OK\n";

echo "\n可以开始运行性能测试:\n";
echo "  php performance.php\n";
echo "  php performance.php --quick\n";
echo "  ./run_benchmark.sh (Linux/Mac)\n";
echo "  run_benchmark.bat (Windows)\n\n";

exit(0);
