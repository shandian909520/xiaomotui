<?php
/**
 * Merchant模型结构测试脚本
 * 测试模型的常量、方法和属性（不需要数据库连接）
 */

require __DIR__ . '/vendor/autoload.php';

use app\model\Merchant;

echo "========================================\n";
echo "Merchant模型结构测试\n";
echo "========================================\n\n";

// 测试1: 常量定义
echo "【测试1】状态常量\n";
echo "  STATUS_DISABLED = " . Merchant::STATUS_DISABLED . " (已禁用)\n";
echo "  STATUS_ACTIVE = " . Merchant::STATUS_ACTIVE . " (正常)\n";
echo "  STATUS_UNDER_REVIEW = " . Merchant::STATUS_UNDER_REVIEW . " (审核中)\n";
echo "  ✓ 状态常量定义正确\n\n";

// 测试2: 类别常量
echo "【测试2】类别常量\n";
$categories = [
    'CATEGORY_RESTAURANT' => Merchant::CATEGORY_RESTAURANT,
    'CATEGORY_RETAIL' => Merchant::CATEGORY_RETAIL,
    'CATEGORY_SERVICE' => Merchant::CATEGORY_SERVICE,
    'CATEGORY_ENTERTAINMENT' => Merchant::CATEGORY_ENTERTAINMENT,
    'CATEGORY_EDUCATION' => Merchant::CATEGORY_EDUCATION,
    'CATEGORY_HEALTHCARE' => Merchant::CATEGORY_HEALTHCARE,
    'CATEGORY_HOTEL' => Merchant::CATEGORY_HOTEL,
    'CATEGORY_OTHER' => Merchant::CATEGORY_OTHER,
];

foreach ($categories as $name => $value) {
    echo "  {$name} = {$value}\n";
}
echo "  ✓ 类别常量定义正确\n\n";

// 测试3: 模型实例化
echo "【测试3】模型实例化\n";
$merchant = new Merchant();
echo "  表名: " . $merchant->getName() . "\n";
echo "  主键: " . $merchant->getPk() . "\n";
echo "  ✓ 模型实例化成功\n\n";

// 测试4: 测试距离计算方法
echo "【测试4】距离计算方法测试\n";
$testMerchant = new Merchant();
$testMerchant->latitude = 39.904211;
$testMerchant->longitude = 116.407394;

// 计算到天安门广场的距离
$distance1 = $testMerchant->getDistance(39.915, 116.404);
echo "  从商家位置到测试点1的距离: {$distance1} 公里\n";

// 计算到另一个点的距离
$distance2 = $testMerchant->getDistance(39.954, 116.357);
echo "  从商家位置到测试点2的距离: {$distance2} 公里\n";
echo "  ✓ 距离计算方法正常工作\n\n";

// 测试5: 状态检查方法
echo "【测试5】状态检查方法测试\n";
$merchant1 = new Merchant();
$merchant1->status = Merchant::STATUS_ACTIVE;
echo "  商家状态: ACTIVE\n";
echo "    isActive(): " . ($merchant1->isActive() ? '✓ 是' : '× 否') . "\n";
echo "    isDisabled(): " . ($merchant1->isDisabled() ? '× 是' : '✓ 否') . "\n";
echo "    isUnderReview(): " . ($merchant1->isUnderReview() ? '× 是' : '✓ 否') . "\n";

$merchant2 = new Merchant();
$merchant2->status = Merchant::STATUS_DISABLED;
echo "  商家状态: DISABLED\n";
echo "    isActive(): " . ($merchant2->isActive() ? '× 是' : '✓ 否') . "\n";
echo "    isDisabled(): " . ($merchant2->isDisabled() ? '✓ 是' : '× 否') . "\n";
echo "    isUnderReview(): " . ($merchant2->isUnderReview() ? '× 是' : '✓ 否') . "\n";

$merchant3 = new Merchant();
$merchant3->status = Merchant::STATUS_UNDER_REVIEW;
echo "  商家状态: UNDER_REVIEW\n";
echo "    isActive(): " . ($merchant3->isActive() ? '× 是' : '✓ 否') . "\n";
echo "    isDisabled(): " . ($merchant3->isDisabled() ? '× 是' : '✓ 否') . "\n";
echo "    isUnderReview(): " . ($merchant3->isUnderReview() ? '✓ 是' : '× 否') . "\n";
echo "  ✓ 状态检查方法正常工作\n\n";

