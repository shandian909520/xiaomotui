<?php
function checkUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => substr($response, 0, 100)];
}

echo "Checking Backend (28080)...\n";
$backend = checkUrl('http://localhost:28080');
echo "Backend: " . $backend['code'] . "\n";

echo "Checking Admin (23003)...\n";
$admin = checkUrl('http://localhost:23003');
echo "Admin: " . $admin['code'] . "\n";

echo "Checking Uni-app (28081)...\n";
$uniapp = checkUrl('http://localhost:28081');
echo "Uni-app: " . $uniapp['code'] . "\n";
