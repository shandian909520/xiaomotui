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
    
    // List tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";

    // Check if merchant for user_id 0 exists
    // Try 'merchants' table first (common pluralization)
    $tableName = 'merchants';
    if (!in_array($tableName, $tables)) {
        $tableName = 'merchant'; // try singular
        if (!in_array($tableName, $tables)) {
             $tableName = 'xmt_merchant'; // try with prefix
             if (!in_array($tableName, $tables)) {
                 $tableName = 'xmt_merchants'; // try plural with prefix
                 if (!in_array($tableName, $tables)) {
                     die("Could not find merchant table in: " . implode(", ", $tables));
                 }
             }
        }
    }
    
    echo "Using table: $tableName\n";
    
    // Show columns
     $stmt = $pdo->query("DESCRIBE $tableName");
     $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
     echo "Columns:\n" . implode("\n", $columns) . "\n";
 
     $stmt = $pdo->prepare("SELECT id FROM $tableName WHERE user_id = 0");
    $stmt->execute();
    $merchant = $stmt->fetch();
    
    if ($merchant) {
        echo "Admin merchant already exists (ID: " . $merchant['id'] . ")\n";
    } else {
        // Construct INSERT based on available columns
        $fields = [];
        $values = [];
        
        if (in_array('user_id', $columns)) { $fields[] = 'user_id'; $values[] = 0; }
        if (in_array('name', $columns)) { $fields[] = 'name'; $values[] = "'系统管理员'"; }
        if (in_array('merchant_name', $columns)) { $fields[] = 'merchant_name'; $values[] = "'系统管理员'"; }
        
        // Add required fields with dummy values
        if (in_array('category', $columns)) { $fields[] = 'category'; $values[] = "'system'"; }
        if (in_array('address', $columns)) { $fields[] = 'address'; $values[] = "'System Address'"; }
        if (in_array('phone', $columns)) { $fields[] = 'phone'; $values[] = "'13800000000'"; }
        if (in_array('longitude', $columns)) { $fields[] = 'longitude'; $values[] = "0"; }
        if (in_array('latitude', $columns)) { $fields[] = 'latitude'; $values[] = "0"; }
        
        // Add other fields if they exist and are likely required (or we just hope for defaults)
        if (in_array('status', $columns)) { $fields[] = 'status'; $values[] = 1; }
        if (in_array('create_time', $columns)) { $fields[] = 'create_time'; $values[] = 'NOW()'; }
        if (in_array('update_time', $columns)) { $fields[] = 'update_time'; $values[] = 'NOW()'; }
        
        $sql = "INSERT INTO $tableName (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
        echo "Executing: $sql\n";
        $pdo->exec($sql);
        $id = $pdo->lastInsertId();
        echo "Created admin merchant (ID: $id)\n";
    }
} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
