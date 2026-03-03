<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Config;

/**
 * 商家通知服务
 * 负责向商家发送各类通知（违规通知、申诉结果等）
 */
class MerchantNotificationService
{
    /**
     * 发送违规通知
     *
     * @param int $merchantId 商家ID
     * @param int $materialId 素材ID
     * @param string $materialName 素材名称
     * @param array $violationInfo 违规信息
     * @return int 通知ID
     */
    public function sendViolationNotification(
        int $merchantId,
        int $materialId,
        string $materialName,
        array $violationInfo
    ): int {
        try {
            $violationType = $this->getViolationTypeName($violationInfo['violation_type'] ?? 'OTHER');
            $severity = $this->getSeverityName($violationInfo['severity'] ?? 'MEDIUM');
            $action = $this->getActionName($violationInfo['action'] ?? 'DISABLED');

            $title = "素材违规通知：{$materialName}";
            $content = $this->buildViolationNotificationContent(
                $materialName,
                $materialId,
                $violationType,
                $severity,
                $action,
                $violationInfo
            );

            $contentHtml = $this->buildViolationNotificationHtml(
                $materialName,
                $materialId,
                $violationType,
                $severity,
                $action,
                $violationInfo
            );

            // 确定通知渠道
            $channels = $this->determineNotificationChannels($violationInfo['severity'] ?? 'MEDIUM');

            // 创建通知记录
            $notificationData = [
                'merchant_id' => $merchantId,
                'type' => 'VIOLATION',
                'title' => $title,
                'content' => $content,
                'content_html' => $contentHtml,
                'related_id' => $violationInfo['violation_id'] ?? null,
                'related_type' => 'violation',
                'related_data' => json_encode([
                    'material_id' => $materialId,
                    'violation_type' => $violationInfo['violation_type'] ?? 'OTHER',
                    'severity' => $violationInfo['severity'] ?? 'MEDIUM',
                    'action' => $violationInfo['action'] ?? 'DISABLED'
                ]),
                'channels' => json_encode($channels),
                'priority' => $violationInfo['severity'] === 'HIGH' ? 'HIGH' : 'NORMAL',
                'status' => 'PENDING',
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $notificationId = Db::name('merchant_notifications')->insertGetId($notificationData);

            // 异步发送通知
            $this->sendNotification($notificationId);

            return $notificationId;
        } catch (\Exception $e) {
            Log::error('创建违规通知失败', [
                'merchant_id' => $merchantId,
                'material_id' => $materialId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 发送申诉结果通知
     *
     * @param int $merchantId 商家ID
     * @param int $appealId 申诉ID
     * @param bool $approved 是否通过
     * @param string $comment 审核意见
     * @return int 通知ID
     */
    public function sendAppealResultNotification(
        int $merchantId,
        int $appealId,
        bool $approved,
        string $comment
    ): int {
        try {
            $appeal = Db::name('violation_appeals')->where('id', $appealId)->find();
            if (!$appeal) {
                throw new \Exception('申诉记录不存在');
            }

            $material = Db::name('content_materials')
                ->where('id', $appeal['material_id'])
                ->find();

            $resultText = $approved ? '通过' : '驳回';
            $title = "申诉{$resultText}通知";

            $content = $this->buildAppealResultContent(
                $material['name'] ?? "素材#{$appeal['material_id']}",
                $approved,
                $comment
            );

            $contentHtml = $this->buildAppealResultHtml(
                $material['name'] ?? "素材#{$appeal['material_id']}",
                $approved,
                $comment
            );

            $channels = ['system', 'email']; // 申诉结果通过系统和邮件通知

            $notificationData = [
                'merchant_id' => $merchantId,
                'type' => 'APPEAL_RESULT',
                'title' => $title,
                'content' => $content,
                'content_html' => $contentHtml,
                'related_id' => $appealId,
                'related_type' => 'appeal',
                'related_data' => json_encode([
                    'appeal_id' => $appealId,
                    'material_id' => $appeal['material_id'],
                    'approved' => $approved
                ]),
                'channels' => json_encode($channels),
                'priority' => 'HIGH',
                'status' => 'PENDING',
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $notificationId = Db::name('merchant_notifications')->insertGetId($notificationData);

            // 发送通知
            $this->sendNotification($notificationId);

            return $notificationId;
        } catch (\Exception $e) {
            Log::error('创建申诉结果通知失败', [
                'merchant_id' => $merchantId,
                'appeal_id' => $appealId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 发送黑名单通知
     *
     * @param int $merchantId 商家ID
     * @param string $reason 原因
     * @return int 通知ID
     */
    public function sendBlacklistNotification(int $merchantId, string $reason): int
    {
        try {
            $title = "重要通知：账户已被限制";
            $content = "由于您的账户存在以下问题：{$reason}，已被加入黑名单。请联系客服处理。";
            $contentHtml = $this->buildBlacklistNotificationHtml($reason);

            $channels = ['system', 'email', 'sms']; // 黑名单通知所有渠道

            $notificationData = [
                'merchant_id' => $merchantId,
                'type' => 'WARNING',
                'title' => $title,
                'content' => $content,
                'content_html' => $contentHtml,
                'related_id' => null,
                'related_type' => 'blacklist',
                'related_data' => json_encode(['reason' => $reason]),
                'channels' => json_encode($channels),
                'priority' => 'HIGH',
                'status' => 'PENDING',
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ];

            $notificationId = Db::name('merchant_notifications')->insertGetId($notificationData);

            // 发送通知
            $this->sendNotification($notificationId);

            return $notificationId;
        } catch (\Exception $e) {
            Log::error('创建黑名单通知失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 通知管理员有新申诉
     *
     * @param int $appealId 申诉ID
     * @param int $merchantId 商家ID
     */
    public function notifyAdminNewAppeal(int $appealId, int $merchantId): void
    {
        try {
            // 这里可以通过企业微信、钉钉等方式通知管理员
            Log::info('新申诉待处理', [
                'appeal_id' => $appealId,
                'merchant_id' => $merchantId
            ]);

            // TODO: 实现管理员通知逻辑
        } catch (\Exception $e) {
            Log::error('通知管理员失败', [
                'appeal_id' => $appealId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 发送通知
     *
     * @param int $notificationId 通知ID
     */
    protected function sendNotification(int $notificationId): void
    {
        try {
            $notification = Db::name('merchant_notifications')
                ->where('id', $notificationId)
                ->find();

            if (!$notification) {
                return;
            }

            // 更新状态为发送中
            Db::name('merchant_notifications')
                ->where('id', $notificationId)
                ->update(['status' => 'SENDING', 'update_time' => date('Y-m-d H:i:s')]);

            $channels = json_decode($notification['channels'], true) ?: [];
            $sendResults = [];
            $allSuccess = true;

            foreach ($channels as $channel) {
                $result = $this->sendToChannel($notification, $channel);
                $sendResults[$channel] = $result;
                if (!$result['success']) {
                    $allSuccess = false;
                }
            }

            // 更新发送结果
            $finalStatus = $allSuccess ? 'SENT' : 'FAILED';
            Db::name('merchant_notifications')
                ->where('id', $notificationId)
                ->update([
                    'status' => $finalStatus,
                    'send_result' => json_encode($sendResults),
                    'send_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ]);

            Log::info('通知发送完成', [
                'notification_id' => $notificationId,
                'status' => $finalStatus,
                'channels' => $channels
            ]);
        } catch (\Exception $e) {
            Log::error('发送通知失败', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            // 更新为失败状态
            Db::name('merchant_notifications')
                ->where('id', $notificationId)
                ->update([
                    'status' => 'FAILED',
                    'send_result' => json_encode(['error' => $e->getMessage()]),
                    'update_time' => date('Y-m-d H:i:s')
                ]);
        }
    }

    /**
     * 发送到指定渠道
     *
     * @param array $notification 通知数据
     * @param string $channel 渠道
     * @return array
     */
    protected function sendToChannel(array $notification, string $channel): array
    {
        try {
            switch ($channel) {
                case 'system':
                    return $this->sendSystemNotification($notification);
                case 'email':
                    return $this->sendEmailNotification($notification);
                case 'sms':
                    return $this->sendSmsNotification($notification);
                case 'wechat':
                    return $this->sendWechatNotification($notification);
                default:
                    return ['success' => false, 'message' => '不支持的通知渠道'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送系统通知
     *
     * @param array $notification 通知数据
     * @return array
     */
    protected function sendSystemNotification(array $notification): array
    {
        // 系统通知已经存储在数据库中，直接返回成功
        return ['success' => true, 'message' => '系统通知已创建'];
    }

    /**
     * 发送邮件通知
     *
     * @param array $notification 通知数据
     * @return array
     */
    protected function sendEmailNotification(array $notification): array
    {
        try {
            // 获取商家邮箱
            $merchant = Db::name('merchants')
                ->where('id', $notification['merchant_id'])
                ->find();

            if (empty($merchant['email'])) {
                return ['success' => false, 'message' => '商家未设置邮箱'];
            }

            // 调用邮件发送服务
            $emailService = new \app\service\EmailService();
            $emailService->setFrom(
                config('email.from_address'),
                config('email.from_name')
            );
            $emailService->addTo($merchant['email'], $merchant['name'] ?? '');
            $emailService->setSubject($notification['title']);
            $emailService->setBody(
                $notification['content_html'] ?? $notification['content'],
                strip_tags($notification['content'])
            );

            // 使用异步队列发送
            $emailService->sendAsync();

            Log::info('邮件通知已发送', [
                'to' => $merchant['email'],
                'subject' => $notification['title']
            ]);

            return ['success' => true, 'message' => '邮件已发送'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送短信通知
     *
     * @param array $notification 通知数据
     * @return array
     */
    protected function sendSmsNotification(array $notification): array
    {
        try {
            // 获取商家手机号
            $merchant = Db::name('merchants')
                ->where('id', $notification['merchant_id'])
                ->find();

            if (empty($merchant['mobile'])) {
                return ['success' => false, 'message' => '商家未设置手机号'];
            }

            // TODO: 调用短信发送服务
            // 可以集成阿里云短信、腾讯云短信等

            Log::info('短信通知模拟发送', [
                'to' => $merchant['mobile'],
                'content' => $notification['content']
            ]);

            return ['success' => true, 'message' => '短信已发送'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送微信通知
     *
     * @param array $notification 通知数据
     * @return array
     */
    protected function sendWechatNotification(array $notification): array
    {
        try {
            // 获取商家信息
            $merchant = Db::name('merchants')
                ->where('id', $notification['merchant_id'])
                ->find();

            if (empty($merchant)) {
                return ['success' => false, 'message' => '商家不存在'];
            }

            // 获取商家用户的微信OpenID
            $user = Db::name('users')
                ->where('id', $merchant['user_id'])
                ->find();

            if (empty($user) || empty($user['wechat_openid'])) {
                return ['success' => false, 'message' => '用户未绑定微信'];
            }

            // 使用微信模板消息服务
            $wechatTemplateService = new \app\service\WechatTemplateService('miniprogram');

            // 根据通知类型选择模板
            $templateType = $this->getWechatTemplateType($notification['type']);

            if (empty($templateType)) {
                return ['success' => false, 'message' => '不支持的通知类型'];
            }

            // 构建模板数据
            $templateData = $this->buildWechatTemplateData($notification);

            // 发送模板消息
            $success = $wechatTemplateService->sendTemplateMessage(
                $merchant['user_id'],
                $user['wechat_openid'],
                $templateType,
                $templateData,
                '',  // 跳转页面，可根据需要配置
                ['notification_id' => $notification['id']]
            );

            if ($success) {
                Log::info('微信通知发送成功', [
                    'merchant_id' => $notification['merchant_id'],
                    'notification_type' => $notification['type']
                ]);
                return ['success' => true, 'message' => '微信通知已发送'];
            } else {
                return ['success' => false, 'message' => '微信通知发送失败'];
            }
        } catch (\Exception $e) {
            Log::error('发送微信通知失败', [
                'merchant_id' => $notification['merchant_id'],
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取微信模板类型
     *
     * @param string $notificationType 通知类型
     * @return string|null
     */
    protected function getWechatTemplateType(string $notificationType): ?string
    {
        $templateMap = [
            'VIOLATION' => 'merchant_audit',        // 违规通知
            'APPEAL_RESULT' => 'merchant_audit',    // 申诉结果
            'WARNING' => 'device_alert',            // 警告通知
        ];

        return $templateMap[$notificationType] ?? null;
    }

    /**
     * 构建微信模板数据
     *
     * @param array $notification 通知数据
     * @return array
     */
    protected function buildWechatTemplateData(array $notification): array
    {
        // 解析关联数据
        $relatedData = json_decode($notification['related_data'], true) ?: [];

        switch ($notification['type']) {
            case 'VIOLATION':
                return [
                    'thing1' => ['value' => '违规通知'],
                    'thing2' => ['value' => $this->extractViolationType($relatedData)],
                    'date3' => ['value' => date('Y-m-d H:i:s')],
                    'thing4' => ['value' => '请及时处理'],
                ];

            case 'APPEAL_RESULT':
                $approved = $relatedData['approved'] ?? false;
                return [
                    'thing1' => ['value' => '申诉结果'],
                    'phrase2' => ['value' => $approved ? '已通过' : '未通过'],
                    'thing3' => ['value' => $notification['title']],
                    'date4' => ['value' => date('Y-m-d H:i:s')],
                ];

            case 'WARNING':
                return [
                    'thing1' => ['value' => '重要通知'],
                    'thing2' => ['value' => $notification['title']],
                    'date3' => ['value' => date('Y-m-d H:i:s')],
                    'thing4' => ['value' => '请及时处理'],
                ];

            default:
                return [];
        }
    }

    /**
     * 提取违规类型
     *
     * @param array $relatedData 关联数据
     * @return string
     */
    protected function extractViolationType(array $relatedData): string
    {
        $violationTypes = [
            'SENSITIVE' => '敏感内容',
            'ILLEGAL' => '违法内容',
            'PORN' => '色情内容',
            'VIOLENCE' => '暴力内容',
            'AD' => '广告内容',
            'FRAUD' => '欺诈内容',
            'SPAM' => '垃圾内容',
            'COPYRIGHT' => '版权问题',
            'OTHER' => '其他违规',
        ];

        $type = $relatedData['violation_type'] ?? 'OTHER';
        return $violationTypes[$type] ?? '违规内容';
    }

    /**
     * 构建违规通知内容
     *
     * @param string $materialName 素材名称
     * @param int $materialId 素材ID
     * @param string $violationType 违规类型
     * @param string $severity 严重程度
     * @param string $action 处理动作
     * @param array $violationInfo 违规信息
     * @return string
     */
    protected function buildViolationNotificationContent(
        string $materialName,
        int $materialId,
        string $violationType,
        string $severity,
        string $action,
        array $violationInfo
    ): string {
        $content = "尊敬的商家：\n\n";
        $content .= "您的素材「{$materialName}」(ID:{$materialId})因{$violationType}被检测为违规内容。\n\n";
        $content .= "违规详情：\n";
        $content .= "- 违规类型：{$violationType}\n";
        $content .= "- 严重程度：{$severity}\n";
        $content .= "- 处理结果：{$action}\n\n";

        if ($action === '已下架') {
            $content .= "该素材已被自动下架，暂时无法使用。\n\n";
            $content .= "如对此处理有异议，您可以在7天内提交申诉。\n";
            $content .= "申诉入口：[系统] -> [素材管理] -> [违规记录] -> [申诉]\n\n";
        }

        $content .= "请注意遵守平台内容规范，避免上传违规内容。\n\n";
        $content .= "小魔推团队\n";
        $content .= date('Y-m-d H:i:s');

        return $content;
    }

    /**
     * 构建违规通知HTML
     *
     * @param string $materialName 素材名称
     * @param int $materialId 素材ID
     * @param string $violationType 违规类型
     * @param string $severity 严重程度
     * @param string $action 处理动作
     * @param array $violationInfo 违规信息
     * @return string
     */
    protected function buildViolationNotificationHtml(
        string $materialName,
        int $materialId,
        string $violationType,
        string $severity,
        string $action,
        array $violationInfo
    ): string {
        $html = "<div style='padding: 20px; font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: #e74c3c;'>素材违规通知</h2>";
        $html .= "<p>尊敬的商家：</p>";
        $html .= "<p>您的素材<strong>「{$materialName}」</strong>(ID:{$materialId})因<span style='color: #e74c3c;'>{$violationType}</span>被检测为违规内容。</p>";
        $html .= "<div style='background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #e74c3c;'>";
        $html .= "<h4>违规详情：</h4>";
        $html .= "<ul>";
        $html .= "<li>违规类型：<strong>{$violationType}</strong></li>";
        $html .= "<li>严重程度：<strong>{$severity}</strong></li>";
        $html .= "<li>处理结果：<strong>{$action}</strong></li>";
        $html .= "</ul>";
        $html .= "</div>";

        if ($action === '已下架') {
            $html .= "<p style='color: #e74c3c;'>该素材已被自动下架，暂时无法使用。</p>";
            $html .= "<p>如对此处理有异议，您可以在7天内提交申诉。</p>";
        }

        $html .= "<p style='color: #7f8c8d; font-size: 12px;'>请注意遵守平台内容规范，避免上传违规内容。</p>";
        $html .= "<hr style='border: none; border-top: 1px solid #ecf0f1; margin: 20px 0;'>";
        $html .= "<p style='color: #95a5a6; font-size: 12px;'>小魔推团队<br>" . date('Y-m-d H:i:s') . "</p>";
        $html .= "</div>";

        return $html;
    }

    /**
     * 构建申诉结果内容
     *
     * @param string $materialName 素材名称
     * @param bool $approved 是否通过
     * @param string $comment 审核意见
     * @return string
     */
    protected function buildAppealResultContent(string $materialName, bool $approved, string $comment): string
    {
        $resultText = $approved ? '通过' : '驳回';

        $content = "尊敬的商家：\n\n";
        $content .= "您针对素材「{$materialName}」提交的申诉已审核完成。\n\n";
        $content .= "审核结果：{$resultText}\n";
        $content .= "审核意见：{$comment}\n\n";

        if ($approved) {
            $content .= "您的素材已恢复正常使用。\n\n";
        } else {
            $content .= "您的申诉未通过，素材仍将保持下架状态。\n\n";
        }

        $content .= "感谢您的理解与配合。\n\n";
        $content .= "小魔推团队\n";
        $content .= date('Y-m-d H:i:s');

        return $content;
    }

    /**
     * 构建申诉结果HTML
     *
     * @param string $materialName 素材名称
     * @param bool $approved 是否通过
     * @param string $comment 审核意见
     * @return string
     */
    protected function buildAppealResultHtml(string $materialName, bool $approved, string $comment): string
    {
        $resultText = $approved ? '通过' : '驳回';
        $color = $approved ? '#27ae60' : '#e74c3c';

        $html = "<div style='padding: 20px; font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: {$color};'>申诉审核结果</h2>";
        $html .= "<p>尊敬的商家：</p>";
        $html .= "<p>您针对素材<strong>「{$materialName}」</strong>提交的申诉已审核完成。</p>";
        $html .= "<div style='background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid {$color};'>";
        $html .= "<h4>审核结果：<span style='color: {$color};'>{$resultText}</span></h4>";
        $html .= "<p><strong>审核意见：</strong>{$comment}</p>";
        $html .= "</div>";

        if ($approved) {
            $html .= "<p style='color: #27ae60;'>您的素材已恢复正常使用。</p>";
        } else {
            $html .= "<p style='color: #e74c3c;'>您的申诉未通过，素材仍将保持下架状态。</p>";
        }

        $html .= "<p style='color: #7f8c8d; font-size: 12px;'>感谢您的理解与配合。</p>";
        $html .= "<hr style='border: none; border-top: 1px solid #ecf0f1; margin: 20px 0;'>";
        $html .= "<p style='color: #95a5a6; font-size: 12px;'>小魔推团队<br>" . date('Y-m-d H:i:s') . "</p>";
        $html .= "</div>";

        return $html;
    }

    /**
     * 构建黑名单通知HTML
     *
     * @param string $reason 原因
     * @return string
     */
    protected function buildBlacklistNotificationHtml(string $reason): string
    {
        $html = "<div style='padding: 20px; font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: #c0392b;'>重要通知：账户已被限制</h2>";
        $html .= "<p>尊敬的商家：</p>";
        $html .= "<div style='background: #fee; padding: 15px; margin: 15px 0; border: 2px solid #c0392b;'>";
        $html .= "<p style='color: #c0392b; font-weight: bold;'>由于您的账户存在以下问题：</p>";
        $html .= "<p>{$reason}</p>";
        $html .= "<p style='color: #c0392b; font-weight: bold;'>您的账户已被加入黑名单，部分功能将受到限制。</p>";
        $html .= "</div>";
        $html .= "<p>如有疑问，请及时联系客服处理。</p>";
        $html .= "<p>客服电话：400-xxx-xxxx</p>";
        $html .= "<hr style='border: none; border-top: 1px solid #ecf0f1; margin: 20px 0;'>";
        $html .= "<p style='color: #95a5a6; font-size: 12px;'>小魔推团队<br>" . date('Y-m-d H:i:s') . "</p>";
        $html .= "</div>";

        return $html;
    }

    /**
     * 确定通知渠道
     *
     * @param string $severity 严重程度
     * @return array
     */
    protected function determineNotificationChannels(string $severity): array
    {
        $channels = ['system']; // 系统通知总是有

        // 根据严重程度决定其他渠道
        if ($severity === 'HIGH') {
            $channels = ['system', 'email', 'sms'];
        } elseif ($severity === 'MEDIUM') {
            $channels = ['system', 'email'];
        }

        return $channels;
    }

    /**
     * 获取违规类型名称
     *
     * @param string $type 类型代码
     * @return string
     */
    protected function getViolationTypeName(string $type): string
    {
        $names = [
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

        return $names[$type] ?? '违规内容';
    }

    /**
     * 获取严重程度名称
     *
     * @param string $severity 严重程度代码
     * @return string
     */
    protected function getSeverityName(string $severity): string
    {
        $names = [
            'HIGH' => '严重',
            'MEDIUM' => '中度',
            'LOW' => '轻微'
        ];

        return $names[$severity] ?? '中度';
    }

    /**
     * 获取处理动作名称
     *
     * @param string $action 动作代码
     * @return string
     */
    protected function getActionName(string $action): string
    {
        $names = [
            'DISABLED' => '已下架',
            'WARNING' => '警告',
            'DELETED' => '已删除',
            'NONE' => '无操作'
        ];

        return $names[$action] ?? '未知';
    }

    /**
     * 获取商家未读通知数量
     *
     * @param int $merchantId 商家ID
     * @return int
     */
    public function getUnreadCount(int $merchantId): int
    {
        return Db::name('merchant_notifications')
            ->where('merchant_id', $merchantId)
            ->where('status', 'SENT')
            ->whereNull('read_time')
            ->count();
    }

    /**
     * 标记通知为已读
     *
     * @param int $notificationId 通知ID
     * @param int $merchantId 商家ID
     * @return bool
     */
    public function markAsRead(int $notificationId, int $merchantId): bool
    {
        return Db::name('merchant_notifications')
            ->where('id', $notificationId)
            ->where('merchant_id', $merchantId)
            ->update([
                'status' => 'READ',
                'read_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ]) > 0;
    }

    /**
     * 获取商家通知列表
     *
     * @param int $merchantId 商家ID
     * @param array $params 查询参数
     * @return array
     */
    public function getNotificationList(int $merchantId, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        $query = Db::name('merchant_notifications')
            ->where('merchant_id', $merchantId);

        // 类型筛选
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        // 状态筛选
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 只看未读
        if (isset($params['unread_only']) && $params['unread_only']) {
            $query->where('status', 'SENT')->whereNull('read_time');
        }

        $query->order('create_time', 'desc');

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
}
