<?php
declare (strict_types = 1);

namespace app\controller;

use app\controller\BaseController;

/**
 * 示例控制器 - 演示ResponseFormatter的使用
 */
class ExampleController extends BaseController
{
    /**
     * 基本成功响应示例
     */
    public function basicSuccess()
    {
        $data = [
            'user_id' => 12345,
            'username' => 'xiaomotui_user',
            'level' => 'vip',
            'balance' => 1000.50
        ];

        return $this->success($data, '用户信息获取成功');
    }

    /**
     * 基本错误响应示例
     */
    public function basicError()
    {
        return $this->error('用户不存在', 404, 'user_not_found');
    }

    /**
     * 验证错误响应示例
     */
    public function validationErrorExample()
    {
        $errors = [
            'phone' => ['手机号格式不正确', '手机号已被注册'],
            'email' => ['邮箱不能为空'],
            'password' => ['密码长度至少6位']
        ];

        return $this->validationError($errors, '用户注册数据验证失败');
    }

    /**
     * 分页响应示例
     */
    public function paginationExample()
    {
        // 模拟用户列表数据
        $userList = [];
        for ($i = 1; $i <= 20; $i++) {
            $userList[] = [
                'id' => $i,
                'username' => 'user_' . $i,
                'email' => 'user' . $i . '@xiaomotui.com',
                'created_at' => date('Y-m-d H:i:s', time() - $i * 3600)
            ];
        }

        return $this->paginate($userList, 1000, 1, 20, '用户列表获取成功');
    }

    /**
     * NFC设备状态响应示例
     */
    public function nfcDeviceExample()
    {
        $deviceData = [
            'device_id' => 'nfc_device_001',
            'device_name' => '小磨兔NFC卡片-001',
            'battery_level' => 85,
            'last_sync' => date('Y-m-d H:i:s'),
            'location' => '北京市朝阳区',
            'status_details' => [
                'signal_strength' => 95,
                'temperature' => 25.5,
                'humidity' => 60
            ]
        ];

        // 根据请求参数决定设备状态
        $status = $this->request->param('status', 'online');

        return $this->nfcDeviceStatus($deviceData, $status);
    }

    /**
     * 内容生成状态响应示例
     */
    public function contentGenerationExample()
    {
        $taskData = [
            'task_id' => 'content_task_' . time(),
            'content_type' => 'product_description',
            'progress' => 75,
            'estimated_completion' => date('Y-m-d H:i:s', time() + 300),
            'current_step' => '正在生成产品特色描述',
            'steps_completed' => 3,
            'total_steps' => 4
        ];

        // 根据请求参数决定生成状态
        $status = $this->request->param('status', 'processing');

        return $this->contentGenerationStatus($status, $taskData);
    }

    /**
     * 平台专用错误响应示例
     */
    public function platformErrorExample()
    {
        $errorType = $this->request->param('error_type', 'nfc_not_found');

        $errorData = [
            'request_id' => uniqid(),
            'timestamp' => time(),
            'user_id' => 12345
        ];

        switch ($errorType) {
            case 'nfc_not_found':
                $errorData['device_id'] = 'nfc_device_404';
                return $this->platformError('NFC_DEVICE_NOT_FOUND', $errorData);

            case 'nfc_offline':
                $errorData['device_id'] = 'nfc_device_offline';
                return $this->platformError('NFC_DEVICE_OFFLINE', $errorData);

            case 'content_failed':
                $errorData['task_id'] = 'content_task_failed_001';
                return $this->platformError('CONTENT_GENERATION_FAILED', $errorData);

            case 'merchant_not_verified':
                $errorData['merchant_id'] = 'merchant_123';
                return $this->platformError('MERCHANT_NOT_VERIFIED', $errorData);

            default:
                return $this->error('未知错误类型', 400);
        }
    }

    /**
     * 批量操作响应示例
     */
    public function batchOperationExample()
    {
        // 模拟批量创建用户的结果
        $results = [
            [
                'success' => true,
                'data' => ['id' => 1001, 'username' => 'batch_user_1'],
                'message' => '用户创建成功'
            ],
            [
                'success' => false,
                'data' => null,
                'message' => '邮箱已存在',
                'error' => 'email_exists'
            ],
            [
                'success' => true,
                'data' => ['id' => 1003, 'username' => 'batch_user_3'],
                'message' => '用户创建成功'
            ],
            [
                'success' => false,
                'data' => null,
                'message' => '手机号格式错误',
                'error' => 'invalid_phone'
            ],
            [
                'success' => true,
                'data' => ['id' => 1005, 'username' => 'batch_user_5'],
                'message' => '用户创建成功'
            ]
        ];

        return $this->batchResponse($results, '批量用户创建操作完成');
    }

