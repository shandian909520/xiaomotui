<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Request;
use think\facade\Log;
use think\facade\Cache;
use app\model\PromoTemplate as PromoTemplateModel;
use app\model\PromoVariant as PromoVariantModel;
use app\model\PromoMaterial as PromoMaterialModel;
use app\service\VideoDedupService;
use app\job\PromoVariantJob;
use app\controller\traits\AdminAccessibleTrait;

/**
 * 视频模板控制器
 */
class PromoTemplate extends BaseController
{
    use AdminAccessibleTrait;

    protected VideoDedupService $dedupService;

    /**
     * 当前商家ID
     */
    protected ?int $merchantId = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->dedupService = new VideoDedupService();
        $this->merchantId = $this->request->merchantId ?? null;
    }

    /**
     * 创建模板
     * POST /api/merchant/promo-template/create
     */
    public function create(Request $request)
    {
        try {
            $name = $request->post('name');
            $description = $request->post('description', '');
            $materialIds = $request->post('material_ids', []);
            $config = $request->post('config', []);
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            // 验证必填参数
            if (empty($name)) {
                return $this->error('模板名称不能为空', 400);
            }

            if (empty($materialIds) || !is_array($materialIds)) {
                return $this->error('请选择至少一个素材', 400);
            }

            if (!$targetMerchantId) {
                return $this->error('商家信息无效', 401);
            }

            // 验证素材
            $materials = PromoMaterialModel::whereIn('id', $materialIds)
                ->where('merchant_id', $targetMerchantId)
                ->where('status', PromoMaterialModel::STATUS_ENABLED)
                ->select();

            if ($materials->isEmpty()) {
                return $this->error('没有有效的素材', 400);
            }

            // 检查是否有图片素材
            $hasImage = false;
            foreach ($materials as $material) {
                if ($material->type === PromoMaterialModel::TYPE_IMAGE) {
                    $hasImage = true;
                    break;
                }
            }

            if (!$hasImage) {
                return $this->error('模板必须包含至少一张图片素材', 400);
            }

            // 验证配置
            if (!empty($config) && !PromoTemplateModel::validateConfig($config)) {
                return $this->error('配置参数无效', 400);
            }

            // 创建模板
            $template = new PromoTemplateModel();
            $template->merchant_id = $targetMerchantId;
            $template->name = $name;
            $template->description = $description;
            $template->material_ids = $materialIds;
            $template->config = $config;
            $template->status = PromoTemplateModel::STATUS_ENABLED;
            $template->save();

            Log::info('创建视频模板成功', [
                'template_id' => $template->id,
                'merchant_id' => $targetMerchantId,
            ]);

            return $this->success([
                'template_id' => $template->id,
                'name' => $template->name,
            ], '模板创建成功');
        } catch (\Exception $e) {
            Log::error('创建视频模板失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('创建失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取模板列表
     * GET /api/merchant/promo-template/list
     */
    public function list(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $page = (int)$request->get('page', 1);
            $pageSize = (int)$request->get('page_size', 20);
            $status = $request->get('status', -1);

            // 验证分页参数
            $page = max(1, $page);
            $pageSize = min(100, max(1, $pageSize));

            $query = PromoTemplateModel::when($targetMerchantId, function($q) use ($targetMerchantId) {
                $q->where('merchant_id', $targetMerchantId);
            });

            if ($status >= 0) {
                $query->where('status', (int)$status);
            }

            $total = $query->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            // 附加额外信息
            foreach ($list as &$item) {
                $item['material_count'] = count($item['material_ids'] ?? []);
                $item['variant_count'] = PromoVariantModel::countByTemplate($item['id']);
                $item['available_variant_count'] = PromoVariantModel::countByTemplate($item['id'], PromoVariantModel::STATUS_ENABLED);
            }

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            Log::error('获取模板列表失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取列表失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取模板详情
     * GET /api/merchant/promo-template/:id
     */
    public function detail(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('模板ID不能为空', 400);
            }

            $template = PromoTemplateModel::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 验证权限
            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权访问此模板', 403);
            }

            $data = $template->toArray();
            $data['materials'] = $template->getMaterials();
            $data['material_count'] = count($data['material_ids'] ?? []);
            $data['variant_count'] = PromoVariantModel::countByTemplate($id);
            $data['available_variant_count'] = PromoVariantModel::countByTemplate($id, PromoVariantModel::STATUS_ENABLED);

            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取模板详情失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取详情失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新模板
     * PUT /api/merchant/promo-template/:id
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('模板ID不能为空', 400);
            }

            $template = PromoTemplateModel::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权操作此模板', 403);
            }

            $name = $request->put('name');
            $description = $request->put('description');
            $materialIds = $request->put('material_ids');
            $config = $request->put('config');

            // 更新字段
            if ($name !== null) {
                if (empty($name)) {
                    return $this->error('模板名称不能为空', 400);
                }
                $template->name = $name;
            }

            if ($description !== null) {
                $template->description = $description;
            }

            if ($materialIds !== null) {
                if (empty($materialIds) || !is_array($materialIds)) {
                    return $this->error('请选择至少一个素材', 400);
                }

                // 验证素材
                $materials = PromoMaterialModel::whereIn('id', $materialIds)
                    ->where('merchant_id', $template->merchant_id)
                    ->where('status', PromoMaterialModel::STATUS_ENABLED)
                    ->select();

                if ($materials->isEmpty()) {
                    return $this->error('没有有效的素材', 400);
                }

                $template->material_ids = $materialIds;
            }

            if ($config !== null) {
                if (!empty($config) && !PromoTemplateModel::validateConfig($config)) {
                    return $this->error('配置参数无效', 400);
                }
                $template->config = $config;
            }

            $template->save();

            Log::info('更新视频模板成功', [
                'template_id' => $id,
                'merchant_id' => $template->merchant_id,
            ]);

            return $this->success(null, '更新成功');
        } catch (\Exception $e) {
            Log::error('更新模板失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('更新失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除模板
     * DELETE /api/merchant/promo-template/:id
     */
    public function delete(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('模板ID不能为空', 400);
            }

            $template = PromoTemplateModel::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权操作此模板', 403);
            }

            // 软删除（禁用）
            $template->status = PromoTemplateModel::STATUS_DISABLED;
            $template->save();

            // 同时禁用所有变体
            PromoVariantModel::where('template_id', $id)
                ->update(['status' => PromoVariantModel::STATUS_DISABLED]);

            Log::info('删除视频模板成功', [
                'template_id' => $id,
                'merchant_id' => $template->merchant_id,
            ]);

            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            Log::error('删除模板失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('删除失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 生成变体（异步任务）
     * POST /api/merchant/promo-template/:id/generate
     */
    public function generate(Request $request)
    {
        try {
            $id = (int)$request->param('id');
            $count = (int)$request->post('count', 5);

            if (!$id) {
                return $this->error('模板ID不能为空', 400);
            }

            // 验证数量
            $count = max(1, min(50, $count));

            $template = PromoTemplateModel::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权操作此模板', 403);
            }

            if (!$template->isEnabled()) {
                return $this->error('模板已禁用', 400);
            }

            // 检查现有变体数量
            $existingCount = PromoVariantModel::countByTemplate($id);
            $maxVariants = 100;

            if ($existingCount + $count > $maxVariants) {
                $allowedCount = $maxVariants - $existingCount;
                if ($allowedCount <= 0) {
                    return $this->error("已达到最大变体数量限制({$maxVariants}个)", 400);
                }
                $count = $allowedCount;
            }

            Log::info('开始生成视频变体', [
                'template_id' => $id,
                'count' => $count,
                'merchant_id' => $template->merchant_id,
            ]);

            $taskId = 'variant_gen_' . $id . '_' . uniqid();

            $this->updatePromoTaskCache($taskId, 'pending', [
                'template_id' => $id, 'count' => $count,
            ]);

            try {
                \think\facade\Queue::push(
                    PromoVariantJob::class,
                    [
                        'task_id' => $taskId,
                        'template_id' => $id,
                        'count' => $count,
                        'merchant_id' => $template->merchant_id,
                    ],
                    'promo_variant'
                );

                Log::info('视频变体生成任务已加入队列', [
                    'task_id' => $taskId,
                    'template_id' => $id,
                    'count' => $count,
                ]);

                return $this->success([
                    'task_id' => $taskId,
                    'template_id' => $id,
                    'requested_count' => $count,
                    'status' => 'pending',
                ], '变体生成任务已提交，请通过任务ID查询进度');
            } catch (\Exception $e) {
                Log::warning('队列推送失败，降级为同步执行', [
                    'template_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                $this->updatePromoTaskCache($taskId, 'processing', [
                    'template_id' => $id, 'count' => $count, 'progress' => 0,
                ]);

                try {
                    $result = $this->dedupService->generateVariants($id, $count);

                    $this->updatePromoTaskCache($taskId, 'completed', [
                        'template_id' => $id, 'count' => $count, 'progress' => 100,
                        'result' => $result, 'complete_time' => date('Y-m-d H:i:s'),
                    ]);

                    return $this->success([
                        'task_id' => $taskId,
                        'template_id' => $id,
                        'requested_count' => $count,
                        'success_count' => $result['success'],
                        'failed_count' => $result['failed'],
                        'variants' => $result['variants'],
                        'status' => 'completed',
                    ], "成功生成 {$result['success']} 个变体");
                } catch (\Exception $syncEx) {
                    $this->updatePromoTaskCache($taskId, 'failed', [
                        'template_id' => $id, 'error' => $syncEx->getMessage(),
                    ]);

                    return $this->error('生成变体失败: ' . $syncEx->getMessage(), 500);
                }
            }
        } catch (\Exception $e) {
            Log::error('生成变体异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('生成失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取模板配置选项
     * GET /api/merchant/promo-template/options
     */
    public function options(Request $request)
    {
        try {
            return $this->success([
                'transitions' => PromoTemplateModel::getTransitionOptions(),
                'resolutions' => PromoTemplateModel::getResolutionOptions(),
                'statuses' => PromoTemplateModel::getStatusOptions(),
                'default_config' => PromoTemplateModel::DEFAULT_CONFIG,
                'dedup_params' => PromoVariantModel::DEFAULT_DEDUP_PARAMS,
            ]);
        } catch (\Exception $e) {
            Log::error('获取配置选项失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取配置选项失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新模板状态
     * PUT /api/merchant/promo-template/:id/status
     */
    public function updateStatus(Request $request)
    {
        try {
            $id = (int)$request->param('id');
            $status = (int)$request->put('status', 1);

            if (!$id) {
                return $this->error('模板ID不能为空', 400);
            }

            $template = PromoTemplateModel::find($id);

            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权操作此模板', 403);
            }

            if ($status === 1) {
                $template->enable();
            } else {
                $template->disable();
            }

            return $this->success(null, '状态更新成功');
        } catch (\Exception $e) {
            Log::error('更新状态失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('更新状态失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 查询变体生成任务状态
     * GET /api/merchant/promo-template/task-status
     */
    public function taskStatus(Request $request)
    {
        try {
            $taskId = $request->get('task_id', '');

            if (empty($taskId)) {
                return $this->error('任务ID不能为空', 400);
            }

            $taskData = Cache::get("promo_task:{$taskId}");

            if (!$taskData) {
                return $this->error('任务不存在或已过期', 404);
            }

            return $this->success($taskData);
        } catch (\Exception $e) {
            Log::error('查询任务状态失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('查询任务状态失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新推广任务缓存（统一写入）
     */
    private function updatePromoTaskCache(string $taskId, string $status, array $extra = []): void
    {
        $data = array_merge([
            'task_id' => $taskId,
            'status' => $status,
            'create_time' => date('Y-m-d H:i:s'),
        ], $extra);

        Cache::set("promo_task:{$taskId}", $data, 3600);
    }
}
