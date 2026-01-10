<?php
/**
 * 测试错误处理功能
 */

require_once __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

try {
    echo "=== 错误处理测试 ===\n\n";

    // 测试1: 业务异常
    echo "1. 测试业务异常:\n";
    try {
        throw new \app\common\exception\BusinessException('这是一个业务异常', 400, ['error_code' => 'BUSINESS_ERROR']);
    } catch (\app\common\exception\BusinessException $e) {
        echo "捕获业务异常: " . $e->getMessage() . "\n";
        echo "异常代码: " . $e->getCode() . "\n";
        echo "异常数据: " . json_encode($e->getData(), JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    // 测试2: 静态方法创建业务异常
    echo "2. 测试静态方法创建业务异常:\n";
    $exceptions = [
        \app\common\exception\BusinessException::authFailed('用户认证失败'),
        \app\common\exception\BusinessException::forbidden('权限不足'),
        \app\common\exception\BusinessException::notFound('用户不存在'),
        \app\common\exception\BusinessException::invalidParameter('参数格式错误', ['field' => 'email']),
        \app\common\exception\BusinessException::serviceUnavailable('AI服务暂时不可用'),
        \app\common\exception\BusinessException::operationFailed('数据库操作失败'),
        \app\common\exception\BusinessException::rateLimited('请求过于频繁', ['retry_after' => 60]),
    ];

    foreach ($exceptions as $e) {
        echo "异常类型: " . get_class($e) . "\n";
        echo "错误信息: " . $e->getMessage() . "\n";
        echo "错误代码: " . $e->getCode() . "\n";
        echo "额外数据: " . json_encode($e->getData(), JSON_UNESCAPED_UNICODE) . "\n";
        echo "---\n";
    }

    // 测试3: 数组转换
    echo "\n3. 测试异常数组转换:\n";
    $businessException = \app\common\exception\BusinessException::invalidParameter(
        '验证失败',
        ['username' => '用户名不能为空', 'email' => '邮箱格式不正确']
    );
    echo "异常数组格式: " . json_encode($businessException->toArray(), JSON_UNESCAPED_UNICODE) . "\n\n";

    // 测试4: 测试API错误处理
    echo "4. 测试API错误处理:\n";
    echo "测试404错误...\n";
    $ch = curl_init('http://localhost:8000/api/nonexistent/endpoint');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'data']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP状态码: $httpCode\n";
    echo "响应内容: " . $response . "\n\n";

    // 测试5: 测试验证错误
    echo "5. 测试验证错误处理...\n";
    $ch = curl_init('http://localhost:8000/api/auth/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['invalid' => 'data']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP状态码: $httpCode\n";
    echo "响应内容: " . $response . "\n\n";

    echo "=== 错误处理测试完成 ===\n";

} catch (Exception $e) {
    echo "测试过程中发生错误: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}