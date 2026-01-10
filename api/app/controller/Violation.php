<?php
declare(strict_types=1);

namespace app\controller;

use app\service\ViolationHandlerService;
use app\service\MerchantNotificationService;
use think\facade\Log;
use think\Response;

/**
 * 违规内容处理控制器
 */
class Violation extends BaseController
{
    protected ViolationHandlerService $violationService;
    protected MerchantNotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->violationService = new ViolationHandlerService();
        $this->notificationService = new MerchantNotificationService();
    }

    /**
     * 检测素材内容
     * POST /api/violation/check
     */
    public function check(): Response
    {
        try {
            $materialId = $this->request->post('material_id', 0);
            $checkType = $this->request->post('check_type', 'MANUAL');

            if (!$materialId) {
                return $this->error('素材ID不能为空');
            }

            $result = $this->violationService->checkContent((int)$materialId, $checkType);

            return $this->success($result, '检测完成');
        } catch (\Exception $e) {
            Log::error('素材检测失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 举报素材违规
     * POST /api/violation/report
     */
    public function report(): Response
    {
        try {
            $materialId = $this->request->post('material_id', 0);
            $violationType = $this->request->post('violation_type', '');
            $reason = $this->request->post('reason', '');
            $evidence = $this->request->post('evidence', []);

            if (!$materialId || !$violationType || !$reason) {
                return $this->error('参数不完整');
            }

            $userId = $this->request->userId ?? 0;

            // 构建违规信息
            $violations = [[
                'type' => $violationType,
                'description' => $reason,
                'severity' => 'MEDIUM'
            ]];

            $additionalData = [
                'reporter_id' => $userId,
                'report_reason' => $reason,
                'evidence_urls' => $evidence,
                'confidence' => 0.7
            ];

            $violationId = $this->violationService->handleViolation(
                (int)$materialId,
                $violations,
                'MEDIUM',
                'REPORT',
                $userId,
                $additionalData
            );

            return $this->success([
                'violation_id' => $violationId
            ], '举报成功，我们会尽快处理');
        } catch (\Exception $e) {
            Log::error('举报失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取违规历史记录
     * GET /api/violation/history
     */
    public function history(): Response
    {
        try {
            $params = [
                'merchant_id' => $this->request->merchantId ?? null,
                'material_id' => $this->request->get('material_id'),
                'status' => $this->request->get('status'),
                'violation_type' => $this->request->get('violation_type'),
                'severity' => $this->request->get('severity'),
                'start_date' => $this->request->get('start_date'),
                'end_date' => $this->request->get('end_date'),
                'page' => $this->request->get('page', 1),
                'limit' => $this->request->get('limit', 20)
            ];

            $result = $this->violationService->getViolationHistory($params);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取违规历史失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 提交申诉
     * POST /api/violation/appeal
     */
    public function appeal(): Response
    {
        try {
            $violationId = $this->request->post('violation_id', 0);
            $reason = $this->request->post('reason', '');
            $evidence = $this->request->post('evidence', []);
            $contact = [
                'phone' => $this->request->post('contact_phone', ''),
                'email' => $this->request->post('contact_email', '')
            ];

            if (!$violationId || !$reason) {
                return $this->error('参数不完整');
            }

            $merchantId = $this->request->merchantId ?? 0;
            if (!$merchantId) {
                return $this->error('商家信息错误');
            }

            $appealId = $this->violationService->submitAppeal(
                (int)$violationId,
                (int)$merchantId,
                $reason,
                $evidence,
                $contact
            );

            return $this->success([
                'appeal_id' => $appealId
            ], '申诉提交成功，我们会在3个工作日内处理');
        } catch (\Exception $e) {
            Log::error('申诉提交失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取申诉列表
     * GET /api/violation/appeals
     */
    public function appeals(): Response
    {
        try {
            $params = [
                'merchant_id' => $this->request->merchantId ?? null,
                'status' => $this->request->get('status'),
                'priority' => $this->request->get('priority'),
                'page' => $this->request->get('page', 1),
                'limit' => $this->request->get('limit', 20)
            ];

            $result = $this->violationService->getAppealList($params);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取申诉列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取申诉详情
     * GET /api/violation/appeal/:id
     */
    public function appealDetail(): Response
    {
        try {
            $appealId = $this->request->param('id', 0);
            if (!$appealId) {
                return $this->error('申诉ID不能为空');
            }

            $appeal = \think\facade\Db::name('violation_appeals')
                ->alias('a')
                ->leftJoin('content_violations v', 'a.violation_id = v.id')
                ->leftJoin('content_materials m', 'a.material_id = m.id')
                ->where('a.id', $appealId)
                ->field('a.*, v.violation_type, v.severity, v.description as violation_description,
                         m.name as material_name, m.type as material_type, m.file_url')
                ->find();

            if (!$appeal) {
                return $this->error('申诉记录不存在');
            }

            // 验证权限
            $merchantId = $this->request->merchantId ?? 0;
            if ($merchantId && $appeal['merchant_id'] != $merchantId) {
                return $this->error('无权查看此申诉记录');
            }

            return $this->success($appeal);
        } catch (\Exception $e) {
            Log::error('获取申诉详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取商家通知列表
     * GET /api/violation/notifications
     */
    public function notifications(): Response
    {
        try {
            $merchantId = $this->request->merchantId ?? 0;
            if (!$merchantId) {
                return $this->error('商家信息错误');
            }

            $params = [
                'type' => $this->request->get('type'),
                'status' => $this->request->get('status'),
                'unread_only' => $this->request->get('unread_only', false),
                'page' => $this->request->get('page', 1),
                'limit' => $this->request->get('limit', 20)
            ];

            $result = $this->notificationService->getNotificationList((int)$merchantId, $params);

            // 添加未读数量
            $result['unread_count'] = $this->notificationService->getUnreadCount((int)$merchantId);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取通知列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 标记通知为已读
     * PUT /api/violation/notification/:id/read
     */
    public function markNotificationRead(): Response
    {
        try {
            $notificationId = $this->request->param('id', 0);
            if (!$notificationId) {
                return $this->error('通知ID不能为空');
            }

            $merchantId = $this->request->merchantId ?? 0;
            if (!$merchantId) {
                return $this->error('商家信息错误');
            }

            $result = $this->notificationService->markAsRead((int)$notificationId, (int)$merchantId);

            if ($result) {
                return $this->success(null, '标记成功');
            } else {
                return $this->error('标记失败');
            }
        } catch (\Exception $e) {
            Log::error('标记通知已读失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取违规统计
     * GET /api/violation/statistics
     */
    public function statistics(): Response
    {
        try {
            $merchantId = $this->request->merchantId ?? null;
            $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->request->get('end_date', date('Y-m-d'));

            $query = \think\facade\Db::name('content_violations');

            if ($merchantId) {
                $query->where('merchant_id', $merchantId);
            }

            $query->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59');

            // 总数
            $total = $query->count();

            // 按状态统计
            $statusStats = $query->field('status, COUNT(*) as count')
                ->group('status')
                ->select()
                ->toArray();

            // 按类型统计
            $typeStats = $query->field('violation_type, COUNT(*) as count')
                ->group('violation_type')
                ->select()
                ->toArray();

            // 按严重程度统计
            $severityStats = $query->field('severity, COUNT(*) as count')
                ->group('severity')
                ->select()
                ->toArray();

            // 趋势数据（按日期）
            $trendData = $query->field('DATE(create_time) as date, COUNT(*) as count')
                ->group('DATE(create_time)')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            return $this->success([
                'total' => $total,
                'status_stats' => $statusStats,
                'type_stats' => $typeStats,
                'severity_stats' => $severityStats,
                'trend_data' => $trendData,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取违规统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    // ========== 管理员接口 ==========

    /**
     * 审核违规举报（管理员）
     * PUT /api/admin/violation/:id/review
     */
    public function adminReview(): Response
    {
        try {
            $violationId = $this->request->param('id', 0);
            $confirmed = $this->request->post('confirmed', false);
            $comment = $this->request->post('comment', '');

            if (!$violationId) {
                return $this->error('违规记录ID不能为空');
            }

            $adminId = $this->request->userId ?? 0;

            // 更新违规记录状态
            $status = $confirmed ? 'CONFIRMED' : 'DISMISSED';
            \think\facade\Db::name('content_violations')
                ->where('id', $violationId)
                ->update([
                    'status' => $status,
                    'resolve_time' => date('Y-m-d H:i:s'),
                    'resolver_id' => $adminId,
                    'resolve_comment' => $comment
                ]);

            // 如果确认违规但之前未下架，现在下架
            if ($confirmed) {
                $violation = \think\facade\Db::name('content_violations')
                    ->where('id', $violationId)
                    ->find();

                if ($violation && $violation['action_taken'] !== 'DISABLED') {
                    $this->violationService->disableMaterial(
                        (int)$violation['material_id'],
                        "管理员确认违规: {$comment}"
                    );
                }
            } else {
                // 如果误判，恢复素材
                $violation = \think\facade\Db::name('content_violations')
                    ->where('id', $violationId)
                    ->find();

                if ($violation && $violation['action_taken'] === 'DISABLED') {
                    $this->violationService->enableMaterial(
                        (int)$violation['material_id'],
                        "管理员审核通过: {$comment}"
                    );
                }
            }

            return $this->success(null, '审核完成');
        } catch (\Exception $e) {
            Log::error('违规审核失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 处理申诉（管理员）
     * PUT /api/admin/appeal/:id/process
     */
    public function adminProcessAppeal(): Response
    {
        try {
            $appealId = $this->request->param('id', 0);
            $approved = $this->request->post('approved', false);
            $comment = $this->request->post('comment', '');

            if (!$appealId || !$comment) {
                return $this->error('参数不完整');
            }

            $reviewerId = $this->request->userId ?? 0;

            $result = $this->violationService->processAppeal(
                (int)$appealId,
                (bool)$approved,
                $comment,
                (int)$reviewerId
            );

            if ($result) {
                return $this->success(null, '申诉处理完成');
            } else {
                return $this->error('处理失败');
            }
        } catch (\Exception $e) {
            Log::error('申诉处理失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取待处理的申诉列表（管理员）
     * GET /api/admin/appeals/pending
     */
    public function adminPendingAppeals(): Response
    {
        try {
            $params = [
                'status' => 'PENDING',
                'page' => $this->request->get('page', 1),
                'limit' => $this->request->get('limit', 20)
            ];

            $result = $this->violationService->getAppealList($params);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取待处理申诉失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量处理违规素材（管理员）
     * POST /api/admin/violation/batch-disable
     */
    public function adminBatchDisable(): Response
    {
        try {
            $materialIds = $this->request->post('material_ids', []);
            $reason = $this->request->post('reason', '批量下架');

            if (empty($materialIds)) {
                return $this->error('请选择要处理的素材');
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($materialIds as $materialId) {
                try {
                    $this->violationService->disableMaterial((int)$materialId, $reason);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('批量下架失败', [
                        'material_id' => $materialId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->success([
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'total' => count($materialIds)
            ], "批量处理完成，成功{$successCount}个，失败{$failCount}个");
        } catch (\Exception $e) {
            Log::error('批量下架失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }
}
