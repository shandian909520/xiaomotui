<?php
/**
 * 调试认证流程
 */

// 1. 发送验证码
echo "步骤1: 发送验证码...\n";
$ch = curl_init('http://127.0.0.1:8000/api/auth/send-code');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['phone' => '13800138000']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response1 = curl_exec($ch);
$data1 = json_decode($response1, true);
echo "响应: " . $response1 . "\n\n";

if (!isset($data1['data']['code'])) {
    die("无法获取验证码\n");
}

$code = $data1['data']['code'];
echo "验证码: {$code}\n\n";

// 2. 手机号登录
echo "步骤2: 手机号登录...\n";
$ch = curl_init('http://127.0.0.1:8000/api/auth/phone-login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['phone' => '13800138000', 'code' => $code]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($ch);
$data2 = json_decode($response2, true);
echo "响应: " . $response2 . "\n\n";

if (!isset($data2['data']['token'])) {
    die("无法获取token\n");
}

$token = $data2['data']['token'];
echo "Token: {$token}\n\n";

// 3. 测试获取用户信息
echo "步骤3: 获取用户信息...\n";
$ch = curl_init('http://127.0.0.1:8000/api/auth/info');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response3 = curl_exec($ch);
echo "响应: " . $response3 . "\n\n";

// 4. 测试AI接口
echo "步骤4: 测试AI状态接口...\n";
$ch = curl_init('http://127.0.0.1:8000/api/ai/status');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response4 = curl_exec($ch);
echo "响应: " . $response4 . "\n\n";

// 5. 测试统计接口
echo "步骤5: 测试统计概览接口...\n";
$ch = curl_init('http://127.0.0.1:8000/api/statistics/overview');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response5 = curl_exec($ch);
echo "响应: " . $response5 . "\n\n";

echo "调试完成!\n";
