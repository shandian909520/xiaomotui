<?php
namespace app\controller;

use app\model\User;
use app\model\Merchant;
use think\facade\Db;

require __DIR__ . '/public/index.php';

// This script checks if admin user exists and has a merchant record
// It's a standalone script, but we need to bootstrap ThinkPHP
// Actually, it's easier to just use a console command or a temporary controller.
// But let's try to just use the existing models if we can bootstrap.

// Simpler approach: Create a temporary route or just use the existing test/setup scripts.
// But I can't easily run those from here without knowing the exact command.

// Let's create a temporary controller method to check/fix this.
// I'll add a method to 'app/controller/ExampleController.php' or create a new one.
