<?php
declare(strict_types=1);

namespace tests\factories;

use app\model\ContentTask;
use app\model\ContentTemplate;
use app\model\NfcDevice;
use app\model\Merchant;
use app\model\User;

/**
 * 内容测试数据工厂
 */
class ContentTestFactory
{
    /**
     * 创建测试用户
     */
    public static function createUser(array $data = []): User
    {
        $defaultData = [
            'openid' => 'test_openid_' . uniqid(),
            'unionid' => 'test_unionid_' . uniqid(),
            'nickname' => '测试用户',
            'avatar' => 'https://example.com/avatar.jpg',
            'gender' => 0,
            'member_level' => 'BASIC',
            'points' => 0,
            'status' => 1,
        ];
        return User::create(array_merge($defaultData, $data));
    }

    /**
     * 创建测试商家
     */
    public static function createMerchant(array $data = []): Merchant
    {
        $defaultData = [
            'name' => '测试商家',
            'category' => '餐饮',
            'contact_name' => '张三',
            'contact_phone' => '13900139000',
            'business_license' => 'LICENSE123456',
            'address' => '测试地址',
            'latitude' => 39.9042,
            'longitude' => 116.4074,
            'status' => 1,
        ];
        return Merchant::create(array_merge($defaultData, $data));
    }

    /**
     * 创建测试NFC设备
     */
    public static function createDevice(int $merchantId, array $data = []): NfcDevice
    {
        $defaultData = [
            'merchant_id' => $merchantId,
            'device_code' => 'DEVICE_' . uniqid(),
            'device_name' => '测试设备',
            'device_type' => 'NFC_TAG',
            'status' => 1,
            'activation_status' => 'ACTIVATED',
        ];
        return NfcDevice::create(array_merge($defaultData, $data));
    }

    /**
     * 创建内容模板
     */
    public static function createTemplate(array $data = []): ContentTemplate
    {
        $defaultData = [
            'name' => '测试模板',
            'type' => 'VIDEO',
            'category' => '菜单',
            'style' => '简约',
            'content' => [
                'duration' => 15,
                'resolution' => '1080p',
                'style' => 'food',
            ],
            'preview_url' => 'https://example.com/preview.jpg',
            'usage_count' => 0,
            'is_public' => 1,
            'status' => 1,
        ];
        return ContentTemplate::create(array_merge($defaultData, $data));
    }

    /**
     * 创建内容任务
     */
    public static function createTask(int $userId, int $merchantId, array $data = []): ContentTask
    {
        $defaultData = [
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'type' => 'VIDEO',
            'status' => 'PENDING',
            'input_data' => [
                'requirements' => ['scene' => '餐厅', 'style' => '温馨'],
            ],
        ];
        return ContentTask::create(array_merge($defaultData, $data));
    }

    /**
     * Mock WenxinService响应
     */
    public static function mockWenxinResponse(bool $success = true): array
    {
        if ($success) {
            return [
                'text' => '这是AI生成的营销文案内容。欢迎光临我们的店铺！',
                'tokens' => 50,
                'time' => 2.5,
                'model' => 'ernie-bot',
            ];
        }
        throw new \Exception('AI服务暂时不可用');
    }

    /**
     * Mock JianyingService响应
     */
    public static function mockJianyingResponse(string $status = 'COMPLETED'): array
    {
        $baseResponse = [
            'success' => true,
            'task_id' => 'jy_' . uniqid(),
        ];

        if ($status === 'PENDING') {
            return array_merge($baseResponse, [
                'status' => 'PENDING',
                'estimated_time' => 15,
                'message' => '视频生成任务已创建',
            ]);
        } elseif ($status === 'COMPLETED') {
            return array_merge($baseResponse, [
                'status' => 'COMPLETED',
                'progress' => 100,
                'video_url' => 'https://example.com/videos/test.mp4',
                'cover_url' => 'https://example.com/covers/test.jpg',
                'duration' => 15,
                'file_size' => 5242880,
            ]);
        }

        return [
            'success' => false,
            'status' => 'FAILED',
            'error' => '视频生成失败',
        ];
    }
}
