<?php
// 获取token并保存到文件
$response = file_get_contents('http://localhost:8000/api/auth/phone-login', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode(['phone' => '13800138000', 'code' => '123456'])
    ]
]));

$data = json_decode($response, true);
if ($data && isset($data['data']['token'])) {
    file_put_contents('test_token.txt', $data['data']['token']);
    echo "Token saved successfully\n";
    echo "User ID: " . $data['data']['user']['id'] . "\n";
} else {
    echo "Failed to get token\n";
    echo "Response: " . $response . "\n";
}