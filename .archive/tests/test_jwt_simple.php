<?php
require_once 'vendor/autoload.php';

// 直接使用Firebase JWT库测试
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

echo "=== JWT简单调试测试 ===\n\n";

try {

    // 配置
    $secret = 'xiaomotui_jwt_secret_key_2024_secure_token';
    $algorithm = 'HS256';
    $issuer = 'xiaomotui';
    $audience = 'miniprogram';

    echo "1. 测试JWT生成...\n";

    // 生成token
    $payload = [
        'iss' => $issuer,
        'aud' => $audience,
        'iat' => time(),
        'exp' => time() + 86400,
        'sub' => 1,
        'openid' => 'test_openid_001',
        'role' => 'merchant',
        'merchant_id' => 1
    ];

    $token = JWT::encode($payload, $secret, $algorithm);
    echo "✓ JWT生成成功\n";
    echo "Token长度: " . strlen($token) . "\n\n";

    echo "2. 测试JWT验证...\n";

    // 验证token
    $decoded = JWT::decode($token, new Key($secret, $algorithm));
    $decodedArray = (array) $decoded;

    echo "✓ JWT验证成功\n";
    echo "用户ID: " . ($decodedArray['sub'] ?? 'N/A') . "\n";
    echo "角色: " . ($decodedArray['role'] ?? 'N/A') . "\n";
    echo "商家ID: " . ($decodedArray['merchant_id'] ?? 'N/A') . "\n\n";

    echo "3. 测试Token解析...\n";

    // 解析token（不验证签名）
    $parts = explode('.', $token);
    if (count($parts) === 3) {
        $header = json_decode(base64_decode($parts[0]), true);
        $payload_part = json_decode(base64_decode($parts[1]), true);

        echo "✓ Token解析成功\n";
        echo "算法: " . ($header['alg'] ?? 'N/A') . "\n";
        echo "类型: " . ($header['typ'] ?? 'N/A') . "\n";
        echo "签发者: " . ($payload_part['iss'] ?? 'N/A') . "\n";
        echo "接收者: " . ($payload_part['aud'] ?? 'N/A') . "\n\n";
    }

    // 保存token到文件
    file_put_contents('debug_token.txt', $token);
    echo "✓ Token已保存到 debug_token.txt\n\n";

    // 测试用这个token访问API
    echo "4. 测试API访问...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/info');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "API响应状态码: " . $httpCode . "\n";
    echo "API响应内容: " . substr($response, 0, 200) . "...\n\n";

    if ($httpCode === 200) {
        echo "✅ API访问成功，JWT认证工作正常\n";
    } else {
        echo "❌ API访问失败，JWT认证存在问题\n";

        // 尝试解析错误响应
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['message'])) {
            echo "错误信息: " . $responseData['message'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ JWT测试失败\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n测试完成。\n";