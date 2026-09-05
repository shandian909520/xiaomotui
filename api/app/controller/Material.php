<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\MaterialImportService;
use app\service\MaterialManageService;
use think\Request;
use think\facade\Log;

/**
 * 素材管理控制器
 */
class Material extends BaseController
{
    protected $materialService;
    protected $manageService;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->materialService = new MaterialImportService();
        $this->manageService = new MaterialManageService();
    }

    /**
     * 获取商家ID：优先从请求参数，其次从JWT token，管理员默认1
     */
    protected function resolveMerchantId(): int
    {
        $merchantId = (int)$this->request->param('merchant_id/d', 0);
        if ($merchantId > 0) {
            return $merchantId;
        }

        $merchantId = $this->request->getMerchantId();
        if ($merchantId > 0) {
            return $merchantId;
        }

        if ($this->request->isAdmin()) {
            return 1;
        }

        return 0;
    }

    /**
     * 上传单个素材
     */
    public function upload(Request $request)
    {
        try {
            $file = $request->file('file');
            $type = strtoupper($request->post('type', 'VIDEO'));

            if (!$file) {
                return json(['code' => 400, 'message' => '请选择文件']);
            }

            // 验证素材类型
            $allowedTypes = ['VIDEO', 'AUDIO', 'TRANSITION', 'TEXT_TEMPLATE', 'IMAGE', 'MUSIC'];
            if (!in_array($type, $allowedTypes)) {
                return json(['code' => 400, 'message' => '不支持的素材类型']);
            }

            $fileData = [
                'name' => $file->getOriginalName(),
                'tmp_name' => $file->getPathname(),
                'size' => $file->getSize(),
                'type' => $file->getMime()
            ];

            $metadata = [
                'name' => $request->post('name', $file->getOriginalName()),
                'category_id' => $request->post('category_id'),
                'tags' => $request->post('tags/a', []),
                'weight' => $request->post('weight', 100),
                'creator_id' => $this->userId ?? null
            ];

            $result = $this->materialService->importSingleMaterial($type, $fileData, $metadata);

            if ($result['success']) {
                return json([
                    'code' => 200,
                    'message' => '素材上传成功',
                    'data' => [
                        'material_id' => $result['material_id'],
                        'file_url' => $result['file_url'],
                        'thumbnail_url' => $result['thumbnail_url']
                    ]
                ]);
            } else {
                return json(['code' => 500, 'message' => $result['message']]);
            }

        } catch (\Exception $e) {
            Log::error('素材上传失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return json(['code' => 500, 'message' => '上传失败：' . $e->getMessage()]);
        }
    }

    /**
     * 批量上传素材
     */
    public function batchUpload(Request $request)
    {
        try {
            $files = $request->file('files');
            $type = strtoupper($request->post('type', 'VIDEO'));

            if (!$files || !is_array($files)) {
                return json(['code' => 400, 'message' => '请选择文件']);
            }

            // 验证素材类型
            $allowedTypes = ['VIDEO', 'AUDIO', 'TRANSITION', 'TEXT_TEMPLATE', 'IMAGE', 'MUSIC'];
            if (!in_array($type, $allowedTypes)) {
                return json(['code' => 400, 'message' => '不支持的素材类型']);
            }

            // 检查文件数量限制
            $maxFiles = config('material.batch_import.max_files', 100);
            if (count($files) > $maxFiles) {
                return json(['code' => 400, 'message' => "单次最多上传{$maxFiles}个文件"]);
            }

            $filesData = [];
            foreach ($files as $file) {
                $filesData[] = [
                    'name' => $file->getOriginalName(),
                    'tmp_name' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'type' => $file->getMime()
                ];
            }

            $options = [
                'category_id' => $request->post('category_id'),
                'tags' => $request->post('tags/a', []),
                'weight' => $request->post('weight', 100),
                'creator_id' => $this->userId ?? null
            ];

            $result = $this->materialService->batchImportMaterials($type, $filesData, $options);

            return json([
                'code' => 200,
                'message' => '批量上传完成',
                'data' => [
                    'total' => $result['total'],
                    'success' => $result['success'],
                    'failed' => $result['failed'],
                    'details' => $result['details']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('批量上传失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return json(['code' => 500, 'message' => '批量上传失败：' . $e->getMessage()]);
        }
    }

    /**
     * 从ZIP压缩包导入素材
     */
    public function importZip(Request $request)
    {
        try {
            $file = $request->file('zip');

            if (!$file) {
                return json(['code' => 400, 'message' => '请选择ZIP文件']);
            }

            // 验证文件扩展名
            $extension = strtolower($file->extension());
            $allowedExtensions = config('material.zip_import.allowed_extensions', ['zip']);
            if (!in_array($extension, $allowedExtensions)) {
                return json(['code' => 400, 'message' => '只支持ZIP格式的压缩包']);
            }

            // 验证文件大小
            $maxSize = config('material.zip_import.max_size', 1024 * 1024 * 1024);
            if ($file->getSize() > $maxSize) {
                return json(['code' => 400, 'message' => 'ZIP文件过大']);
            }

            $options = [
                'creator_id' => $this->userId ?? null,
                'category_id' => $request->post('category_id'),
                'tags' => $request->post('tags/a', [])
            ];

            $result = $this->materialService->importFromZip($file->getPathname(), $options);

            if ($result['success']) {
                return json([
                    'code' => 200,
                    'message' => 'ZIP导入成功',
                    'data' => $result['results']
                ]);
            } else {
                return json(['code' => 500, 'message' => $result['message']]);
            }

        } catch (\Exception $e) {
            Log::error('ZIP导入失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return json(['code' => 500, 'message' => 'ZIP导入失败：' . $e->getMessage()]);
        }
    }

    /**
     * 验证素材文件
     */
    public function validateFile(Request $request)
    {
        try {
            $file = $request->file('file');
            $type = strtoupper($request->post('type', 'VIDEO'));

            if (!$file) {
                return json(['code' => 400, 'message' => '请选择文件']);
            }

            $result = $this->materialService->validateMaterial($type, $file->getPathname());

            if ($result['valid']) {
                return json([
                    'code' => 200,
                    'message' => '验证通过',
                    'data' => ['valid' => true]
                ]);
            } else {
                return json([
                    'code' => 400,
                    'message' => $result['message'],
                    'data' => ['valid' => false]
                ]);
            }

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '验证失败：' . $e->getMessage()]);
        }
    }

    /**
     * 创建素材分类
     */
    public function createCategory(Request $request)
    {
        try {
            $name = $request->post('name');
            $type = strtoupper($request->post('type'));

            if (empty($name)) {
                return json(['code' => 400, 'message' => '分类名称不能为空']);
            }

            $data = [
                'parent_id' => $request->post('parent_id', 0),
                'name' => $name,
                'type' => $type,
                'description' => $request->post('description', ''),
                'sort' => $request->post('sort', 0),
                'status' => 1
            ];

            $categoryId = $this->materialService->createMaterialCategory($data);

            return json([
                'code' => 200,
                'message' => '分类创建成功',
                'data' => ['category_id' => $categoryId]
            ]);

        } catch (\Exception $e) {
            Log::error('创建分类失败', [
                'error' => $e->getMessage()
            ]);
            return json(['code' => 500, 'message' => '创建失败：' . $e->getMessage()]);
        }
    }

    /**
     * 获取素材分类列表
     */
    public function getCategoryList(Request $request)
    {
        try {
            $filters = [
                'type' => $request->get('type'),
                'parent_id' => $request->get('parent_id')
            ];

            $categories = $this->materialService->getMaterialCategories($filters);

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    /**
     * 为素材添加标签
     */
    public function addTags(Request $request)
    {
        try {
            $materialId = $request->post('material_id/d');
            $tags = $request->post('tags/a', []);

            if (!$materialId) {
                return json(['code' => 400, 'message' => '素材ID不能为空']);
            }

            if (empty($tags)) {
                return json(['code' => 400, 'message' => '标签不能为空']);
            }

            $result = $this->materialService->addMaterialTags($materialId, $tags);

            if ($result) {
                return json(['code' => 200, 'message' => '标签添加成功']);
            } else {
                return json(['code' => 500, 'message' => '标签添加失败']);
            }

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    /**
     * 根据标签搜索素材
     */
    public function searchByTags(Request $request)
    {
        try {
            $tags = $request->post('tags/a', []);

            if (empty($tags)) {
                return json(['code' => 400, 'message' => '标签不能为空']);
            }

            $filters = [
                'type' => $request->post('type'),
                'category_id' => $request->post('category_id')
            ];

            $materials = $this->materialService->searchMaterialsByTags($tags, $filters);

            return json([
                'code' => 200,
                'message' => '搜索成功',
                'data' => [
                    'total' => count($materials),
                    'list' => $materials
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '搜索失败：' . $e->getMessage()]);
        }
    }

    /**
     * 获取素材列表
     */
    public function getList(Request $request)
    {
        try {
            $page = $request->get('page/d', 1);
            $limit = $request->get('limit/d', 20);
            $type = $request->get('type');
            $categoryId = $request->get('category_id/d');
            $keyword = $request->get('keyword');
            $folderId = $request->get('folder_id/d', 0);
            $materialType = $request->get('material_type');
            $isAi = $request->get('is_ai');
            $merchantId = $request->get('merchant_id/d', 0);

            $query = \app\model\Material::where('status', 1)
                                       ->where('audit_status', 1)
                                       ->where('is_deleted', 0);

            if ($merchantId > 0) {
                $query->where('merchant_id', $merchantId);
            }

            if ($type) {
                $query->where('type', strtoupper($type));
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($folderId > 0) {
                $childIds = \app\model\MaterialFolder::getChildIds($folderId);
                $query->where('folder_id', 'in', $childIds);
            }

            if ($materialType) {
                $query->where('material_type', $materialType);
            }

            if (isset($isAi) && $isAi !== '') {
                $query->where('is_ai', (int)$isAi);
            }

            if ($keyword) {
                $query->whereLike('name', "%{$keyword}%");
            }

            $total = $query->count();
            $materials = $query->order('weight', 'desc')
                              ->order('usage_count', 'desc')
                              ->page($page, $limit)
                              ->select();

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                    'list' => $materials
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    /**
     * 获取素材详情
     */
    public function getDetail(Request $request)
    {
        try {
            $id = $request->get('id/d');

            if (!$id) {
                return json(['code' => 400, 'message' => '素材ID不能为空']);
            }

            $material = \app\model\Material::find($id);

            if (!$material) {
                return json(['code' => 404, 'message' => '素材不存在']);
            }

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $material
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    /**
     * 获取素材统计
     */
    public function getStats(Request $request)
    {
        try {
            $filters = [
                'type' => $request->get('type')
            ];

            $stats = \app\model\Material::getMaterialStats($filters);

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    /**
     * 获取热门素材
     */
    public function getPopular(Request $request)
    {
        try {
            $limit = $request->get('limit/d', 10);
            $type = $request->get('type');

            $materials = \app\model\Material::getPopular($limit, $type);

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $materials
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    // ========== 文件夹管理 ==========

    public function getFolders(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }

            $tree = $this->manageService->getFolderTree($merchantId);

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $tree,
            ]);
        } catch (\Exception $e) {
            Log::error('获取文件夹树失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    public function createFolder(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $name = trim($request->post('name', ''));
            $parentId = (int)$request->post('parent_id/d', 0);
            $sort = (int)$request->post('sort/d', 0);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if ($name === '') {
                return json(['code' => 400, 'message' => '文件夹名称不能为空']);
            }

            $folderId = $this->manageService->createFolder($merchantId, $name, $parentId, $sort);

            return json([
                'code' => 200,
                'message' => '创建成功',
                'data' => ['folder_id' => $folderId],
            ]);
        } catch (\Exception $e) {
            Log::error('创建文件夹失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '创建失败：' . $e->getMessage()]);
        }
    }

    public function renameFolder(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $folderId = (int)$request->post('folder_id/d', 0);
            $name = trim($request->post('name', ''));

            if ($merchantId <= 0 || $folderId <= 0) {
                return json(['code' => 400, 'message' => '参数不完整']);
            }
            if ($name === '') {
                return json(['code' => 400, 'message' => '文件夹名称不能为空']);
            }

            $this->manageService->renameFolder($merchantId, $folderId, $name);

            return json(['code' => 200, 'message' => '重命名成功']);
        } catch (\Exception $e) {
            Log::error('重命名文件夹失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    public function deleteFolder(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $folderId = (int)$request->post('folder_id/d', 0);

            if ($merchantId <= 0 || $folderId <= 0) {
                return json(['code' => 400, 'message' => '参数不完整']);
            }

            $this->manageService->deleteFolder($merchantId, $folderId);

            return json(['code' => 200, 'message' => '删除成功']);
        } catch (\Exception $e) {
            Log::error('删除文件夹失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    // ========== 素材操作 ==========

    public function move(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $materialIds = $request->post('material_ids/a', []);
            $folderId = (int)$request->post('folder_id/d', 0);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if (empty($materialIds)) {
                return json(['code' => 400, 'message' => '请选择要移动的素材']);
            }

            $this->manageService->moveMaterials($merchantId, $materialIds, $folderId);

            return json(['code' => 200, 'message' => '移动成功']);
        } catch (\Exception $e) {
            Log::error('移动素材失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    public function batchDelete(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $materialIds = $request->post('material_ids/a', []);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if (empty($materialIds)) {
                return json(['code' => 400, 'message' => '请选择要删除的素材']);
            }

            $count = $this->manageService->batchDelete($merchantId, $materialIds);

            return json([
                'code' => 200,
                'message' => '删除成功',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            Log::error('批量删除素材失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    public function softDelete(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $materialIds = $request->post('material_ids/a', []);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if (empty($materialIds)) {
                return json(['code' => 400, 'message' => '请选择要删除的素材']);
            }

            $count = $this->manageService->batchDelete($merchantId, $materialIds);

            return json([
                'code' => 200,
                'message' => '已移入回收站',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            Log::error('软删除素材失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    public function getTrash(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }

            $params = [
                'page' => $request->get('page/d', 1),
                'limit' => $request->get('limit/d', 20),
                'material_type' => $request->get('material_type'),
                'keyword' => $request->get('keyword'),
            ];

            $result = $this->manageService->getTrashList($merchantId, $params);

            return json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('获取回收站列表失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '获取失败：' . $e->getMessage()]);
        }
    }

    public function restore(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $materialIds = $request->post('material_ids/a', []);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if (empty($materialIds)) {
                return json(['code' => 400, 'message' => '请选择要恢复的素材']);
            }

            $count = $this->manageService->restoreMaterials($merchantId, $materialIds);

            return json([
                'code' => 200,
                'message' => '恢复成功',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            Log::error('恢复素材失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    public function permanentDelete(Request $request)
    {
        try {
            $merchantId = $this->resolveMerchantId();
            $materialIds = $request->post('material_ids/a', []);

            if ($merchantId <= 0) {
                return json(['code' => 400, 'message' => '商家ID不能为空']);
            }
            if (empty($materialIds)) {
                return json(['code' => 400, 'message' => '请选择要彻底删除的素材']);
            }

            $count = $this->manageService->permanentDelete($merchantId, $materialIds);

            return json([
                'code' => 200,
                'message' => '已彻底删除',
                'data' => ['count' => $count],
            ]);
        } catch (\Exception $e) {
            Log::error('彻底删除素材失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }
}