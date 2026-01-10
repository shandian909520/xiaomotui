<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 素材使用记录模型
 * @property int $id 记录ID
 * @property int $user_id 用户ID
 * @property int $merchant_id 商家ID
 * @property int $template_id 模板ID
 * @property int $content_task_id 内容任务ID
 * @property array $usage_context 使用上下文
 * @property string $result 使用结果
 * @property string $create_time 创建时间
 */
class MaterialUsageLog extends Model
{
    protected $name = 'material_usage_logs';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'user_id'         => 'int',
        'merchant_id'     => 'int',
        'template_id'     => 'int',
        'content_task_id' => 'int',
        'usage_context'   => 'json',
        'result'          => 'string',
        'create_time'     => 'datetime',
    ];

    // 自动时间戳（只有创建时间）
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'merchant_id'     => 'integer',
        'template_id'     => 'integer',
        'content_task_id' => 'integer',
        'usage_context'   => 'array',
        'create_time'     => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['usage_context'];

    // 只读字段
    protected $readonly = ['id', 'create_time'];

    // 允许批量赋值的字段
    protected $field = [
        'user_id', 'merchant_id', 'template_id', 'content_task_id',
        'usage_context', 'result'
    ];

    /**
     * 使用结果常量
     */
    const RESULT_SUCCESS = 'SUCCESS';
    const RESULT_FAILED = 'FAILED';

    /**
     * 结果文本获取器
     */
    public function getResultTextAttr($value, $data): string
    {
        $results = [
            self::RESULT_SUCCESS => '成功',
            self::RESULT_FAILED => '失败',
        ];
        return $results[$data['result']] ?? '未知';
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    /**
     * 关联内容任务
     */
    public function contentTask()
    {
        return $this->belongsTo(ContentTask::class, 'content_task_id');
    }

    /**
     * 记录素材使用
     *
     * @param array $data 使用数据
     * @return MaterialUsageLog|null
     */
    public static function logUsage(array $data): ?MaterialUsageLog
    {
        $log = new static();
        $log->user_id = $data['user_id'];
        $log->merchant_id = $data['merchant_id'];
        $log->template_id = $data['template_id'];
        $log->content_task_id = $data['content_task_id'] ?? null;
        $log->usage_context = $data['usage_context'] ?? [];
        $log->result = $data['result'] ?? self::RESULT_SUCCESS;

        return $log->save() ? $log : null;
    }

    /**
     * 获取模板的使用统计
     *
     * @param int $templateId 模板ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getTemplateUsageStats(int $templateId, string $startDate = null, string $endDate = null): array
    {
        $query = static::where('template_id', $templateId);

        if ($startDate) {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $success = $query->where('result', self::RESULT_SUCCESS)->count();
        $failed = $query->where('result', self::RESULT_FAILED)->count();
        $successRate = $total > 0 ? round($success / $total * 100, 2) : 0;

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $successRate,
        ];
    }

    /**
     * 获取用户的使用历史
     *
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getUserUsageHistory(int $userId, int $page = 1, int $limit = 20): array
    {
        $query = static::where('user_id', $userId)
            ->with(['template', 'merchant', 'contentTask'])
            ->order('create_time', 'desc');

        $total = $query->count();
        $logs = $query->page($page, $limit)->select();

        return [
            'list' => $logs->toArray(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * 获取商家的使用统计
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getMerchantUsageStats(int $merchantId, string $startDate = null, string $endDate = null): array
    {
        $query = static::where('merchant_id', $merchantId);

        if ($startDate) {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $success = $query->where('result', self::RESULT_SUCCESS)->count();
        $failed = $query->where('result', self::RESULT_FAILED)->count();

        // 按模板统计
        $templateStats = $query->field('template_id, count(*) as count')
            ->group('template_id')
            ->order('count', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'top_templates' => $templateStats,
        ];
    }

    /**
     * 获取用户最常使用的模板
     *
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array
     */
    public static function getUserFrequentTemplates(int $userId, int $limit = 5): array
    {
        return static::where('user_id', $userId)
            ->where('result', self::RESULT_SUCCESS)
            ->field('template_id, count(*) as usage_count')
            ->group('template_id')
            ->order('usage_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取商家最常使用的模板
     *
     * @param int $merchantId 商家ID
     * @param int $limit 限制数量
     * @return array
     */
    public static function getMerchantFrequentTemplates(int $merchantId, int $limit = 5): array
    {
        return static::where('merchant_id', $merchantId)
            ->where('result', self::RESULT_SUCCESS)
            ->field('template_id, count(*) as usage_count')
            ->group('template_id')
            ->order('usage_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'user_id' => 'require|integer|>:0',
            'merchant_id' => 'require|integer|>:0',
            'template_id' => 'require|integer|>:0',
            'content_task_id' => 'integer|>:0',
            'result' => 'in:SUCCESS,FAILED',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'template_id.require' => '模板ID不能为空',
            'template_id.integer' => '模板ID必须是整数',
            'template_id.>' => '模板ID必须大于0',
            'content_task_id.integer' => '内容任务ID必须是整数',
            'content_task_id.>' => '内容任务ID必须大于0',
            'result.in' => '使用结果值无效',
        ];
    }
}