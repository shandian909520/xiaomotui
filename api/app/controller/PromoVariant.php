<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Request;
use think\facade\Log;
use app\model\PromoVariant as PromoVariantModel;
use app\model\PromoTemplate as PromoTemplateModel;
use app\controller\traits\AdminAccessibleTrait;

/**
 * 视频变体控制器
 */
class PromoVariant extends BaseController
{
    use AdminAccessibleTrait;

    /**
     * 当前商家ID
     */
    protected ?int $merchantId = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->merchantId = $this->request->merchantId ?? null;
    }

    /**
     * 获取变体列表
     * GET /api/merchant/promo-variant/list
     */
    public function list(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $templateId = (int)$request->get('template_id', 0);
            $page = (int)$request->get('page', 1);
            $pageSize = (int)$request->get('page_size', 20);
            $status = $request->get('status', -1);

            // 验证分页参数
            $page = max(1, $page);
            $pageSize = min(100, max(1, $pageSize));

            $query = PromoVariantModel::when($targetMerchantId, function($q) use ($targetMerchantId) {
                $q->where('merchant_id', $targetMerchantId);
            });

            if ($templateId > 0) {
                $query->where('template_id', $templateId);
            }

            if ($status >= 0) {
                $query->where('status', (int)$status);
            }

            $total = $query->count();
            $list = $query->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            // 附加模板信息
            $templateIds = array_unique(array_column($list, 'template_id'));
            $templates = [];
            if (!empty($templateIds)) {
                $templates = PromoTemplateModel::whereIn('id', $templateIds)
                    ->column('name', 'id');
            }

            foreach ($list as &$item) {
                $item['template_name'] = $templates[$item['template_id']] ?? '未知模板';
                // 隐藏敏感参数
                if (isset($item['params_json'])) {
                    unset($item['params_json']['random_seed']);
                    unset($item['params_json']['timestamp']);
                }
            }

            return $this->paginate($list, $total, $page, $pageSize);
        } catch (\Exception $e) {
            Log::error('获取变体列表失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取列表失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取变体详情
     * GET /api/merchant/promo-variant/:id
     */
    public function detail(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('变体ID不能为空', 400);
            }

            $variant = PromoVariantModel::find($id);

            if (!$variant) {
                return $this->error('变体不存在', 404);
            }

            // 验证权限
            if (!$this->checkMerchantAccess($variant->merchant_id)) {
                return $this->error('无权访问此变体', 403);
            }

            $data = $variant->toArray();
            $data['template'] = $variant->template ? $variant->template->toArray() : null;

            // 隐藏敏感参数
            if (isset($data['params_json'])) {
                unset($data['params_json']['random_seed']);
                unset($data['params_json']['timestamp']);
            }

            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取变体详情失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取详情失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取下一个可用变体
     * GET /api/merchant/promo-variant/next
     */
    public function getNext(Request $request)
    {
        try {
            $templateId = (int)$request->get('template_id', 0);
            $strategy = $request->get('strategy', PromoVariantModel::STRATEGY_ROUND_ROBIN);

            if ($templateId <= 0) {
                return $this->error('模板ID不能为空', 400);
            }

            // 验证策略
            $validStrategies = [
                PromoVariantModel::STRATEGY_ROUND_ROBIN,
                PromoVariantModel::STRATEGY_RANDOM,
                PromoVariantModel::STRATEGY_LEAST_USED,
            ];
            if (!in_array($strategy, $validStrategies)) {
                $strategy = PromoVariantModel::STRATEGY_ROUND_ROBIN;
            }

            // 获取模板
            $template = PromoTemplateModel::find($templateId);
            if (!$template) {
                return $this->error('模板不存在', 404);
            }

            // 如果有商家信息，验证权限
            if (!$this->checkMerchantAccess($template->merchant_id)) {
                return $this->error('无权访问此模板', 403);
            }

            // 获取下一个变体
            $variant = PromoVariantModel::getNextByStrategy($templateId, $strategy);

            if (!$variant) {
                return $this->error('没有可用的变体', 404);
            }

            Log::info('获取下一个可用变体', [
                'variant_id' => $variant->id,
                'template_id' => $templateId,
                'strategy' => $strategy,
            ]);

            return $this->success([
                'variant_id' => $variant->id,
                'file_url' => $variant->file_url,
                'file_url_full' => $variant->file_url_full,
                'duration' => $variant->duration,
                'use_count' => $variant->use_count,
                'template_name' => $template->name,
            ]);
        } catch (\Exception $e) {
            Log::error('获取下一个变体失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 记录变体使用
     * POST /api/merchant/promo-variant/:id/record-use
     */
    public function recordUse(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('变体ID不能为空', 400);
            }

            $variant = PromoVariantModel::find($id);

            if (!$variant) {
                return $this->error('变体不存在', 404);
            }

            // 如果有商家信息，验证权限
            if (!$this->checkMerchantAccess($variant->merchant_id)) {
                return $this->error('无权操作此变体', 403);
            }

            if (!$variant->isEnabled()) {
                return $this->error('变体已禁用', 400);
            }

            // 记录使用
            $variant->recordUse();

            Log::info('记录变体使用', [
                'variant_id' => $id,
                'use_count' => $variant->use_count,
            ]);

            return $this->success([
                'variant_id' => $id,
                'use_count' => $variant->use_count,
            ], '使用记录成功');
        } catch (\Exception $e) {
            Log::error('记录使用失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('记录失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新变体状态
     * PUT /api/merchant/promo-variant/:id/status
     */
    public function updateStatus(Request $request)
    {
        try {
            $id = (int)$request->param('id');
            $status = (int)$request->put('status', 1);

            if (!$id) {
                return $this->error('变体ID不能为空', 400);
            }

            $variant = PromoVariantModel::find($id);

            if (!$variant) {
                return $this->error('变体不存在', 404);
            }

            if (!$this->checkMerchantAccess($variant->merchant_id)) {
                return $this->error('无权操作此变体', 403);
            }

            if ($status === 1) {
                $variant->enable();
            } else {
                $variant->disable();
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
     * 删除变体
     * DELETE /api/merchant/promo-variant/:id
     */
    public function delete(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('变体ID不能为空', 400);
            }

            $variant = PromoVariantModel::find($id);

            if (!$variant) {
                return $this->error('变体不存在', 404);
            }

            if (!$this->checkMerchantAccess($variant->merchant_id)) {
                return $this->error('无权操作此变体', 403);
            }

            // 删除物理文件
            if ($variant->file_url) {
                $filePath = public_path() . ltrim($variant->file_url, '/');
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // 删除数据库记录
            $variant->delete();

            Log::info('删除视频变体成功', [
                'variant_id' => $id,
                'merchant_id' => $variant->merchant_id,
            ]);

            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            Log::error('删除变体失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('删除失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量删除变体
     * POST /api/merchant/promo-variant/batch-delete
     */
    public function batchDelete(Request $request)
    {
        try {
            $ids = $request->post('ids', []);

            if (empty($ids) || !is_array($ids)) {
                return $this->error('请选择要删除的变体', 400);
            }

            // 限制批量删除数量
            $ids = array_slice($ids, 0, 50);

            $query = PromoVariantModel::whereIn('id', $ids);
            // 非admin只能删除自己的
            if ($this->merchantId && !$this->isAdmin()) {
                $query->where('merchant_id', $this->merchantId);
            }

            $variants = $query->select();

            $deletedCount = 0;
            foreach ($variants as $variant) {
                // 删除物理文件
                if ($variant->file_url) {
                    $filePath = public_path() . ltrim($variant->file_url, '/');
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                $variant->delete();
                $deletedCount++;
            }

            Log::info('批量删除视频变体成功', [
                'ids' => $ids,
                'deleted_count' => $deletedCount,
            ]);

            return $this->success([
                'deleted_count' => $deletedCount,
            ], "成功删除 {$deletedCount} 个变体");
        } catch (\Exception $e) {
            Log::error('批量删除变体失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('批量删除失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取变体统计
     * GET /api/merchant/promo-variant/stats
     */
    public function stats(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $templateId = (int)$request->get('template_id', 0);

            $query = PromoVariantModel::when($targetMerchantId, function($q) use ($targetMerchantId) {
                $q->where('merchant_id', $targetMerchantId);
            });

            if ($templateId > 0) {
                $query->where('template_id', $templateId);
            }

            $total = (clone $query)->count();
            $enabled = (clone $query)->where('status', PromoVariantModel::STATUS_ENABLED)->count();
            $disabled = (clone $query)->where('status', PromoVariantModel::STATUS_DISABLED)->count();
            $totalUseCount = $query->sum('use_count');

            // 按模板分组统计
            $byTemplateQuery = PromoVariantModel::when($targetMerchantId, function($q) use ($targetMerchantId) {
                $q->where('merchant_id', $targetMerchantId);
            });
            $byTemplate = $byTemplateQuery->field('template_id, COUNT(*) as count, SUM(use_count) as use_count')
                ->group('template_id')
                ->select()
                ->toArray();

            // 获取模板名称
            $templateIds = array_column($byTemplate, 'template_id');
            $templates = [];
            if (!empty($templateIds)) {
                $templates = PromoTemplateModel::whereIn('id', $templateIds)
                    ->column('name', 'id');
            }

            foreach ($byTemplate as &$item) {
                $item['template_name'] = $templates[$item['template_id']] ?? '未知模板';
            }

            return $this->success([
                'total' => $total,
                'enabled' => $enabled,
                'disabled' => $disabled,
                'total_use_count' => (int)$totalUseCount,
                'by_template' => $byTemplate,
            ]);
        } catch (\Exception $e) {
            Log::error('获取变体统计失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取统计失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取轮换策略选项
     * GET /api/merchant/promo-variant/strategies
     */
    public function strategies(Request $request)
    {
        try {
            return $this->success([
                'strategies' => PromoVariantModel::getStrategyOptions(),
                'statuses' => PromoVariantModel::getStatusOptions(),
            ]);
        } catch (\Exception $e) {
            Log::error('获取策略选项失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取策略选项失败: ' . $e->getMessage(), 500);
        }
    }
}
