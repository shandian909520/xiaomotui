<?php
/**
 * 测试缓存服务
 */

require_once __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

try {
    echo "=== 缓存服务测试 ===\n\n";

    // 测试1: 基本缓存操作
    echo "1. 测试基本缓存操作:\n";
    \app\common\service\CacheService::set('test_key', 'Hello Cache!', 60);
    $value = \app\common\service\CacheService::get('test_key');
    echo "设置缓存: test_key = 'Hello Cache!'\n";
    echo "获取缓存: " . ($value ?? 'NULL') . "\n\n";

    // 测试2: 缓存统计信息
    echo "2. 缓存统计信息:\n";
    $stats = \app\common\service\CacheService::getStats();
    echo "缓存驱动: " . $stats['driver'] . "\n";
    echo "连接状态: " . ($stats['connected'] ? '已连接' : '未连接') . "\n";
    if (isset($stats['info'])) {
        echo "连接信息: " . json_encode($stats['info'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";

    // 测试3: 批量操作
    echo "3. 测试批量操作:\n";
    $items = [
        'batch1' => 'Value 1',
        'batch2' => 'Value 2',
        'batch3' => 'Value 3'
    ];
    $result = \app\common\service\CacheService::mset($items, 60);
    echo "批量设置成功数量: $result\n";

    $values = [];
    foreach (array_keys($items) as $key) {
        $values[$key] = \app\common\service\CacheService::get($key);
    }
    echo "批量获取结果: " . json_encode($values, JSON_UNESCAPED_UNICODE) . "\n\n";

    // 测试4: 自增操作
    echo "4. 测试自增操作:\n";
    \app\common\service\CacheService::set('counter', 10, 60);
    $newValue = \app\common\service\CacheService::increment('counter', 5);
    echo "计数器初始值: 10\n";
    echo "自增5后: $newValue\n\n";

    // 测试5: 带标签的缓存
    echo "5. 测试带标签的缓存:\n";
    \app\common\service\CacheService::set('tagged_key', 'Tagged Value', 60, 'test_tag');
    $taggedValue = \app\common\service\CacheService::get('tagged_key');
    echo "设置带标签缓存: tagged_key = 'Tagged Value' (tag: test_tag)\n";
    echo "获取带标签缓存: " . ($taggedValue ?? 'NULL') . "\n\n";

    // 测试6: 记忆回调功能
    echo "6. 测试记忆回调功能:\n";
    $callbackResult = \app\common\service\CacheService::rememberCallback(
        'callback_test',
        function() {
            return 'Generated at ' . date('Y-m-d H:i:s');
        },
        60
    );
    echo "记忆回调结果: $callbackResult\n\n";

    // 清理测试数据
    echo "7. 清理测试数据:\n";
    $deleteKeys = array_merge(['test_key', 'counter', 'tagged_key', 'callback_test'], array_keys($items));
    $deleteResult = \app\common\service\CacheService::mdelete($deleteKeys);
    echo "删除缓存成功数量: $deleteResult\n\n";

    echo "=== 缓存服务测试完成 ===\n";

} catch (Exception $e) {
    echo "测试过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误追踪: " . $e->getTraceAsString() . "\n";
}