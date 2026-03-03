<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 设备触发记录模型
 * @property int $id 记录ID
 * @property int $device_id 设备ID
 * @property string $device_code 设备编码
 * @property int $user_id 用户ID
 * @property string $user_openid 用户OpenID
 * @property string $trigger_mode 触发模式
 * @property string $response_type 响应类型
 * @property array $response_data 响应数据
 * @property int $response_time 响应时间(毫秒)
 * @property string $client_ip 客户端IP
 * @property string $user_agent 用户代理
 * @property int $success 是否成功 1成功 0失败
 * @property string $error_message 错误信息
 * @property string $create_time 创建时间
 */
class DeviceTrigger extends Model
{
    protected $table = 'xmt_device_triggers';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'device_id'      => 'int',
        'device_code'    => 'string',
        'merchant_id'    => 'int',
        'user_id'        => 'int',
        'user_openid'    => 'string',
        'trigger_mode'   => 'string',
        'response_type'  => 'string',
        'response_data'  => 'json',
        'response_time'  => 'int',
        'client_ip'      => 'string',
        'user_agent'     => 'string',
        'success'        => 'int',
        'error_message'  => 'string',
        'trigger_time'   => 'datetime',
        'create_time'    => 'datetime',
    ];

    // 自动时间戳 - 只记录创建时间
    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;

    // 字段类型转换
    protected $type = [
        'id'           => 'integer',
        'device_id'    => 'integer',
        'merchant_id'  => 'integer',
        'user_id'      => 'integer',
        'response_data'=> 'array',
        'response_time'=> 'integer',
        'success'      => 'integer',
    ];

    // JSON 字段
    protected $json = ['response_data'];

    // 允许批量赋值的字段
    protected $field = [
        'device_id', 'device_code', 'merchant_id', 'user_id', 'user_openid', 'trigger_mode',
        'response_type', 'response_data', 'response_time', 'client_ip',
        'user_agent', 'success', 'error_message', 'trigger_time'
    ];

    /**
     * 触发模式常量
     */
    const TRIGGER_VIDEO = 'VIDEO';     // 视频展示
    const TRIGGER_COUPON = 'COUPON';   // 优惠券
    const TRIGGER_WIFI = 'WIFI';       // WiFi连接
    const TRIGGER_CONTACT = 'CONTACT'; // 联系方式
    const TRIGGER_MENU = 'MENU';       // 菜单展示

    /**
     * 响应类型常量
     */
    const RESPONSE_VIDEO = 'video';
    const RESPONSE_COUPON = 'coupon';
    const RESPONSE_WIFI = 'wifi';
    const RESPONSE_CONTACT = 'contact';
    const RESPONSE_MENU = 'menu';
    const RESPONSE_ERROR = 'error';

    /**
     * 触发模式获取器
     */
    public function getTriggerModeTextAttr($value, $data): string
    {
        $modes = [
            self::TRIGGER_VIDEO => '视频展示',
            self::TRIGGER_COUPON => '优惠券',
            self::TRIGGER_WIFI => 'WiFi连接',
            self::TRIGGER_CONTACT => '联系方式',
            self::TRIGGER_MENU => '菜单展示'
        ];
        return $modes[$data['trigger_mode']] ?? '未知';
    }

    /**
     * 成功状态获取器
     */
    public function getSuccessTextAttr($value, $data): string
    {
        return $data['success'] ? '成功' : '失败';
    }

    /**
     * 响应时间获取器（格式化显示）
     */
    public function getResponseTimeTextAttr($value, $data): string
    {
        $time = $data['response_time'] ?? 0;
        if ($time < 1000) {
            return $time . 'ms';
        } else {
            return round($time / 1000, 2) . 's';
        }
    }

    /**
     * 记录设备触发
     * @param array $data 触发数据
     * @return DeviceTrigger
     */
    public static function recordTrigger(array $data): self
    {
        $trigger = new self();
        $trigger->device_id = $data['device_id'];
        $trigger->device_code = $data['device_code'];
        $trigger->merchant_id = $data['merchant_id'] ?? 0;
        $trigger->user_id = $data['user_id'] ?? null;
        $trigger->user_openid = $data['user_openid'];
        $trigger->trigger_mode = $data['trigger_mode'];
        $trigger->response_type = $data['response_type'];
        $trigger->response_data = $data['response_data'] ?? [];
        $trigger->response_time = $data['response_time'] ?? 0;
        $trigger->client_ip = $data['client_ip'] ?? '';
        $trigger->user_agent = $data['user_agent'] ?? '';
        $trigger->success = $data['success'] ? 1 : 0;
        $trigger->error_message = $data['error_message'] ?? '';
        $trigger->trigger_time = date('Y-m-d H:i:s');
        $trigger->save();

        return $trigger;
    }

    /**
     * 记录触发成功
     * @param int $deviceId
     * @param string $deviceCode
     * @param int|null $userId
     * @param string $userOpenid
     * @param string $triggerMode
     * @param string $responseType
     * @param array $responseData
     * @param int $responseTime
     * @param string $clientIp
     * @param string $userAgent
     * @return DeviceTrigger
     */
    public static function recordSuccess(
        int $deviceId,
        string $deviceCode,
        int $merchantId,
        ?int $userId,
        string $userOpenid,
        string $triggerMode,
        string $responseType,
        array $responseData,
        int $responseTime,
        string $clientIp = '',
        string $userAgent = ''
    ): self {
        return self::recordTrigger([
            'device_id' => $deviceId,
            'device_code' => $deviceCode,
            'merchant_id' => $merchantId,
            'user_id' => $userId,
            'user_openid' => $userOpenid,
            'trigger_mode' => $triggerMode,
            'response_type' => $responseType,
            'response_data' => $responseData,
            'response_time' => $responseTime,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'success' => true
        ]);
    }

    /**
     * 记录触发失败
     * @param int|null $deviceId
     * @param string $deviceCode
     * @param int|null $userId
     * @param string $userOpenid
     * @param string $triggerMode
     * @param string $errorMessage
     * @param int $responseTime
     * @param string $clientIp
     * @param string $userAgent
     * @return DeviceTrigger
     */
    public static function recordError(
        ?int $deviceId,
        string $deviceCode,
        int $merchantId,
        ?int $userId,
        string $userOpenid,
        string $triggerMode,
        string $errorMessage,
        int $responseTime,
        string $clientIp = '',
        string $userAgent = ''
    ): self {
        return self::recordTrigger([
            'device_id' => $deviceId,
            'device_code' => $deviceCode,
            'merchant_id' => $merchantId,
            'user_id' => $userId,
            'user_openid' => $userOpenid,
            'trigger_mode' => $triggerMode,
            'response_type' => self::RESPONSE_ERROR,
            'response_data' => [],
            'response_time' => $responseTime,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'success' => false,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * 获取设备触发统计
     * @param int $deviceId
     * @param string $date 日期格式 Y-m-d
     * @return array
     */
    public static function getDeviceStats(int $deviceId, string $date = null): array
    {
        $where = ['device_id' => $deviceId];

        if ($date) {
            $where['create_time'] = ['between', [$date . ' 00:00:00', $date . ' 23:59:59']];
        } else {
            // 默认查询今天的数据
            $today = date('Y-m-d');
            $where['create_time'] = ['between', [$today . ' 00:00:00', $today . ' 23:59:59']];
        }

        $total = self::where($where)->count();
        $success = self::where($where)->where('success', 1)->count();
        $failed = $total - $success;

        // 按触发模式统计
        $triggerStats = self::where($where)
            ->field('trigger_mode, COUNT(*) as count')
            ->group('trigger_mode')
            ->select()
            ->toArray();

        // 按小时统计
        $hourlyStats = self::where($where)
            ->field('HOUR(create_time) as hour, COUNT(*) as count')
            ->group('hour')
            ->order('hour')
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'trigger_stats' => $triggerStats,
            'hourly_stats' => $hourlyStats
        ];
    }

    /**
     * 获取用户触发历史
     * @param string $userOpenid
     * @param int $limit
     * @return array
     */
    public static function getUserHistory(string $userOpenid, int $limit = 20): array
    {
        return self::where('user_openid', $userOpenid)
            ->where('success', 1)
            ->order('create_time', 'desc')
            ->limit($limit)
            ->with(['device'])
            ->select()
            ->toArray();
    }

    /**
     * 获取响应时间统计
     * @param int $deviceId
     * @param string $date
     * @return array
     */
    public static function getResponseTimeStats(int $deviceId, string $date = null): array
    {
        $where = ['device_id' => $deviceId, 'success' => 1];

        if ($date) {
            $where['create_time'] = ['between', [$date . ' 00:00:00', $date . ' 23:59:59']];
        } else {
            $today = date('Y-m-d');
            $where['create_time'] = ['between', [$today . ' 00:00:00', $today . ' 23:59:59']];
        }

        $stats = self::where($where)
            ->field('AVG(response_time) as avg_time, MIN(response_time) as min_time, MAX(response_time) as max_time')
            ->find();

        $slowRequests = self::where($where)
            ->where('response_time', '>', 1000) // 大于1秒的请求
            ->count();

        return [
            'avg_time' => round($stats['avg_time'] ?? 0, 2),
            'min_time' => $stats['min_time'] ?? 0,
            'max_time' => $stats['max_time'] ?? 0,
            'slow_requests' => $slowRequests
        ];
    }

    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'device_id' => 'require|integer|>:0',
            'device_code' => 'require|max:32',
            'user_openid' => 'require|max:64',
            'trigger_mode' => 'require|in:VIDEO,COUPON,WIFI,CONTACT,MENU',
            'response_type' => 'require|max:20',
            'response_time' => 'integer|>=:0',
            'client_ip' => 'max:45',
            'user_agent' => 'max:255',
            'success' => 'in:0,1',
            'error_message' => 'max:500'
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
            'user_openid.require' => '用户OpenID不能为空',
            'user_openid.max' => '用户OpenID长度不能超过64个字符',
            'trigger_mode.require' => '触发模式不能为空',
            'trigger_mode.in' => '触发模式值无效',
            'response_type.require' => '响应类型不能为空',
            'response_type.max' => '响应类型长度不能超过20个字符',
            'response_time.integer' => '响应时间必须是整数',
            'response_time.>=' => '响应时间不能为负数',
            'client_ip.max' => '客户端IP长度不能超过45个字符',
            'user_agent.max' => '用户代理长度不能超过255个字符',
            'success.in' => '成功状态值无效',
            'error_message.max' => '错误信息长度不能超过500个字符'
        ];
    }
}