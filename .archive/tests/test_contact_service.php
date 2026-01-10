<?php
/**
 * ContactService 功能测试脚本
 * 用于验证好友添加服务的基本功能
 */

// 引入ThinkPHP框架
require __DIR__ . '/vendor/autoload.php';

use app\service\ContactService;
use think\facade\Db;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "===== ContactService 功能测试 =====\n\n";

try {
    // 创建服务实例
    $contactService = new ContactService();
    echo "✓ ContactService 实例创建成功\n\n";

    // 测试1: 验证商家配置
    echo "【测试1】验证商家配置\n";
    $merchantId = 1;

    try {
        $config = $contactService->getMerchantContactConfig($merchantId);
        echo "✓ 获取商家配置成功\n";
        echo "配置内容:\n";
        print_r($config);
    } catch (\Exception $e) {
        echo "✗ 获取商家配置失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试2: 验证联系方式配置
    echo "【测试2】验证联系方式配置\n";
    $contactTypes = ['wework', 'wechat', 'phone'];

    foreach ($contactTypes as $type) {
        $isValid = $contactService->validateContactConfig($merchantId, $type);
        $status = $isValid ? '✓ 有效' : '✗ 无效';
        echo "{$status} - {$type}\n";
    }
    echo "\n";

    // 测试3: 生成企业微信链接
    echo "【测试3】生成企业微信添加链接\n";
    try {
        $url = $contactService->generateWeworkContactUrl('TestUser', ['source' => 'test']);
        echo "✓ 生成成功: {$url}\n";
    } catch (\Exception $e) {
        echo "✗ 生成失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试4: 生成个人微信二维码
    echo "【测试4】生成个人微信二维码\n";
    try {
        $qrData = $contactService->generateWechatQrcode('xiaomotui_test');
        echo "✓ 生成成功\n";
        echo "二维码信息:\n";
        print_r($qrData);
    } catch (\Exception $e) {
        echo "✗ 生成失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试5: 生成联系方式数据（如果配置有效）
    echo "【测试5】生成联系方式数据\n";
    foreach ($contactTypes as $type) {
        try {
            $data = $contactService->generateContactData($merchantId, $type);
            echo "✓ {$type} 数据生成成功\n";
            echo "数据结构:\n";
            print_r($data);
            echo "\n";
        } catch (\Exception $e) {
            echo "✗ {$type} 数据生成失败: " . $e->getMessage() . "\n\n";
        }
    }

    // 测试6: 记录联系行为（模拟）
    echo "【测试6】记录联系行为\n";
    try {
        // 注意：这需要有效的设备ID，如果数据库中没有设备，会失败
        // $success = $contactService->recordContactAction(1, null, 'wework', ['test' => true]);
        // echo $success ? "✓ 记录成功\n" : "✗ 记录失败\n";
        echo "⚠ 跳过（需要有效的设备ID）\n";
    } catch (\Exception $e) {
        echo "✗ 记录失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试7: 缓存操作
    echo "【测试7】缓存操作\n";
    try {
        $result = $contactService->clearMerchantContactCache($merchantId);
        echo $result ? "✓ 清除缓存成功\n" : "✗ 清除缓存失败\n";
    } catch (\Exception $e) {
        echo "✗ 缓存操作失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试8: 获取统计数据
    echo "【测试8】获取统计数据\n";
    try {
        $stats = $contactService->getContactStats($merchantId, [
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d')
        ]);
        echo "✓ 获取统计成功\n";
        echo "统计数据:\n";
        print_r($stats);
    } catch (\Exception $e) {
        echo "✗ 获取统计失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "===== 测试完成 =====\n";
    echo "\n提示：\n";
    echo "1. 如果看到配置无效的提示，请先在数据库中配置商家联系方式\n";
    echo "2. 企业微信功能需要配置 .env 文件中的 WEWORK_* 参数\n";
    echo "3. 记录行为功能需要数据库中有有效的NFC设备\n";
    echo "4. 运行数据库迁移文件以创建 contact_actions 表\n";

} catch (\Exception $e) {
    echo "✗ 测试过程出现错误: " . $e->getMessage() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n测试脚本执行完毕\n";