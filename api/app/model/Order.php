<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\facade\Db;

/**
 * 订单模型
 * @property int $id
 * @property string $order_no 订单号
 * @property int $user_id 用户ID
 * @property int $merchant_id 商家ID
 * @property float $amount 金额
 * @property string $pay_method 支付方式 wechat/alipay
 * @property string $status 状态 pending/paid/refunded/closed
 * @property string $package_type 套餐类型 basic/standard/chain
 * @property int $duration 时长(月)
 * @property string $transaction_id 第三方交易号
 * @property string $paid_at 支付时间
 * @property string $create_time
 * @property string $update_time
 */
class Order extends Model
{
    protected $table = 'xmt_orders';

    protected $pk = 'id';

    protected $schema = [
        'id'             => 'int',
        'order_no'       => 'string',
        'user_id'        => 'int',
        'merchant_id'    => 'int',
        'amount'         => 'float',
        'pay_method'     => 'string',
        'status'         => 'string',
        'package_type'   => 'string',
        'duration'       => 'int',
        'transaction_id' => 'string',
        'paid_at'        => 'datetime',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'merchant_id'    => 'integer',
        'amount'         => 'float',
        'duration'       => 'integer',
        'paid_at'        => 'datetime',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    // 状态常量
    const STATUS_PENDING  = 'pending';
    const STATUS_PAID     = 'paid';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CLOSED   = 'closed';

    // 支付方式
    const PAY_METHOD_WECHAT = 'wechat';
    const PAY_METHOD_ALIPAY = 'alipay';

    protected static array $statusText = [
        self::STATUS_PENDING  => '待支付',
        self::STATUS_PAID     => '已支付',
        self::STATUS_REFUNDED => '已退款',
        self::STATUS_CLOSED   => '已关闭',
    ];

    public function getStatusTextAttr($value, $data): string
    {
        return self::$statusText[$data['status']] ?? '未知';
    }

    public static function generateOrderNo(): string
    {
        return 'XMT' . date('YmdHis') . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsPaid(string $transactionId): bool
    {
        $this->status = self::STATUS_PAID;
        $this->transaction_id = $transactionId;
        $this->paid_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsClosed(): bool
    {
        $this->status = self::STATUS_CLOSED;
        return $this->save();
    }

    public function markAsRefunded(): bool
    {
        $this->status = self::STATUS_REFUNDED;
        return $this->save();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * 检查商家是否有指定版本的有效付费订单
     */
    public static function hasValidPaidOrder(int $merchantId, string $packageType): bool
    {
        return self::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_PAID)
            ->where('package_type', $packageType)
            ->whereNotNull('paid_at')
            ->count() > 0;
    }

    /**
     * 获取商家最新的有效付费订单
     */
    public static function getLatestPaidOrder(int $merchantId): ?self
    {
        return self::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_PAID)
            ->order('paid_at', 'desc')
            ->find();
    }
}
