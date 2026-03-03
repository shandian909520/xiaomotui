<?php
require __DIR__ . '/vendor/autoload.php';
use think\App;
use think\facade\Env;
use think\facade\Config;

$app = new App();
$app->initialize();

echo "Debug Environment Variables:\n";
echo "AI.BAIDU_WENXIN_PROTOCOL: [" . Env::get('AI.BAIDU_WENXIN_PROTOCOL') . "]\n";
echo "AI.BAIDU_WENXIN_API_KEY: [" . Env::get('AI.BAIDU_WENXIN_API_KEY') . "]\n";
echo "AI.BAIDU_WENXIN_MODEL: [" . Env::get('AI.BAIDU_WENXIN_MODEL') . "]\n";

echo "\nDebug Config:\n";
$config = Config::get('ai.wenxin');
print_r($config);
