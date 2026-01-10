<?php
/**
 * 端到端测试 - 完整用户流程测试
 *
 * 功能说明:
 * - 模拟完整用户流程从NFC触发到内容发布
 * - 验证数据一致性
 * - 测试并发场景
 * - 错误处理验证
 * - 商家工作流测试
 *
 * 使用方法:
 * php full_flow.php
 *
 * 注意事项:
 * 1. 确保数据库连接正常
 * 2. 测试数据会自动创建和清理
 * 3. 建议在测试环境中运行
 */

declare(strict_types=1);

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 设置执行时间限制
set_time_limit(300); // 5分钟

// 引入自动加载
require_once __DIR__ . '/../../vendor/autoload.php';

// 引入配置
$config = require_once __DIR__ . '/config.php';

// 引入测试类
require_once __DIR__ . '/TestDataGenerator.php';
require_once __DIR__ . '/DataConsistencyChecker.php';
require_once __DIR__ . '/E2ETestRunner.php';

use tests\e2e\TestDataGenerator;
use tests\e2e\E2ETestRunner;

// 显示欢迎信息
echo "\n";
echo str_repeat("=", 60) . "\n";
echo "          端到端测试 - 完整用户流程测试\n";
echo str_repeat("=", 60) . "\n";
echo "环境: " . $config['environment'] . "\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // 步骤1: 生成测试数据
    echo "[步骤 1/7] 生成测试数据\n";
    echo str_repeat("-", 60) . "\n";

    $generator = new TestDataGenerator($config);
    $testData = $generator->generateAll();

    // 步骤2: 初始化测试运行器
    echo "[步骤 2/7] 初始化测试运行器\n";
    echo str_repeat("-", 60) . "\n";

    $runner = new E2ETestRunner($config);
    $runner->setTestData($testData);
    echo "✓ 测试运行器初始化完成\n\n";

    // 步骤3: 场景1 - 完整NFC到发布流程
    echo "[步骤 3/7] 场景1 - 完整NFC到发布流程\n";
    echo str_repeat("-", 60) . "\n";
    $runner->testCompleteFlow();

    // 步骤4: 场景2 - 多设备并发流程
    echo "[步骤 4/7] 场景2 - 多设备并发流程\n";
    echo str_repeat("-", 60) . "\n";
    $runner->testConcurrentFlow();

    // 步骤5: 场景3 - 错误处理流程
    echo "[步骤 5/7] 场景3 - 错误处理流程\n";
    echo str_repeat("-", 60) . "\n";
    $runner->testErrorHandling();

    // 步骤6: 场景4 - 完整商家工作流
    echo "[步骤 6/7] 场景4 - 完整商家工作流\n";
    echo str_repeat("-", 60) . "\n";
    $runner->testMerchantWorkflow();

    // 步骤7: 数据一致性检查
    echo "[步骤 7/7] 最终数据一致性检查\n";
    echo str_repeat("-", 60) . "\n";
    $consistencyResult = $runner->checkDataConsistency();

    // 生成测试报告
    echo "\n";
    $runner->generateReport();

    // 清理测试数据
    if ($config['cleanup']['enabled']) {
        echo "\n清理测试数据\n";
        echo str_repeat("-", 60) . "\n";
        $runner->cleanup();
    }

    // 显示完成信息
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "端到端测试完成！\n";
    echo str_repeat("=", 60) . "\n\n";

    // 根据测试结果设置退出码
    $results = $runner->getResults();
    $allPassed = true;

    foreach ($results as $key => $result) {
        if (strpos($key, 'scenario_') === 0 && !$result['passed']) {
            $allPassed = false;
            break;
        }
    }

    if (!$allPassed || !$consistencyResult['passed']) {
        exit(1); // 测试失败
    }

    exit(0); // 测试成功

} catch (\Exception $e) {
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "❌ 测试执行失败\n";
    echo str_repeat("=", 60) . "\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n";
    echo $e->getTraceAsString() . "\n";
    echo str_repeat("=", 60) . "\n\n";

    exit(1);
} catch (\Throwable $e) {
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "❌ 发生严重错误\n";
    echo str_repeat("=", 60) . "\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n";
    echo $e->getTraceAsString() . "\n";
    echo str_repeat("=", 60) . "\n\n";

    exit(1);
}
