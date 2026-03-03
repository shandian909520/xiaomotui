<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Request;
use think\facade\Log;
use app\service\PromoMaterialService;
use app\model\PromoMaterial as PromoMaterialModel;

/**
 * 推广素材控制器
 */
class PromoMaterial extends BaseController
{
    protected PromoMaterialService $materialService;

    /**
     * 当前商家ID（从认证中间件获取）
     */
    protected ?int $merchantId = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->materialService = new PromoMaterialService();

        // 从请求中获取商家ID（由认证中间件注入）
        $this->merchantId = $this->request->merchantId ?? null;
    }

    /**
     * 检查是否为管理员
     */
    protected function isAdmin(): bool
    {
        return $this->request->role === 'admin';
    }

    /**
     * 获取有效的商家ID（支持admin查看所有或指定商家）
     * @param Request $request
     * @return int|null
     */
    protected function getEffectiveMerchantId(Request $request): ?int
    {
        // 如果请求中指定了merchant_id参数，使用该值（仅admin可用）
        $requestedMerchantId = $request->get('merchant_id') ?: $request->post('merchant_id');
        if ($requestedMerchantId && $this->isAdmin()) {
            return (int)$requestedMerchantId;
        }
        // 否则返回当前用户的merchant_id
        return $this->merchantId;
    }

    /**
     * 检查商家权限
     * @param int|null $requiredMerchantId
     * @return bool
     */
    protected function checkMerchantAccess(?int $requiredMerchantId = null): bool
    {
        if ($this->isAdmin()) {
            return true; // admin可以访问所有
        }
        if (!$this->merchantId) {
            return false;
        }
        if ($requiredMerchantId && $this->merchantId !== $requiredMerchantId) {
            return false;
        }
        return true;
    }

    /**
     * 上传单个素材
     * POST /api/merchant/promo/materials
     */
    public function upload(Request $request)
    {
        try {
            $file = $request->file('file');
            $type = strtolower($request->post('type', ''));
            $name = $request->post('name');
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            // 验证必填参数
            if (!$file) {
                return $this->error('请选择要上传的文件', 400);
            }

            if (empty($type)) {
                return $this->error('素材类型不能为空', 400);
            }

            if (!$targetMerchantId) {
                return $this->error('商家信息无效', 401);
            }

            // 调用服务上传
            $result = $this->materialService->upload($targetMerchantId, $file, $type, $name);

            if ($result['success']) {
                return $this->success([
                    'material_id' => $result['material_id'],
                    'file_url' => $result['file_url'],
                    'thumbnail_url' => $result['thumbnail_url'],
                ], '上传成功');
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('推广素材上传异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('上传失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量上传素材
     * POST /api/merchant/promo/materials/batch
     */
    public function batchUpload(Request $request)
    {
        try {
            $files = $request->file('files');
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$files || !is_array($files)) {
                return $this->error('请选择要上传的文件', 400);
            }

            if (!$targetMerchantId) {
                return $this->error('商家信息无效', 401);
            }

            // 检查文件数量限制
            if (count($files) > 20) {
                return $this->error('单次最多上传20个文件', 400);
            }

            // 构建上传数据
            $filesData = [];
            foreach ($files as $index => $file) {
                $type = strtolower($request->post("type.{$index}", ''));
                if (empty($type)) {
                    // 尝试根据文件扩展名推断类型
                    $extension = strtolower($file->extension());
                    $type = $this->detectTypeByExtension($extension);
                }

                if (!$type) {
                    continue;
                }

                $filesData[] = [
                    'file' => $file,
                    'type' => $type,
                    'name' => $request->post("name.{$index}"),
                ];
            }

            if (empty($filesData)) {
                return $this->error('没有有效的上传文件', 400);
            }

            $result = $this->materialService->batchUpload($targetMerchantId, $filesData);

            return $this->success([
                'total' => $result['total'],
                'success' => $result['success'],
                'failed' => $result['failed'],
                'details' => $result['details'],
            ], '批量上传完成');
        } catch (\Exception $e) {
            Log::error('批量上传异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('批量上传失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取素材列表
     * GET /api/merchant/promo/materials
     */
    public function list(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $type = $request->get('type');
            $page = (int)$request->get('page', 1);
            $pageSize = (int)$request->get('page_size', 20);

            // 验证类型
            if ($type && !in_array($type, [PromoMaterialModel::TYPE_IMAGE, PromoMaterialModel::TYPE_VIDEO, PromoMaterialModel::TYPE_MUSIC])) {
                return $this->error('无效的素材类型', 400);
            }

            // 验证分页参数
            $page = max(1, $page);
            $pageSize = min(100, max(1, $pageSize));

            // admin不传merchant_id时返回所有数据
            if ($targetMerchantId) {
                $result = $this->materialService->getList($targetMerchantId, $type, $page, $pageSize);
            } else {
                // admin查看所有
                $query = PromoMaterialModel::where('status', '>=', 0);
                if ($type) {
                    $query->where('type', $type);
                }
                $total = $query->count();
                $list = $query->order('create_time', 'desc')
                    ->page($page, $pageSize)
                    ->select()
                    ->toArray();
                $result = ['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
            }

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['page_size']
            );
        } catch (\Exception $e) {
            Log::error('获取素材列表异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取素材列表失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取素材详情
     * GET /api/merchant/promo/materials/:id
     */
    public function detail(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('素材ID不能为空', 400);
            }

            $material = PromoMaterialModel::find($id);

            if (!$material) {
                return $this->error('素材不存在', 404);
            }

            // 验证权限（admin可访问所有）
            if (!$this->checkMerchantAccess($material->merchant_id)) {
                return $this->error('无权访问此素材', 403);
            }

            return $this->success($material->toArray());
        } catch (\Exception $e) {
            Log::error('获取素材详情异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取素材详情失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除素材
     * DELETE /api/merchant/promo/materials/:id
     */
    public function delete(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('素材ID不能为空', 400);
            }

            $material = PromoMaterialModel::find($id);
            if (!$material) {
                return $this->error('素材不存在', 404);
            }

            if (!$this->checkMerchantAccess($material->merchant_id)) {
                return $this->error('商家信息无效或无权操作', 401);
            }

            $result = $this->materialService->delete($id, $material->merchant_id);

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除素材异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('删除素材失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新素材排序
     * PUT /api/merchant/promo/materials/sort
     */
    public function sort(Request $request)
    {
        try {
            $sortData = $request->put('sort_data');

            if (empty($sortData) || !is_array($sortData)) {
                return $this->error('排序数据无效', 400);
            }

            foreach ($sortData as $item) {
                if (!isset($item['id']) || !isset($item['sort_order'])) {
                    continue;
                }

                $material = PromoMaterialModel::find($item['id']);
                if ($material && $this->checkMerchantAccess($material->merchant_id)) {
                    $material->sort_order = (int)$item['sort_order'];
                    $material->save();
                }
            }

            return $this->success(null, '排序更新成功');
        } catch (\Exception $e) {
            Log::error('更新排序异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('更新排序失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新素材状态
     * PUT /api/merchant/promo/materials/:id/status
     */
    public function updateStatus(Request $request)
    {
        try {
            $id = (int)$request->param('id');
            $status = (int)$request->put('status', 1);

            if (!$id) {
                return $this->error('素材ID不能为空', 400);
            }

            $material = PromoMaterialModel::find($id);

            if (!$material) {
                return $this->error('素材不存在', 404);
            }

            if (!$this->checkMerchantAccess($material->merchant_id)) {
                return $this->error('无权操作此素材', 403);
            }

            if ($status === 1) {
                $material->enable();
            } else {
                $material->disable();
            }

            return $this->success(null, '状态更新成功');
        } catch (\Exception $e) {
            Log::error('更新状态异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('更新状态失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取素材统计
     * GET /api/merchant/promo/materials/stats
     */
    public function stats(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            if ($targetMerchantId) {
                $typeCount = PromoMaterialModel::getTypeCount($targetMerchantId);
                $totalCount = array_sum($typeCount);
            } else {
                // admin查看所有统计
                $typeCount = [
                    PromoMaterialModel::TYPE_IMAGE => PromoMaterialModel::where('type', PromoMaterialModel::TYPE_IMAGE)->count(),
                    PromoMaterialModel::TYPE_VIDEO => PromoMaterialModel::where('type', PromoMaterialModel::TYPE_VIDEO)->count(),
                    PromoMaterialModel::TYPE_MUSIC => PromoMaterialModel::where('type', PromoMaterialModel::TYPE_MUSIC)->count(),
                ];
                $totalCount = array_sum($typeCount);
            }

            return $this->success([
                'total' => $totalCount,
                'by_type' => $typeCount,
            ]);
        } catch (\Exception $e) {
            Log::error('获取素材统计异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取素材统计失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 根据文件扩展名推断素材类型
     *
     * @param string $extension 文件扩展名
     * @return string|null
     */
    private function detectTypeByExtension(string $extension): ?string
    {
        $imageExts = PromoMaterialModel::getAllowedExtensions(PromoMaterialModel::TYPE_IMAGE);
        $videoExts = PromoMaterialModel::getAllowedExtensions(PromoMaterialModel::TYPE_VIDEO);
        $musicExts = PromoMaterialModel::getAllowedExtensions(PromoMaterialModel::TYPE_MUSIC);

        if (in_array($extension, $imageExts)) {
            return PromoMaterialModel::TYPE_IMAGE;
        }
        if (in_array($extension, $videoExts)) {
            return PromoMaterialModel::TYPE_VIDEO;
        }
        if (in_array($extension, $musicExts)) {
            return PromoMaterialModel::TYPE_MUSIC;
        }

        return null;
    }
}
