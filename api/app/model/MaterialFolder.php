<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class MaterialFolder extends Model
{
    protected $name = 'material_folders';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id' => 'integer',
        'merchant_id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
        'delete_time' => 'timestamp',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->order('sort', 'asc');
    }

    public function materials()
    {
        return $this->hasMany(Material::class, 'folder_id');
    }

    public static function getTree(int $merchantId): array
    {
        $folders = static::where('merchant_id', $merchantId)
            ->whereNull('delete_time')
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return self::buildTree($folders, 0);
    }

    private static function buildTree(array $folders, int $parentId): array
    {
        $tree = [];
        foreach ($folders as $folder) {
            if ((int)$folder['parent_id'] === $parentId) {
                $folder['children'] = self::buildTree($folders, (int)$folder['id']);
                $tree[] = $folder;
            }
        }
        return $tree;
    }

    public static function getChildIds(int $folderId): array
    {
        $ids = [$folderId];
        $children = static::where('parent_id', $folderId)
            ->whereNull('delete_time')
            ->column('id');

        foreach ($children as $childId) {
            $ids = array_merge($ids, self::getChildIds((int)$childId));
        }

        return $ids;
    }
}
