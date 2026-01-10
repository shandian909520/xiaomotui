<?php
/**
 * Merchant模型测试脚本
 * 用于测试Merchant模型的基本功能、关联关系、查询作用域等
 */

namespace think;

// 引入ThinkPHP框架
require __DIR__ . '/vendor/autoload.php';

use app\model\Merchant;
use app\model\User;
use app\model\NfcDevice;

// 初始化应用
$app = new App();
$app->initialize();

echo "========================================\n";
echo "Merchant模型测试开始\n";
echo "========================================\n\n";

try {
    // 测试1: 检查模型是否可以正常实例化
    echo "【测试1】模型实例化测试\n";
    $merchant = new Merchant();
    echo "✓ Merchant模型实例化成功\n";
    echo "  表名: {$merchant->getName()}\n";
    echo "  主键: {$merchant->getPk()}\n\n";

    // 测试2: 测试状态常量
    echo "【测试2】状态常量测试\n";
    echo "  STATUS_DISABLED: " . Merchant::STATUS_DISABLED . "\n";
    echo "  STATUS_ACTIVE: " . Merchant::STATUS_ACTIVE . "\n";
    echo "  STATUS_UNDER_REVIEW: " . Merchant::STATUS_UNDER_REVIEW . "\n\n";

    // 测试3: 测试类别常量
    echo "【测试3】类别常量测试\n";
    echo "  CATEGORY_RESTAURANT: " . Merchant::CATEGORY_RESTAURANT . "\n";
    echo "  CATEGORY_RETAIL: " . Merchant::CATEGORY_RETAIL . "\n";
    echo "  CATEGORY_SERVICE: " . Merchant::CATEGORY_SERVICE . "\n";
    echo "  CATEGORY_ENTERTAINMENT: " . Merchant::CATEGORY_ENTERTAINMENT . "\n";
    echo "  CATEGORY_EDUCATION: " . Merchant::CATEGORY_EDUCATION . "\n";
    echo "  CATEGORY_HEALTHCARE: " . Merchant::CATEGORY_HEALTHCARE . "\n";
    echo "  CATEGORY_HOTEL: " . Merchant::CATEGORY_HOTEL . "\n";
    echo "  CATEGORY_OTHER: " . Merchant::CATEGORY_OTHER . "\n\n";

    // 测试4: 查询总记录数
    echo "【测试4】查询商家总数\n";
    $totalCount = Merchant::count();
    echo "  商家总数: {$totalCount}\n\n";

    // 测试5: 查询正常营业的商家
    echo "【测试5】查询正常营业的商家\n";
    $activeMerchants = Merchant::active()->select();
    echo "  正常营业商家数量: " . count($activeMerchants) . "\n";
    if (!empty($activeMerchants)) {
        $first = $activeMerchants[0];
        echo "  示例商家:\n";
        echo "    - ID: {$first->id}\n";
        echo "    - 名称: {$first->name}\n";
        echo "    - 类别: {$first->category}\n";
        echo "    - 状态: {$first->status_text}\n";
    }
    echo "\n";

    // 测试6: 测试商家创建
    echo "【测试6】创建测试商家\n";

    // 先查找一个用户
    $user = User::find(1);
    if (!$user) {
        echo "  警告: 未找到ID为1的用户，跳过创建测试\n\n";
    } else {
        $testData = [
            'user_id' => $user->id,
            'name' => '测试商家_' . date('YmdHis'),
            'category' => Merchant::CATEGORY_RESTAURANT,
            'address' => '测试地址 - 北京市朝阳区',
            'longitude' => 116.407394,
            'latitude' => 39.904211,
            'phone' => '13800138000',
            'description' => '这是一个测试商家描述',
            'logo' => '/uploads/test_logo.png',
            'business_hours' => [
                'open' => '09:00',
                'close' => '22:00'
            ],
            'status' => Merchant::STATUS_ACTIVE
        ];

        $newMerchant = Merchant::create($testData);
        if ($newMerchant) {
            echo "  ✓ 商家创建成功\n";
            echo "    - ID: {$newMerchant->id}\n";
            echo "    - 名称: {$newMerchant->name}\n";
            echo "    - 状态文本: {$newMerchant->status_text}\n";
            echo "    - Logo URL: {$newMerchant->logo_url}\n";
            echo "    - 营业时间: {$newMerchant->business_hours_text}\n";
            echo "    - 坐标: 经度={$newMerchant->longitude}, 纬度={$newMerchant->latitude}\n";

            // 测试7: 测试模型方法
            echo "\n【测试7】测试模型方法\n";
            echo "  isActive(): " . ($newMerchant->isActive() ? '是' : '否') . "\n";
            echo "  isDisabled(): " . ($newMerchant->isDisabled() ? '是' : '否') . "\n";
            echo "  isUnderReview(): " . ($newMerchant->isUnderReview() ? '是' : '否') . "\n";

            // 测试距离计算
            echo "\n【测试8】测试距离计算\n";
            $testLat = 39.915;
            $testLon = 116.404;
            $distance = $newMerchant->getDistance($testLat, $testLon);
            echo "  到测试坐标 (纬度: {$testLat}, 经度: {$testLon}) 的距离: {$distance} 公里\n";

            // 测试9: 测试状态更新
            echo "\n【测试9】测试状态更新\n";
            $result = $newMerchant->updateStatus(Merchant::STATUS_UNDER_REVIEW);
            if ($result) {
                echo "  ✓ 状态更新成功\n";
                echo "  新状态: {$newMerchant->status_text}\n";
                echo "  isUnderReview(): " . ($newMerchant->isUnderReview() ? '是' : '否') . "\n";
            }

            // 测试10: 测试关联查询
            echo "\n【测试10】测试关联查询\n";
            $merchantWithUser = Merchant::with('user')->find($newMerchant->id);
            if ($merchantWithUser && $merchantWithUser->user) {
                echo "  ✓ 关联用户查询成功\n";
                echo "    - 用户ID: {$merchantWithUser->user->id}\n";
                echo "    - 用户昵称: {$merchantWithUser->user->nickname}\n";
            } else {
                echo "  - 未找到关联用户\n";
            }

            // 查询NFC设备
            $devices = $newMerchant->nfcDevices()->select();
            echo "  关联NFC设备数量: " . count($devices) . "\n";

            // 测试11: 测试按类别查询
            echo "\n【测试11】测试按类别查询\n";
            $restaurants = Merchant::byCategory(Merchant::CATEGORY_RESTAURANT)->select();
            echo "  餐饮类商家数量: " . count($restaurants) . "\n";

            // 测试12: 测试附近商家查询
            echo "\n【测试12】测试附近商家查询\n";
            $nearbyMerchants = Merchant::nearby(39.904211, 116.407394, 10)->select();
            echo "  10公里内的商家数量: " . count($nearbyMerchants) . "\n";

            // 测试13: 测试静态方法
            echo "\n【测试13】测试静态方法\n";
            $userMerchants = Merchant::getByUserId($user->id);
            echo "  用户 ID={$user->id} 的商家数量: " . count($userMerchants) . "\n";

            // 测试14: 测试附近商家方法（包含距离计算）
            echo "\n【测试14】测试附近商家方法（包含距离计算）\n";
            $nearbyWithDistance = Merchant::getNearbyMerchants(39.915, 116.404, 5, 10);
            echo "  5公里内的商家数量: " . count($nearbyWithDistance) . "\n";
            if (!empty($nearbyWithDistance)) {
                echo "  最近的3个商家:\n";
                $count = 0;
                foreach ($nearbyWithDistance as $m) {
                    if ($count >= 3) break;
                    echo "    {$count}. {$m['name']} - 距离: {$m['distance']} 公里\n";
                    $count++;
                }
            }

            // 测试15: 测试验证规则
            echo "\n【测试15】验证规则测试\n";
            $rules = Merchant::getValidateRules();
            echo "  验证规则数量: " . count($rules) . "\n";
            echo "  必填字段: user_id, name, category, address\n";

            // 清理测试数据
            echo "\n【测试16】清理测试数据\n";
            $deleted = $newMerchant->delete();
            if ($deleted) {
                echo "  ✓ 测试商家已删除\n";
            }
        } else {
            echo "  × 商家创建失败\n";
        }
    }

    // 测试17: 测试模型获取器
    echo "\n【测试17】测试模型获取器\n";
    $existingMerchant = Merchant::find(1);
    if ($existingMerchant) {
        echo "  ✓ 找到商家 ID=1\n";
        echo "  状态文本获取器: {$existingMerchant->status_text}\n";
        echo "  完整地址获取器: {$existingMerchant->full_address}\n";
        echo "  Logo URL获取器: {$existingMerchant->logo_url}\n";
        echo "  营业时间文本: {$existingMerchant->business_hours_text}\n";
        $coords = $existingMerchant->coordinates;
        echo "  坐标获取器: 经度={$coords['longitude']}, 纬度={$coords['latitude']}\n";
    } else {
        echo "  - 未找到ID为1的商家\n";
    }

    echo "\n========================================\n";
    echo "所有测试完成！\n";
    echo "========================================\n";

} catch (\Exception $e) {
    echo "\n✗ 测试出错:\n";
    echo "  错误信息: " . $e->getMessage() . "\n";
    echo "  文件: " . $e->getFile() . "\n";
    echo "  行号: " . $e->getLine() . "\n";
    echo "  堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}
