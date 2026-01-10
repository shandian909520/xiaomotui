<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 用餐会话用户关联模型
 * @property int $id 关联ID
 * @property int $session_id 会话ID
 * @property int $user_id 用户ID
 * @property int $is_host 是否为主用户
 * @property string $join_time 加入时间
 * @property string $leave_time 离开时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class SessionUser extends Model
{
    protected $name = 'session_users';

    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'session_id'  => 'int',
        'user_id'     => 'int',
        'is_host'     => 'int',
        'join_time'   => 'datetime',
        'leave_time'  => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'          => 'integer',
        'session_id'  => 'integer',
        'user_id'     => 'integer',
        'is_host'     => 'integer',
        'join_time'   => 'timestamp',
        'leave_time'  => 'timestamp',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'session_id', 'user_id', 'is_host', 'join_time', 'leave_time'
    ];

    /**
     * 检查是否为主用户
     */
    public function isHost(): bool
    {
        return $this->is_host === 1;
    }

    /**
     * 设置为主用户
     */
    public function setAsHost(): bool
    {
        $this->is_host = 1;
        return $this->save();
    }

    /**
     * 记录离开时间
     */
    public function leave(): bool
    {
        $this->leave_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 检查是否还在会话中
     */
    public function isInSession(): bool
    {
        return $this->leave_time === null;
    }

    /**
     * 计算停留时长（分钟）
     */
    public function getStayDuration(): int
    {
        $endTime = $this->leave_time ?: date('Y-m-d H:i:s');
        $startTimestamp = strtotime($this->join_time);
        $endTimestamp = strtotime($endTime);

        return (int)(($endTimestamp - $startTimestamp) / 60);
    }

    /**
     * 查找用户在会话中的记录
     */
    public static function findBySessionAndUser(int $sessionId, int $userId)
    {
        return static::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->find();
    }

    /**
     * 获取会话的所有用户
     */
    public static function getUsersBySessionId(int $sessionId, bool $onlyActive = true)
    {
        $query = static::where('session_id', $sessionId);

        if ($onlyActive) {
            $query = $query->where('leave_time', null);
        }

        return $query->select();
    }

    /**
     * 获取会话的主用户
     */
    public static function getHostBySessionId(int $sessionId)
    {
        return static::where('session_id', $sessionId)
            ->where('is_host', 1)
            ->find();
    }

    /**
     * 获取用户的所有会话
     */
    public static function getSessionsByUserId(int $userId, ?string $status = null)
    {
        $query = static::alias('su')
            ->join('dining_sessions ds', 'su.session_id = ds.id')
            ->where('su.user_id', $userId);

        if ($status !== null) {
            $query = $query->where('ds.status', $status);
        }

        return $query->order('su.join_time', 'desc')->select();
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
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'session_id' => 'require|integer|>:0',
            'user_id' => 'require|integer|>:0',
            'is_host' => 'in:0,1',
            'join_time' => 'require',
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
            'is_host.in' => '是否主用户值无效',
            'join_time.require' => '加入时间不能为空',
        ];
    }
}