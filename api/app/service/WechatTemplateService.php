<?php
declare(strict_types=1);

namespace app\service;

use app\model\WechatTemplateLog;
use app\model\Merchant;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * 微信模板消息服务类
 * 支持小程序订阅消息和公众号模板消息
 */
class WechatTemplateService
{
    /**
     * @var WechatService 微信服务实例
     */
    private WechatService $wechatService;

    /**
     * @var Client HTTP客户端
     */
    private Client $httpClient;

    /**
     * @var string 平台类型 miniprogram|official
     */
    private string $platform;

    /**
     * 模板ID配置
     */
    private const TEMPLATE_IDS = [
        // 小程序订阅消息模板
        'miniprogram' => [
            'content_generated' => 'CONTENT_GENERATED_TEMPLATE', // 内容生成完成通知
            'device_alert' => 'DEVICE_ALERT_TEMPLATE',           // 设备告警通知
            'coupon_received' => 'COUPON_RECEIVED_TEMPLATE',     // 优惠券领取通知
            'merchant_audit' => 'MERCHANT_AUDIT_TEMPLATE',       // 商家审核结果通知
            'order_status' => 'ORDER_STATUS_TEMPLATE',           // 订单状态变更通知
        ],
        // 公众号模板消息模板
        'official' => [
            'content_generated' => 'OFFICIAL_CONTENT_GENERATED_TEMPLATE',
            'device_alert' => 'OFFICIAL_DEVICE_ALERT_TEMPLATE',
            'coupon_received' => 'OFFICIAL_COUPON_RECEIVED_TEMPLATE',
            'merchant_audit' => 'OFFICIAL_MERCHANT_AUDIT_TEMPLATE',
            'order_status' => 'OFFICIAL_ORDER_STATUS_TEMPLATE',
        ]
    ];

    /**
     * 最大重试次数
     */
    private const MAX_RETRY = 3;

    /**
     * 重试延迟（秒）
     */
    private const RETRY_DELAY = 5;

