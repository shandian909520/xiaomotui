<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 内容任务模型
 * @property int $id 任务ID
 * @property int $device_id 设备ID
 * @property int $merchant_id 商家ID
 * @property int $user_id 创建用户ID
 * @property string $type 内容类型 video/menu/image
 * @property string $title 标题
 * @property string $description 描述
 * @property string $content_url 内容链接
 * @property string $status 状态 pending/processing/completed/failed
 * @property string $priority 优先级 low/normal/high/urgent
 * @property array $extra_data 扩展数据
 * @property string $error_message 错误信息
 * @property string $started_at 开始处理时间
 * @property string $completed_at 完成时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class ContentTask extends Model
{
    protected $table = 'xmt_content_tasks';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'device_id'       => 'int',
        'merchant_id'     => 'int',
        'user_id'         => 'int',
        'template_id'     => 'int',
        'type'            => 'string',
        'status'          => 'string',
        'input_data'      => 'json',
        'output_data'     => 'json',
        'ai_provider'     => 'string',
        'generation_time' => 'int',
        'error_message'   => 'string',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
        'complete_time'   => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'device_id'       => 'integer',
        'merchant_id'     => 'integer',
        'user_id'         => 'integer',
        'template_id'     => 'integer',
        'input_data'      => 'array',
        'output_data'     => 'array',
        'generation_time' => 'integer',
        'create_time'     => 'timestamp',
        'update_time'     => 'timestamp',
        'complete_time'   => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['input_data', 'output_data'];

    // 允许批量赋值的字段
    protected $field = [
        'device_id', 'merchant_id', 'user_id', 'template_id', 'type', 'status',
        'input_data', 'output_data', 'ai_provider', 'generation_time', 'error_message'
    ];

    /**
     * 内容类型常量
     */
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_TEXT = 'TEXT';
    const TYPE_IMAGE = 'IMAGE';

    /**
     * 状态常量
     */
    const STATUS_PENDING = 'PENDING';       // 待处理
    const STATUS_PROCESSING = 'PROCESSING'; // 处理中
    const STATUS_COMPLETED = 'COMPLETED';   // 已完成
    const STATUS_FAILED = 'FAILED';         // 失败

    /**
     * 内容类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_VIDEO => '视频内容',
            self::TYPE_TEXT => '文本内容',
            self::TYPE_IMAGE => '图片内容'
        ];
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败'
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 是否已完成获取器
     */
    public function getIsCompletedAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_COMPLETED;
    }

    /**
     * 是否失败获取器
     */
    public function getIsFailedAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_FAILED;
    }

    /**
     * 更新任务状态
     *
     * @param string $status 状态值(PENDING|PROCESSING|COMPLETED|FAILED)
     * @param string $errorMessage 错误信息(可选)
     * @return bool
     */
    public function updateStatus(string $status, string $errorMessage = ''): bool
    {
        // 验证状态值
        $validStatuses = [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED
        ];

        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->status = $status;

        // 如果状态为完成或失败，记录完成时间
        if (in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED])) {
            $this->complete_time = date('Y-m-d H:i:s');
        }

        // 如果提供了错误信息，保存错误信息
        if (!empty($errorMessage)) {
            $this->error_message = $errorMessage;
        }

        return $this->save();
    }

    /**
     * 开始处理任务
     *
     * @return bool
     */
    public function startProcessing(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_PROCESSING;
        return $this->save();
    }

    /**
     * 完成任务
     *
     * @param array $outputData 输出数据
     * @param int $generationTime 生成耗时(秒)
     * @return bool
     */
    public function complete(array $outputData, int $generationTime = 0): bool
    {
        if ($this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->output_data = $outputData;
        $this->generation_time = $generationTime;
        $this->complete_time = date('Y-m-d H:i:s');

        return $this->save();
    }

    /**
     * 标记任务失败
     *
     * @param string $errorMessage 错误信息
     * @return bool
     */
    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;
        $this->complete_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 重置任务状态
     *
     * @return bool
     */
    public function reset(): bool
    {
        $this->status = self::STATUS_PENDING;
        $this->complete_time = null;
        $this->error_message = '';
        $this->output_data = null;
        $this->generation_time = null;
        return $this->save();
    }

    /**
     * 获取设备的内容任务
     *
     * @param int $deviceId
     * @param string $type 可选，筛选类型
     * @param string $status 可选，筛选状态
     * @return array
     */
    public static function getDeviceTasks(int $deviceId, string $type = null, string $status = null): array
    {
        $query = self::where('device_id', $deviceId);

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->order('create_time', 'desc')->select()->toArray();
    }

    /**
     * 获取待处理的任务
     *
     * @param int $limit
     * @return array
     */
    public static function getPendingTasks(int $limit = 10): array
    {
        return self::where('status', self::STATUS_PENDING)
            ->order('priority', 'desc')
            ->order('create_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取正在处理的任务
     *
     * @return array
     */
    public static function getProcessingTasks(): array
    {
        return self::where('status', self::STATUS_PROCESSING)
            ->order('started_at', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取超时的处理中任务
     *
     * @param int $timeoutMinutes 超时分钟数，默认30分钟
     * @return array
     */
    public static function getTimeoutTasks(int $timeoutMinutes = 30): array
    {
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        return self::where('status', self::STATUS_PROCESSING)
            ->where('update_time', '<', $timeoutTime)
            ->select()
            ->toArray();
    }

    /**
     * 批量重置超时任务
     *
     * @param int $timeoutMinutes
     * @return int 重置的任务数量
     */
    public static function resetTimeoutTasks(int $timeoutMinutes = 30): int
    {
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        return self::where('status', self::STATUS_PROCESSING)
            ->where('update_time', '<', $timeoutTime)
            ->update([
                'status' => self::STATUS_PENDING,
                'error_message' => '任务处理超时，已重置',
                'update_time' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * 获取任务统计
     *
     * @param int $deviceId 可选，指定设备
     * @param int $merchantId 可选，指定商家
     * @return array
     */
    public static function getTaskStats(int $deviceId = null, int $merchantId = null): array
    {
        $query = self::query();

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $total = $query->count();
        $pending = $query->where('status', self::STATUS_PENDING)->count();
        $processing = $query->where('status', self::STATUS_PROCESSING)->count();
        $completed = $query->where('status', self::STATUS_COMPLETED)->count();
        $failed = $query->where('status', self::STATUS_FAILED)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0
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
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'device_id' => 'integer|>:0',
            'merchant_id' => 'require|integer|>:0',
            'user_id' => 'require|integer|>:0',
            'template_id' => 'integer|>:0',
            'type' => 'require|in:VIDEO,TEXT,IMAGE',
            'status' => 'in:PENDING,PROCESSING,COMPLETED,FAILED',
            'input_data' => 'array',
            'output_data' => 'array',
            'ai_provider' => 'max:20',
            'generation_time' => 'integer|>=:0',
            'error_message' => 'max:500'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'device_id.integer' => '设备ID必须是整数',
            'device_id.>' => '设备ID必须大于0',
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'template_id.integer' => '模板ID必须是整数',
            'template_id.>' => '模板ID必须大于0',
            'type.require' => '内容类型不能为空',
            'type.in' => '内容类型值无效',
            'status.in' => '状态值无效',
            'input_data.array' => '输入数据必须是数组格式',
            'output_data.array' => '输出数据必须是数组格式',
            'ai_provider.max' => 'AI服务商名称长度不能超过20个字符',
            'generation_time.integer' => '生成耗时必须是整数',
            'generation_time.>=' => '生成耗时不能为负数',
            'error_message.max' => '错误信息长度不能超过500个字符'
        ];
    }
}