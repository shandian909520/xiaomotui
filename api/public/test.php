<?php
// 简单的API测试文件
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

echo json_encode([
    'code' => 200,
    'msg' => '小磨推API测试成功',
    'data' => [
        'version' => '1.0.0',
        'timestamp' => time(),
        'status' => 'running'
    ]
]);