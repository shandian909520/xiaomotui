<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * 违规内容处理服务
 * 负责违规检测、自动下架、通知商家、申诉处理等
 */
class ViolationHandlerService
{
    protected ContentModerationService $moderationService;
    protected MerchantNotificationService $notificationService;

    public function __construct()
    {
        $this->moderationService = new ContentModerationService();
        $this->notificationService = new MerchantNotificationService();
    }

    /**
     * 检测内容是否违规
     *
     * @param int $materialId 素材ID
     * @param string $checkType 检测类型: AUTO|MANUAL|REPORT
     * @return array [
     *   'has_violation' => bool,
     *   'violations' => array,
     *   'severity' => string,
     *   'auto_action' => string
     * ]
     */
    public function checkContent(int $materialId, string $checkType = 'AUTO'): array
    {
        // 获取素材信息
        $material = Db::name('content_materials')->where('id', $materialId)->find();

        if (!$material) {
            throw new Exception('素材不存在');
        }

        // 执行内容审核
        $checkResult = $this->moderationService->checkMaterial($material);

        // 根据检测结果决定自动处理动作
        $autoAction = 'NONE';
        if ($checkResult['has_violation']) {
            $autoAction = $this->determineAutoAction(
                $checkResult['severity'],
                $checkResult['confidence']
            );
        }

        // 记录检测结果
        Log::info('内容违规检测', [
            'material_id' => $materialId,
            'check_type' => $checkType,
            'has_violation' => $checkResult['has_violation'],
            'severity' => $checkResult['severity'] ?? null,
            'auto_action' => $autoAction
        ]);

        return [
            'has_violation' => $checkResult['has_violation'],
            'violations' => $checkResult['violations'] ?? [],
            'severity' => $checkResult['severity'] ?? ContentModerationService::SEVERITY_LOW,
            'confidence' => $checkResult['confidence'] ?? 0,
            'auto_action' => $autoAction,
            'material_info' => [
                'id' => $material['id'],
                'name' => $material['name'],
                'type' => $material['type'],
                'merchant_id' => $material['creator_id']
            ]
        ];
    }

