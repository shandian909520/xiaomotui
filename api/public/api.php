<?php
// 简单的API测试接口
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 获取请求路径
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = $_GET['path'] ?? '';

// 简单的路由
if ($path == '/api/test' || $path == '/api/test.php' || $query == '/api/test') {
    echo json_encode([
        'code' => 200,
        'msg' => '小魔推API测试成功',
        'data' => [
            'version' => '1.0.0',
            'timestamp' => time(),
            'status' => 'running',
            'service' => 'xiaomotui-api'
        ]
    ]);
} else if ($path == '/api/auth/info' || $query == '/api/auth/info') {
    echo json_encode([
        'code' => 200,
        'msg' => '获取用户信息成功',
        'data' => [
            'user_id' => 1,
            'username' => 'test_user',
            'role' => 'admin',
            'login_time' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode([
        'code' => 404,
        'msg' => '接口不存在',
        'data' => null
    ]);
}
?>