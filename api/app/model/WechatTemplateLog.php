<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 微信模板消息发送日志模型
 *
 * @property int $id 日志ID
 * @property int $user_id 用户ID
 * @property string $openid 微信OpenID
 * @property string $platform 平台类型 miniprogram|official
 * @property string $template_type 模板类型
 * @property string $template_id 模板ID
 * @property string $template_data 模板数据JSON
 * @property string $page 跳转页面
 * @property string $related_data 关联数据JSON
 * @property string $status 发送状态 sending|success|failed
 * @property int $retry_count 重试次数
 * @property string $error_code 错误码
 * @property string $error_message 错误信息
 * @property string $response_data 响应数据JSON
 * @property string $send_time 发送时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class WechatTemplateLog extends Model
{
    protected $name = 'wechat_template_logs';

    // 主键
    protected $pk = 'id';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'user_id' => 'int',
        'openid' => 'string',
        'platform' => 'string',
        'template_type' => 'string',
        'template_id' => 'string',
        'template_data' => 'json',
        'page' => 'string',
        'related_data' => 'json',
        'status' => 'string',
        'retry_count' => 'int',
        'error_code' => 'string',
        'error_message' => 'string',
        'response_data' => 'json',
        'send_time' => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'retry_count' => 'integer',
        'template_data' => 'json',
        'related_data' => 'json',
        'response_data' => 'json',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
        'send_time' => 'timestamp',
    ];

    /**
     * 发送状态常量
     */
    const STATUS_SENDING = 'sending';  // 发送中
    const STATUS_SUCCESS = 'success';  // 发送成功
    const STATUS_FAILED = 'failed';    // 发送失败

    /**
     * 平台类型常量
     */
    const PLATFORM_MINIPROGRAM = 'miniprogram';  // 小程序
    const PLATFORM_OFFICIAL = 'official';        // 公众号

    /**
     * 模板类型常量
     */
    const TEMPLATE_CONTENT_GENERATED = 'content_generated';  // 内容生成完成
    const TEMPLATE_DEVICE_ALERT = 'device_alert';            // 设备告警
    const TEMPLATE_COUPON_RECEIVED = 'coupon_received';      // 优惠券领取
    const TEMPLATE_MERCHANT_AUDIT = 'merchant_audit';        // 商家审核
    const TEMPLATE_ORDER_STATUS = 'order_status';            // 订单状态

    /**
     * 状态文本映射
     */
    protected static $statusText = [
        self::STATUS_SENDING => '发送中',
        self::STATUS_SUCCESS => '发送成功',
        self::STATUS_FAILED => '发送失败',
    ];

    /**
     * 模板类型文本映射
     */
    protected static $templateTypeText = [
        self::TEMPLATE_CONTENT_GENERATED => '内容生成完成通知',
        self::TEMPLATE_DEVICE_ALERT => '设备告警通知',
        self::TEMPLATE_COUPON_RECEIVED => '优惠券领取通知',
        self::TEMPLATE_MERCHANT_AUDIT => '商家审核结果通知',
        self::TEMPLATE_ORDER_STATUS => '订单状态变更通知',
    ];

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    /**
     * 模板类型获取器
     */
    public function getTemplateTypeTextAttr($value, $data): string
    {
        return self::$templateTypeText[$data['template_type']] ?? '未知';
    }

    /**
     * 平台类型获取器
     */
    public function getPlatformTextAttr($value, $data): string
    {
        $platforms = [
            self::PLATFORM_MINIPROGRAM => '小程序',
            self::PLATFORM_OFFICIAL => '公众号',
        ];

        return $platforms[$data['platform']] ?? '未知';
    }

    /**
     * 检查是否发送成功
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * 检查是否发送失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查是否可以重试
     */
    public function canRetry(int $maxRetry = 3): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < $maxRetry;
    }

    /**
     * 查询作用域：按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 查询作用域：按状态筛选
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：按模板类型筛选
     */
    public function scopeByTemplateType($query, string $templateType)
    {
        return $query->where('template_type', $templateType);
    }

    /**
     * 查询作用域：按平台筛选
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * 查询作用域：发送失败可重试
     */
    public function scopeCanRetry($query, int $maxRetry = 3)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetry);
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取用户发送统计
     *
     * @param int $userId 用户ID
     * @param int $days 统计天数
     * @return array
     */
    public static function getUserStatistics(int $userId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $stats = self::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->field('
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
            ')
            ->find();

        return [
            'total' => (int)($stats->total ?? 0),
            'success_count' => (int)($stats->success_count ?? 0),
            'failed_count' => (int)($stats->failed_count ?? 0),
            'success_rate' => $stats->total > 0
                ? round(($stats->success_count / $stats->total) * 100, 2)
                : 0,
            'period_days' => $days,
        ];
    }

    /**
     * 获取模板类型使用统计
     *
     * @param int $userId 用户ID
     * @param int $days 统计天数
     * @return array
     */
    public static function getTemplateTypeStatistics(int $userId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $stats = self::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->field('
                template_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count
            ')
            ->group('template_type')
            ->select()
            ->toArray();

        $result = [];
        foreach ($stats as $stat) {
            $result[$stat['template_type']] = [
                'total' => $stat['total'],
                'success_count' => $stat['success_count'],
                'failed_count' => $stat['failed_count'],
                'success_rate' => $stat['total'] > 0
                    ? round(($stat['success_count'] / $stat['total']) * 100, 2)
                    : 0,
            ];
        }

        return $result;
    }
}
