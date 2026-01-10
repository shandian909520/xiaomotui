<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\MaterialImportService;
use think\Request;
use think\facade\Log;

/**
 * 素材管理控制器
 */
class Material extends BaseController
{
    protected $materialService;

    public function __construct()
    {
        $this->materialService = new MaterialImportService();
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
    public function validate(Request $request)
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

            $query = \app\model\Material::where('status', 1)
                                       ->where('audit_status', 1);

            if ($type) {
                $query->where('type', strtoupper($type));
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
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
}