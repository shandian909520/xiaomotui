<?php
$host = '127.0.0.1';
$db   = 'xiaomotui_dev';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if merchant with user_id = 0 exists
    $stmt = $pdo->query("SELECT id FROM xmt_merchants WHERE user_id = 0");
    $merchant = $stmt->fetch();

    if ($merchant) {
        echo "Merchant for admin (user_id=0) already exists. ID: " . $merchant['id'] . "\n";
    } else {
        echo "Creating merchant for admin (user_id=0)...\n";
        $sql = "INSERT INTO xmt_merchants (user_id, name, category, status, create_time, update_time) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            0, 
            'Admin Merchant', 
            'System', 
            1, 
            date('Y-m-d H:i:s'), 
            date('Y-m-d H:i:s')
        ]);
        echo "Merchant created successfully.\n";
    }

} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    // Try without prefix if it fails?
    try {
        $stmt = $pdo->query("SELECT id FROM merchants WHERE user_id = 0");
        // ... similar logic ...
    } catch (\PDOException $e2) {
        echo "Database Error (no prefix): " . $e2->getMessage() . "\n";
    }
}
