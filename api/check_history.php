<?php
require __DIR__ . '/vendor/autoload.php';

// Redirect output to log file
ob_start();

$app = new \think\App();

echo "Checking ContentTask records...\n";

try {
    use app\model\ContentTask;
    use think\facade\Db;

    $count = ContentTask::count();
    echo "Total records: " . $count . "\n";

    if ($count > 0) {
        $latest = ContentTask::order('create_time', 'desc')->find();
        echo "Latest record:\n";
        print_r($latest->toArray());
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/check_history.log', $output);
echo $output;
