<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 服务呼叫模型
 * @property int $id 呼叫ID
 * @property int $session_id 会话ID
 * @property int $user_id 用户ID
 * @property int $merchant_id 商家ID
 * @property int $table_id 桌台ID
 * @property string $call_type 呼叫类型
 * @property string $description 描述
 * @property string $priority 优先级
 * @property string $status 呼叫状态
 * @property int $staff_id 处理员工ID
 * @property int $response_time 响应时间(秒)
 * @property string $complete_time 完成时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class ServiceCall extends Model
{
    protected $name = 'service_calls';

    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'session_id'    => 'int',
        'user_id'       => 'int',
        'merchant_id'   => 'int',
        'table_id'      => 'int',
        'call_type'     => 'string',
        'description'   => 'string',
        'priority'      => 'string',
        'status'        => 'string',
        'staff_id'      => 'int',
        'response_time' => 'int',
        'complete_time' => 'datetime',
        'create_time'   => 'datetime',
        'update_time'   => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'            => 'integer',
        'session_id'    => 'integer',
        'user_id'       => 'integer',
        'merchant_id'   => 'integer',
        'table_id'      => 'integer',
        'staff_id'      => 'integer',
        'response_time' => 'integer',
        'complete_time' => 'timestamp',
        'create_time'   => 'timestamp',
        'update_time'   => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'session_id', 'user_id', 'merchant_id', 'table_id', 'call_type',
        'description', 'priority', 'status', 'staff_id', 'response_time', 'complete_time'
    ];

    /**
     * 呼叫类型常量
     */
    const TYPE_ORDER = 'ORDER';  // 点餐
    const TYPE_WATER = 'WATER';  // 加水
    const TYPE_BILL = 'BILL';    // 结账
    const TYPE_OTHER = 'OTHER';  // 其他

    /**
     * 优先级常量
     */
    const PRIORITY_LOW = 'LOW';        // 低
    const PRIORITY_NORMAL = 'NORMAL';  // 普通
    const PRIORITY_HIGH = 'HIGH';      // 高
    const PRIORITY_URGENT = 'URGENT';  // 紧急

    /**
     * 状态常量
     */
    const STATUS_PENDING = 'PENDING';        // 待处理
    const STATUS_PROCESSING = 'PROCESSING';  // 处理中
    const STATUS_COMPLETED = 'COMPLETED';    // 已完成
    const STATUS_CANCELLED = 'CANCELLED';    // 已取消

    /**
     * 呼叫类型获取器
     */
    public function getCallTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_ORDER => '点餐',
            self::TYPE_WATER => '加水',
            self::TYPE_BILL => '结账',
            self::TYPE_OTHER => '其他'
        ];
        return $types[$data['call_type']] ?? '未知';
    }

    /**
     * 优先级获取器
     */
    public function getPriorityTextAttr($value, $data): string
    {
        $priorities = [
            self::PRIORITY_LOW => '低',
            self::PRIORITY_NORMAL => '普通',
            self::PRIORITY_HIGH => '高',
            self::PRIORITY_URGENT => '紧急'
        ];
        return $priorities[$data['priority']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 检查是否待处理
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 检查是否处理中
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * 检查是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * 开始处理
     */
    public function startProcessing(int $staffId): bool
    {
        $this->status = self::STATUS_PROCESSING;
        $this->staff_id = $staffId;

        // 计算响应时间（秒）
        $createTimestamp = strtotime($this->create_time);
        $currentTimestamp = time();
        $this->response_time = $currentTimestamp - $createTimestamp;

        return $this->save();
    }

    /**
     * 完成处理
     */
    public function complete(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->complete_time = date('Y-m-d H:i:s');

        // 如果还没有响应时间，计算它
        if ($this->response_time === null) {
            $createTimestamp = strtotime($this->create_time);
            $currentTimestamp = time();
            $this->response_time = $currentTimestamp - $createTimestamp;
        }

        return $this->save();
    }

    /**
     * 取消呼叫
     */
    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        $this->complete_time = date('Y-m-d H:i:s');

        return $this->save();
    }

    /**
     * 获取处理时长（秒）
     */
    public function getProcessingDuration(): ?int
    {
        if (!$this->complete_time) {
            return null;
        }

        $createTimestamp = strtotime($this->create_time);
        $completeTimestamp = strtotime($this->complete_time);

        return $completeTimestamp - $createTimestamp;
    }

    /**
     * 获取商家的待处理呼叫
     */
    public static function getPendingCalls(int $merchantId, ?string $priority = null)
    {
        $query = static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_PENDING);

        if ($priority !== null) {
            $query = $query->where('priority', $priority);
        }

        return $query->order('priority', 'desc')
            ->order('create_time', 'asc')
            ->select();
    }

    /**
     * 获取商家的处理中呼叫
     */
    public static function getProcessingCalls(int $merchantId, ?int $staffId = null)
    {
        $query = static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_PROCESSING);

        if ($staffId !== null) {
            $query = $query->where('staff_id', $staffId);
        }

        return $query->order('create_time', 'asc')->select();
    }

    /**
     * 获取会话的所有呼叫
     */
    public static function getCallsBySessionId(int $sessionId)
    {
        return static::where('session_id', $sessionId)
            ->order('create_time', 'desc')
            ->select();
    }

    /**
     * 获取桌台的所有呼叫
     */
    public static function getCallsByTableId(int $tableId, ?string $status = null)
    {
        $query = static::where('table_id', $tableId);

        if ($status !== null) {
            $query = $query->where('status', $status);
        }

        return $query->order('create_time', 'desc')->select();
    }

    /**
     * 所属会话关联
     */
    public function session()
    {
        return $this->belongsTo(DiningSession::class, 'session_id');
    }

    /**
     * 所属用户关联
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 所属商家关联
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 所属桌台关联
     */
    public function table()
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'session_id' => 'require|integer|>:0',
            'user_id' => 'require|integer|>:0',
            'merchant_id' => 'require|integer|>:0',
            'table_id' => 'require|integer|>:0',
            'call_type' => 'require|in:ORDER,WATER,BILL,OTHER',
            'description' => 'max:255',
            'priority' => 'in:LOW,NORMAL,HIGH,URGENT',
            'status' => 'in:PENDING,PROCESSING,COMPLETED,CANCELLED',
            'staff_id' => 'integer|>:0',
            'response_time' => 'integer|>=:0',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'session_id.require' => '会话ID不能为空',
            'session_id.integer' => '会话ID必须是整数',
            'session_id.>' => '会话ID必须大于0',
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'table_id.require' => '桌台ID不能为空',
            'table_id.integer' => '桌台ID必须是整数',
            'table_id.>' => '桌台ID必须大于0',
            'call_type.require' => '呼叫类型不能为空',
            'call_type.in' => '呼叫类型值无效',
            'description.max' => '描述长度不能超过255个字符',
            'priority.in' => '优先级值无效',
            'status.in' => '状态值无效',
            'staff_id.integer' => '员工ID必须是整数',
            'staff_id.>' => '员工ID必须大于0',
            'response_time.integer' => '响应时间必须是整数',
            'response_time.>=' => '响应时间不能为负数',
        ];
    }
}