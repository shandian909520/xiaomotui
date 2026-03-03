<?php
$url = 'http://localhost:8080/api/ai-content/history?limit=5';
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ4aWFvbW90dWkiLCJhdWQiOiJhZG1pbiIsImlhdCI6MTc3MDYxOTA0NCwiZXhwIjoxNzcwNzA1NDQ0LCJzdWIiOjAsInJvbGUiOiJhZG1pbiIsInVzZXJuYW1lIjoiYWRtaW4ifQ.Te4pXf1aV9V5K8fdVv9k7zaVrBMHz2inbsHtYISEBM4';

ob_start();

echo "Testing URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch) . "\n";
}

curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
$responseData = json_decode($response, true);
echo "Full Response:\n";
print_r($responseData);
if (!$responseData && $response) {
    echo "Raw Response: " . $response . "\n";
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/test_history_api.log', $output);
echo $output;
