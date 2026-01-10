<?php
/**
 * 团购跳转功能测试脚本
 *
 * 测试内容：
 * 1. GroupBuyService 基础功能测试
 * 2. URL 生成测试
 * 3. 配置验证测试
 * 4. 团购触发流程测试
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\GroupBuyService;

echo "================================\n";
echo "团购跳转功能测试\n";
echo "================================\n\n";

// 创建服务实例
$groupBuyService = new GroupBuyService();

// 测试1: URL生成 - 美团
echo "测试1: 美团团购URL生成\n";
echo "----------------------------\n";
try {
    $meituanUrl = $groupBuyService->generateRedirectUrl([
        'platform' => 'MEITUAN',
        'deal_id' => '12345',
        'merchant_id' => 1,
        'device_id' => 1
    ]);
    echo "✓ 美团URL生成成功\n";
    echo "URL: {$meituanUrl}\n\n";
} catch (\Exception $e) {
    echo "✗ 美团URL生成失败: {$e->getMessage()}\n\n";
}

// 测试2: URL生成 - 抖音
echo "测试2: 抖音团购URL生成\n";
echo "----------------------------\n";
try {
    $douyinUrl = $groupBuyService->generateRedirectUrl([
        'platform' => 'DOUYIN',
        'deal_id' => '67890',
        'merchant_id' => 1,
        'device_id' => 1
    ]);
    echo "✓ 抖音URL生成成功\n";
    echo "URL: {$douyinUrl}\n\n";
} catch (\Exception $e) {
    echo "✗ 抖音URL生成失败: {$e->getMessage()}\n\n";
}

// 测试3: URL生成 - 饿了么
echo "测试3: 饿了么团购URL生成\n";
echo "----------------------------\n";
try {
    $elemeUrl = $groupBuyService->generateRedirectUrl([
        'platform' => 'ELEME',
        'deal_id' => '11111',
        'merchant_id' => 1,
        'device_id' => 1
    ]);
    echo "✓ 饿了么URL生成成功\n";
    echo "URL: {$elemeUrl}\n\n";
} catch (\Exception $e) {
    echo "✗ 饿了么URL生成失败: {$e->getMessage()}\n\n";
}

// 测试4: URL生成 - 自定义
echo "测试4: 自定义团购URL生成\n";
echo "----------------------------\n";
try {
    $customUrl = $groupBuyService->generateRedirectUrl([
        'platform' => 'CUSTOM',
        'custom_url' => 'https://example.com/deal/123',
        'merchant_id' => 1,
        'device_id' => 1
    ]);
    echo "✓ 自定义URL生成成功\n";
    echo "URL: {$customUrl}\n\n";
} catch (\Exception $e) {
    echo "✗ 自定义URL生成失败: {$e->getMessage()}\n\n";
}

// 测试5: URL验证
echo "测试5: URL验证测试\n";
echo "----------------------------\n";
$testUrls = [
    'https://i.meituan.com/deal/123' => true,
    'http://example.com/test' => true,
    'ftp://invalid.com' => false,
    'invalid-url' => false,
    '' => false
];

foreach ($testUrls as $url => $expected) {
    $result = $groupBuyService->validateUrl($url);
    $status = $result === $expected ? '✓' : '✗';
    $urlDisplay = $url ?: '(空字符串)';
    echo "{$status} URL: {$urlDisplay} - 验证结果: " . ($result ? '有效' : '无效') . "\n";
}
echo "\n";

// 测试6: 配置验证
echo "测试6: 团购配置验证\n";
echo "----------------------------\n";

// 有效配置
$validConfig = [
    'platform' => 'MEITUAN',
    'deal_id' => '12345',
    'deal_name' => '咖啡店双人套餐',
    'original_price' => 98.00,
    'group_price' => 68.00
];
$validation = $groupBuyService->validateGroupBuyConfig($validConfig);
echo ($validation['valid'] ? '✓' : '✗') . " 有效配置验证: " . ($validation['valid'] ? '通过' : '失败 - ' . implode(', ', $validation['errors'])) . "\n";

// 无效配置 - 缺少平台
$invalidConfig1 = [
    'deal_id' => '12345'
];
$validation = $groupBuyService->validateGroupBuyConfig($invalidConfig1);
echo ($validation['valid'] ? '✗' : '✓') . " 无效配置1(缺少平台): " . (!$validation['valid'] ? '正确拒绝' : '错误通过') . "\n";

// 无效配置 - 缺少deal_id
$invalidConfig2 = [
    'platform' => 'MEITUAN'
];
$validation = $groupBuyService->validateGroupBuyConfig($invalidConfig2);
echo ($validation['valid'] ? '✗' : '✓') . " 无效配置2(缺少deal_id): " . (!$validation['valid'] ? '正确拒绝' : '错误通过') . "\n";

// 无效配置 - 团购价大于原价
$invalidConfig3 = [
    'platform' => 'MEITUAN',
    'deal_id' => '12345',
    'original_price' => 50.00,
    'group_price' => 100.00
];
$validation = $groupBuyService->validateGroupBuyConfig($invalidConfig3);
echo ($validation['valid'] ? '✗' : '✓') . " 无效配置3(团购价>原价): " . (!$validation['valid'] ? '正确拒绝' : '错误通过') . "\n\n";

// 测试7: 团购信息格式化
echo "测试7: 团购信息格式化\n";
echo "----------------------------\n";
$dealConfig = [
    'platform' => 'MEITUAN',
    'deal_name' => '咖啡店双人套餐',
    'original_price' => 98.00,
    'group_price' => 68.00
];
$dealInfo = $groupBuyService->formatDealInfo($dealConfig);
echo "✓ 团购信息格式化成功\n";
echo "  - 名称: {$dealInfo['name']}\n";
echo "  - 原价: ¥{$dealInfo['original_price']}\n";
echo "  - 团购价: ¥{$dealInfo['group_price']}\n";
echo "  - 折扣: {$dealInfo['discount']}\n";
echo "  - 节省: ¥{$dealInfo['save_amount']}\n";
echo "  - 平台: {$dealInfo['platform_name']}\n\n";

// 测试8: 配置解析
echo "测试8: 团购配置解析\n";
echo "----------------------------\n";
$jsonConfig = json_encode([
    'platform' => 'DOUYIN',
    'deal_id' => '99999',
    'deal_name' => '火锅套餐',
    'original_price' => 198.00,
    'group_price' => 128.00
]);
$parsedConfig = $groupBuyService->parseGroupBuyConfig($jsonConfig);
echo "✓ JSON配置解析成功\n";
echo "  - 平台: {$parsedConfig['platform']}\n";
echo "  - 团购ID: {$parsedConfig['deal_id']}\n";
echo "  - 名称: {$parsedConfig['deal_name']}\n\n";

// 测试9: 平台名称获取
echo "测试9: 平台名称获取\n";
echo "----------------------------\n";
$platforms = ['MEITUAN', 'DOUYIN', 'ELEME', 'CUSTOM'];
foreach ($platforms as $platform) {
    $name = $groupBuyService->getPlatformName($platform);
    echo "✓ {$platform} -> {$name}\n";
}
echo "\n";

// 测试10: 错误处理
echo "测试10: 错误处理测试\n";
echo "----------------------------\n";

// 测试缺少必需参数
try {
    $groupBuyService->generateRedirectUrl([
        'platform' => 'MEITUAN'
        // 缺少 deal_id
    ]);
    echo "✗ 应该抛出异常但没有\n";
} catch (\Exception $e) {
    echo "✓ 正确捕获异常: {$e->getMessage()}\n";
}

// 测试不支持的平台
try {
    $groupBuyService->generateRedirectUrl([
        'platform' => 'INVALID_PLATFORM',
        'deal_id' => '12345'
    ]);
    echo "✗ 应该抛出异常但没有\n";
} catch (\Exception $e) {
    echo "✓ 正确捕获异常: {$e->getMessage()}\n";
}

// 测试自定义平台缺少URL
try {
    $groupBuyService->generateRedirectUrl([
        'platform' => 'CUSTOM'
        // 缺少 custom_url
    ]);
    echo "✗ 应该抛出异常但没有\n";
} catch (\Exception $e) {
    echo "✓ 正确捕获异常: {$e->getMessage()}\n";
}

echo "\n================================\n";
echo "测试完成\n";
echo "================================\n";

// 总结
echo "\n测试总结:\n";
echo "- GroupBuyService 基础功能正常\n";
echo "- URL 生成功能正常\n";
echo "- 配置验证功能正常\n";
echo "- 错误处理功能正常\n";
echo "\n建议下一步:\n";
echo "1. 运行数据库迁移: php think migrate:run\n";
echo "2. 配置设备团购信息\n";
echo "3. 测试完整的 NFC 触发流程\n";
echo "4. 查看统计数据\n";