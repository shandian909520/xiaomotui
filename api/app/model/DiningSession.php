<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 用餐会话模型
 * @property int $id 会话ID
 * @property int $merchant_id 所属商家ID
 * @property int $table_id 桌台ID
 * @property int $device_id NFC设备ID
 * @property string $session_code 会话编码
 * @property string $status 会话状态
 * @property int $guest_count 用餐人数
 * @property string $start_time 开始时间
 * @property string $end_time 结束时间
 * @property int $duration 用餐时长(分钟)
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class DiningSession extends Model
{
    protected $table = 'xmt_dining_sessions';

    // 设置字段信息
    protected $schema = [
        'id'           => 'int',
        'merchant_id'  => 'int',
        'table_id'     => 'int',
        'device_id'    => 'int',
        'session_code' => 'string',
        'status'       => 'string',
        'guest_count'  => 'int',
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
        'duration'     => 'int',
        'create_time'  => 'datetime',
        'update_time'  => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'          => 'integer',
        'merchant_id' => 'integer',
        'table_id'    => 'integer',
        'device_id'   => 'integer',
        'guest_count' => 'integer',
        'duration'    => 'integer',
        'start_time'  => 'timestamp',
        'end_time'    => 'timestamp',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 只读字段
    protected $readonly = ['session_code'];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'table_id', 'device_id', 'session_code',
        'status', 'guest_count', 'start_time', 'end_time', 'duration'
    ];

    /**
     * 会话状态常量
     */
    const STATUS_ACTIVE = 'ACTIVE';        // 进行中
    const STATUS_COMPLETED = 'COMPLETED';  // 已完成
    const STATUS_CANCELLED = 'CANCELLED';  // 已取消

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_ACTIVE => '进行中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 检查是否进行中
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
     * 完成会话
     */
    public function complete(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->end_time = date('Y-m-d H:i:s');

        // 计算用餐时长（分钟）
        if ($this->start_time) {
            $startTimestamp = strtotime($this->start_time);
            $endTimestamp = strtotime($this->end_time);
            $this->duration = (int)(($endTimestamp - $startTimestamp) / 60);
        }

        return $this->save();
    }

    /**
     * 取消会话
     */
    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        $this->end_time = date('Y-m-d H:i:s');

        // 计算用餐时长（分钟）
        if ($this->start_time) {
            $startTimestamp = strtotime($this->start_time);
            $endTimestamp = strtotime($this->end_time);
            $this->duration = (int)(($endTimestamp - $startTimestamp) / 60);
        }

        return $this->save();
    }

    /**
     * 更新用餐人数
     */
    public function updateGuestCount(int $count): bool
    {
        if ($count <= 0) {
            return false;
        }

        $this->guest_count = $count;
        return $this->save();
    }

    /**
     * 生成会话编码
     */
    public static function generateSessionCode(): string
    {
        return 'DS' . date('YmdHis') . str_pad((string)mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 根据会话编码查找
     */
    public static function findBySessionCode(string $sessionCode)
    {
        return static::where('session_code', $sessionCode)->find();
    }

    /**
     * 获取桌台的当前会话
     */
    public static function getCurrentSessionByTableId(int $tableId)
    {
        return static::where('table_id', $tableId)
            ->where('status', self::STATUS_ACTIVE)
            ->order('create_time', 'desc')
            ->find();
    }

    /**
     * 获取商家的进行中会话
     */
    public static function getActiveSessions(int $merchantId)
    {
        return static::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_ACTIVE)
            ->order('start_time', 'desc')
            ->select();
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
     * 所属设备关联
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 会话用户关联
     */
    public function sessionUsers()
    {
        return $this->hasMany(SessionUser::class, 'session_id');
    }

    /**
     * 服务呼叫关联
     */
    public function serviceCalls()
    {
        return $this->hasMany(ServiceCall::class, 'session_id');
    }

    /**
     * 获取主用户
     */
    public function getHostUser()
    {
        $sessionUser = SessionUser::where('session_id', $this->id)
            ->where('is_host', 1)
            ->find();

        return $sessionUser ? $sessionUser->user : null;
    }

    /**
     * 获取所有用户
     */
    public function getAllUsers()
    {
        return User::alias('u')
            ->join('session_users su', 'u.id = su.user_id')
            ->where('su.session_id', $this->id)
            ->where('su.leave_time', null)
            ->select();
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'require|integer|>:0',
            'table_id' => 'require|integer|>:0',
            'device_id' => 'integer|>:0',
            'session_code' => 'require|max:32|unique:dining_sessions',
            'status' => 'in:ACTIVE,COMPLETED,CANCELLED',
            'guest_count' => 'integer|>=:1',
            'start_time' => 'require',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'table_id.require' => '桌台ID不能为空',
            'table_id.integer' => '桌台ID必须是整数',
            'table_id.>' => '桌台ID必须大于0',
            'device_id.integer' => '设备ID必须是整数',
            'device_id.>' => '设备ID必须大于0',
            'session_code.require' => '会话编码不能为空',
            'session_code.max' => '会话编码长度不能超过32个字符',
            'session_code.unique' => '会话编码已存在',
            'status.in' => '状态值无效',
            'guest_count.integer' => '用餐人数必须是整数',
            'guest_count.>=' => '用餐人数必须大于等于1',
            'start_time.require' => '开始时间不能为空',
        ];
    }
}