    /**
     * 缓存响应示例（模拟分析数据）
     */
    public function cachedAnalyticsExample()
    {
        $userId = $this->request->param('user_id', 12345);
        $date = $this->request->param('date', date('Y-m-d'));

        return $this->cachedResponse(
            "user_analytics_{$userId}_{$date}",
            function() use ($userId, $date) {
                // 模拟复杂的数据分析计算
                sleep(2); // 模拟计算时间

                return [
                    'user_id' => $userId,
                    'date' => $date,
                    'metrics' => [
                        'page_views' => rand(100, 1000),
                        'unique_visitors' => rand(50, 500),
                        'bounce_rate' => round(rand(20, 80) / 100, 2),
                        'avg_session_duration' => rand(60, 300),
                        'conversion_rate' => round(rand(1, 10) / 100, 2)
                    ],
                    'trends' => [
                        'daily_growth' => round((rand(-20, 20) / 100), 2),
                        'weekly_growth' => round((rand(-30, 30) / 100), 2),
                        'monthly_growth' => round((rand(-50, 50) / 100), 2)
                    ],
                    'top_pages' => [
                        ['url' => '/product/nfc-card', 'views' => rand(50, 200)],
                        ['url' => '/content/generator', 'views' => rand(30, 150)],
                        ['url' => '/analytics/dashboard', 'views' => rand(20, 100)]
                    ],
                    'generated_at' => date('Y-m-d H:i:s')
                ];
            },
            600, // 缓存10分钟
            '用户分析数据获取成功'
        );
    }

    /**
     * 自定义响应头示例
     */
    public function customHeadersExample()
    {
        $data = [
            'api_version' => '1.0',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => 'Asia/Shanghai'
        ];

        $customHeaders = [
            'X-API-Version' => '1.0',
            'X-Rate-Limit-Remaining' => '999',
            'X-Rate-Limit-Reset' => (string)(time() + 3600),
            'X-Custom-Feature' => 'xiaomotui-nfc-platform'
        ];

        return $this->success($data, '服务器信息获取成功', 200, $customHeaders);
    }

    /**
     * 模拟真实业务场景 - 发布内容到平台
     */
    public function publishContentExample()
    {
        $contentId = $this->request->param('content_id');
        $platform = $this->request->param('platform', 'wechat');

        if (!$contentId) {
            return $this->validationError(
                ['content_id' => ['内容ID不能为空']],
                '发布参数验证失败'
            );
        }

        // 模拟业务逻辑
        $platforms = ['wechat', 'douyin', 'xiaohongshu', 'weibo'];
        if (!in_array($platform, $platforms)) {
            return $this->error('不支持的发布平台', 400, 'unsupported_platform');
        }

        // 模拟发布结果
        $publishResult = [
            'content_id' => $contentId,
            'platform' => $platform,
            'publish_status' => 'success',
            'published_at' => date('Y-m-d H:i:s'),
            'platform_post_id' => 'platform_' . $platform . '_' . time(),
            'estimated_reach' => rand(1000, 10000),
            'publish_url' => "https://{$platform}.example.com/post/" . time()
        ];

        return $this->success($publishResult, '内容发布成功', 201);
    }

    /**
     * 错误处理演示 - 模拟各种异常情况
     */
    public function errorHandlingDemo()
    {
        $errorType = $this->request->param('error_type', 'none');

        switch ($errorType) {
            case 'validation':
                // 验证异常
                throw new \think\exception\ValidateException('手机号格式不正确');

            case 'http_404':
                // HTTP异常
                throw new \think\exception\HttpException(404, '页面不存在');

            case 'db_error':
                // 数据库异常（模拟）
                throw new \think\db\exception\DbException('数据库连接失败');

            case 'jwt_error':
                // JWT异常（模拟）
                throw new \Exception('JWT Token 已过期');

            case 'custom_error':
                // 自定义业务异常
                return $this->platformError('CONTENT_GENERATION_FAILED', [
                    'task_id' => 'failed_task_001',
                    'reason' => '内容长度超过限制'
                ], 422);

            default:
                return $this->success(['message' => '没有错误，一切正常'], '演示完成');
        }
    }
}