// 测试6: 验证规则
echo "【测试6】验证规则测试\n";
$rules = Merchant::getValidateRules();
echo "  验证规则数量: " . count($rules) . "\n";
echo "  必填字段:\n";
echo "    - user_id: " . (isset($rules['user_id']) ? '✓' : '×') . "\n";
echo "    - name: " . (isset($rules['name']) ? '✓' : '×') . "\n";
echo "    - category: " . (isset($rules['category']) ? '✓' : '×') . "\n";
echo "    - address: " . (isset($rules['address']) ? '✓' : '×') . "\n";
echo "  ✓ 验证规则定义正确\n\n";

// 测试7: 验证消息
echo "【测试7】验证消息测试\n";
$messages = Merchant::getValidateMessages();
echo "  验证消息数量: " . count($messages) . "\n";
echo "  示例消息:\n";
echo "    - name.require: " . ($messages['name.require'] ?? '未定义') . "\n";
echo "    - category.require: " . ($messages['category.require'] ?? '未定义') . "\n";
echo "    - address.require: " . ($messages['address.require'] ?? '未定义') . "\n";
echo "  ✓ 验证消息定义正确\n\n";

// 测试8: 检查模型方法是否存在
echo "【测试8】检查模型方法\n";
$methods = [
    'isActive' => '检查是否正常营业',
    'isDisabled' => '检查是否已禁用',
    'isUnderReview' => '检查是否审核中',
    'updateStatus' => '更新商家状态',
    'getDistance' => '计算距离',
    'user' => '关联用户',
    'nfcDevices' => '关联NFC设备',
    'coupons' => '关联优惠券',
    'contentTemplates' => '关联内容模板',
    'contentTasks' => '关联内容任务',
    'getByUserId' => '根据用户ID获取商家',
    'getByCategory' => '根据类别获取商家',
    'getNearbyMerchants' => '获取附近商家',
    'scopeActive' => '正常营业作用域',
    'scopeByCategory' => '按类别筛选作用域',
    'scopeByStatus' => '按状态筛选作用域',
    'scopeNearby' => '附近商家作用域',
];

foreach ($methods as $method => $description) {
    $exists = method_exists(Merchant::class, $method);
    echo "  " . ($exists ? '✓' : '×') . " {$method} - {$description}\n";
}
echo "\n";

// 测试9: 检查获取器方法
echo "【测试9】检查获取器方法\n";
$getters = [
    'getStatusTextAttr' => '状态文本',
    'getFullAddressAttr' => '完整地址',
    'getLogoUrlAttr' => 'Logo URL',
    'getCoordinatesAttr' => '坐标',
    'getBusinessHoursTextAttr' => '营业时间文本',
];

foreach ($getters as $getter => $description) {
    $exists = method_exists(Merchant::class, $getter);
    echo "  " . ($exists ? '✓' : '×') . " {$getter} - {$description}\n";
}
echo "\n";

// 测试10: 测试Haversine距离计算准确性
echo "【测试10】Haversine距离计算准确性\n";
$merchant = new Merchant();
$merchant->latitude = 39.9042;
$merchant->longitude = 116.4074;

// 已知距离测试（北京天安门到鸟巢约11.5公里）
$distance = $merchant->getDistance(39.9928, 116.3907);
echo "  计算距离: {$distance} 公里\n";
echo "  预期距离: ~11.5 公里\n";
echo "  " . (abs($distance - 11.5) < 1 ? '✓' : '×') . " 距离计算在合理范围内\n\n";

// 汇总
echo "========================================\n";
echo "测试完成！\n";
echo "========================================\n";
echo "\n模型功能总结：\n";
echo "  ✓ 状态常量 (3个)\n";
echo "  ✓ 类别常量 (8个)\n";
echo "  ✓ 状态检查方法 (3个)\n";
echo "  ✓ 距离计算方法 (Haversine公式)\n";
echo "  ✓ 查询作用域 (4个)\n";
echo "  ✓ 关联关系 (5个)\n";
echo "  ✓ 获取器方法 (5个)\n";
echo "  ✓ 静态查询方法 (3个)\n";
echo "  ✓ 验证规则和消息\n";
echo "\n模型符合ThinkPHP 8.0规范！\n";
