<?php
/**
 * 临时修复脚本：开启短信调试模式
 * 使用方法：浏览器访问 http://your-domain.com/api/fix_sms_debug.php
 * 使用后请删除此文件！
 */

header('Content-Type: text/html; charset=utf-8');

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die("错误：.env 文件不存在");
}

$content = file_get_contents($envFile);

// 检查是否已配置
$hasDebugEnabled = strpos($content, 'SMS_DEBUG_ENABLED') !== false;
$hasTestCode = strpos($content, 'SMS_DEBUG_TEST_CODE') !== false;
$hasReturnCode = strpos($content, 'SMS_DEBUG_RETURN_CODE') !== false;

if ($hasDebugEnabled && $hasTestCode && $hasReturnCode) {
    echo "<h1>短信调试模式已开启</h1>";
    echo "<p>验证码固定为：<strong>123456</strong></p>";
    echo "<p>请删除此文件以确保安全！</p>";
    exit;
}

// 添加配置
$additions = "\n# 短信调试模式（临时）\nSMS_DEBUG_ENABLED = true\nSMS_DEBUG_TEST_CODE = 123456\nSMS_DEBUG_RETURN_CODE = true\n";

file_put_contents($envFile, $additions, FILE_APPEND);

echo "<h1>配置已修改成功！</h1>";
echo "<p>已添加以下配置到 .env 文件：</p>";
echo "<pre>SMS_DEBUG_ENABLED = true\nSMS_DEBUG_TEST_CODE = 123456\nSMS_DEBUG_RETURN_CODE = true</pre>";
echo "<p>现在发送验证码会返回：<strong>123456</strong></p>";
echo "<p style='color:red;'><strong>重要：请立即删除此文件！</strong></p>";
echo "<p><a href='/api/'>返回首页</a></p>";
