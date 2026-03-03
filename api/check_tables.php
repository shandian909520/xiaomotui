<?php
require __DIR__ . '/vendor/autoload.php';
$app = new \think\App();
$app->initialize();

$tables = think\facade\Db::query('SHOW TABLES');
echo "=== 数据库表列表 ===\n";
foreach($tables as $t) {
    $arr = array_values($t);
    echo $arr[0] . "\n";
}
echo "共 " . count($tables) . " 张表\n";
