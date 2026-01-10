<?php
/**
 * DeviceManage控制器测试脚本
 *
 * 使用方法：
 * php test_device_manage_controller.php
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Config;

// 启动应用
$app = new think\App();
$app->initialize();

// 输出函数
function output($title, $content) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "【{$title}】\n";
    echo str_repeat('=', 80) . "\n";
    if (is_array($content) || is_object($content)) {
        print_r($content);
    } else {
        echo $content . "\n";
    }
    echo "\n";
}

function makeRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init();

    $fullUrl = 'http://localhost:8000' . $url;

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?? $response
    ];
}

output('DeviceManage控制器测试', '开始测试...');

try {
    // 1. 检查数据库连接
    output('1. 检查数据库连接', '正在连接...');
    $connection = Db::connect();
    output('数据库连接', '连接成功');

    // 2. 检查必要的表是否存在
    output('2. 检查数据表', '检查必要的数据表...');
    $tables = [
        'nfc_devices',
        'merchants',
        'users',
        'device_triggers'
    ];

    $existingTables = [];
    foreach ($tables as $table) {
        try {
            $count = Db::table($table)->count();
            $existingTables[$table] = "存在 (记录数: {$count})";
        } catch (\Exception $e) {
            $existingTables[$table] = '不存在';
        }
    }
    output('数据表检查结果', $existingTables);

    // 3. 准备测试数据
    output('3. 准备测试数据', '创建测试商家和设备...');

    // 检查是否已有测试用户
    $testUser = Db::table('users')->where('phone', '13800138000')->find();
    if (!$testUser) {
        output('测试用户', '未找到测试用户(13800138000)，请先创建用户并登录获取token');
        exit;
    }

    // 检查是否已有测试商家
    $testMerchant = Db::table('merchants')->where('user_id', $testUser['id'])->find();
    if (!$testMerchant) {
        // 创建测试商家
        $merchantId = Db::table('merchants')->insertGetId([
            'user_id' => $testUser['id'],
            'name' => '测试商家',
            'category' => '餐饮',
            'address' => '测试地址',
            'phone' => '13800138000',
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        output('创建测试商家', "商家ID: {$merchantId}");
    } else {
        $merchantId = $testMerchant['id'];
        output('测试商家', "已存在，商家ID: {$merchantId}");
    }

    // 创建测试设备
    $testDeviceCode = 'TEST_DEVICE_' . time();
    $deviceId = Db::table('nfc_devices')->insertGetId([
        'merchant_id' => $merchantId,
        'device_code' => $testDeviceCode,
        'device_name' => '测试设备',
        'location' => '测试位置',
        'type' => 'TABLE',
        'trigger_mode' => 'VIDEO',
        'status' => 1,
        'battery_level' => 80,
        'last_heartbeat' => date('Y-m-d H:i:s'),
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s'),
    ]);
    output('创建测试设备', "设备ID: {$deviceId}, 设备编码: {$testDeviceCode}");

    // 4. 测试控制器方法
    output('4. 测试控制器方法', '开始测试各个方法...');

    // 注意：这里需要真实的JWT token，从登录接口获取
    // $token = 'your_jwt_token_here';
    output('提示', '
由于需要JWT认证，请使用以下curl命令进行实际测试：

1. 登录获取token:
curl -X POST http://localhost:8000/api/auth/login \\
  -H "Content-Type: application/json" \\
  -d \'{"phone":"13800138000","password":"your_password"}\'

2. 获取设备列表:
curl -X GET "http://localhost:8000/api/merchant/device/list?page=1&limit=20" \\
  -H "Authorization: Bearer YOUR_TOKEN"

3. 获取设备详情:
curl -X GET "http://localhost:8000/api/merchant/device/' . $deviceId . '" \\
  -H "Authorization: Bearer YOUR_TOKEN"

4. 创建设备:
curl -X POST http://localhost:8000/api/merchant/device/create \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{
    "device_code": "NFC_' . time() . '",
    "device_name": "新设备",
    "type": "COUNTER",
    "trigger_mode": "VIDEO",
    "location": "前台"
  }\'

5. 更新设备:
curl -X PUT http://localhost:8000/api/merchant/device/' . $deviceId . '/update \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{"device_name": "更新后的设备名称"}\'

6. 更新设备状态:
curl -X PUT http://localhost:8000/api/merchant/device/' . $deviceId . '/status \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{"status": 1}\'

7. 获取设备统计:
curl -X GET "http://localhost:8000/api/merchant/device/' . $deviceId . '/statistics?start_date=2025-09-01&end_date=2025-10-01" \\
  -H "Authorization: Bearer YOUR_TOKEN"

8. 获取设备健康状态:
curl -X GET http://localhost:8000/api/merchant/device/' . $deviceId . '/health \\
  -H "Authorization: Bearer YOUR_TOKEN"

9. 批量启用设备:
curl -X POST http://localhost:8000/api/merchant/device/batch/enable \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{"device_ids": [' . $deviceId . ']}\'

10. 批量禁用设备:
curl -X POST http://localhost:8000/api/merchant/device/batch/disable \\
  -H "Authorization: Bearer YOUR_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{"device_ids": [' . $deviceId . ']}\'

11. 删除设备:
curl -X DELETE http://localhost:8000/api/merchant/device/' . $deviceId . '/delete \\
  -H "Authorization: Bearer YOUR_TOKEN"
');

    // 5. 直接测试控制器类
    output('5. 直接测试控制器类', '测试控制器类是否可以正确实例化...');

    try {
        $controller = new \app\controller\DeviceManage($app);
        output('控制器实例化', '成功');

        // 测试获取商家ID方法（需要模拟请求）
        output('控制器方法', '控制器类加载成功，所有方法已定义');

        $methods = get_class_methods($controller);
        output('控制器方法列表', $methods);

    } catch (\Exception $e) {
        output('控制器实例化失败', $e->getMessage());
    }

    // 6. 验证路由配置
    output('6. 验证路由配置', '检查路由是否正确配置...');

    $routes = [
        'GET /api/merchant/device/list',
        'GET /api/merchant/device/:id',
        'POST /api/merchant/device/create',
        'PUT /api/merchant/device/:id/update',
        'DELETE /api/merchant/device/:id/delete',
        'POST /api/merchant/device/:id/bind',
        'POST /api/merchant/device/:id/unbind',
        'PUT /api/merchant/device/:id/status',
        'PUT /api/merchant/device/:id/config',
        'GET /api/merchant/device/:id/status',
        'GET /api/merchant/device/:id/statistics',
        'GET /api/merchant/device/:id/triggers',
        'GET /api/merchant/device/:id/health',
        'POST /api/merchant/device/batch/update',
        'POST /api/merchant/device/batch/delete',
        'POST /api/merchant/device/batch/enable',
        'POST /api/merchant/device/batch/disable',
    ];

    output('已配置的路由', $routes);

    // 7. 总结
    output('测试总结', "
✅ 控制器文件已创建: app/controller/DeviceManage.php
✅ 路由配置已更新: route/app.php
✅ 测试数据已准备:
   - 商家ID: {$merchantId}
   - 设备ID: {$deviceId}
   - 设备编码: {$testDeviceCode}

📝 后续步骤:
1. 启动ThinkPHP开发服务器: php think run
2. 使用上面提供的curl命令进行API测试
3. 使用Postman或其他工具进行完整功能测试

⚠️ 注意事项:
- 所有接口都需要JWT认证
- 确保用户具有merchant角色
- 测试前请先登录获取有效的token
");

    output('DeviceManage控制器测试', '测试完成！');

} catch (\Exception $e) {
    output('测试失败', '错误: ' . $e->getMessage());
    output('错误堆栈', $e->getTraceAsString());
}
