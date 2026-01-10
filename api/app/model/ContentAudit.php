<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 内容审核记录模型
 * @property int $id 审核ID
 * @property int $content_id 内容ID
 * @property string $content_type 内容类型
 * @property string $audit_type 审核类型
 * @property string $audit_method 审核方式
 * @property int $status 审核状态
 * @property array $auto_result 自动审核结果
 * @property array $manual_result 人工审核结果
 * @property string $risk_level 风险等级
 * @property array $violation_types 违规类型
 * @property string $audit_message 审核信息
 * @property int $auditor_id 审核员ID
 * @property string $submit_time 提交时间
 * @property string $audit_time 审核完成时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class ContentAudit extends Model
{
    protected $name = 'content_audits';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'content_id' => 'int',
        'content_type' => 'string',
        'audit_type' => 'string',
        'audit_method' => 'string',
        'status' => 'int',
        'auto_result' => 'json',
        'manual_result' => 'json',
        'risk_level' => 'string',
        'violation_types' => 'json',
        'audit_message' => 'string',
        'auditor_id' => 'int',
        'submit_time' => 'datetime',
        'audit_time' => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'content_id' => 'integer',
        'status' => 'integer',
        'auto_result' => 'array',
        'manual_result' => 'array',
        'violation_types' => 'array',
        'auditor_id' => 'integer',
        'submit_time' => 'timestamp',
        'audit_time' => 'timestamp',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['auto_result', 'manual_result', 'violation_types'];

    // 允许批量赋值的字段
    protected $field = [
        'content_id', 'content_type', 'audit_type', 'audit_method', 'status',
        'auto_result', 'manual_result', 'risk_level', 'violation_types',
        'audit_message', 'auditor_id', 'submit_time', 'audit_time'
    ];

    /**
     * 内容类型常量
     */
    const CONTENT_TYPE_MATERIAL = 'MATERIAL';
    const CONTENT_TYPE_TASK = 'CONTENT_TASK';
    const CONTENT_TYPE_COMMENT = 'COMMENT';
    const CONTENT_TYPE_USER = 'USER_CONTENT';

    /**
     * 审核类型常量
     */
    const AUDIT_TYPE_TEXT = 'TEXT';
    const AUDIT_TYPE_IMAGE = 'IMAGE';
    const AUDIT_TYPE_VIDEO = 'VIDEO';
    const AUDIT_TYPE_AUDIO = 'AUDIO';

    /**
     * 审核方式常量
     */
    const METHOD_AUTO = 'AUTO';
    const METHOD_MANUAL = 'MANUAL';
    const METHOD_MIXED = 'MIXED';

    /**
     * 审核状态常量
     */
    const STATUS_PENDING = 0;    // 待审核
    const STATUS_APPROVED = 1;   // 通过
    const STATUS_REJECTED = 2;   // 拒绝
    const STATUS_AUDITING = 3;   // 审核中

    /**
     * 风险等级常量
     */
    const RISK_LOW = 'LOW';
    const RISK_MEDIUM = 'MEDIUM';
    const RISK_HIGH = 'HIGH';
    const RISK_CRITICAL = 'CRITICAL';

    /**
     * 内容类型获取器
     */
    public function getContentTypeTextAttr($value, $data): string
    {
        $types = [
            self::CONTENT_TYPE_MATERIAL => '素材内容',
            self::CONTENT_TYPE_TASK => '内容任务',
            self::CONTENT_TYPE_COMMENT => '评论内容',
            self::CONTENT_TYPE_USER => '用户内容',
        ];
        return $types[$data['content_type']] ?? '未知';
    }

    /**
     * 审核类型获取器
     */
    public function getAuditTypeTextAttr($value, $data): string
    {
        $types = [
            self::AUDIT_TYPE_TEXT => '文本审核',
            self::AUDIT_TYPE_IMAGE => '图片审核',
            self::AUDIT_TYPE_VIDEO => '视频审核',
            self::AUDIT_TYPE_AUDIO => '音频审核',
        ];
        return $types[$data['audit_type']] ?? '未知';
    }

    /**
     * 审核方式获取器
     */
    public function getAuditMethodTextAttr($value, $data): string
    {
        $methods = [
            self::METHOD_AUTO => '自动审核',
            self::METHOD_MANUAL => '人工审核',
            self::METHOD_MIXED => '混合审核',
        ];
        return $methods[$data['audit_method']] ?? '未知';
    }

    /**
     * 审核状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '审核通过',
            self::STATUS_REJECTED => '审核拒绝',
            self::STATUS_AUDITING => '审核中',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 风险等级获取器
     */
    public function getRiskLevelTextAttr($value, $data): string
    {
        $levels = [
            self::RISK_LOW => '低风险',
            self::RISK_MEDIUM => '中风险',
            self::RISK_HIGH => '高风险',
            self::RISK_CRITICAL => '严重风险',
        ];
        return $levels[$data['risk_level']] ?? '未知';
    }

    /**
     * 风险等级颜色获取器
     */
    public function getRiskLevelColorAttr($value, $data): string
    {
        $colors = [
            self::RISK_LOW => '#52c41a',
            self::RISK_MEDIUM => '#faad14',
            self::RISK_HIGH => '#ff7a45',
            self::RISK_CRITICAL => '#f5222d',
        ];
        return $colors[$data['risk_level']] ?? '#d9d9d9';
    }

    /**
     * 是否通过获取器
     */
    public function getIsApprovedAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_APPROVED;
    }

    /**
     * 是否拒绝获取器
     */
    public function getIsRejectedAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_REJECTED;
    }

    /**
     * 是否待审核获取器
     */
    public function getIsPendingAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_PENDING;
    }

    /**
     * 审核通过
     *
     * @param string $message
     * @return bool
     */
    public function approve(string $message = ''): bool
    {
        $this->status = self::STATUS_APPROVED;
        $this->audit_message = $message ?: '审核通过';
        $this->audit_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 审核拒绝
     *
     * @param string $message
     * @param array $violations
     * @return bool
     */
    public function reject(string $message, array $violations = []): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->audit_message = $message;
        $this->violation_types = $violations;
        $this->audit_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 标记为审核中
     *
     * @return bool
     */
    public function markAsAuditing(): bool
    {
        $this->status = self::STATUS_AUDITING;
        return $this->save();
    }

    /**
     * 审核员关联
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /**
     * 获取待审核记录
     *
     * @param int $limit
     * @return array
     */
    public static function getPendingAudits(int $limit = 10): array
    {
        return self::where('status', self::STATUS_PENDING)
            ->order('risk_level', 'desc')
            ->order('submit_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取审核中的记录
     *
     * @return array
     */
    public static function getAuditingRecords(): array
    {
        return self::where('status', self::STATUS_AUDITING)
            ->order('submit_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取超时的审核记录
     *
     * @param int $timeoutMinutes
     * @return array
     */
    public static function getTimeoutAudits(int $timeoutMinutes = 60): array
    {
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        return self::where('status', self::STATUS_AUDITING)
            ->where('submit_time', '<', $timeoutTime)
            ->select()
            ->toArray();
    }

    /**
     * 获取内容的审核记录
     *
     * @param int $contentId
     * @param string $contentType
     * @return array
     */
    public static function getContentAudits(int $contentId, string $contentType): array
    {
        return self::where('content_id', $contentId)
            ->where('content_type', $contentType)
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取最新审核记录
     *
     * @param int $contentId
     * @param string $contentType
     * @return ContentAudit|null
     */
    public static function getLatestAudit(int $contentId, string $contentType): ?ContentAudit
    {
        return self::where('content_id', $contentId)
            ->where('content_type', $contentType)
            ->order('create_time', 'desc')
            ->find();
    }

    /**
     * 获取审核统计
     *
     * @param array $filters
     * @return array
     */
    public static function getAuditStats(array $filters = []): array
    {
        $query = self::query();

        // 应用过滤条件
        if (!empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (!empty($filters['audit_type'])) {
            $query->where('audit_type', $filters['audit_type']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date']);
        }

        $total = (clone $query)->count();
        $approved = (clone $query)->where('status', self::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', self::STATUS_REJECTED)->count();
        $pending = (clone $query)->where('status', self::STATUS_PENDING)->count();

        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'approval_rate' => $total > 0 ? round($approved / $total * 100, 2) : 0
        ];
    }

    /**
     * 按时间段统计
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getStatsByDateRange(string $startDate, string $endDate): array
    {
        $query = self::whereBetween('create_time', [$startDate, $endDate]);

        return [
            'total' => $query->count(),
            'approved' => (clone $query)->where('status', self::STATUS_APPROVED)->count(),
            'rejected' => (clone $query)->where('status', self::STATUS_REJECTED)->count(),
            'by_type' => (clone $query)->group('audit_type')
                ->field('audit_type, count(*) as count')
                ->select()
                ->toArray(),
            'by_risk' => (clone $query)->group('risk_level')
                ->field('risk_level, count(*) as count')
                ->select()
                ->toArray()
        ];
    }

    /**
     * 获取违规统计
     *
     * @param array $filters
     * @return array
     */
    public static function getViolationStats(array $filters = []): array
    {
        $query = self::where('status', self::STATUS_REJECTED);

        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date']);
        }

        $records = $query->select()->toArray();

        $violations = [];
        foreach ($records as $record) {
            if (!empty($record['violation_types'])) {
                foreach ($record['violation_types'] as $type) {
                    if (!isset($violations[$type])) {
                        $violations[$type] = 0;
                    }
                    $violations[$type]++;
                }
            }
        }

        arsort($violations);

        return [
            'total_violations' => count($records),
            'violation_types' => $violations
        ];
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'content_id' => 'require|integer|>:0',
            'content_type' => 'require|in:MATERIAL,CONTENT_TASK,COMMENT,USER_CONTENT',
            'audit_type' => 'require|in:TEXT,IMAGE,VIDEO,AUDIO',
            'audit_method' => 'in:AUTO,MANUAL,MIXED',
            'status' => 'in:0,1,2,3',
            'risk_level' => 'in:LOW,MEDIUM,HIGH,CRITICAL',
            'audit_message' => 'max:500',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'content_id.require' => '内容ID不能为空',
            'content_id.integer' => '内容ID必须是整数',
            'content_id.>' => '内容ID必须大于0',
            'content_type.require' => '内容类型不能为空',
            'content_type.in' => '内容类型值无效',
            'audit_type.require' => '审核类型不能为空',
            'audit_type.in' => '审核类型值无效',
            'audit_method.in' => '审核方式值无效',
            'status.in' => '审核状态值无效',
            'risk_level.in' => '风险等级值无效',
            'audit_message.max' => '审核信息长度不能超过500个字符',
        ];
    }
}