    /**
     * 处理违规内容
     *
     * @param int $materialId 素材ID
     * @param array $violations 违规信息
     * @param string $severity 严重程度
     * @param string $detectionMethod 检测方式
     * @param int|null $detectorId 检测人ID
     * @param array $additionalData 额外数据
     * @return int 违规记录ID
     */
    public function handleViolation(
        int $materialId,
        array $violations,
        string $severity,
        string $detectionMethod = 'AUTO',
        ?int $detectorId = null,
        array $additionalData = []
    ): int {
        Db::startTrans();
        try {
            // 获取素材信息
            $material = Db::name('content_materials')->where('id', $materialId)->find();
            if (!$material) {
                throw new Exception('素材不存在');
            }

            // 确定违规类型
            $violationType = $this->determineViolationType($violations);

            // 确定处理动作
            $action = $this->determineAutoAction($severity, $additionalData['confidence'] ?? 0.8);

            // 创建违规记录
            $violationData = [
                'material_id' => $materialId,
                'merchant_id' => $material['creator_id'] ?? 0,
                'violation_type' => $violationType,
                'severity' => $severity,
                'title' => $this->generateViolationTitle($violationType, $severity),
                'description' => $this->generateViolationDescription($violations),
                'details' => json_encode([
                    'violations' => $violations,
                    'confidence' => $additionalData['confidence'] ?? 0,
                    'material_name' => $material['name'],
                    'material_type' => $material['type'],
                    'check_time' => date('Y-m-d H:i:s')
                ]),
                'detection_method' => $detectionMethod,
                'detector_id' => $detectorId,
                'reporter_id' => $additionalData['reporter_id'] ?? null,
                'report_reason' => $additionalData['report_reason'] ?? null,
                'action_taken' => $action,
                'status' => 'PENDING',
                'auto_disable' => $action === 'DISABLED' ? 1 : 0,
                'notification_sent' => 0,
                'create_time' => date('Y-m-d H:i:s')
            ];

            $violationId = Db::name('content_violations')->insertGetId($violationData);

            // 如果需要自动下架
            if ($action === 'DISABLED') {
                $this->disableMaterial($materialId, "违规内容自动下架: {$violationType}");
            }

            // 发送通知
            $this->notifyMerchant(
                (int)$material['creator_id'],
                $materialId,
                [
                    'violation_id' => $violationId,
                    'violation_type' => $violationType,
                    'severity' => $severity,
                    'action' => $action,
                    'violations' => $violations
                ]
            );

            // 更新违规记录为已通知
            Db::name('content_violations')
                ->where('id', $violationId)
                ->update([
                    'notification_sent' => 1,
                    'notification_time' => date('Y-m-d H:i:s')
                ]);

            // 检查商家违规次数，必要时加入黑名单
            $this->checkMerchantViolationCount((int)$material['creator_id']);

            Db::commit();

            Log::info('违规内容处理完成', [
                'violation_id' => $violationId,
                'material_id' => $materialId,
                'action' => $action
            ]);

            return $violationId;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('违规内容处理失败', [
                'material_id' => $materialId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 禁用素材
     *
     * @param int $materialId 素材ID
     * @param string $reason 原因
     * @return bool
     */
    public function disableMaterial(int $materialId, string $reason): bool
    {
        try {
            $result = Db::name('content_materials')
                ->where('id', $materialId)
                ->update([
                    'status' => 0,
                    'review_status' => 'REJECTED',
                    'rejection_reason' => $reason,
                    'review_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ]);

            Log::info('素材已禁用', [
                'material_id' => $materialId,
                'reason' => $reason
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            Log::error('素材禁用失败', [
                'material_id' => $materialId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 启用素材（申诉通过后）
     *
     * @param int $materialId 素材ID
     * @param string $reason 原因
     * @return bool
     */
    public function enableMaterial(int $materialId, string $reason): bool
    {
        try {
            $result = Db::name('content_materials')
                ->where('id', $materialId)
                ->update([
                    'status' => 1,
                    'review_status' => 'APPROVED',
                    'rejection_reason' => null,
                    'review_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ]);

            Log::info('素材已启用', [
                'material_id' => $materialId,
                'reason' => $reason
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            Log::error('素材启用失败', [
                'material_id' => $materialId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 通知商家
     *
     * @param int $merchantId 商家ID
     * @param int $materialId 素材ID
     * @param array $violationInfo 违规信息
     */
    public function notifyMerchant(int $merchantId, int $materialId, array $violationInfo): void
    {
        try {
            $material = Db::name('content_materials')->where('id', $materialId)->find();
            if (!$material) {
                return;
            }

            $this->notificationService->sendViolationNotification(
                $merchantId,
                $materialId,
                $material['name'] ?? "素材#{$materialId}",
                $violationInfo
            );
        } catch (\Exception $e) {
            Log::error('商家通知发送失败', [
                'merchant_id' => $merchantId,
                'material_id' => $materialId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 提交申诉
     *
     * @param int $violationId 违规记录ID
     * @param int $merchantId 商家ID
     * @param string $reason 申诉理由
     * @param array $evidence 申诉证据
     * @param array $contact 联系方式
     * @return int 申诉ID
     */
    public function submitAppeal(
        int $violationId,
        int $merchantId,
        string $reason,
        array $evidence = [],
        array $contact = []
    ): int {
        Db::startTrans();
        try {
            // 获取违规记录
            $violation = Db::name('content_violations')->where('id', $violationId)->find();
            if (!$violation) {
                throw new Exception('违规记录不存在');
            }

            // 验证商家权限
            if ($violation['merchant_id'] != $merchantId) {
                throw new Exception('无权申诉此违规记录');
            }

            // 检查是否已经申诉过
            $existingAppeal = Db::name('violation_appeals')
                ->where('violation_id', $violationId)
                ->where('status', 'in', ['PENDING', 'REVIEWING'])
                ->find();

            if ($existingAppeal) {
                throw new Exception('该违规记录已经提交过申诉，请等待审核结果');
            }

            // 创建申诉记录
            $appealData = [
                'violation_id' => $violationId,
                'merchant_id' => $merchantId,
                'material_id' => $violation['material_id'],
                'reason' => $reason,
                'evidence' => json_encode($evidence),
                'contact_phone' => $contact['phone'] ?? null,
                'contact_email' => $contact['email'] ?? null,
                'status' => 'PENDING',
                'priority' => $violation['severity'] === 'HIGH' ? 1 : 0,
                'submit_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $appealId = Db::name('violation_appeals')->insertGetId($appealData);

            // 更新违规记录状态
            Db::name('content_violations')
                ->where('id', $violationId)
                ->update([
                    'status' => 'APPEALED',
                    'appeal_id' => $appealId
                ]);

            // 通知管理员有新申诉
            $this->notificationService->notifyAdminNewAppeal($appealId, $merchantId);

            Db::commit();

            Log::info('申诉提交成功', [
                'appeal_id' => $appealId,
                'violation_id' => $violationId,
                'merchant_id' => $merchantId
            ]);

            return $appealId;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('申诉提交失败', [
                'violation_id' => $violationId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 处理申诉
     *
     * @param int $appealId 申诉ID
     * @param bool $approved 是否批准
     * @param string $adminComment 管理员意见
     * @param int $reviewerId 审核人ID
     * @return bool
     */
    public function processAppeal(
        int $appealId,
        bool $approved,
        string $adminComment,
        int $reviewerId
    ): bool {
        Db::startTrans();
        try {
            // 获取申诉记录
            $appeal = Db::name('violation_appeals')->where('id', $appealId)->find();
            if (!$appeal) {
                throw new Exception('申诉记录不存在');
            }

            if ($appeal['status'] !== 'PENDING' && $appeal['status'] !== 'REVIEWING') {
                throw new Exception('该申诉已经处理过了');
            }

            // 更新申诉状态
            $appealStatus = $approved ? 'APPROVED' : 'REJECTED';
            Db::name('violation_appeals')
                ->where('id', $appealId)
                ->update([
                    'status' => $appealStatus,
                    'reviewer_id' => $reviewerId,
                    'review_comment' => $adminComment,
                    'review_result' => json_encode([
                        'approved' => $approved,
                        'comment' => $adminComment,
                        'review_time' => date('Y-m-d H:i:s')
                    ]),
                    'review_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ]);

            // 更新违规记录状态
            $violationStatus = $approved ? 'DISMISSED' : 'CONFIRMED';
            Db::name('content_violations')
                ->where('id', $appeal['violation_id'])
                ->update([
                    'status' => $violationStatus,
                    'resolve_time' => date('Y-m-d H:i:s'),
                    'resolver_id' => $reviewerId,
                    'resolve_comment' => $adminComment
                ]);

            // 如果申诉通过，恢复素材
            if ($approved) {
                $this->enableMaterial(
                    (int)$appeal['material_id'],
                    "申诉通过，恢复素材: {$adminComment}"
                );
            }

            // 通知商家申诉结果
            $this->notificationService->sendAppealResultNotification(
                (int)$appeal['merchant_id'],
                $appealId,
                $approved,
                $adminComment
            );

            Db::commit();

            Log::info('申诉处理完成', [
                'appeal_id' => $appealId,
                'approved' => $approved,
                'reviewer_id' => $reviewerId
            ]);

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('申诉处理失败', [
                'appeal_id' => $appealId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取违规历史
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getViolationHistory(array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Db::name('content_violations')->alias('v');

        // 商家筛选
        if (!empty($params['merchant_id'])) {
            $query->where('v.merchant_id', $params['merchant_id']);
        }

        // 素材筛选
        if (!empty($params['material_id'])) {
            $query->where('v.material_id', $params['material_id']);
        }

        // 状态筛选
        if (!empty($params['status'])) {
            $query->where('v.status', $params['status']);
        }

        // 违规类型筛选
        if (!empty($params['violation_type'])) {
            $query->where('v.violation_type', $params['violation_type']);
        }

        // 严重程度筛选
        if (!empty($params['severity'])) {
            $query->where('v.severity', $params['severity']);
        }

        // 时间范围筛选
        if (!empty($params['start_date'])) {
            $query->where('v.create_time', '>=', $params['start_date'] . ' 00:00:00');
        }
        if (!empty($params['end_date'])) {
            $query->where('v.create_time', '<=', $params['end_date'] . ' 23:59:59');
        }

        // 关联素材表
        $query->leftJoin('content_materials m', 'v.material_id = m.id')
            ->field('v.*, m.name as material_name, m.type as material_type');

        // 排序
        $query->order('v.create_time', 'desc');

        // 分页
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 获取申诉列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getAppealList(array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Db::name('violation_appeals')->alias('a');

        // 商家筛选
        if (!empty($params['merchant_id'])) {
            $query->where('a.merchant_id', $params['merchant_id']);
        }

        // 状态筛选
        if (!empty($params['status'])) {
            $query->where('a.status', $params['status']);
        }

        // 优先级筛选
        if (isset($params['priority'])) {
            $query->where('a.priority', $params['priority']);
        }

        // 关联违规记录和素材
        $query->leftJoin('content_violations v', 'a.violation_id = v.id')
            ->leftJoin('content_materials m', 'a.material_id = m.id')
            ->field('a.*, v.violation_type, v.severity, m.name as material_name, m.type as material_type');

        // 排序（优先级高的在前，然后按时间）
        $query->order('a.priority', 'desc')->order('a.submit_time', 'desc');

        // 分页
        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 确定违规类型
     *
     * @param array $violations 违规列表
     * @return string
     */
    protected function determineViolationType(array $violations): string
    {
        if (empty($violations)) {
            return ContentModerationService::VIOLATION_OTHER;
        }

        // 按严重程度排序，返回最严重的违规类型
        $severityOrder = [
            ContentModerationService::VIOLATION_ILLEGAL => 10,
            ContentModerationService::VIOLATION_PORN => 9,
            ContentModerationService::VIOLATION_VIOLENCE => 8,
            ContentModerationService::VIOLATION_FRAUD => 7,
            ContentModerationService::VIOLATION_SENSITIVE => 6,
            ContentModerationService::VIOLATION_COPYRIGHT => 5,
            ContentModerationService::VIOLATION_AD => 4,
            ContentModerationService::VIOLATION_SPAM => 3,
            ContentModerationService::VIOLATION_OTHER => 1
        ];

        $maxSeverity = 0;
        $violationType = ContentModerationService::VIOLATION_OTHER;

        foreach ($violations as $violation) {
            $type = $violation['type'] ?? ContentModerationService::VIOLATION_OTHER;
            $severity = $severityOrder[$type] ?? 0;

            if ($severity > $maxSeverity) {
                $maxSeverity = $severity;
                $violationType = $type;
            }
        }

        return $violationType;
    }

    /**
     * 确定自动处理动作
     *
     * @param string $severity 严重程度
     * @param float $confidence 置信度
     * @return string
     */
    protected function determineAutoAction(string $severity, float $confidence): string
    {
        // 根据严重程度和置信度决定动作
        if ($severity === ContentModerationService::SEVERITY_HIGH && $confidence >= 0.8) {
            return 'DISABLED'; // 自动下架
        } elseif ($severity === ContentModerationService::SEVERITY_MEDIUM && $confidence >= 0.9) {
            return 'DISABLED'; // 自动下架
        } elseif ($severity === ContentModerationService::SEVERITY_HIGH || $severity === ContentModerationService::SEVERITY_MEDIUM) {
            return 'WARNING'; // 警告
        }

        return 'NONE'; // 不处理
    }

    /**
     * 生成违规标题
     *
     * @param string $violationType 违规类型
     * @param string $severity 严重程度
     * @return string
     */
    protected function generateViolationTitle(string $violationType, string $severity): string
    {
        $typeNames = [
            'SENSITIVE' => '敏感内容',
            'ILLEGAL' => '违法内容',
            'PORN' => '色情内容',
            'VIOLENCE' => '暴力内容',
            'AD' => '广告内容',
            'FRAUD' => '欺诈内容',
            'SPAM' => '垃圾内容',
            'COPYRIGHT' => '版权问题',
            'OTHER' => '违规内容'
        ];

        $severityNames = [
            'HIGH' => '严重',
            'MEDIUM' => '中度',
            'LOW' => '轻微'
        ];

        $typeName = $typeNames[$violationType] ?? '违规内容';
        $severityName = $severityNames[$severity] ?? '';

        return "{$severityName}{$typeName}违规";
    }

    /**
     * 生成违规描述
     *
     * @param array $violations 违规列表
     * @return string
     */
    protected function generateViolationDescription(array $violations): string
    {
        if (empty($violations)) {
            return '检测到违规内容';
        }

        $descriptions = [];
        foreach ($violations as $violation) {
            $descriptions[] = $violation['description'] ?? '违规内容';
        }

        return implode('；', array_unique($descriptions));
    }

    /**
     * 检查商家违规次数
     *
     * @param int $merchantId 商家ID
     */
    protected function checkMerchantViolationCount(int $merchantId): void
    {
        try {
            // 统计最近30天的违规次数
            $count = Db::name('content_violations')
                ->where('merchant_id', $merchantId)
                ->where('status', 'in', ['CONFIRMED', 'PENDING'])
                ->where('create_time', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->count();

            $highSeverityCount = Db::name('content_violations')
                ->where('merchant_id', $merchantId)
                ->where('severity', 'HIGH')
                ->where('status', 'in', ['CONFIRMED', 'PENDING'])
                ->where('create_time', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->count();

            // 根据配置决定是否加入黑名单
            $thresholds = Config::get('moderation.blacklist_thresholds', [
                'total_violations' => 10,
                'high_severity_violations' => 3
            ]);

            if ($highSeverityCount >= $thresholds['high_severity_violations'] ||
                $count >= $thresholds['total_violations']) {
                $this->addToBlacklist(
                    $merchantId,
                    "违规次数过多（总计{$count}次，严重违规{$highSeverityCount}次）",
                    $count,
                    $highSeverityCount >= $thresholds['high_severity_violations'] ? 'HIGH' : 'MEDIUM'
                );
            }
        } catch (\Exception $e) {
            Log::error('检查商家违规次数失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 将商家加入黑名单
     *
     * @param int $merchantId 商家ID
     * @param string $reason 原因
     * @param int $violationCount 违规次数
     * @param string $severityLevel 严重程度
     */
    protected function addToBlacklist(
        int $merchantId,
        string $reason,
        int $violationCount,
        string $severityLevel
    ): void {
        try {
            // 检查是否已在黑名单中
            $existing = Db::name('merchant_blacklist')
                ->where('merchant_id', $merchantId)
                ->where('status', 'ACTIVE')
                ->find();

            if ($existing) {
                // 更新黑名单记录
                Db::name('merchant_blacklist')
                    ->where('id', $existing['id'])
                    ->update([
                        'violation_count' => $violationCount,
                        'severity_level' => $severityLevel,
                        'update_time' => date('Y-m-d H:i:s')
                    ]);
            } else {
                // 创建新的黑名单记录
                Db::name('merchant_blacklist')->insert([
                    'merchant_id' => $merchantId,
                    'reason' => $reason,
                    'violation_count' => $violationCount,
                    'severity_level' => $severityLevel,
                    'status' => 'ACTIVE',
                    'start_time' => date('Y-m-d H:i:s'),
                    'expire_time' => date('Y-m-d H:i:s', strtotime('+90 days')), // 默认90天
                    'operator_id' => 0, // 系统自动
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ]);

                // 通知商家
                $this->notificationService->sendBlacklistNotification($merchantId, $reason);
            }

            Log::warning('商家已加入黑名单', [
                'merchant_id' => $merchantId,
                'reason' => $reason,
                'violation_count' => $violationCount
            ]);
        } catch (\Exception $e) {
            Log::error('加入黑名单失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
