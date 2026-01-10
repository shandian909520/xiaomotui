<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 敏感词模型
 * @property int $id 词ID
 * @property string $word 敏感词
 * @property string $category 分类
 * @property int $level 等级 1-5
 * @property string $action 处理动作
 * @property int $status 状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class SensitiveWord extends Model
{
    protected $name = 'sensitive_words';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'word' => 'string',
        'category' => 'string',
        'level' => 'int',
        'action' => 'string',
        'status' => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'level' => 'integer',
        'status' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'word', 'category', 'level', 'action', 'status'
    ];

    /**
     * 处理动作常量
     */
    const ACTION_BLOCK = 'BLOCK';       // 直接屏蔽
    const ACTION_REVIEW = 'REVIEW';     // 人工审核
    const ACTION_REPLACE = 'REPLACE';   // 替换处理

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    /**
     * 分类常量
     */
    const CATEGORY_POLITICAL = 'POLITICAL';           // 政治敏感
    const CATEGORY_PORNOGRAPHIC = 'PORNOGRAPHIC';     // 色情低俗
    const CATEGORY_VIOLENCE = 'VIOLENCE';             // 暴力血腥
    const CATEGORY_GAMBLING = 'GAMBLING';             // 赌博诈骗
    const CATEGORY_DRUGS = 'DRUGS';                   // 涉毒内容
    const CATEGORY_ILLEGAL = 'ILLEGAL';               // 违法违规
    const CATEGORY_SPAM = 'SPAM';                     // 垃圾广告
    const CATEGORY_ABUSE = 'ABUSE';                   // 辱骂诽谤
    const CATEGORY_OTHER = 'OTHER';                   // 其他

    /**
     * 分类获取器
     */
    public function getCategoryTextAttr($value, $data): string
    {
        $categories = [
            self::CATEGORY_POLITICAL => '政治敏感',
            self::CATEGORY_PORNOGRAPHIC => '色情低俗',
            self::CATEGORY_VIOLENCE => '暴力血腥',
            self::CATEGORY_GAMBLING => '赌博诈骗',
            self::CATEGORY_DRUGS => '涉毒内容',
            self::CATEGORY_ILLEGAL => '违法违规',
            self::CATEGORY_SPAM => '垃圾广告',
            self::CATEGORY_ABUSE => '辱骂诽谤',
            self::CATEGORY_OTHER => '其他',
        ];
        return $categories[$data['category']] ?? '未知';
    }

    /**
     * 等级获取器
     */
    public function getLevelTextAttr($value, $data): string
    {
        $levels = [
            1 => '一级（轻微）',
            2 => '二级（一般）',
            3 => '三级（较重）',
            4 => '四级（严重）',
            5 => '五级（极严重）',
        ];
        return $levels[$data['level']] ?? '未知';
    }

    /**
     * 动作获取器
     */
    public function getActionTextAttr($value, $data): string
    {
        $actions = [
            self::ACTION_BLOCK => '直接屏蔽',
            self::ACTION_REVIEW => '人工审核',
            self::ACTION_REPLACE => '替换处理',
        ];
        return $actions[$data['action']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 启用敏感词
     *
     * @return bool
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用敏感词
     *
     * @return bool
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 检查是否启用
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 获取所有启用的敏感词
     *
     * @return array
     */
    public static function getEnabledWords(): array
    {
        return self::where('status', self::STATUS_ENABLED)
            ->order('level', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 按分类获取敏感词
     *
     * @param string $category
     * @return array
     */
    public static function getByCategory(string $category): array
    {
        return self::where('category', $category)
            ->where('status', self::STATUS_ENABLED)
            ->order('level', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 按等级获取敏感词
     *
     * @param int $minLevel 最小等级
     * @param int $maxLevel 最大等级
     * @return array
     */
    public static function getByLevel(int $minLevel = 1, int $maxLevel = 5): array
    {
        return self::where('status', self::STATUS_ENABLED)
            ->where('level', '>=', $minLevel)
            ->where('level', '<=', $maxLevel)
            ->order('level', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 按动作获取敏感词
     *
     * @param string $action
     * @return array
     */
    public static function getByAction(string $action): array
    {
        return self::where('action', $action)
            ->where('status', self::STATUS_ENABLED)
            ->order('level', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 搜索敏感词
     *
     * @param string $keyword
     * @return array
     */
    public static function search(string $keyword): array
    {
        return self::whereLike('word', "%{$keyword}%")
            ->order('level', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 批量添加敏感词
     *
     * @param array $words 敏感词数组
     * @param string $category 分类
     * @param int $level 等级
     * @param string $action 动作
     * @return int 添加的数量
     */
    public static function batchAdd(array $words, string $category = self::CATEGORY_OTHER, int $level = 3, string $action = self::ACTION_REVIEW): int
    {
        $count = 0;

        foreach ($words as $word) {
            // 检查是否已存在
            $exists = self::where('word', $word)->find();
            if ($exists) {
                continue;
            }

            $sensitiveWord = new self();
            $sensitiveWord->word = $word;
            $sensitiveWord->category = $category;
            $sensitiveWord->level = $level;
            $sensitiveWord->action = $action;
            $sensitiveWord->status = self::STATUS_ENABLED;

            if ($sensitiveWord->save()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 批量更新敏感词状态
     *
     * @param array $ids 敏感词ID数组
     * @param int $status 状态
     * @return int 更新的数量
     */
    public static function batchUpdateStatus(array $ids, int $status): int
    {
        return self::whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * 批量删除敏感词
     *
     * @param array $ids 敏感词ID数组
     * @return int 删除的数量
     */
    public static function batchDelete(array $ids): int
    {
        return self::whereIn('id', $ids)->delete();
    }

    /**
     * 获取敏感词统计
     *
     * @return array
     */
    public static function getStats(): array
    {
        $total = self::count();
        $enabled = self::where('status', self::STATUS_ENABLED)->count();

        // 按分类统计
        $byCategory = self::group('category')
            ->field('category, count(*) as count')
            ->select()
            ->toArray();

        // 按等级统计
        $byLevel = self::group('level')
            ->field('level, count(*) as count')
            ->select()
            ->toArray();

        // 按动作统计
        $byAction = self::group('action')
            ->field('action, count(*) as count')
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
            'by_category' => $byCategory,
            'by_level' => $byLevel,
            'by_action' => $byAction
        ];
    }

    /**
     * 导入敏感词
     *
     * @param string $filePath 文件路径
     * @param string $category 分类
     * @param int $level 等级
     * @return array 导入结果
     */
    public static function importFromFile(string $filePath, string $category = self::CATEGORY_OTHER, int $level = 3): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => '文件不存在', 'count' => 0];
        }

        $content = file_get_contents($filePath);
        $words = array_filter(explode("\n", $content));

        // 清理每个词
        $words = array_map(function($word) {
            return trim($word);
        }, $words);

        // 去重
        $words = array_unique($words);

        $count = self::batchAdd($words, $category, $level);

        return [
            'success' => true,
            'message' => "成功导入{$count}个敏感词",
            'count' => $count,
            'total' => count($words)
        ];
    }

    /**
     * 导出敏感词
     *
     * @param string $filePath 文件路径
     * @param array $filters 过滤条件
     * @return array 导出结果
     */
    public static function exportToFile(string $filePath, array $filters = []): array
    {
        $query = self::query();

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $words = $query->column('word');

        $content = implode("\n", $words);
        $result = file_put_contents($filePath, $content);

        return [
            'success' => $result !== false,
            'message' => $result !== false ? "成功导出{$result}个字符" : '导出失败',
            'count' => count($words),
            'file_path' => $filePath
        ];
    }

    /**
     * 获取分类选项
     *
     * @return array
     */
    public static function getCategoryOptions(): array
    {
        return [
            self::CATEGORY_POLITICAL => '政治敏感',
            self::CATEGORY_PORNOGRAPHIC => '色情低俗',
            self::CATEGORY_VIOLENCE => '暴力血腥',
            self::CATEGORY_GAMBLING => '赌博诈骗',
            self::CATEGORY_DRUGS => '涉毒内容',
            self::CATEGORY_ILLEGAL => '违法违规',
            self::CATEGORY_SPAM => '垃圾广告',
            self::CATEGORY_ABUSE => '辱骂诽谤',
            self::CATEGORY_OTHER => '其他',
        ];
    }

    /**
     * 获取动作选项
     *
     * @return array
     */
    public static function getActionOptions(): array
    {
        return [
            self::ACTION_BLOCK => '直接屏蔽',
            self::ACTION_REVIEW => '人工审核',
            self::ACTION_REPLACE => '替换处理',
        ];
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'word' => 'require|max:100',
            'category' => 'require|max:50',
            'level' => 'require|integer|between:1,5',
            'action' => 'require|in:BLOCK,REVIEW,REPLACE',
            'status' => 'in:0,1',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'word.require' => '敏感词不能为空',
            'word.max' => '敏感词长度不能超过100个字符',
            'category.require' => '分类不能为空',
            'category.max' => '分类长度不能超过50个字符',
            'level.require' => '等级不能为空',
            'level.integer' => '等级必须是整数',
            'level.between' => '等级必须在1-5之间',
            'action.require' => '处理动作不能为空',
            'action.in' => '处理动作值无效',
            'status.in' => '状态值无效',
        ];
    }
}