<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 内容模板模型
 * @property int $id 模板ID
 * @property int $merchant_id 商家ID（为空表示系统模板）
 * @property string $name 模板名称
 * @property string $type 模板类型 VIDEO/TEXT/IMAGE
 * @property string $category 模板分类
 * @property string $style 风格标签
 * @property array $content 模板内容配置（JSON）
 * @property string $preview_url 预览图
 * @property int $usage_count 使用次数
 * @property int $is_public 是否公开 0否 1是
 * @property int $status 状态 0禁用 1启用
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class ContentTemplate extends Model
{
    protected $name = 'content_templates';

    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'merchant_id' => 'int',
        'name'        => 'string',
        'type'        => 'string',
        'category'    => 'string',
        'style'       => 'string',
        'content'     => 'json',
        'preview_url' => 'string',
        'usage_count' => 'int',
        'is_public'   => 'int',
        'status'      => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'          => 'integer',
        'merchant_id' => 'integer',
        'content'     => 'array',
        'usage_count' => 'integer',
        'is_public'   => 'integer',
        'status'      => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['content'];

    // 只读字段
    protected $readonly = [];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'name', 'type', 'category', 'style', 'content',
        'preview_url', 'usage_count', 'is_public', 'status'
    ];

    /**
     * 模板类型常量
     */
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_TEXT = 'TEXT';
    const TYPE_IMAGE = 'IMAGE';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 公开状态常量
     */
    const PUBLIC_NO = 0;   // 私有
    const PUBLIC_YES = 1;  // 公开

    /**
     * 模板分类常量
     */
    const CATEGORY_MENU = '菜单';
    const CATEGORY_PROMOTION = '促销';
    const CATEGORY_ANNOUNCEMENT = '公告';
    const CATEGORY_CONTACT = '联系方式';
    const CATEGORY_WIFI = 'WiFi';
    const CATEGORY_CUSTOM = '自定义';

    /**
     * 风格标签常量
     */
    const STYLE_SIMPLE = '简约';
    const STYLE_COLORFUL = '多彩';
    const STYLE_ELEGANT = '优雅';
    const STYLE_MODERN = '现代';
    const STYLE_CLASSIC = '经典';

    /**
     * 模板类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_VIDEO => '视频模板',
            self::TYPE_TEXT => '文本模板',
            self::TYPE_IMAGE => '图片模板'
        ];
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $status = [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 公开状态获取器
     */
    public function getIsPublicTextAttr($value, $data): string
    {
        $public = [
            self::PUBLIC_NO => '私有',
            self::PUBLIC_YES => '公开'
        ];
        return $public[$data['is_public']] ?? '私有';
    }

    /**
     * 模板来源获取器
     */
    public function getTemplateSourceAttr($value, $data): string
    {
        return empty($data['merchant_id']) ? '系统模板' : '商家模板';
    }

    /**
     * 预览图获取器 - 处理相对路径转换为完整URL
     */
    public function getPreviewUrlAttr($value): string
    {
        if (empty($value)) {
            return '';
        }

        // 如果已经是完整URL，直接返回
        if (strpos($value, 'http') === 0) {
            return $value;
        }

        // 如果是相对路径，转换为完整URL
        return request()->domain() . $value;
    }

    /**
     * 增加使用次数
     */
    public function incrementUsageCount(): bool
    {
        $this->usage_count = $this->usage_count + 1;
        return $this->save();
    }

    /**
     * 设置模板状态
     */
    public function setTemplateStatus(int $status): bool
    {
        if (!in_array($status, [self::STATUS_DISABLED, self::STATUS_ENABLED])) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * 启用模板
     */
    public function enable(): bool
    {
        return $this->setTemplateStatus(self::STATUS_ENABLED);
    }

    /**
     * 禁用模板
     */
    public function disable(): bool
    {
        return $this->setTemplateStatus(self::STATUS_DISABLED);
    }

    /**
     * 设置公开状态
     */
    public function setPublicStatus(int $isPublic): bool
    {
        if (!in_array($isPublic, [self::PUBLIC_NO, self::PUBLIC_YES])) {
            return false;
        }

        $this->is_public = $isPublic;
        return $this->save();
    }

    /**
     * 设为公开
     */
    public function makePublic(): bool
    {
        return $this->setPublicStatus(self::PUBLIC_YES);
    }

    /**
     * 设为私有
     */
    public function makePrivate(): bool
    {
        return $this->setPublicStatus(self::PUBLIC_NO);
    }

    /**
     * 检查是否为系统模板
     */
    public function isSystemTemplate(): bool
    {
        return empty($this->merchant_id);
    }

    /**
     * 检查是否为商家模板
     */
    public function isMerchantTemplate(): bool
    {
        return !empty($this->merchant_id);
    }

    /**
     * 检查模板是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查模板是否公开
     */
    public function isPublic(): bool
    {
        return $this->is_public === self::PUBLIC_YES;
    }

    /**
     * 复制模板
     */
    public function copyTemplate(int $merchantId = null, string $newName = null): ?ContentTemplate
    {
        $data = $this->toArray();

        // 移除主键和时间戳字段
        unset($data['id'], $data['create_time'], $data['update_time']);

        // 设置新的商家ID
        $data['merchant_id'] = $merchantId;

        // 设置新名称
        if ($newName) {
            $data['name'] = $newName;
        } else {
            $data['name'] = $data['name'] . '_副本';
        }

        // 重置使用次数
        $data['usage_count'] = 0;

        // 如果是复制给商家，设为私有
        if ($merchantId) {
            $data['is_public'] = self::PUBLIC_NO;
        }

        $newTemplate = new static();
        if ($newTemplate->save($data)) {
            return $newTemplate;
        }

        return null;
    }

    /**
     * 根据类型获取模板列表
     */
    public static function getByType(string $type, int $merchantId = null, bool $includeSystem = true): array
    {
        $query = static::where('type', $type)
                      ->where('status', self::STATUS_ENABLED);

        if ($merchantId && $includeSystem) {
            // 包含系统模板和指定商家的模板
            $query->where(function($query) use ($merchantId) {
                $query->whereNull('merchant_id')
                      ->whereOr('merchant_id', $merchantId);
            });
        } elseif ($merchantId) {
            // 只包含指定商家的模板
            $query->where('merchant_id', $merchantId);
        } else {
            // 只包含系统模板
            $query->whereNull('merchant_id');
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 根据分类获取模板列表
     */
    public static function getByCategory(string $category, int $merchantId = null): array
    {
        $query = static::where('category', $category)
                      ->where('status', self::STATUS_ENABLED);

        if ($merchantId) {
            $query->where(function($query) use ($merchantId) {
                $query->whereNull('merchant_id')
                      ->whereOr('merchant_id', $merchantId);
            });
        } else {
            $query->whereNull('merchant_id');
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取公开模板列表
     */
    public static function getPublicTemplates(string $type = null): array
    {
        $query = static::where('is_public', self::PUBLIC_YES)
                      ->where('status', self::STATUS_ENABLED);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取商家模板列表
     */
    public static function getMerchantTemplates(int $merchantId, string $type = null): array
    {
        $query = static::where('merchant_id', $merchantId)
                      ->where('status', self::STATUS_ENABLED);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取系统模板列表
     */
    public static function getSystemTemplates(string $type = null): array
    {
        $query = static::whereNull('merchant_id')
                      ->where('status', self::STATUS_ENABLED);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取热门模板
     */
    public static function getPopularTemplates(int $limit = 10, string $type = null): array
    {
        $query = static::where('status', self::STATUS_ENABLED)
                      ->where('usage_count', '>', 0);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->limit($limit)
                    ->select()
                    ->toArray();
    }

    /**
     * 搜索模板
     */
    public static function searchTemplates(string $keyword, int $merchantId = null, string $type = null): array
    {
        $query = static::where('status', self::STATUS_ENABLED)
                      ->where(function($query) use ($keyword) {
                          $query->whereLike('name', "%{$keyword}%")
                                ->whereOr('category', 'like', "%{$keyword}%")
                                ->whereOr('style', 'like', "%{$keyword}%");
                      });

        if ($merchantId) {
            $query->where(function($query) use ($merchantId) {
                $query->whereNull('merchant_id')
                      ->whereOr('merchant_id', $merchantId);
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        return $query->order('usage_count', 'desc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取模板统计
     */
    public static function getTemplateStats(int $merchantId = null): array
    {
        $query = static::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $total = $query->count();
        $enabled = $query->where('status', self::STATUS_ENABLED)->count();
        $disabled = $query->where('status', self::STATUS_DISABLED)->count();
        $public = $query->where('is_public', self::PUBLIC_YES)->count();

        // 按类型统计
        $typeStats = static::field('type, count(*) as count')
                          ->group('type')
                          ->select()
                          ->toArray();

        $typeCount = [];
        foreach ($typeStats as $stat) {
            $typeCount[$stat['type']] = $stat['count'];
        }

        return [
            'total' => $total,
            'enabled' => $enabled,
            'disabled' => $disabled,
            'public' => $public,
            'private' => $total - $public,
            'type_count' => $typeCount,
            'total_usage' => static::sum('usage_count') ?: 0
        ];
    }

    /**
     * 所属商家关联
     */
    public function merchant()
    {
        return $this->belongsTo(\app\model\Merchant::class);
    }

    /**
     * 使用该模板的设备关联
     */
    public function devices()
    {
        return $this->hasMany(\app\model\NfcDevice::class, 'template_id');
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'integer|>:0',
            'name' => 'require|max:100',
            'type' => 'require|in:VIDEO,TEXT,IMAGE',
            'category' => 'max:50',
            'style' => 'max:50',
            'content' => 'require',
            'preview_url' => 'url|max:255',
            'usage_count' => 'integer|>=:0',
            'is_public' => 'in:0,1',
            'status' => 'in:0,1',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'name.require' => '模板名称不能为空',
            'name.max' => '模板名称长度不能超过100个字符',
            'type.require' => '模板类型不能为空',
            'type.in' => '模板类型值无效',
            'category.max' => '模板分类长度不能超过50个字符',
            'style.max' => '风格标签长度不能超过50个字符',
            'content.require' => '模板内容不能为空',
            'preview_url.url' => '预览图链接格式不正确',
            'preview_url.max' => '预览图链接长度不能超过255个字符',
            'usage_count.integer' => '使用次数必须是整数',
            'usage_count.>=' => '使用次数不能为负数',
            'is_public.in' => '公开状态值无效',
            'status.in' => '状态值无效',
        ];
    }

    /**
     * 获取所有模板类型选项
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_VIDEO => '视频模板',
            self::TYPE_TEXT => '文本模板',
            self::TYPE_IMAGE => '图片模板'
        ];
    }

    /**
     * 获取所有分类选项
     */
    public static function getCategoryOptions(): array
    {
        return [
            self::CATEGORY_MENU => '菜单',
            self::CATEGORY_PROMOTION => '促销',
            self::CATEGORY_ANNOUNCEMENT => '公告',
            self::CATEGORY_CONTACT => '联系方式',
            self::CATEGORY_WIFI => 'WiFi',
            self::CATEGORY_CUSTOM => '自定义'
        ];
    }

    /**
     * 获取所有风格选项
     */
    public static function getStyleOptions(): array
    {
        return [
            self::STYLE_SIMPLE => '简约',
            self::STYLE_COLORFUL => '多彩',
            self::STYLE_ELEGANT => '优雅',
            self::STYLE_MODERN => '现代',
            self::STYLE_CLASSIC => '经典'
        ];
    }
}