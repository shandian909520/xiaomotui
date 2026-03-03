<?php
$url = 'http://localhost:8080/api/ai-content/generate-text';
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ4aWFvbW90dWkiLCJhdWQiOiJhZG1pbiIsImlhdCI6MTc3MDYxOTA0NCwiZXhwIjoxNzcwNzA1NDQ0LCJzdWIiOjAsInJvbGUiOiJhZG1pbiIsInVzZXJuYW1lIjoiYWRtaW4ifQ.Te4pXf1aV9V5K8fdVv9k7zaVrBMHz2inbsHtYISEBM4';

$data = [
    'scene' => '探店推广',
    'category' => '餐饮美食',
    'platform' => 'douyin',
    'style' => '亲切',
    'requirements' => '环境优美'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);
echo "Full Response:\n";
print_r($responseData);
echo "HTTP Code: " . $httpCode . "\n";
if (isset($responseData['data']['model'])) {
    echo "Model: " . $responseData['data']['model'] . "\n";
    echo "Text: " . ($responseData['data']['text'] ?? 'No text generated') . "\n";
} else {
    echo "Response: " . $response . "\n";
}
