<?php
// 简单的认证测试脚本
require_once 'vendor/autoload.php';

// 设置基本环境
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'phone' => '13800138000',
    'code' => '123456'
];

// 模拟请求
$request = new think\Request();
$request->withPost($_POST);

try {
    echo "开始测试手机号登录...\n";

    // 直接测试AuthService
    $authService = new \app\service\AuthService();
    $result = $authService->phoneLogin('13800138000');

    echo "登录成功!\n";
    echo "用户ID: " . $result['user_id'] . "\n";
    echo "Token: " . substr($result['token'], 0, 20) . "...\n";

} catch (\Exception $e) {
    echo "登录失败: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n测试完成。\n";