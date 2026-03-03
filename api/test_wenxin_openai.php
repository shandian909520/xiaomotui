<?php
// Test script for Baidu Wenxin OpenAI compatible protocol

$apiKey = 'bce-v3/ALTAK-iJcAR2zLQUgkEu006Q9QL/db76f75ad434ed4fbb05d311e95e768ab9d05c14';
$baseUrl = 'https://qianfan.baidubce.com/v2';
$url = $baseUrl . '/chat/completions';

$data = [
    'model' => 'ernie-3.5-8k', // Try a standard model name usually supported
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, are you working?']
    ],
    'stream' => false
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
curl_setopt($ch, CURLOPT_VERBOSE, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . $error . "\n";
echo "Response: " . $response . "\n";