    /**
     * 构造函数
     *
     * @param string $platform 平台类型 miniprogram|official
     */
    public function __construct(string $platform = 'miniprogram')
    {
        $this->platform = in_array($platform, ['miniprogram', 'official'])
            ? $platform
            : 'miniprogram';

        $this->wechatService = new WechatService();
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * 发送内容生成完成通知
     *
     * @param int $userId 用户ID
     * @param string $openid 微信OpenID
     * @param array $data 内容数据
     * @return bool
     */
    public function sendContentGeneratedNotification(int $userId, string $openid, array $data): bool
    {
        $templateData = [
            'thing1' => ['value' => $data['content_name'] ?? '内容生成完成'], // 内容名称
            'thing2' => ['value' => $data['content_type'] ?? '图文内容'],       // 内容类型
            'date3' => ['value' => date('Y-m-d H:i:s')],                       // 生成时间
            'thing4' => ['value' => $data['platform'] ?? '抖音'],               // 发布平台
        ];

        $page = $data['page'] ?? 'pages/content/detail?id=' . ($data['content_id'] ?? '');

        return $this->sendTemplateMessage(
            $userId,
            $openid,
            'content_generated',
            $templateData,
            $page,
            ['content_id' => $data['content_id'] ?? null]
        );
    }

    /**
     * 发送设备告警通知
     *
     * @param int $merchantId 商家ID
     * @param string $openid 微信OpenID
     * @param array $data 告警数据
     * @return bool
     */
    public function sendDeviceAlertNotification(int $merchantId, string $openid, array $data): bool
    {
        $templateData = [
            'thing1' => ['value' => $data['device_name'] ?? '设备告警'],       // 设备名称
            'character_string2' => ['value' => $data['device_code'] ?? ''],    // 设备编号
            'thing3' => ['value' => $data['alert_type'] ?? '离线告警'],        // 告警类型
            'time4' => ['value' => date('Y-m-d H:i:s')],                       // 告警时间
        ];

        $page = $data['page'] ?? 'pages/device/detail?id=' . ($data['device_id'] ?? '');

        return $this->sendTemplateMessage(
            $merchantId,
            $openid,
            'device_alert',
            $templateData,
            $page,
            [
                'device_id' => $data['device_id'] ?? null,
                'alert_type' => $data['alert_type'] ?? 'offline',
            ]
        );
    }

    /**
     * 发送优惠券领取通知
     *
     * @param int $userId 用户ID
     * @param string $openid 微信OpenID
     * @param array $data 优惠券数据
     * @return bool
     */
    public function sendCouponReceivedNotification(int $userId, string $openid, array $data): bool
    {
        $templateData = [
            'thing1' => ['value' => $data['coupon_name'] ?? '优惠券'],          // 优惠券名称
            'amount2' => ['value' => $data['amount'] ?? '10'],                   // 优惠金额
            'date3' => ['value' => $data['expire_date'] ?? ''],                 // 有效期至
            'thing4' => ['value' => $data['merchant_name'] ?? '商家'],           // 商家名称
        ];

        $page = $data['page'] ?? 'pages/coupon/detail?id=' . ($data['coupon_id'] ?? '');

        return $this->sendTemplateMessage(
            $userId,
            $openid,
            'coupon_received',
            $templateData,
            $page,
            ['coupon_id' => $data['coupon_id'] ?? null]
        );
    }

    /**
     * 发送商家审核结果通知
     *
     * @param int $merchantId 商家ID
     * @param string $openid 微信OpenID
     * @param array $data 审核数据
     * @return bool
     */
    public function sendMerchantAuditNotification(int $merchantId, string $openid, array $data): bool
    {
        $resultText = $data['approved'] ? '审核通过' : '审核驳回';
        $resultText2 = $data['approved'] ? '您的申请已通过审核' : $data['reason'] ?? '未通过审核';

        $templateData = [
            'thing1' => ['value' => $data['merchant_name'] ?? '商家审核'],      // 商家名称
            'phrase2' => ['value' => $resultText],                             // 审核结果
            'thing3' => ['value' => $resultText2],                            // 审核说明
            'date4' => ['value' => date('Y-m-d H:i:s')],                      // 审核时间
        ];

        $page = $data['page'] ?? 'pages/merchant/result';

        return $this->sendTemplateMessage(
            $merchantId,
            $openid,
            'merchant_audit',
            $templateData,
            $page,
            [
                'merchant_id' => $merchantId,
                'approved' => $data['approved'] ?? false,
            ]
        );
    }

    /**
     * 发送订单状态变更通知
     *
     * @param int $userId 用户ID
     * @param string $openid 微信OpenID
     * @param array $data 订单数据
     * @return bool
     */
    public function sendOrderStatusNotification(int $userId, string $openid, array $data): bool
    {
        $templateData = [
            'character_string1' => ['value' => $data['order_no'] ?? ''],        // 订单编号
            'thing2' => ['value' => $data['product_name'] ?? '商品'],            // 商品名称
            'thing3' => ['value' => $data['status_text'] ?? '待支付'],          // 订单状态
            'amount4' => ['value' => $data['amount'] ?? '0.00'],                 // 订单金额
        ];

        $page = $data['page'] ?? 'pages/order/detail?id=' . ($data['order_id'] ?? '');

        return $this->sendTemplateMessage(
            $userId,
            $openid,
            'order_status',
            $templateData,
            $page,
            ['order_id' => $data['order_id'] ?? null]
        );
    }

    /**
     * 发送模板消息（核心方法）
     *
     * @param int $userId 用户ID
     * @param string $openid 微信OpenID
     * @param string $templateType 模板类型
     * @param array $templateData 模板数据
     * @param string $page 跳转页面
     * @param array $relatedData 关联数据
     * @return bool
     */
    private function sendTemplateMessage(
        int $userId,
        string $openid,
        string $templateType,
        array $templateData,
        string $page = '',
        array $relatedData = []
    ): bool {
        try {
            // 获取模板ID
            $templateId = $this->getTemplateId($templateType);
            if (empty($templateId)) {
                Log::error('微信模板ID未配置', [
                    'template_type' => $templateType,
                    'platform' => $this->platform
                ]);
                return false;
            }

            // 记录发送日志
            $logId = $this->createLog(
                $userId,
                $openid,
                $templateType,
                $templateId,
                $templateData,
                $page,
                $relatedData
            );

            // 尝试发送，失败重试
            $result = $this->sendWithRetry($openid, $templateId, $templateData, $page);

            // 更新发送结果
            $this->updateLogResult($logId, $result);

            return $result['success'];
        } catch (Exception $e) {
            Log::error('发送微信模板消息异常', [
                'user_id' => $userId,
                'openid' => $openid,
                'template_type' => $templateType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 获取模板ID
     *
     * @param string $templateType 模板类型
     * @return string
     */
    private function getTemplateId(string $templateType): string
    {
        $templates = self::TEMPLATE_IDS[$this->platform] ?? [];

        // 优先从配置文件读取
        $configTemplateId = config('wechat.template.' . $this->platform . '.' . $templateType, '');
        if (!empty($configTemplateId)) {
            return $configTemplateId;
        }

        // 从数据库读取
        $template = Db::name('wechat_templates')
            ->where('platform', $this->platform)
            ->where('template_key', $templateType)
            ->where('status', 1)
            ->value('template_id');

        return $template ?: ($templates[$templateType] ?? '');
    }

    /**
     * 带重试的发送
     *
     * @param string $openid 微信OpenID
     * @param string $templateId 模板ID
     * @param array $data 模板数据
     * @param string $page 跳转页面
     * @return array
     */
    private function sendWithRetry(string $openid, string $templateId, array $data, string $page): array
    {
        $lastError = '';

        for ($i = 1; $i <= self::MAX_RETRY; $i++) {
            try {
                if ($this->platform === 'miniprogram') {
                    $result = $this->sendMiniProgramMessage($openid, $templateId, $data, $page);
                } else {
                    $result = $this->sendOfficialMessage($openid, $templateId, $data, $page);
                }

                if ($result['success']) {
                    return $result;
                }

                $lastError = $result['message'] ?? '发送失败';

                // 如果是用户拒绝订阅等不需要重试的错误，直接返回
                if ($this->shouldNotRetry($result)) {
                    return $result;
                }

                // 延迟重试
                if ($i < self::MAX_RETRY) {
                    sleep(self::RETRY_DELAY);
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();

                Log::warning('微信模板消息发送失败，准备重试', [
                    'attempt' => $i,
                    'max_retry' => self::MAX_RETRY,
                    'error' => $lastError
                ]);

                if ($i < self::MAX_RETRY) {
                    sleep(self::RETRY_DELAY);
                }
            }
        }

        return [
            'success' => false,
            'message' => "重试{$i}次后仍失败: {$lastError}",
            'error_code' => 'MAX_RETRY_EXCEEDED'
        ];
    }

    /**
     * 发送小程序订阅消息
     *
     * @param string $openid 微信OpenID
     * @param string $templateId 模板ID
     * @param array $data 模板数据
     * @param string $page 跳转页面
     * @return array
     */
    private function sendMiniProgramMessage(string $openid, string $templateId, array $data, string $page): array
    {
        try {
            $result = $this->wechatService->sendTemplateMessage($openid, $templateId, $data, $page);

            Log::info('小程序订阅消息发送成功', [
                'openid' => $openid,
                'template_id' => $templateId,
            ]);

            return [
                'success' => true,
                'message' => '发送成功',
                'data' => $result
            ];
        } catch (Exception $e) {
            $errorCode = $this->parseErrorCode($e->getMessage());

            Log::error('小程序订阅消息发送失败', [
                'openid' => $openid,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'error_code' => $errorCode
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $errorCode
            ];
        }
    }

    /**
     * 发送公众号模板消息
     *
     * @param string $openid 微信OpenID
     * @param string $templateId 模板ID
     * @param array $data 模板数据
     * @param string $url 跳转URL
     * @return array
     */
    private function sendOfficialMessage(string $openid, string $templateId, array $data, string $url = ''): array
    {
        try {
            $accessToken = $this->wechatService->getAccessToken();
            $apiUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$accessToken}";

            $payload = [
                'touser' => $openid,
                'template_id' => $templateId,
                'data' => $data,
            ];

            if (!empty($url)) {
                $payload['url'] = $url;
            }

            $response = $this->httpClient->post($apiUrl, [
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                throw new Exception($result['errmsg'] ?? '发送失败', $result['errcode']);
            }

            Log::info('公众号模板消息发送成功', [
                'openid' => $openid,
                'template_id' => $templateId,
            ]);

            return [
                'success' => true,
                'message' => '发送成功',
                'data' => $result
            ];
        } catch (Exception $e) {
            $errorCode = $this->parseErrorCode($e->getMessage());

            Log::error('公众号模板消息发送失败', [
                'openid' => $openid,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'error_code' => $errorCode
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $errorCode
            ];
        }
    }

    /**
     * 判断是否不需要重试
     *
     * @param array $result 发送结果
     * @return bool
     */
    private function shouldNotRetry(array $result): bool
    {
        $errorCode = $result['error_code'] ?? '';

        // 用户拒绝订阅、模板ID不存在等不需要重试
        $noRetryCodes = [
            '43101', // 用户拒绝接受消息
            '40037', // template_id不正确
            '41030', // page路径不正确
            '43104', // 用户未订阅该模板
        ];

        return in_array($errorCode, $noRetryCodes);
    }

    /**
     * 解析错误码
     *
     * @param string $errorMessage 错误信息
     * @return string
     */
    private function parseErrorCode(string $errorMessage): string
    {
        if (preg_match('/errcode[:\s]+(\d+)/', $errorMessage, $matches)) {
            return $matches[1];
        }

        if (preg_match('/code[:\s]+["\']?(\d+)/', $errorMessage, $matches)) {
            return $matches[1];
        }

        return 'UNKNOWN';
    }

    /**
     * 创建发送日志
     *
     * @param int $userId 用户ID
     * @param string $openid 微信OpenID
     * @param string $templateType 模板类型
     * @param string $templateId 模板ID
     * @param array $templateData 模板数据
     * @param string $page 跳转页面
     * @param array $relatedData 关联数据
     * @return int 日志ID
     */
    private function createLog(
        int $userId,
        string $openid,
        string $templateType,
        string $templateId,
        array $templateData,
        string $page,
        array $relatedData
    ): int {
        $log = WechatTemplateLog::create([
            'user_id' => $userId,
            'openid' => $openid,
            'platform' => $this->platform,
            'template_type' => $templateType,
            'template_id' => $templateId,
            'template_data' => json_encode($templateData, JSON_UNESCAPED_UNICODE),
            'page' => $page,
            'related_data' => !empty($relatedData) ? json_encode($relatedData, JSON_UNESCAPED_UNICODE) : null,
            'status' => 'sending',
            'retry_count' => 0,
            'create_time' => date('Y-m-d H:i:s'),
        ]);

        return $log->id;
    }

    /**
     * 更新日志结果
     *
     * @param int $logId 日志ID
     * @param array $result 发送结果
     * @return void
     */
    private function updateLogResult(int $logId, array $result): void
    {
        $log = WechatTemplateLog::find($logId);
        if (!$log) {
            return;
        }

        $status = $result['success'] ? 'success' : 'failed';

        $log->save([
            'status' => $status,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['message'] ?? null,
            'response_data' => isset($result['data']) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE) : null,
            'send_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 批量发送消息（用于群发）
     *
     * @param array $receivers 接收者列表 [['user_id' => 1, 'openid' => 'xxx'], ...]
     * @param string $templateType 模板类型
     * @param array $templateData 模板数据
     * @param string $page 跳转页面
     * @return array
     */
    public function batchSend(array $receivers, string $templateType, array $templateData, string $page = ''): array
    {
        $results = [
            'total' => count($receivers),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($receivers as $receiver) {
            $userId = $receiver['user_id'] ?? 0;
            $openid = $receiver['openid'] ?? '';

            if (empty($openid)) {
                $results['failed']++;
                $results['details'][] = [
                    'user_id' => $userId,
                    'success' => false,
                    'message' => 'OpenID为空'
                ];
                continue;
            }

            $success = $this->sendTemplateMessage(
                $userId,
                $openid,
                $templateType,
                $templateData,
                $page
            );

            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'user_id' => $userId,
                'success' => $success
            ];

            // 避免触发微信API频率限制
            usleep(100000); // 0.1秒
        }

        return $results;
    }

    /**
     * 获取发送统计
     *
     * @param int $userId 用户ID
     * @param int $days 统计天数
     * @return array
     */
    public function getSendStatistics(int $userId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $logs = WechatTemplateLog::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->field('status, template_type, COUNT(*) as count')
            ->group('status,template_type')
            ->select()
            ->toArray();

        $stats = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'sending' => 0,
            'by_type' => []
        ];

        foreach ($logs as $log) {
            $stats['total'] += $log['count'];
            $stats[$log['status']] = ($stats[$log['status']] ?? 0) + $log['count'];

            if (!isset($stats['by_type'][$log['template_type']])) {
                $stats['by_type'][$log['template_type']] = [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0
                ];
            }

            $stats['by_type'][$log['template_type']]['total'] += $log['count'];
            $stats['by_type'][$log['template_type']][$log['status']] =
                ($stats['by_type'][$log['template_type']][$log['status']] ?? 0) + $log['count'];
        }

        return $stats;
    }

    /**
     * 获取发送历史
     *
     * @param int $userId 用户ID
     * @param array $params 查询参数
     * @return array
     */
    public function getSendHistory(int $userId, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = WechatTemplateLog::where('user_id', $userId);

        // 模板类型筛选
        if (!empty($params['template_type'])) {
            $query->where('template_type', $params['template_type']);
        }

        // 状态筛选
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 平台筛选
        if (!empty($params['platform'])) {
            $query->where('platform', $params['platform']);
        }

        $query->order('create_time', 'desc');

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 重新发送失败的消息
     *
     * @param int $logId 日志ID
     * @return bool
     */
    public function resend(int $logId): bool
    {
        $log = WechatTemplateLog::find($logId);
        if (!$log) {
            return false;
        }

        // 检查重试次数
        if ($log->retry_count >= self::MAX_RETRY) {
            Log::warning('消息重试次数已达上限', ['log_id' => $logId]);
            return false;
        }

        try {
            $templateData = json_decode($log->template_data, true) ?: [];
            $result = $this->sendWithRetry($log->openid, $log->template_id, $templateData, $log->page);

            // 更新日志
            $log->save([
                'status' => $result['success'] ? 'success' : 'failed',
                'retry_count' => $log->retry_count + 1,
                'error_code' => $result['error_code'] ?? null,
                'error_message' => $result['message'] ?? null,
                'response_data' => isset($result['data']) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE) : null,
                'send_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);

            return $result['success'];
        } catch (Exception $e) {
            Log::error('重新发送消息失败', [
                'log_id' => $logId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清理过期日志
     *
     * @param int $days 保留天数
     * @return int 删除数量
     */
    public function cleanExpiredLogs(int $days = 30): int
    {
        $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return WechatTemplateLog::where('create_time', '<', $expireDate)
            ->where('status', 'in', ['success', 'failed'])
            ->delete();
    }
}
