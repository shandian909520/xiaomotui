<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 设备告警记录模型
 * @property int $id 告警ID
 * @property int $device_id 设备ID
 * @property string $device_code 设备编码
 * @property int $merchant_id 商家ID
 * @property string $alert_type 告警类型
 * @property string $alert_level 告警级别
 * @property string $alert_title 告警标题
 * @property string $alert_message 告警内容
 * @property array $alert_data 告警数据
 * @property string $status 告警状态
 * @property string $trigger_time 触发时间
 * @property string $resolve_time 解决时间
 * @property int $resolve_user_id 解决者ID
 * @property string $resolve_note 解决备注
 * @property int $notification_sent 是否已发送通知
 * @property array $notification_channels 通知渠道
 * @property array $notification_logs 通知日志
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class DeviceAlert extends Model
{
    protected $name = 'device_alerts';

    // 设置字段信息
    protected $schema = [
        'id'                    => 'int',
        'device_id'             => 'int',
        'device_code'           => 'string',
        'merchant_id'           => 'int',
        'alert_type'            => 'string',
        'alert_level'           => 'string',
        'alert_title'           => 'string',
        'alert_message'         => 'text',
        'alert_data'            => 'json',
        'status'                => 'string',
        'trigger_time'          => 'datetime',
        'resolve_time'          => 'datetime',
        'resolve_user_id'       => 'int',
        'resolve_note'          => 'text',
        'notification_sent'     => 'int',
        'notification_channels' => 'json',
        'notification_logs'     => 'json',
        'create_time'           => 'datetime',
        'update_time'           => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'                => 'integer',
        'device_id'         => 'integer',
        'merchant_id'       => 'integer',
        'alert_data'        => 'array',
        'resolve_user_id'   => 'integer',
        'notification_sent' => 'integer',
        'notification_channels' => 'array',
        'notification_logs' => 'array',
        'trigger_time'      => 'timestamp',
        'resolve_time'      => 'timestamp',
        'create_time'       => 'timestamp',
        'update_time'       => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['alert_data', 'notification_channels', 'notification_logs'];

    // 允许批量赋值的字段
    protected $field = [
        'device_id', 'device_code', 'merchant_id', 'alert_type', 'alert_level',
        'alert_title', 'alert_message', 'alert_data', 'status', 'trigger_time',
        'resolve_time', 'resolve_user_id', 'resolve_note', 'notification_sent',
        'notification_channels', 'notification_logs'
    ];

    /**
     * 告警类型常量
     */
    const TYPE_OFFLINE = 'offline';               // 设备离线
    const TYPE_LOW_BATTERY = 'low_battery';       // 电池电量低
    const TYPE_RESPONSE_TIMEOUT = 'response_timeout'; // 响应超时
    const TYPE_DEVICE_ERROR = 'device_error';     // 设备故障
    const TYPE_SIGNAL_WEAK = 'signal_weak';       // 信号弱
    const TYPE_TEMPERATURE = 'temperature';       // 温度异常
    const TYPE_HEARTBEAT = 'heartbeat';           // 心跳异常
    const TYPE_TRIGGER_FAILED = 'trigger_failed'; // 触发失败

    /**
     * 告警级别常量
     */
    const LEVEL_LOW = 'low';        // 低级
    const LEVEL_MEDIUM = 'medium';  // 中级
    const LEVEL_HIGH = 'high';      // 高级
    const LEVEL_CRITICAL = 'critical'; // 严重

    /**
     * 告警状态常量
     */
    const STATUS_PENDING = 'pending';     // 待处理
    const STATUS_ACKNOWLEDGED = 'acknowledged'; // 已确认
    const STATUS_RESOLVED = 'resolved';   // 已解决
    const STATUS_IGNORED = 'ignored';     // 已忽略

    /**
     * 通知渠道常量
     */
    const CHANNEL_WECHAT = 'wechat';      // 微信
    const CHANNEL_SMS = 'sms';            // 短信
    const CHANNEL_EMAIL = 'email';        // 邮件
    const CHANNEL_WEBHOOK = 'webhook';    // Webhook
    const CHANNEL_SYSTEM = 'system';      // 系统内消息

    /**
     * 告警类型获取器
     */
    public function getAlertTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_OFFLINE => '设备离线',
            self::TYPE_LOW_BATTERY => '电池电量低',
            self::TYPE_RESPONSE_TIMEOUT => '响应超时',
            self::TYPE_DEVICE_ERROR => '设备故障',
            self::TYPE_SIGNAL_WEAK => '信号弱',
            self::TYPE_TEMPERATURE => '温度异常',
            self::TYPE_HEARTBEAT => '心跳异常',
            self::TYPE_TRIGGER_FAILED => '触发失败'
        ];
        return $types[$data['alert_type']] ?? '未知';
    }

    /**
     * 告警级别获取器
     */
    public function getAlertLevelTextAttr($value, $data): string
    {
        $levels = [
            self::LEVEL_LOW => '低级',
            self::LEVEL_MEDIUM => '中级',
            self::LEVEL_HIGH => '高级',
            self::LEVEL_CRITICAL => '严重'
        ];
        return $levels[$data['alert_level']] ?? '未知';
    }

    /**
     * 告警状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_PENDING => '待处理',
            self::STATUS_ACKNOWLEDGED => '已确认',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_IGNORED => '已忽略'
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 告警级别颜色获取器
     */
    public function getLevelColorAttr($value, $data): string
    {
        $colors = [
            self::LEVEL_LOW => '#52c41a',       // 绿色
            self::LEVEL_MEDIUM => '#faad14',    // 橙色
            self::LEVEL_HIGH => '#ff4d4f',      // 红色
            self::LEVEL_CRITICAL => '#ff0000'  // 深红色
        ];
        return $colors[$data['alert_level']] ?? '#666666';
    }

    /**
     * 创建设备告警
     *
     * @param int $deviceId 设备ID
     * @param string $deviceCode 设备编码
     * @param int $merchantId 商家ID
     * @param string $alertType 告警类型
     * @param string $alertLevel 告警级别
     * @param string $alertTitle 告警标题
     * @param string $alertMessage 告警消息
     * @param array $alertData 告警数据
     * @param array $notificationChannels 通知渠道
     * @return DeviceAlert
     */
    public static function createAlert(
        int $deviceId,
        string $deviceCode,
        int $merchantId,
        string $alertType,
        string $alertLevel,
        string $alertTitle,
        string $alertMessage,
        array $alertData = [],
        array $notificationChannels = []
    ): self {
        // 检查是否存在相同的未解决告警
        $existingAlert = self::where([
            'device_id' => $deviceId,
            'alert_type' => $alertType,
            'status' => ['in', [self::STATUS_PENDING, self::STATUS_ACKNOWLEDGED]]
        ])->find();

        if ($existingAlert) {
            // 更新已存在的告警
            $existingAlert->alert_message = $alertMessage;
            $existingAlert->alert_data = array_merge($existingAlert->alert_data ?: [], $alertData);
            $existingAlert->trigger_time = date('Y-m-d H:i:s');
            $existingAlert->save();
            return $existingAlert;
        }

        // 创建新告警
        $alert = new self();
        $alert->device_id = $deviceId;
        $alert->device_code = $deviceCode;
        $alert->merchant_id = $merchantId;
        $alert->alert_type = $alertType;
        $alert->alert_level = $alertLevel;
        $alert->alert_title = $alertTitle;
        $alert->alert_message = $alertMessage;
        $alert->alert_data = $alertData;
        $alert->status = self::STATUS_PENDING;
        $alert->trigger_time = date('Y-m-d H:i:s');
        $alert->notification_sent = 0;
        $alert->notification_channels = $notificationChannels;
        $alert->notification_logs = [];
        $alert->save();

        return $alert;
    }

    /**
     * 确认告警
     *
     * @param int $userId 确认用户ID
     * @return bool
     */
    public function acknowledge(int $userId): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->resolve_user_id = $userId;
        return $this->save();
    }

    /**
     * 解决告警
     *
     * @param int $userId 解决用户ID
     * @param string $note 解决备注
     * @return bool
     */
    public function resolve(int $userId, string $note = ''): bool
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_IGNORED])) {
            return false;
        }

        $this->status = self::STATUS_RESOLVED;
        $this->resolve_time = date('Y-m-d H:i:s');
        $this->resolve_user_id = $userId;
        $this->resolve_note = $note;
        return $this->save();
    }

    /**
     * 忽略告警
     *
     * @param int $userId 忽略用户ID
     * @param string $note 忽略备注
     * @return bool
     */
    public function ignore(int $userId, string $note = ''): bool
    {
        if (in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_IGNORED])) {
            return false;
        }

        $this->status = self::STATUS_IGNORED;
        $this->resolve_time = date('Y-m-d H:i:s');
        $this->resolve_user_id = $userId;
        $this->resolve_note = $note;
        return $this->save();
    }

    /**
     * 记录通知发送
     *
     * @param string $channel 通知渠道
     * @param bool $success 是否成功
     * @param string $message 消息
     * @param array $data 附加数据
     * @return bool
     */
    public function recordNotification(string $channel, bool $success, string $message = '', array $data = []): bool
    {
        $logs = $this->notification_logs ?: [];
        $logs[] = [
            'channel' => $channel,
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'send_time' => date('Y-m-d H:i:s')
        ];

        $this->notification_logs = $logs;
        $this->notification_sent = 1;
        return $this->save();
    }

    /**
     * 检查是否需要发送通知
     *
     * @return bool
     */
    public function needsNotification(): bool
    {
        return $this->notification_sent == 0 && !empty($this->notification_channels);
    }

    /**
     * 获取未解决的告警数量
     *
     * @param int $merchantId 商家ID（可选）
     * @param int $deviceId 设备ID（可选）
     * @return int
     */
    public static function getUnresolvedCount(int $merchantId = null, int $deviceId = null): int
    {
        $where = ['status' => ['in', [self::STATUS_PENDING, self::STATUS_ACKNOWLEDGED]]];

        if ($merchantId !== null) {
            $where['merchant_id'] = $merchantId;
        }

        if ($deviceId !== null) {
            $where['device_id'] = $deviceId;
        }

        return self::where($where)->count();
    }

    /**
     * 获取告警统计
     *
     * @param int $merchantId 商家ID
     * @param string $dateStart 开始日期
     * @param string $dateEnd 结束日期
     * @return array
     */
    public static function getAlertStats(int $merchantId, string $dateStart = null, string $dateEnd = null): array
    {
        $where = ['merchant_id' => $merchantId];

        if ($dateStart && $dateEnd) {
            $where['create_time'] = ['between', [$dateStart, $dateEnd]];
        } elseif (!$dateStart && !$dateEnd) {
            // 默认查询最近7天
            $dateStart = date('Y-m-d', strtotime('-7 days'));
            $dateEnd = date('Y-m-d');
            $where['create_time'] = ['between', [$dateStart . ' 00:00:00', $dateEnd . ' 23:59:59']];
        }

        $total = self::where($where)->count();
        $pending = self::where($where)->where('status', self::STATUS_PENDING)->count();
        $resolved = self::where($where)->where('status', self::STATUS_RESOLVED)->count();

        // 按告警类型统计
        $typeStats = self::where($where)
            ->field('alert_type, COUNT(*) as count')
            ->group('alert_type')
            ->select()
            ->toArray();

        // 按告警级别统计
        $levelStats = self::where($where)
            ->field('alert_level, COUNT(*) as count')
            ->group('alert_level')
            ->select()
            ->toArray();

        // 按天统计
        $dailyStats = self::where($where)
            ->field('DATE(create_time) as date, COUNT(*) as count')
            ->group('date')
            ->order('date')
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'pending' => $pending,
            'resolved' => $resolved,
            'ignored' => self::where($where)->where('status', self::STATUS_IGNORED)->count(),
            'resolution_rate' => $total > 0 ? round($resolved / $total * 100, 2) : 0,
            'type_stats' => $typeStats,
            'level_stats' => $levelStats,
            'daily_stats' => $dailyStats
        ];
    }

    /**
     * 获取设备告警历史
     *
     * @param int $deviceId 设备ID
     * @param int $limit 限制数量
     * @return array
     */
    public static function getDeviceHistory(int $deviceId, int $limit = 50): array
    {
        return self::where('device_id', $deviceId)
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联解决用户
     */
    public function resolveUser()
    {
        return $this->belongsTo(User::class, 'resolve_user_id');
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'device_id' => 'require|integer|>:0',
            'device_code' => 'require|max:32',
            'merchant_id' => 'require|integer|>:0',
            'alert_type' => 'require|in:offline,low_battery,response_timeout,device_error,signal_weak,temperature,heartbeat,trigger_failed',
            'alert_level' => 'require|in:low,medium,high,critical',
            'alert_title' => 'require|max:255',
            'alert_message' => 'require',
            'status' => 'in:pending,acknowledged,resolved,ignored',
            'resolve_user_id' => 'integer|>:0',
            'resolve_note' => 'max:1000',
            'notification_sent' => 'in:0,1'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'device_id.require' => '设备ID不能为空',
            'device_id.integer' => '设备ID必须是整数',
            'device_id.>' => '设备ID必须大于0',
            'device_code.require' => '设备编码不能为空',
            'device_code.max' => '设备编码长度不能超过32个字符',
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'alert_type.require' => '告警类型不能为空',
            'alert_type.in' => '告警类型值无效',
            'alert_level.require' => '告警级别不能为空',
            'alert_level.in' => '告警级别值无效',
            'alert_title.require' => '告警标题不能为空',
            'alert_title.max' => '告警标题长度不能超过255个字符',
            'alert_message.require' => '告警内容不能为空',
            'status.in' => '告警状态值无效',
            'resolve_user_id.integer' => '解决用户ID必须是整数',
            'resolve_user_id.>' => '解决用户ID必须大于0',
            'resolve_note.max' => '解决备注长度不能超过1000个字符',
            'notification_sent.in' => '通知发送状态值无效'
        ];
    }
}