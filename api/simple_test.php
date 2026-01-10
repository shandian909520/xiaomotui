<?php
/**
 * 简单功能测试脚本 - 验证核心修复功能
 */

require_once __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

echo "=== 核心功能验证测试 ===\n\n";

$tests = [];
$startTime = microtime(true);

// 测试1: API服务基础状态
echo "1. API服务状态测试\n";
echo "-------------------\n";
$ch = curl_init('http://localhost:8000/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ API服务正常运行\n";
    $tests['api_service'] = true;
} else {
    echo "❌ API服务异常 (HTTP: $httpCode)\n";
    $tests['api_service'] = false;
}

// 测试2: Redis缓存功能
echo "\n2. Redis缓存功能测试\n";
echo "---------------------\n";
try {
    \app\common\service\CacheService::set('test_key', 'test_value', 60);
    $value = \app\common\service\CacheService::get('test_key');
    if ($value === 'test_value') {
        echo "✅ Redis缓存读写正常\n";
        $tests['redis_cache'] = true;

        $stats = \app\common\service\CacheService::getStats();
        echo "   连接状态: " . ($stats['connected'] ? '已连接' : '未连接') . "\n";
        echo "   缓存驱动: " . $stats['driver'] . "\n";
    } else {
        echo "❌ Redis缓存数据异常\n";
        $tests['redis_cache'] = false;
    }
} catch (Exception $e) {
    echo "❌ Redis缓存测试失败: " . $e->getMessage() . "\n";
    $tests['redis_cache'] = false;
}

// 测试3: AI内容生成
echo "\n3. AI内容生成测试\n";
echo "------------------\n";
try {
    $wenxinService = new \app\service\WenxinService();
    $result = $wenxinService->generateText([
        'scene' => '测试场景',
        'style' => '温馨',
        'platform' => 'douyin',
        'category' => '测试类别'
    ]);

    if (!empty($result['text'])) {
        echo "✅ AI内容生成正常\n";
        echo "   生成内容: " . mb_substr($result['text'], 0, 30) . "...\n";
        echo "   耗时: {$result['time']}秒\n";
        $tests['ai_content'] = true;
    } else {
        echo "❌ AI内容生成失败\n";
        $tests['ai_content'] = false;
    }
} catch (Exception $e) {
    echo "❌ AI内容生成异常: " . $e->getMessage() . "\n";
    $tests['ai_content'] = false;
}

// 测试4: 数据库连接
echo "\n4. 数据库连接测试\n";
echo "------------------\n";
try {
    $userModel = new \app\model\User();
    $count = $userModel->count();
    echo "✅ 数据库连接正常\n";
    echo "   用户表记录数: $count\n";
    $tests['database'] = true;
} catch (Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
    $tests['database'] = false;
}

// 测试5: 业务异常类
echo "\n5. 业务异常类测试\n";
echo "-------------------\n";
try {
    $exceptions = [
        \app\common\exception\BusinessException::authFailed(),
        \app\common\exception\BusinessException::notFound(),
        \app\common\exception\BusinessException::invalidParameter()
    ];

    echo "✅ 业务异常类正常\n";
    echo "   创建了 " . count($exceptions) . " 种异常类型\n";
    $tests['business_exception'] = true;
} catch (Exception $e) {
    echo "❌ 业务异常类失败: " . $e->getMessage() . "\n";
    $tests['business_exception'] = false;
}

// 测试6: 前端服务状态
echo "\n6. 前端服务状态测试\n";
echo "---------------------\n";
$frontendTests = [];

// H5前端
$ch = curl_init('http://localhost:6151');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ H5前端服务正常\n";
    $frontendTests['h5'] = true;
} else {
    echo "❌ H5前端服务异常 (HTTP: $httpCode)\n";
    $frontendTests['h5'] = false;
}

// 管理后台
$ch = curl_init('http://localhost:3000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ 管理后台服务正常\n";
    $frontendTests['admin'] = true;
} else {
    echo "❌ 管理后台服务异常 (HTTP: $httpCode)\n";
    $frontendTests['admin'] = false;
}

$tests['frontend'] = array_sum($frontendTests) === count($frontendTests);

// 汇总结果
$duration = round(microtime(true) - $startTime, 2);
$passed = array_sum($tests);
$total = count($tests);
$successRate = round(($passed / $total) * 100, 1);

echo "\n=== 测试结果汇总 ===\n";
echo "测试耗时: {$duration}秒\n";
echo "通过测试: $passed/$total\n";
echo "成功率: $successRate%\n\n";

foreach ($tests as $test => $result) {
    $status = $result ? '✅ 正常' : '❌ 异常';
    $names = [
        'api_service' => 'API服务',
        'redis_cache' => 'Redis缓存',
        'ai_content' => 'AI内容生成',
        'database' => '数据库连接',
        'business_exception' => '业务异常处理',
        'frontend' => '前端服务'
    ];
    echo "$status {$names[$test]}\n";
}

echo "\n";

if ($successRate >= 80) {
    echo "🎉 系统核心功能运行良好！\n";
} elseif ($successRate >= 60) {
    echo "⚠️ 系统基本正常，部分功能需要优化\n";
} else {
    echo "🚨 系统存在较多问题，需要进一步修复\n";
}

echo "\n=== 测试完成 ===\n";