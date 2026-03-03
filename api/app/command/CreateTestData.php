<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\User;
use app\model\Merchant;
use app\model\NfcDevice;
use think\facade\Db;

/**
 * 创建测试数据命令
 * 用于创建API测试所需的用户、商家和设备数据
 */
class CreateTestData extends Command
{
    protected function configure()
    {
        $this->setName('create:test-data')
            ->setDescription('创建API测试所需的测试数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('======================================');
        $output->writeln('开始创建测试数据...');
        $output->writeln('======================================');

        Db::startTrans();
        try {
            // 1. 创建测试用户
            $output->writeln("\n1. 创建测试用户...");

            $testUser = User::where('phone', '13800138000')->find();
            if (!$testUser) {
                $testUser = User::create([
                    'phone' => '13800138000',
                    'nickname' => '测试商家用户',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'role' => 'merchant',
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                $output->writeln("✓ 用户创建成功 - ID: {$testUser->id}, 手机号: 13800138000");
            } else {
                $output->writeln("✓ 用户已存在 - ID: {$testUser->id}");
            }

            // 2. 创建测试商家
            $output->writeln("\n2. 创建测试商家...");

            $testMerchant = Merchant::where('user_id', $testUser->id)->find();
            if (!$testMerchant) {
                $testMerchant = Merchant::create([
                    'user_id' => $testUser->id,
                    'merchant_name' => '测试商家',
                    'contact_name' => '测试联系人',
                    'contact_phone' => '13800138000',
                    'business_hours' => '09:00-22:00',
                    'address' => '测试地址',
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                $output->writeln("✓ 商家创建成功 - ID: {$testMerchant->id}, 名称: 测试商家");
            } else {
                $output->writeln("✓ 商家已存在 - ID: {$testMerchant->id}");
            }

            // 3. 创建测试设备
            $output->writeln("\n3. 创建测试设备...");

            $existingDevices = NfcDevice::where('merchant_id', $testMerchant->id)->count();
            $output->writeln("当前已有 {$existingDevices} 个设备");

            $deviceTypes = ['TABLE', 'WALL', 'COUNTER', 'ENTRANCE'];
            $triggerModes = ['VIDEO', 'COUPON', 'WIFI', 'CONTACT', 'MENU', 'GROUP_BUY'];
            $locations = ['一楼A区', '一楼B区', '二楼VIP区', '三楼露台'];

            // 创建5个测试设备
            for ($i = 1; $i <= 5; $i++) {
                $deviceCode = 'TEST' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);

                $device = NfcDevice::where('device_code', $deviceCode)->find();
                if (!$device) {
                    $device = NfcDevice::create([
                        'merchant_id' => $testMerchant->id,
                        'device_code' => $deviceCode,
                        'device_name' => "测试设备{$i}",
                        'type' => $deviceTypes[array_rand($deviceTypes)],
                        'trigger_mode' => $triggerModes[array_rand($triggerModes)],
                        'location' => $locations[array_rand($locations)],
                        'status' => rand(0, 1), // 0=离线, 1=在线
                        'battery_level' => rand(60, 100),
                        'template_id' => rand(1, 5),
                        'redirect_url' => 'https://example.com/device/' . $i,
                    ]);
                    $output->writeln("✓ 设备创建成功 - 编码: {$deviceCode}, ID: {$device->id}");
                } else {
                    $output->writeln("✓ 设备已存在 - 编码: {$deviceCode}");
                }
            }

            Db::commit();

            $output->writeln("\n======================================");
            $output->writeln("测试数据创建完成！");
            $output->writeln("======================================");
            $output->writeln("\n测试账号信息:");
            $output->writeln("手机号: 13800138000");
            $output->writeln("验证码: 123456 (测试码)");
            $output->writeln("用户ID: {$testUser->id}");
            $output->writeln("商家ID: {$testMerchant->id}");
            $output->writeln("\n现在可以使用手机号登录进行API测试");
            $output->writeln("登录接口: POST /api/auth/phone-login");
            $output->writeln("\n注意: 确保短信服务配置正确，或在代码中临时允许测试验证码 123456");

            return 0;

        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln("\n✗ 创建测试数据失败: " . $e->getMessage());
            $output->writeln("错误详情: " . $e->getTraceAsString());
            return 1;
        }
    }
}
