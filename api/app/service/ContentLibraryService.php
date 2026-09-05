<?php
declare(strict_types=1);

namespace app\service;

use app\model\ContentLibrary;
use app\model\ContentLibraryItem;
use think\facade\Db;
use think\facade\Log;

class ContentLibraryService
{
    // ==================== 视频库 ====================

    public function getVideoLibraryList(int $merchantId, array $filters = []): array
    {
        $query = ContentLibrary::where('merchant_id', $merchantId)
            ->where('library_type', ContentLibrary::TYPE_VIDEO);

        if (!empty($filters['keyword'])) {
            $query->whereLike('name', "%{$filters['keyword']}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        } else {
            $query->where('status', '>=', 0);
        }

        $page = (int)($filters['page'] ?? 1);
        $pageSize = min((int)($filters['page_size'] ?? 20), 100);
        $total = $query->count();

        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function createVideoLibrary(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $library = new ContentLibrary();
            $library->merchant_id = $merchantId;
            $library->library_type = ContentLibrary::TYPE_VIDEO;
            $library->name = $data['name'];
            $library->max_use_count = (int)($data['max_use_count'] ?? 0);
            $library->total_count = 0;
            $library->used_count = 0;
            $library->remaining_count = $library->max_use_count;
            $library->warning_email = $data['warning_email'] ?? null;
            $library->status = ContentLibrary::STATUS_ENABLED;
            $library->save();

            Db::commit();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建视频库失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getVideoLibraryDetail(int $id): ?array
    {
        $library = ContentLibrary::with(['items' => function ($q) {
            $q->where('item_type', ContentLibraryItem::TYPE_VIDEO)->order('create_time', 'desc');
        }])->find($id);

        if (!$library) {
            return null;
        }

        $data = $library->toArray();
        $data['items'] = array_map(function ($item) {
            unset($item['library']);
            return $item;
        }, $data['items'] ?? []);

        return $data;
    }

    public function updateVideoLibrary(int $id, array $data): array
    {
        try {
            $library = ContentLibrary::find($id);
            if (!$library) {
                return ['success' => false, 'message' => '视频库不存在'];
            }

            if (isset($data['name'])) {
                $library->name = $data['name'];
            }
            if (isset($data['max_use_count'])) {
                $library->max_use_count = (int)$data['max_use_count'];
                $library->remaining_count = max(0, $library->max_use_count - $library->used_count);
            }
            if (isset($data['warning_email'])) {
                $library->warning_email = $data['warning_email'];
            }
            if (isset($data['status'])) {
                $library->status = (int)$data['status'];
            }
            $library->save();

            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Log::error('更新视频库失败', ['id' => $id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteVideoLibrary(int $id): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($id);
            if (!$library) {
                return ['success' => false, 'message' => '视频库不存在'];
            }

            ContentLibraryItem::where('library_id', $id)->delete();
            $library->delete();

            Db::commit();
            return ['success' => true, 'message' => '删除成功'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('删除视频库失败', ['id' => $id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addLocalVideo(int $libraryId, array $data): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '视频库不存在'];
            }

            $item = new ContentLibraryItem();
            $item->library_id = $libraryId;
            $item->item_type = ContentLibraryItem::TYPE_VIDEO;
            $item->title = $data['title'] ?? '';
            $item->file_url = $data['file_url'];
            $item->thumbnail_url = $data['thumbnail_url'] ?? null;
            $item->metadata = $data['metadata'] ?? null;
            $item->source = ContentLibraryItem::SOURCE_LOCAL;
            $item->use_count = 0;
            $item->status = ContentLibraryItem::STATUS_ENABLED;
            $item->save();

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'data' => $item->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加本地视频失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function importVideo(int $libraryId, array $data): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '视频库不存在'];
            }

            $items = $data['items'] ?? [];
            $imported = 0;

            foreach ($items as $itemData) {
                $item = new ContentLibraryItem();
                $item->library_id = $libraryId;
                $item->item_type = ContentLibraryItem::TYPE_VIDEO;
                $item->title = $itemData['title'] ?? '';
                $item->file_url = $itemData['file_url'];
                $item->thumbnail_url = $itemData['thumbnail_url'] ?? null;
                $item->metadata = $itemData['metadata'] ?? null;
                $item->source = ContentLibraryItem::SOURCE_IMPORT;
                $item->use_count = 0;
                $item->status = ContentLibraryItem::STATUS_ENABLED;
                $item->save();
                $imported++;
            }

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'imported' => $imported];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('导入视频失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==================== 图文库 ====================

    public function getGraphicLibraryList(int $merchantId, array $filters = []): array
    {
        $query = ContentLibrary::where('merchant_id', $merchantId)
            ->where('library_type', ContentLibrary::TYPE_GRAPHIC);

        if (!empty($filters['keyword'])) {
            $query->whereLike('name', "%{$filters['keyword']}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        } else {
            $query->where('status', '>=', 0);
        }

        $page = (int)($filters['page'] ?? 1);
        $pageSize = min((int)($filters['page_size'] ?? 20), 100);
        $total = $query->count();

        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function createGraphicLibrary(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $library = new ContentLibrary();
            $library->merchant_id = $merchantId;
            $library->library_type = ContentLibrary::TYPE_GRAPHIC;
            $library->name = $data['name'];
            $library->max_use_count = (int)($data['max_use_count'] ?? 0);
            $library->total_count = 0;
            $library->used_count = 0;
            $library->remaining_count = $library->max_use_count;
            $library->warning_email = $data['warning_email'] ?? null;
            $library->status = ContentLibrary::STATUS_ENABLED;
            $library->save();

            Db::commit();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建图文库失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getGraphicLibraryDetail(int $id): ?array
    {
        $library = ContentLibrary::with(['items' => function ($q) {
            $q->whereIn('item_type', [ContentLibraryItem::TYPE_IMAGE, ContentLibraryItem::TYPE_TEXT])
                ->order('create_time', 'desc');
        }])->find($id);

        if (!$library) {
            return null;
        }

        $data = $library->toArray();
        $data['items'] = array_map(function ($item) {
            unset($item['library']);
            return $item;
        }, $data['items'] ?? []);

        return $data;
    }

    public function addGraphicContent(int $libraryId, ?int $imageItemId, ?int $textItemId): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '图文库不存在'];
            }

            if ($imageItemId) {
                $imageItem = ContentLibraryItem::find($imageItemId);
                if (!$imageItem || $imageItem->library_id != $libraryId) {
                    return ['success' => false, 'message' => '图片条目不存在或不属于该库'];
                }
            }

            if ($textItemId) {
                $textItem = ContentLibraryItem::find($textItemId);
                if (!$textItem || $textItem->library_id != $libraryId) {
                    return ['success' => false, 'message' => '文案条目不存在或不属于该库'];
                }
            }

            if ($imageItemId && $textItemId) {
                $imageItem->paired_item_id = $textItemId;
                $imageItem->save();
                $textItem->paired_item_id = $imageItemId;
                $textItem->save();
            }

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'message' => '图文配对成功'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加图文内容失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateGraphicLibrary(int $id, array $data): array
    {
        return $this->updateVideoLibrary($id, $data);
    }

    public function deleteGraphicLibrary(int $id): array
    {
        return $this->deleteVideoLibrary($id);
    }

    // ==================== 图片库 ====================

    public function createImageLibrary(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $library = new ContentLibrary();
            $library->merchant_id = $merchantId;
            $library->library_type = ContentLibrary::TYPE_IMAGE;
            $library->name = $data['name'];
            $library->max_use_count = (int)($data['max_use_count'] ?? 0);
            $library->total_count = 0;
            $library->used_count = 0;
            $library->remaining_count = $library->max_use_count;
            $library->warning_email = $data['warning_email'] ?? null;
            $library->status = ContentLibrary::STATUS_ENABLED;
            $library->save();

            Db::commit();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建图片库失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getImageLibraryList(int $merchantId, array $filters = []): array
    {
        $query = ContentLibrary::where('merchant_id', $merchantId)
            ->where('library_type', ContentLibrary::TYPE_IMAGE);

        if (!empty($filters['keyword'])) {
            $query->whereLike('name', "%{$filters['keyword']}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        } else {
            $query->where('status', '>=', 0);
        }

        $page = (int)($filters['page'] ?? 1);
        $pageSize = min((int)($filters['page_size'] ?? 20), 100);
        $total = $query->count();

        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function getImageLibraryDetail(int $id): ?array
    {
        $library = ContentLibrary::with(['items' => function ($q) {
            $q->where('item_type', ContentLibraryItem::TYPE_IMAGE)->order('create_time', 'desc');
        }])->find($id);

        if (!$library) {
            return null;
        }

        $data = $library->toArray();
        $data['items'] = array_map(function ($item) {
            unset($item['library']);
            return $item;
        }, $data['items'] ?? []);

        return $data;
    }

    public function updateImageLibrary(int $id, array $data): array
    {
        return $this->updateVideoLibrary($id, $data);
    }

    public function deleteImageLibrary(int $id): array
    {
        return $this->deleteVideoLibrary($id);
    }

    public function addImage(int $libraryId, array $data): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '图片库不存在'];
            }

            $item = new ContentLibraryItem();
            $item->library_id = $libraryId;
            $item->item_type = ContentLibraryItem::TYPE_IMAGE;
            $item->title = $data['title'] ?? '';
            $item->file_url = $data['file_url'];
            $item->thumbnail_url = $data['thumbnail_url'] ?? null;
            $item->metadata = $data['metadata'] ?? null;
            $item->source = ContentLibraryItem::SOURCE_LOCAL;
            $item->use_count = 0;
            $item->status = ContentLibraryItem::STATUS_ENABLED;
            $item->save();

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'data' => $item->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加图片失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==================== 文案库 ====================

    public function createTextLibrary(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $library = new ContentLibrary();
            $library->merchant_id = $merchantId;
            $library->library_type = ContentLibrary::TYPE_TEXT;
            $library->name = $data['name'];
            $library->max_use_count = (int)($data['max_use_count'] ?? 0);
            $library->total_count = 0;
            $library->used_count = 0;
            $library->remaining_count = $library->max_use_count;
            $library->warning_email = $data['warning_email'] ?? null;
            $library->status = ContentLibrary::STATUS_ENABLED;
            $library->save();

            Db::commit();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建文案库失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTextLibraryList(int $merchantId, array $filters = []): array
    {
        $query = ContentLibrary::where('merchant_id', $merchantId)
            ->where('library_type', ContentLibrary::TYPE_TEXT);

        if (!empty($filters['keyword'])) {
            $query->whereLike('name', "%{$filters['keyword']}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        } else {
            $query->where('status', '>=', 0);
        }

        $page = (int)($filters['page'] ?? 1);
        $pageSize = min((int)($filters['page_size'] ?? 20), 100);
        $total = $query->count();

        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function getTextLibraryDetail(int $id): ?array
    {
        $library = ContentLibrary::with(['items' => function ($q) {
            $q->where('item_type', ContentLibraryItem::TYPE_TEXT)->order('create_time', 'desc');
        }])->find($id);

        if (!$library) {
            return null;
        }

        $data = $library->toArray();
        $data['items'] = array_map(function ($item) {
            unset($item['library']);
            return $item;
        }, $data['items'] ?? []);

        return $data;
    }

    public function updateTextLibrary(int $id, array $data): array
    {
        return $this->updateVideoLibrary($id, $data);
    }

    public function deleteTextLibrary(int $id): array
    {
        return $this->deleteVideoLibrary($id);
    }

    public function addText(int $libraryId, array $data): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '文案库不存在'];
            }

            $item = new ContentLibraryItem();
            $item->library_id = $libraryId;
            $item->item_type = ContentLibraryItem::TYPE_TEXT;
            $item->title = $data['title'] ?? '';
            $item->content = $data['content'] ?? '';
            $item->source = ContentLibraryItem::SOURCE_LOCAL;
            $item->use_count = 0;
            $item->status = ContentLibraryItem::STATUS_ENABLED;
            $item->save();

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'data' => $item->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加文案失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==================== 话题库 ====================

    public function getTopicLibraryList(int $merchantId, array $filters = []): array
    {
        $query = ContentLibrary::where('merchant_id', $merchantId)
            ->where('library_type', ContentLibrary::TYPE_TOPIC);

        if (!empty($filters['keyword'])) {
            $query->whereLike('name', "%{$filters['keyword']}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        } else {
            $query->where('status', '>=', 0);
        }

        $page = (int)($filters['page'] ?? 1);
        $pageSize = min((int)($filters['page_size'] ?? 20), 100);
        $total = $query->count();

        $list = $query->order('create_time', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function createTopicLibrary(int $merchantId, array $data): array
    {
        Db::startTrans();
        try {
            $library = new ContentLibrary();
            $library->merchant_id = $merchantId;
            $library->library_type = ContentLibrary::TYPE_TOPIC;
            $library->name = $data['name'];
            $library->max_use_count = (int)($data['max_use_count'] ?? 0);
            $library->total_count = 0;
            $library->used_count = 0;
            $library->remaining_count = $library->max_use_count;
            $library->warning_email = $data['warning_email'] ?? null;
            $library->status = ContentLibrary::STATUS_ENABLED;
            $library->save();

            Db::commit();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('创建话题库失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTopicLibraryDetail(int $id): ?array
    {
        $library = ContentLibrary::with(['items' => function ($q) {
            $q->where('item_type', ContentLibraryItem::TYPE_TOPIC)->order('create_time', 'desc');
        }])->find($id);

        if (!$library) {
            return null;
        }

        $data = $library->toArray();
        $data['items'] = array_map(function ($item) {
            unset($item['library']);
            return $item;
        }, $data['items'] ?? []);

        return $data;
    }

    public function addTopic(int $libraryId, array $data): array
    {
        Db::startTrans();
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '话题库不存在'];
            }

            $item = new ContentLibraryItem();
            $item->library_id = $libraryId;
            $item->item_type = ContentLibraryItem::TYPE_TOPIC;
            $item->title = $data['title'] ?? '';
            $item->content = $data['content'] ?? '';
            $item->source = ContentLibraryItem::SOURCE_LOCAL;
            $item->use_count = 0;
            $item->status = ContentLibraryItem::STATUS_ENABLED;
            $item->save();

            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'data' => $item->toArray()];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('添加话题失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function renameTopicLibrary(int $id, string $name): array
    {
        try {
            $library = ContentLibrary::find($id);
            if (!$library) {
                return ['success' => false, 'message' => '话题库不存在'];
            }
            $library->name = $name;
            $library->save();
            return ['success' => true, 'data' => $library->toArray()];
        } catch (\Exception $e) {
            Log::error('重命名话题库失败', ['id' => $id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteTopicLibrary(int $id): array
    {
        return $this->deleteVideoLibrary($id);
    }

    // ==================== 通用功能 ====================

    public function setWarningEmail(int $libraryId, string $email): array
    {
        try {
            $library = ContentLibrary::find($libraryId);
            if (!$library) {
                return ['success' => false, 'message' => '内容库不存在'];
            }
            $library->warning_email = $email;
            $library->save();
            return ['success' => true, 'message' => '预警邮箱设置成功'];
        } catch (\Exception $e) {
            Log::error('设置预警邮箱失败', ['library_id' => $libraryId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteItem(int $itemId): array
    {
        Db::startTrans();
        try {
            $item = ContentLibraryItem::find($itemId);
            if (!$item) {
                return ['success' => false, 'message' => '条目不存在'];
            }

            $libraryId = $item->library_id;

            if ($item->paired_item_id) {
                ContentLibraryItem::where('id', $item->paired_item_id)
                    ->update(['paired_item_id' => null]);
            }
            ContentLibraryItem::where('paired_item_id', $itemId)
                ->update(['paired_item_id' => null]);

            $item->delete();
            $this->updateLibraryCounts($libraryId);

            Db::commit();
            return ['success' => true, 'message' => '删除成功'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('删除条目失败', ['item_id' => $itemId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateLibraryCounts(int $libraryId): void
    {
        $totalCount = ContentLibraryItem::where('library_id', $libraryId)
            ->where('status', ContentLibraryItem::STATUS_ENABLED)
            ->count();

        $usedCount = ContentLibraryItem::where('library_id', $libraryId)
            ->where('status', ContentLibraryItem::STATUS_ENABLED)
            ->where('use_count', '>', 0)
            ->sum('use_count');
        $usedCount = (int)$usedCount;

        $library = ContentLibrary::find($libraryId);
        if ($library) {
            $library->total_count = $totalCount;
            $library->used_count = $usedCount;
            $library->remaining_count = $library->max_use_count > 0
                ? max(0, $library->max_use_count - $usedCount)
                : 0;
            $library->save();
        }
    }

    public function getLibraryStatistics(int $merchantId): array
    {
        $types = [
            ContentLibrary::TYPE_VIDEO,
            ContentLibrary::TYPE_GRAPHIC,
            ContentLibrary::TYPE_IMAGE,
            ContentLibrary::TYPE_TEXT,
            ContentLibrary::TYPE_TOPIC,
        ];

        $stats = [];
        foreach ($types as $type) {
            $query = ContentLibrary::where('merchant_id', $merchantId)
                ->where('library_type', $type)
                ->where('status', '>=', 0);

            $stats[$type] = [
                'library_count' => $query->count(),
                'total_items' => (clone $query)->sum('total_count'),
                'total_used' => (clone $query)->sum('used_count'),
            ];
        }

        return $stats;
    }
}
