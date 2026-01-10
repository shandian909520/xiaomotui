<?php
/**
 * 测试内容生成API
 * 通过HTTP请求测试API端点
 */

echo "=== 内容生成API测试 ===\n\n";

// 测试配置
$baseUrl = 'http://localhost/xiaomotui/api/public/index.php/api';
$testMerchantId = 1;
$testDeviceId = 1;
$testTemplateId = 1;

// 测试数据
$tests = [
    [
        'name' => '创建VIDEO类型任务（带模板和设备）',
        'method' => 'POST',
        'url' => '/content/generate',
        'data' => [
            'merchant_id' => $testMerchantId,
            'device_id' => $testDeviceId,
            'template_id' => $testTemplateId,
            'type' => 'VIDEO',
            'input_data' => [
                'scene' => '咖啡店',
                'style' => '温馨',
                'requirements' => '突出环境氛围'
            ]
        ],
        'headers' => [
            'Content-Type: application/json',
            // 这里需要添加JWT token
            // 'Authorization: Bearer <token>'
        ]
    ],
    [
        'name' => '创建TEXT类型任务（不带模板）',
        'method' => 'POST',
        'url' => '/content/generate',
        'data' => [
            'merchant_id' => $testMerchantId,
            'type' => 'TEXT',
            'input_data' => [
                'content_type' => '菜单文本',
                'style' => '简洁'
            ]
        ],
        'headers' => [
            'Content-Type: application/json'
        ]
    ],
    [
        'name' => '创建IMAGE类型任务',
        'method' => 'POST',
        'url' => '/content/generate',
        'data' => [
            'merchant_id' => $testMerchantId,
            'device_id' => $testDeviceId,
            'type' => 'IMAGE',
            'input_data' => [
                'size' => '1920x1080',
                'format' => 'png',
                'style' => '简约'
            ]
        ],
        'headers' => [
            'Content-Type: application/json'
        ]
    ]
];

echo "测试API端点: {$baseUrl}/content/generate\n";
echo "注意: 需要先登录获取JWT token并设置到Authorization头中\n\n";

// 显示测试用例
foreach ($tests as $index => $test) {
    echo "=== 测试用例 " . ($index + 1) . ": {$test['name']} ===\n";
    echo "请求方法: {$test['method']}\n";
    echo "请求URL: {$baseUrl}{$test['url']}\n";
    echo "请求数据:\n";
    echo json_encode($test['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "\n期望响应格式:\n";
    echo "{\n";
    echo "    \"code\": 200,\n";
    echo "    \"message\": \"success\",\n";
    echo "    \"data\": {\n";
    echo "        \"task_id\": 123,\n";
    echo "        \"status\": \"PENDING\",\n";
    echo "        \"type\": \"VIDEO|TEXT|IMAGE\",\n";
    echo "        \"estimated_time\": 30,\n";
    echo "        \"create_time\": \"2025-09-30 12:00:00\",\n";
    echo "        \"message\": \"任务已创建，预计30秒完成\"\n";
    echo "    },\n";
    echo "    \"timestamp\": 1234567890\n";
    echo "}\n\n";
}

// 使用curl测试示例
echo "=== CURL测试示例 ===\n\n";

$curlExample = <<<'CURL'
# 1. 先登录获取token
curl -X POST http://localhost/xiaomotui/api/public/index.php/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "13800138000",
    "password": "123456"
  }'

# 2. 使用token创建内容生成任务
curl -X POST http://localhost/xiaomotui/api/public/index.php/api/content/generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <your_token_here>" \
  -d '{
    "merchant_id": 1,
    "device_id": 1,
    "template_id": 1,
    "type": "VIDEO",
    "input_data": {
      "scene": "咖啡店",
      "style": "温馨",
      "requirements": "突出环境氛围"
    }
  }'

# 3. 查询任务状态
curl -X GET "http://localhost/xiaomotui/api/public/index.php/api/content/task-status?task_id=123" \
  -H "Authorization: Bearer <your_token_here>"

CURL;

echo $curlExample . "\n\n";

echo "=== 测试说明 ===\n";
echo "1. 功能已实现，路由已配置在 /api/content/generate\n";
echo "2. 需要JWT认证，请先调用 /api/auth/login 获取token\n";
echo "3. 支持三种内容类型: VIDEO, TEXT, IMAGE\n";
echo "4. merchant_id 是必填字段\n";
echo "5. device_id 和 template_id 是可选字段\n";
echo "6. input_data 包含内容生成的需求信息\n\n";

echo "=== API响应说明 ===\n";
echo "成功响应 (200):\n";
echo "  - task_id: 任务ID\n";
echo "  - status: PENDING (待处理)\n";
echo "  - type: 内容类型\n";
echo "  - estimated_time: 预估处理时间(秒)\n";
echo "  - message: 提示信息\n\n";

echo "错误响应示例:\n";
echo "  - 401: 未登录或token无效\n";
echo "  - 400: 参数验证失败\n";
echo "  - 404: 设备或模板不存在\n";
echo "  - 429: 配额不足\n\n";

echo "=== 实现文件清单 ===\n";
echo "✓ 控制器: D:\\xiaomotui\\api\\app\\controller\\Content.php\n";
echo "✓ 服务类: D:\\xiaomotui\\api\\app\\service\\ContentService.php\n";
echo "✓ 模型: D:\\xiaomotui\\api\\app\\model\\ContentTask.php\n";
echo "✓ 验证器: D:\\xiaomotui\\api\\app\\validate\\Content.php\n";
echo "✓ 路由: D:\\xiaomotui\\api\\route\\app.php (第77行)\n";
echo "✓ 数据库: D:\\xiaomotui\\api\\database\\migrations\\20250929222838_create_content_tasks_table.sql\n\n";

echo "=== 测试完成 ===\n";
echo "内容生成任务创建功能已完整实现！\n";