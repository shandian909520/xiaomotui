<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 素材分类模型
 * @property int $id 分类ID
 * @property int $parent_id 父分类ID
 * @property string $name 分类名称
 * @property string $type 素材类型
 * @property string $description 分类描述
 * @property int $sort 排序
 * @property int $status 状态
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class MaterialCategory extends Model
{
    protected $name = 'material_categories';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'parent_id' => 'int',
        'name' => 'string',
        'type' => 'string',
        'description' => 'string',
        'sort' => 'int',
        'status' => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'parent_id', 'name', 'type', 'description', 'sort', 'status'
    ];

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 素材类型常量
     */
    const TYPE_VIDEO = 'VIDEO';
    const TYPE_AUDIO = 'AUDIO';
    const TYPE_TRANSITION = 'TRANSITION';
    const TYPE_TEXT_TEMPLATE = 'TEXT_TEMPLATE';
    const TYPE_IMAGE = 'IMAGE';
    const TYPE_MUSIC = 'MUSIC';

    /**
     * 类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_VIDEO => '视频片段',
            self::TYPE_AUDIO => '音效',
            self::TYPE_TRANSITION => '转场效果',
            self::TYPE_TEXT_TEMPLATE => '文案模板',
            self::TYPE_IMAGE => '图片素材',
            self::TYPE_MUSIC => '背景音乐'
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
     * 父分类关联
     */
    public function parent()
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    /**
     * 子分类关联
     */
    public function children()
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }

    /**
     * 素材关联
     */
    public function materials()
    {
        return $this->hasMany(Material::class, 'category_id');
    }

    /**
     * 启用分类
     */
    public function enable(): bool
    {
        $this->status = self::STATUS_ENABLED;
        return $this->save();
    }

    /**
     * 禁用分类
     */
    public function disable(): bool
    {
        $this->status = self::STATUS_DISABLED;
        return $this->save();
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查是否为顶级分类
     */
    public function isRoot(): bool
    {
        return $this->parent_id === 0;
    }

    /**
     * 获取分类树
     */
    public static function getTree(string $type = null, int $parentId = 0): array
    {
        $query = static::where('status', self::STATUS_ENABLED)
                      ->where('parent_id', $parentId);

        if ($type) {
            $query->where('type', $type);
        }

        $categories = $query->order('sort', 'asc')
                           ->order('create_time', 'desc')
                           ->select();

        $tree = [];
        foreach ($categories as $category) {
            $item = $category->toArray();
            $item['children'] = static::getTree($type, $category->id);
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * 获取所有子分类ID
     */
    public function getChildrenIds(bool $includeSelf = false): array
    {
        $ids = $includeSelf ? [$this->id] : [];

        $children = static::where('parent_id', $this->id)->select();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getChildrenIds(false));
        }

        return array_unique($ids);
    }

    /**
     * 根据类型获取分类列表
     */
    public static function getByType(string $type): array
    {
        return static::where('type', $type)
                    ->where('status', self::STATUS_ENABLED)
                    ->order('sort', 'asc')
                    ->order('create_time', 'desc')
                    ->select()
                    ->toArray();
    }

    /**
     * 获取所有类型选项
     */
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_VIDEO => '视频片段',
            self::TYPE_AUDIO => '音效',
            self::TYPE_TRANSITION => '转场效果',
            self::TYPE_TEXT_TEMPLATE => '文案模板',
            self::TYPE_IMAGE => '图片素材',
            self::TYPE_MUSIC => '背景音乐'
        ];
    }
}