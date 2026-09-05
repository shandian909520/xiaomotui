<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 内容库条目模型
 * @property int $id
 * @property int $library_id 库ID
 * @property string $item_type 条目类型
 * @property string|null $title 标题
 * @property string|null $content 文本内容
 * @property string|null $file_url 文件URL
 * @property string|null $thumbnail_url 缩略图URL
 * @property int|null $paired_item_id 配对条目ID
 * @property array|null $metadata 元数据
 * @property int $use_count 使用次数
 * @property string $source 来源
 * @property int $status 状态
 * @property string $create_time
 * @property string $update_time
 */
class ContentLibraryItem extends Model
{
    protected $table = 'xmt_content_library_items';
    protected $pk = 'id';

    public const TYPE_VIDEO = 'video';
    public const TYPE_IMAGE = 'image';
    public const TYPE_TEXT = 'text';
    public const TYPE_TOPIC = 'topic';

    public const SOURCE_LOCAL = 'local';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_AI = 'ai';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'library_id' => 'integer',
        'paired_item_id' => 'integer',
        'metadata' => 'json',
        'use_count' => 'integer',
        'status' => 'integer',
    ];

    protected $field = [
        'library_id', 'item_type', 'title', 'content', 'file_url',
        'thumbnail_url', 'paired_item_id', 'metadata', 'use_count',
        'source', 'status',
    ];

    public function library()
    {
        return $this->belongsTo(ContentLibrary::class, 'library_id');
    }

    public function pairedItem()
    {
        return $this->belongsTo(self::class, 'paired_item_id');
    }

    public function scopeByLibrary($query, int $libraryId)
    {
        return $query->where('library_id', $libraryId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }
}
