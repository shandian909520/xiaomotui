<?php
declare(strict_types=1);

namespace tests\e2e;

use think\facade\Db;
use think\facade\Log;

/**
 * 测试数据生成器
 * 负责创建和管理测试数据
 */
class TestDataGenerator
{
    private array $config;
    private array $createdData = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 生成所有测试数据
     *
     * @return array
     */
    public function generateAll(): array
    {
        echo "正在生成测试数据...\n";

        try {
            // 生成用户
            $users = $this->generateUsers();
            echo "✓ 生成 " . count($users) . " 个测试用户\n";

            // 生成商家
            $merchants = $this->generateMerchants();
            echo "✓ 生成 " . count($merchants) . " 个测试商家\n";

            // 生成设备
            $devices = $this->generateDevices($merchants);
            echo "✓ 生成 " . count($devices) . " 个测试设备\n";

            // 生成模板
            $templates = $this->generateTemplates($merchants);
            echo "✓ 生成 " . count($templates) . " 个测试模板\n";

            // 生成优惠券
            $coupons = $this->generateCoupons($merchants);
            echo "✓ 生成 " . count($coupons) . " 个测试优惠券\n";

            $this->createdData = [
                'users' => $users,
                'merchants' => $merchants,
                'devices' => $devices,
                'templates' => $templates,
                'coupons' => $coupons,
            ];

            echo "✓ 测试数据生成完成\n\n";

            return $this->createdData;

        } catch (\Exception $e) {
            echo "✗ 测试数据生成失败: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * 生成测试用户
     *
     * @return array
     */
    private function generateUsers(): array
    {
        $users = [];

        foreach ($this->config['test_users'] as $userData) {
            // 检查用户是否已存在
            $existingUser = Db::table('users')
                ->where('openid', $userData['openid'])
                ->find();

            if ($existingUser) {
                $users[] = $existingUser;
                continue;
            }

            // 创建新用户
            $userId = Db::table('users')->insertGetId([
                'openid' => $userData['openid'],
                'unionid' => $userData['unionid'] ?? null,
                'nickname' => $userData['nickname'],
                'avatar' => $userData['avatar'],
                'gender' => $userData['gender'],
                'member_level' => 'BASIC',
                'points' => 0,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $users[] = array_merge(['id' => $userId], $userData);
        }

        return $users;
    }

    /**
     * 生成测试商家
     *
     * @return array
     */
    private function generateMerchants(): array
    {
        $merchants = [];

        foreach ($this->config['test_merchants'] as $merchantData) {
            // 检查商家是否已存在
            $existingMerchant = Db::table('merchants')
                ->where('name', $merchantData['name'])
                ->find();

            if ($existingMerchant) {
                $merchants[] = $existingMerchant;
                continue;
            }

            // 创建新商家
            $merchantId = Db::table('merchants')->insertGetId([
                'name' => $merchantData['name'],
                'contact_name' => $merchantData['contact_name'],
                'contact_phone' => $merchantData['contact_phone'],
                'email' => $merchantData['email'],
                'address' => $merchantData['address'],
                'latitude' => $merchantData['latitude'],
                'longitude' => $merchantData['longitude'],
                'business_hours' => $merchantData['business_hours'],
                'status' => $merchantData['status'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $merchants[] = array_merge(['id' => $merchantId], $merchantData);
        }

        return $merchants;
    }

    /**
     * 生成测试设备
     *
     * @param array $merchants
     * @return array
     */
    private function generateDevices(array $merchants): array
    {
        $devices = [];

        if (empty($merchants)) {
            return $devices;
        }

        $merchant = $merchants[0]; // 使用第一个商家

        foreach ($this->config['test_devices'] as $deviceData) {
            // 检查设备是否已存在
            $existingDevice = Db::table('nfc_devices')
                ->where('device_code', $deviceData['device_code'])
                ->find();

            if ($existingDevice) {
                $devices[] = $existingDevice;
                continue;
            }

            // 创建新设备
            $deviceId = Db::table('nfc_devices')->insertGetId([
                'device_code' => $deviceData['device_code'],
                'device_name' => $deviceData['device_name'],
                'merchant_id' => $merchant['id'],
                'type' => $deviceData['type'],
                'trigger_mode' => $deviceData['trigger_mode'],
                'status' => $deviceData['status'],
                'battery_level' => 100,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $devices[] = array_merge(['id' => $deviceId, 'merchant_id' => $merchant['id']], $deviceData);
        }

        return $devices;
    }

    /**
     * 生成测试模板
     *
     * @param array $merchants
     * @return array
     */
    private function generateTemplates(array $merchants): array
    {
        $templates = [];

        foreach ($this->config['test_templates'] as $templateData) {
            // 检查模板是否已存在
            $existingTemplate = Db::table('content_templates')
                ->where('name', $templateData['name'])
                ->find();

            if ($existingTemplate) {
                $templates[] = $existingTemplate;
                continue;
            }

            // 创建新模板
            $templateId = Db::table('content_templates')->insertGetId([
                'name' => $templateData['name'],
                'type' => $templateData['type'],
                'category' => $templateData['category'],
                'style' => $templateData['style'],
                'content' => $templateData['content'],
                'is_public' => 1,
                'status' => $templateData['status'],
                'usage_count' => 0,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            $templates[] = array_merge(['id' => $templateId], $templateData);
        }

        return $templates;
    }

    /**
     * 生成测试优惠券
     *
     * @param array $merchants
     * @return array
     */
    private function generateCoupons(array $merchants): array
    {
        $coupons = [];

        if (empty($merchants)) {
            return $coupons;
        }

        $merchant = $merchants[0];

        // 生成几个测试优惠券
        $couponData = [
            [
                'title' => 'E2E测试优惠券-满100减20',
                'description' => '测试优惠券',
                'discount_type' => 'AMOUNT',
                'discount_value' => 20.00,
                'min_amount' => 100.00,
                'total_count' => 1000,
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ],
        ];

        foreach ($couponData as $data) {
            // 检查优惠券是否已存在
            $existingCoupon = Db::table('coupons')
                ->where('title', $data['title'])
                ->where('merchant_id', $merchant['id'])
                ->find();

            if ($existingCoupon) {
                $coupons[] = $existingCoupon;
                continue;
            }

            $couponId = Db::table('coupons')->insertGetId(array_merge($data, [
                'merchant_id' => $merchant['id'],
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]));

            $coupons[] = array_merge(['id' => $couponId, 'merchant_id' => $merchant['id']], $data);
        }

        return $coupons;
    }

    /**
     * 清理测试数据
     */
    public function cleanup(): void
    {
        if (!$this->config['cleanup']['enabled']) {
            echo "跳过数据清理（cleanup.enabled = false）\n";
            return;
        }

        echo "\n正在清理测试数据...\n";

        try {
            // 按照依赖顺序清理表
            $tables = $this->config['cleanup']['cleanup_tables'];

            foreach ($tables as $table) {
                $this->cleanupTable($table);
            }

            echo "✓ 测试数据清理完成\n";

        } catch (\Exception $e) {
            echo "✗ 测试数据清理失败: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * 清理指定表的测试数据
     *
     * @param string $table
     */
    private function cleanupTable(string $table): void
    {
        try {
            // 根据表的特征清理测试数据
            switch ($table) {
                case 'users':
                    $deleted = Db::table($table)
                        ->where('openid', 'like', 'test_e2e_%')
                        ->delete();
                    break;

                case 'merchants':
                    $deleted = Db::table($table)
                        ->where('name', 'like', 'E2E测试%')
                        ->delete();
                    break;

                case 'nfc_devices':
                    $deleted = Db::table($table)
                        ->where('device_code', 'like', 'E2E_TEST_%')
                        ->delete();
                    break;

                case 'content_templates':
                    $deleted = Db::table($table)
                        ->where('name', 'like', 'E2E测试%')
                        ->delete();
                    break;

                case 'coupons':
                    $deleted = Db::table($table)
                        ->where('title', 'like', 'E2E测试%')
                        ->delete();
                    break;

                case 'device_triggers':
                case 'content_tasks':
                    // 这些表清理与测试设备相关的记录
                    $deviceIds = Db::table('nfc_devices')
                        ->where('device_code', 'like', 'E2E_TEST_%')
                        ->column('id');

                    if (!empty($deviceIds)) {
                        $deleted = Db::table($table)
                            ->whereIn('device_id', $deviceIds)
                            ->delete();
                    } else {
                        $deleted = 0;
                    }
                    break;

                default:
                    $deleted = 0;
                    break;
            }

            if ($deleted > 0) {
                echo "  - 清理表 {$table}: {$deleted} 条记录\n";
            }

        } catch (\Exception $e) {
            Log::error("清理表 {$table} 失败: " . $e->getMessage());
        }
    }

    /**
     * 获取已创建的数据
     *
     * @return array
     */
    public function getCreatedData(): array
    {
        return $this->createdData;
    }
}
