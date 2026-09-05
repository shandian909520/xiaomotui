<?php
declare(strict_types=1);

namespace app\service;

use app\model\Material;
use app\model\MaterialFolder;
use think\facade\Log;

class MaterialManageService
{
    public function getFolderTree(int $merchantId): array
    {
        return MaterialFolder::getTree($merchantId);
    }

    public function createFolder(int $merchantId, string $name, int $parentId = 0, int $sort = 0): int
    {
        if ($parentId > 0) {
            $parent = MaterialFolder::where('id', $parentId)
                ->where('merchant_id', $merchantId)
                ->whereNull('delete_time')
                ->find();
            if (!$parent) {
                throw new \Exception('父文件夹不存在');
            }
        }

        $folder = MaterialFolder::create([
            'merchant_id' => $merchantId,
            'parent_id' => $parentId,
            'name' => $name,
            'sort' => $sort,
        ]);

        return (int)$folder->id;
    }

    public function renameFolder(int $merchantId, int $folderId, string $name): bool
    {
        $folder = MaterialFolder::where('id', $folderId)
            ->where('merchant_id', $merchantId)
            ->whereNull('delete_time')
            ->find();

        if (!$folder) {
            throw new \Exception('文件夹不存在');
        }

        $folder->name = $name;
        return $folder->save();
    }

    public function deleteFolder(int $merchantId, int $folderId): bool
    {
        $folder = MaterialFolder::where('id', $folderId)
            ->where('merchant_id', $merchantId)
            ->whereNull('delete_time')
            ->find();

        if (!$folder) {
            throw new \Exception('文件夹不存在');
        }

        $childIds = MaterialFolder::getChildIds($folderId);
        $now = date('Y-m-d H:i:s');

        MaterialFolder::where('id', 'in', $childIds)
            ->where('merchant_id', $merchantId)
            ->update(['delete_time' => $now]);

        Material::where('folder_id', 'in', $childIds)
            ->where('merchant_id', $merchantId)
            ->update(['folder_id' => 0]);

        return true;
    }

    public function getMaterialList(int $merchantId, array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        $query = Material::where('is_deleted', 0)
            ->where('merchant_id', $merchantId);

        if (!empty($params['folder_id'])) {
            $folderId = (int)$params['folder_id'];
            $childIds = MaterialFolder::getChildIds($folderId);
            $query->where('folder_id', 'in', $childIds);
        }

        if (!empty($params['material_type'])) {
            $query->where('material_type', $params['material_type']);
        }

        if (!empty($params['type'])) {
            $query->where('type', strtoupper($params['type']));
        }

        if (!empty($params['category_id'])) {
            $query->where('category_id', (int)$params['category_id']);
        }

        if (isset($params['is_ai'])) {
            $query->where('is_ai', (int)$params['is_ai']);
        }

        if (!empty($params['keyword'])) {
            $query->whereLike('name', "%{$params['keyword']}%");
        }

        $total = $query->count();
        $list = $query->order('weight', 'desc')
            ->order('create_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit),
            'list' => $list,
        ];
    }

    public function moveMaterials(int $merchantId, array $materialIds, int $folderId): bool
    {
        if ($folderId > 0) {
            $folder = MaterialFolder::where('id', $folderId)
                ->where('merchant_id', $merchantId)
                ->whereNull('delete_time')
                ->find();
            if (!$folder) {
                throw new \Exception('目标文件夹不存在');
            }
        }

        return Material::where('id', 'in', $materialIds)
            ->where('merchant_id', $merchantId)
            ->where('is_deleted', 0)
            ->update(['folder_id' => $folderId]) !== false;
    }

    public function batchDelete(int $merchantId, array $materialIds): int
    {
        $now = date('Y-m-d H:i:s');

        return Material::where('id', 'in', $materialIds)
            ->where('merchant_id', $merchantId)
            ->where('is_deleted', 0)
            ->update([
                'is_deleted' => 1,
                'delete_time' => $now,
            ]);
    }

    public function getTrashList(int $merchantId, array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        $query = Material::where('is_deleted', 1)
            ->where('merchant_id', $merchantId);

        if (!empty($params['material_type'])) {
            $query->where('material_type', $params['material_type']);
        }

        if (!empty($params['keyword'])) {
            $query->whereLike('name', "%{$params['keyword']}%");
        }

        $total = $query->count();
        $list = $query->order('delete_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit),
            'list' => $list,
        ];
    }

    public function restoreMaterials(int $merchantId, array $materialIds): int
    {
        return Material::where('id', 'in', $materialIds)
            ->where('merchant_id', $merchantId)
            ->where('is_deleted', 1)
            ->update([
                'is_deleted' => 0,
                'delete_time' => null,
            ]);
    }

    public function permanentDelete(int $merchantId, array $materialIds): int
    {
        return Material::where('id', 'in', $materialIds)
            ->where('merchant_id', $merchantId)
            ->where('is_deleted', 1)
            ->delete();
    }
}
