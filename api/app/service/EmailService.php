<?php
declare(strict_types=1);

namespace app\service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use think\facade\Config;
use think\facade\Log;
use think\facade\Db;
use think\facade\Cache;

/**
 * 邮件服务类
 * 支持SMTP邮件发送、模板系统、异步队列、发送记录、重试机制
 */
class EmailService
{
    /**
     * 邮件配置
     */
    protected array $config;

    /**
     * PHPMailer实例
     */
    protected ?PHPMailer $mailer = null;

    /**
     * 当前邮件实例ID
     */
    protected ?int $emailId = null;

    /**
     * 附件列表
     */
    protected array $attachments = [];

    /**
     * 模板变量
     */
    protected array $templateVars = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = Config::get('email');
    }

    /**
     * 创建邮件实例
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * 设置发件人
     *
     * @param string $address 邮箱地址
     * @param string $name 名称
     * @return self
     */
    public function setFrom(string $address, string $name = ''): self
    {
        $this->getMailer()->setFrom($address, $name);
        return $this;
    }

    /**
     * 添加收件人
     *
     * @param string $address 邮箱地址
     * @param string $name 名称
     * @return self
     */
    public function addTo(string $address, string $name = ''): self
    {
        $this->getMailer()->addAddress($address, $name);
        return $this;
    }

    /**
     * 添加抄送
     *
     * @param string $address 邮箱地址
     * @param string $name 名称
     * @return self
     */
    public function addCc(string $address, string $name = ''): self
    {
        $this->getMailer()->addCC($address, $name);
        return $this;
    }

    /**
     * 添加密送
     *
     * @param string $address 邮箱地址
     * @param string $name 名称
     * @return self
     */
    public function addBcc(string $address, string $name = ''): self
    {
        $this->getMailer()->addBCC($address, $name);
        return $this;
    }

    /**
     * 设置回复地址
     *
     * @param string $address 邮箱地址
     * @param string $name 名称
     * @return self
     */
    public function setReplyTo(string $address, string $name = ''): self
    {
        $this->getMailer()->addReplyTo($address, $name);
        return $this;
    }

    /**
     * 设置邮件主题
     *
     * @param string $subject 主题
     * @return self
     */
    public function setSubject(string $subject): self
    {
        $this->getMailer()->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return $this;
    }

    /**
     * 设置邮件正文（HTML）
     *
     * @param string $body 正文
     * @return self
     */
    public function setHtmlBody(string $body): self
    {
        $this->getMailer()->isHTML(true);
        $this->getMailer()->Body = $body;
        return $this;
    }

    /**
     * 设置邮件正文（纯文本）
     *
     * @param string $body 正文
     * @return self
     */
    public function setTextBody(string $body): self
    {
        $this->getMailer()->isHTML(false);
        $this->getMailer()->Body = $body;
        return $this;
    }

    /**
     * 同时设置HTML和纯文本正文
     *
     * @param string $htmlBody HTML正文
     * @param string $textBody 纯文本正文
     * @return self
     */
    public function setBody(string $htmlBody, string $textBody = ''): self
    {
        $this->getMailer()->isHTML(true);
        $this->getMailer()->Body = $htmlBody;
        if (!empty($textBody)) {
            $this->getMailer()->AltBody = $textBody;
        }
        return $this;
    }

    /**
     * 使用模板设置邮件正文
     *
     * @param string $template 模板名称
     * @param array $vars 模板变量
     * @return self
     */
    public function useTemplate(string $template, array $vars = []): self
    {
        $this->templateVars = array_merge($this->templateVars, $vars);
        $content = $this->renderTemplate($template, $this->templateVars);
        return $this->setHtmlBody($content);
    }

    /**
     * 添加附件
     *
     * @param string $path 文件路径
     * @param string $name 附件名称
     * @return self
     */
    public function addAttachment(string $path, string $name = ''): self
    {
        try {
            $this->getMailer()->addAttachment($path, $name);
            $this->attachments[] = [
                'path' => $path,
                'name' => $name ?: basename($path)
            ];
            return $this;
        } catch (\Exception $e) {
            Log::error('添加附件失败', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 添加字符串附件
     *
     * @param string $content 文件内容
     * @param string $name 文件名
     * @param string $encoding 编码
     * @param string $type MIME类型
     * @return self
     */
    public function addStringAttachment(
        string $content,
        string $name,
        string $encoding = 'base64',
        string $type = 'application/octet-stream'
    ): self {
        $this->getMailer()->addStringAttachment($content, $name, $encoding, $type);
        $this->attachments[] = [
            'type' => 'string',
            'name' => $name,
            'size' => strlen($content)
        ];
        return $this;
    }

    /**
     * 发送邮件（同步）
     *
     * @return array
     */
    public function send(): array
    {
        try {
            // 检查速率限制
            if (!$this->checkRateLimit()) {
                throw new \Exception('邮件发送频率超过限制');
            }

            // 检查测试模式
            if ($this->config['test_mode'] ?? false) {
                Log::info('测试模式：邮件未实际发送', [
                    'to' => $this->getMailer()->getToAddresses(),
                    'subject' => $this->getMailer()->Subject
                ]);
                return $this->createSendResult(true, '测试模式：邮件未实际发送');
            }

            // 发送邮件
            $result = $this->getMailer()->send();

            // 记录发送日志
            $this->logEmail(true, '');

            return $this->createSendResult(true, '邮件发送成功');

        } catch (\Exception $e) {
            // 记录发送日志
            $this->logEmail(false, $e->getMessage());

            // 记录错误日志
            Log::error('邮件发送失败', [
                'to' => $this->getToAddresses(),
                'subject' => $this->getMailer()->Subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createSendResult(false, $e->getMessage());
        }
    }

    /**
     * 发送邮件（异步队列）
     *
     * @param int $delay 延迟时间（秒）
     * @return bool
     */
    public function sendAsync(int $delay = 0): bool
    {
        try {
            if (!($this->config['queue']['enabled'] ?? true)) {
                // 队列未启用，使用同步发送
                $result = $this->send();
                return $result['success'];
            }

            // 获取邮件数据
            $emailData = $this->serializeEmailData();

            // 添加到队列
            $queueClass = '\\app\\service\\queue\\EmailJob';
            if (class_exists($queueClass)) {
                $queue = new $queueClass();
                $queue->push($queueClass, $emailData, $delay);
            } else {
                // 使用ThinkPHP队列
                think\facade\Queue::push('app\service\queue\EmailJob', $emailData);
            }

            Log::info('邮件已加入发送队列', [
                'to' => $this->getToAddresses(),
                'delay' => $delay
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('邮件加入队列失败', [
                'to' => $this->getToAddresses(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送欢迎邮件
     *
     * @param string $to 收件人邮箱
     * @param string $username 用户名
     * @param array $extraData 额外数据
     * @return array
     */
    public function sendWelcomeEmail(string $to, string $username, array $extraData = []): array
    {
        try {
            $vars = array_merge([
                'username' => $username,
                'email' => $to,
                'app_name' => $this->config['from_name'] ?? '小魔推',
                'year' => date('Y')
            ], $extraData);

            $this->resetMailer();
            $this->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $this->addTo($to);
            $this->setSubject('欢迎加入' . ($this->config['from_name'] ?? '小魔推'));
            $this->useTemplate('welcome', $vars);

            return $this->send();

        } catch (\Exception $e) {
            Log::error('发送欢迎邮件失败', [
                'to' => $to,
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return $this->createSendResult(false, $e->getMessage());
        }
    }

    /**
     * 发送商家审核通知邮件
     *
     * @param string $to 收件人邮箱
     * @param array $merchantData 商家数据
     * @param string $status 审核状态（approved/rejected）
     * @param string $reason 审核说明
     * @return array
     */
    public function sendMerchantAuditEmail(
        string $to,
        array $merchantData,
        string $status,
        string $reason = ''
    ): array {
        try {
            $statusText = $status === 'approved' ? '审核通过' : '审核未通过';
            $statusColor = $status === 'approved' ? '#52c41a' : '#ff4d4f';

            $vars = [
                'merchant_name' => $merchantData['name'] ?? '',
                'merchant_id' => $merchantData['id'] ?? '',
                'status' => $statusText,
                'status_color' => $statusColor,
                'reason' => $reason,
                'audit_time' => date('Y-m-d H:i:s'),
                'app_name' => $this->config['from_name'] ?? '小魔推',
                'year' => date('Y')
            ];

            $this->resetMailer();
            $this->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $this->addTo($to);
            $this->setSubject("商家账号{$statusText}通知");
            $this->useTemplate('merchant_audit', $vars);

            return $this->send();

        } catch (\Exception $e) {
            Log::error('发送商家审核邮件失败', [
                'to' => $to,
                'merchant_id' => $merchantData['id'] ?? '',
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return $this->createSendResult(false, $e->getMessage());
        }
    }

    /**
     * 发送设备告警邮件
     *
     * @param string $to 收件人邮箱
     * @param array $alertData 告警数据
     * @return array
     */
    public function sendDeviceAlertEmail(string $to, array $alertData): array
    {
        try {
            $levelColors = [
                'info' => '#1890ff',
                'warning' => '#faad14',
                'error' => '#ff4d4f',
                'critical' => '#f5222d'
            ];

            $vars = [
                'device_code' => $alertData['device_code'] ?? '',
                'device_name' => $alertData['device_name'] ?? '',
                'alert_type' => $alertData['alert_type'] ?? '',
                'alert_level' => $alertData['alert_level'] ?? '',
                'alert_level_color' => $levelColors[$alertData['alert_level']] ?? '#1890ff',
                'alert_message' => $alertData['alert_message'] ?? '',
                'trigger_time' => $alertData['trigger_time'] ?? date('Y-m-d H:i:s'),
                'location' => $alertData['location'] ?? '',
                'suggestions' => $alertData['suggestions'] ?? [],
                'app_name' => $this->config['from_name'] ?? '小魔推',
                'year' => date('Y')
            ];

            $this->resetMailer();
            $this->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $this->addTo($to);
            $this->setSubject('[设备告警] ' . ($alertData['alert_type'] ?? '设备异常'));
            $this->useTemplate('device_alert', $vars);

            return $this->send();

        } catch (\Exception $e) {
            Log::error('发送设备告警邮件失败', [
                'to' => $to,
                'alert_data' => $alertData,
                'error' => $e->getMessage()
            ]);
            return $this->createSendResult(false, $e->getMessage());
        }
    }

    /**
     * 发送优惠券过期提醒邮件
     *
     * @param string $to 收件人邮箱
     * @param array $couponData 优惠券数据
     * @return array
     */
    public function sendCouponExpiryEmail(string $to, array $couponData): array
    {
        try {
            $vars = [
                'coupon_name' => $couponData['name'] ?? '',
                'coupon_code' => $couponData['code'] ?? '',
                'expiry_date' => $couponData['expiry_date'] ?? '',
                'days_left' => $couponData['days_left'] ?? 0,
                'discount' => $couponData['discount'] ?? '',
                'merchant_name' => $couponData['merchant_name'] ?? '',
                'app_name' => $this->config['from_name'] ?? '小魔推',
                'year' => date('Y')
            ];

            $this->resetMailer();
            $this->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $this->addTo($to);
            $this->setSubject('优惠券即将过期提醒');
            $this->useTemplate('coupon_expiry', $vars);

            return $this->send();

        } catch (\Exception $e) {
            Log::error('发送优惠券过期邮件失败', [
                'to' => $to,
                'coupon_data' => $couponData,
                'error' => $e->getMessage()
            ]);
            return $this->createSendResult(false, $e->getMessage());
        }
    }

    /**
     * 获取PHPMailer实例
     *
     * @return PHPMailer
     */
    protected function getMailer(): PHPMailer
    {
        if ($this->mailer === null) {
            $this->mailer = new PHPMailer(true);
            $this->configureMailer();
        }
        return $this->mailer;
    }

    /**
     * 配置PHPMailer
     *
     * @return void
     */
    protected function configureMailer(): void
    {
        $options = $this->config['options'] ?? [];

        // 设置字符集
        $this->mailer->CharSet = $options['charset'] ?? 'UTF-8';

        // 设置调试模式
        if ($options['debug'] ?? false) {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mailer->Debugoutput = function($str, $level) {
                Log::info('PHPMailer Debug', ['level' => $level, 'message' => $str]);
            };
        }

        // SMTP配置
        if ($this->config['default'] === 'smtp') {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->Port = $this->config['port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];

            // 加密方式
            if (!empty($this->config['encryption'])) {
                $this->mailer->SMTPSecure = $this->config['encryption'] === 'tls'
                    ? PHPMailer::ENCRYPTION_STARTTLS
                    : PHPMailer::ENCRYPTION_SMTPS;
            }

            // 超时设置
            $this->mailer->Timeout = $options['timeout'] ?? 30;

            // SSL验证
            if (!($this->config['verify_peer'] ?? true)) {
                $this->mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }
        }

        // 设置默认发件人
        $this->mailer->setFrom(
            $this->config['from_address'],
            $this->config['from_name']
        );

        // 设置默认为HTML邮件
        if ($options['is_html'] ?? true) {
            $this->mailer->isHTML(true);
        }
    }

    /**
     * 重置邮件实例
     *
     * @return void
     */
    protected function resetMailer(): void
    {
        $this->mailer = null;
        $this->attachments = [];
        $this->templateVars = [];
        $this->emailId = null;
    }

    /**
     * 渲染邮件模板
     *
     * @param string $template 模板名称
     * @param array $vars 模板变量
     * @return string
     */
    protected function renderTemplate(string $template, array $vars = []): string
    {
        $templatePath = $this->config['template']['path'] ?? app_path() . '/service/email/templates/';
        $templateFile = $templatePath . $template . '.html';

        if (!file_exists($templateFile)) {
            Log::warning('邮件模板不存在', ['template' => $templateFile]);
            return $this->getDefaultTemplate($template, $vars);
        }

        $content = file_get_contents($templateFile);

        // 替换模板变量
        $leftDelimiter = $this->config['template']['left_delimiter'] ?? '{';
        $rightDelimiter = $this->config['template']['right_delimiter'] ?? '}';

        foreach ($vars as $key => $value) {
            $placeholder = $leftDelimiter . $key . $rightDelimiter;
            $content = str_replace($placeholder, (string)$value, $content);
        }

        return $content;
    }

    /**
     * 获取默认模板内容
     *
     * @param string $template 模板名称
     * @param array $vars 模板变量
     * @return string
     */
    protected function getDefaultTemplate(string $template, array $vars = []): string
    {
        return $this->createTemplate($template, $vars);
    }

    /**
     * 创建邮件模板
     *
     * @param string $type 模板类型
     * @param array $vars 模板变量
     * @return string
     */
    protected function createTemplate(string $type, array $vars = []): string
    {
        $style = $this->config['template']['default_style'] ?? [];
        $primaryColor = $style['primary_color'] ?? '#1890ff';
        $textColor = $style['text_color'] ?? '#333333';
        $bgColor = $style['bg_color'] ?? '#f5f5f5';

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="zh-CN">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>' . ($vars['subject'] ?? '邮件通知') . '</title>';
        $html .= '</head>';
        $html .= '<body style="margin:0;padding:0;background-color:' . $bgColor . ';">';
        $html .= '<div style="max-width:600px;margin:0 auto;padding:20px;">';

        // 根据类型生成不同内容
        switch ($type) {
            case 'welcome':
                $html .= $this->createWelcomeTemplate($vars, $primaryColor, $textColor);
                break;
            case 'merchant_audit':
                $html .= $this->createMerchantAuditTemplate($vars, $primaryColor, $textColor);
                break;
            case 'device_alert':
                $html .= $this->createDeviceAlertTemplate($vars, $primaryColor, $textColor);
                break;
            case 'coupon_expiry':
                $html .= $this->createCouponExpiryTemplate($vars, $primaryColor, $textColor);
                break;
            default:
                $html .= $this->createDefaultTemplate($vars, $primaryColor, $textColor);
        }

        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

    /**
     * 创建欢迎邮件模板
     */
    protected function createWelcomeTemplate(array $vars, string $primaryColor, string $textColor): string
    {
        $html = '<div style="background-color:#ffffff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<h1 style="color:' . $primaryColor . ';margin:0 0 20px;">欢迎加入' . ($vars['app_name'] ?? '小魔推') . '</h1>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">尊敬的 <strong>' . ($vars['username'] ?? '') . '</strong>，您好！</p>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">';
        $html .= '感谢您注册成为' . ($vars['app_name'] ?? '小魔推') . '的用户。我们很高兴为您提供优质的服务。</p>';
        $html .= '<div style="background-color:#f5f5f5;border-left:4px solid ' . $primaryColor . ';padding:15px;margin:20px 0;">';
        $html .= '<h3 style="color:' . $primaryColor . ';margin:0 0 10px;">您可以开始使用以下功能：</h3>';
        $html .= '<ul style="color:' . $textColor . ';line-height:1.8;">';
        $html .= '<li>创建和管理您的营销内容</li>';
        $html .= '<li>使用NFC设备进行智能推广</li>';
        $html .= '<li>查看详细的数据分析报告</li>';
        $html .= '<li>参与团购活动，提升营业额</li>';
        $html .= '</ul></div>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">如有任何问题，请随时联系我们的客服团队。</p>';
        $html .= '<hr style="border:none;border-top:1px solid #e8e8e8;margin:30px 0;">';
        $html .= '<p style="color:#999999;font-size:12px;">此邮件由系统自动发送，请勿直接回复</p>';
        $html .= '<p style="color:#999999;font-size:12px;">' . ($vars['app_name'] ?? '小魔推') . ' · ' . ($vars['year'] ?? date('Y')) . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 创建商家审核邮件模板
     */
    protected function createMerchantAuditTemplate(array $vars, string $primaryColor, string $textColor): string
    {
        $html = '<div style="background-color:#ffffff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<h2 style="color:' . ($vars['status_color'] ?? $primaryColor) . ';margin:0 0 20px;">商家账号' . ($vars['status'] ?? '') . '</h2>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">尊敬的商家，您好！</p>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">';
        $html .= '您的商家账号 <strong>' . ($vars['merchant_name'] ?? '') . '</strong> ';
        $html .= '已完成审核，审核结果为：<span style="color:' . ($vars['status_color'] ?? $primaryColor) . ';font-weight:bold;">' . ($vars['status'] ?? '') . '</span></p>';

        if (!empty($vars['reason'])) {
            $html .= '<div style="background-color:#f5f5f5;border-left:4px solid ' . ($vars['status_color'] ?? $primaryColor) . ';padding:15px;margin:20px 0;">';
            $html .= '<h4 style="color:' . ($vars['status_color'] ?? $primaryColor) . ';margin:0 0 10px;">审核说明：</h4>';
            $html .= '<p style="color:' . $textColor . ';line-height:1.6;">' . ($vars['reason'] ?? '') . '</p>';
            $html .= '</div>';
        }

        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">';
        $html .= '审核时间：' . ($vars['audit_time'] ?? '') . '</p>';
        $html .= '<hr style="border:none;border-top:1px solid #e8e8e8;margin:30px 0;">';
        $html .= '<p style="color:#999999;font-size:12px;">此邮件由系统自动发送，请勿直接回复</p>';
        $html .= '<p style="color:#999999;font-size:12px;">' . ($vars['app_name'] ?? '小魔推') . ' · ' . ($vars['year'] ?? date('Y')) . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 创建设备告警邮件模板
     */
    protected function createDeviceAlertTemplate(array $vars, string $primaryColor, string $textColor): string
    {
        $html = '<div style="background-color:#ffffff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<h2 style="color:' . ($vars['alert_level_color'] ?? '#ff4d4f') . ';margin:0 0 20px;">⚠️ 设备告警通知</h2>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">尊敬的用户，您好！</p>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">您的设备检测到异常情况，请及时处理。</p>';

        $html .= '<div style="background-color:#fff2e8;border-left:4px solid ' . ($vars['alert_level_color'] ?? '#ff4d4f') . ';padding:15px;margin:20px 0;">';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>设备名称：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['device_name'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>设备编码：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['device_code'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>告警类型：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['alert_type'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>告警级别：</strong></td><td style="padding:8px 0;color:' . ($vars['alert_level_color'] ?? '#ff4d4f') . ';font-weight:bold;">' . ($vars['alert_level'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>告警内容：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['alert_message'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>触发时间：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['trigger_time'] ?? '') . '</td></tr>';
        if (!empty($vars['location'])) {
            $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>设备位置：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['location'] ?? '') . '</td></tr>';
        }
        $html .= '</table></div>';

        if (!empty($vars['suggestions']) && is_array($vars['suggestions'])) {
            $html .= '<div style="background-color:#f5f5f5;padding:15px;margin:20px 0;">';
            $html .= '<h4 style="color:' . $primaryColor . ';margin:0 0 10px;">处理建议：</h4>';
            $html .= '<ul style="color:' . $textColor . ';line-height:1.8;">';
            foreach ($vars['suggestions'] as $suggestion) {
                $html .= '<li>' . $suggestion . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '<hr style="border:none;border-top:1px solid #e8e8e8;margin:30px 0;">';
        $html .= '<p style="color:#999999;font-size:12px;">此邮件由设备告警系统自动发送，请勿直接回复</p>';
        $html .= '<p style="color:#999999;font-size:12px;">' . ($vars['app_name'] ?? '小魔推') . ' · ' . ($vars['year'] ?? date('Y')) . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 创建优惠券过期提醒模板
     */
    protected function createCouponExpiryTemplate(array $vars, string $primaryColor, string $textColor): string
    {
        $html = '<div style="background-color:#ffffff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<h2 style="color:' . $primaryColor . ';margin:0 0 20px;">🎫 优惠券即将过期提醒</h2>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">尊敬的用户，您好！</p>';
        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">您的优惠券即将过期，请尽快使用。</p>';

        $html .= '<div style="background-color:#fff7e6;border:2px dashed ' . $primaryColor . ';padding:20px;margin:20px 0;border-radius:8px;">';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>优惠券名称：</strong></td><td style="padding:8px 0;color:' . $primaryColor . ';font-size:18px;font-weight:bold;">' . ($vars['coupon_name'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>优惠券码：</strong></td><td style="padding:8px 0;color:' . $textColor . ';font-family:monospace;">' . ($vars['coupon_code'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>优惠金额：</strong></td><td style="padding:8px 0;color:#ff4d4f;font-weight:bold;">' . ($vars['discount'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>过期时间：</strong></td><td style="padding:8px 0;color:#ff4d4f;font-weight:bold;">' . ($vars['expiry_date'] ?? '') . '</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>剩余天数：</strong></td><td style="padding:8px 0;color:#ff4d4f;font-size:20px;font-weight:bold;">' . ($vars['days_left'] ?? 0) . ' 天</td></tr>';
        $html .= '<tr><td style="padding:8px 0;color:' . $textColor . ';"><strong>适用商家：</strong></td><td style="padding:8px 0;color:' . $textColor . ';">' . ($vars['merchant_name'] ?? '') . '</td></tr>';
        $html .= '</table></div>';

        $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">';
        $html .= '请及时使用优惠券，避免过期造成损失。</p>';

        $html .= '<hr style="border:none;border-top:1px solid #e8e8e8;margin:30px 0;">';
        $html .= '<p style="color:#999999;font-size:12px;">此邮件由系统自动发送，请勿直接回复</p>';
        $html .= '<p style="color:#999999;font-size:12px;">' . ($vars['app_name'] ?? '小魔推') . ' · ' . ($vars['year'] ?? date('Y')) . '</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 创建默认邮件模板
     */
    protected function createDefaultTemplate(array $vars, string $primaryColor, string $textColor): string
    {
        $html = '<div style="background-color:#ffffff;border-radius:8px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<h2 style="color:' . $primaryColor . ';margin:0 0 20px;">邮件通知</h2>';
        foreach ($vars as $key => $value) {
            if (!is_array($value)) {
                $html .= '<p style="color:' . $textColor . ';font-size:16px;line-height:1.6;">';
                $html .= '<strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars((string)$value);
                $html .= '</p>';
            }
        }
        $html .= '<hr style="border:none;border-top:1px solid #e8e8e8;margin:30px 0;">';
        $html .= '<p style="color:#999999;font-size:12px;">此邮件由系统自动发送</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 检查速率限制
     *
     * @return bool
     */
    protected function checkRateLimit(): bool
    {
        if (!($this->config['rate_limit']['enabled'] ?? true)) {
            return true;
        }

        $to = $this->getToAddresses();
        $email = $to[0] ?? 'unknown';

        $cacheKey = "email_rate_limit:{$email}";
        $limits = Cache::get($cacheKey, [
            'minute' => 0,
            'hour' => 0,
            'day' => 0,
            'minute_time' => time(),
            'hour_time' => time(),
            'day_time' => time()
        ]);

        $now = time();
        $config = $this->config['rate_limit'];

        // 重置分钟计数
        if ($now - $limits['minute_time'] >= 60) {
            $limits['minute'] = 0;
            $limits['minute_time'] = $now;
        }

        // 重置小时计数
        if ($now - $limits['hour_time'] >= 3600) {
            $limits['hour'] = 0;
            $limits['hour_time'] = $now;
        }

        // 重置天计数
        if ($now - $limits['day_time'] >= 86400) {
            $limits['day'] = 0;
            $limits['day_time'] = $now;
        }

        // 检查限制
        if ($limits['minute'] >= ($config['per_minute'] ?? 60)) {
            return false;
        }

        if ($limits['hour'] >= ($config['per_hour'] ?? 500)) {
            return false;
        }

        if ($limits['day'] >= ($config['per_day'] ?? 5000)) {
            return false;
        }

        // 增加计数
        $limits['minute']++;
        $limits['hour']++;
        $limits['day']++;

        Cache::set($cacheKey, $limits, 86400);

        return true;
    }

    /**
     * 获取收件人地址列表
     *
     * @return array
     */
    protected function getToAddresses(): array
    {
        $addresses = [];
        foreach ($this->getMailer()->getToAddresses() as $address) {
            $addresses[] = $address[0];
        }
        return $addresses;
    }

    /**
     * 序列化邮件数据（用于队列）
     *
     * @return array
     */
    protected function serializeEmailData(): array
    {
        return [
            'from' => $this->getMailer()->From,
            'from_name' => $this->getMailer()->FromName,
            'to' => $this->getMailer()->getToAddresses(),
            'cc' => $this->getMailer()->getCcAddresses(),
            'bcc' => $this->getMailer()->getBccAddresses(),
            'reply_to' => $this->getMailer()->getReplyToAddresses(),
            'subject' => $this->getMailer()->Subject,
            'body' => $this->getMailer()->Body,
            'alt_body' => $this->getMailer()->AltBody,
            'is_html' => $this->getMailer()->ContentType === 'text/html',
            'attachments' => $this->attachments,
            'template_vars' => $this->templateVars,
        ];
    }

    /**
     * 记录邮件发送日志
     *
     * @param bool $success 是否成功
     * @param string $errorMessage 错误信息
     * @return void
     */
    protected function logEmail(bool $success, string $errorMessage = ''): void
    {
        try {
            $logConfig = $this->config['log'] ?? [];

            if (!($logConfig['enabled'] ?? true)) {
                return;
            }

            $to = implode(', ', array_map(function($addr) {
                return $addr[0];
            }, $this->getMailer()->getToAddresses()));

            $logData = [
                'from' => $this->getMailer()->From,
                'to' => $to,
                'subject' => $this->getMailer()->Subject,
                'is_html' => $this->getMailer()->ContentType === 'text/html',
                'success' => $success ? 1 : 0,
                'error_message' => $errorMessage,
                'has_attachment' => !empty($this->attachments) ? 1 : 0,
                'attachment_count' => count($this->attachments),
                'send_time' => date('Y-m-d H:i:s'),
                'create_time' => date('Y-m-d H:i:s')
            ];

            // 可选：记录邮件内容
            if ($logConfig['log_content'] ?? false) {
                $logData['body'] = $this->getMailer()->Body;
                $logData['alt_body'] = $this->getMailer()->AltBody;
            }

            // 可选：记录附件信息
            if (($logConfig['log_attachments'] ?? true) && !empty($this->attachments)) {
                $logData['attachments'] = json_encode($this->attachments);
            }

            Db::name('email_logs')->insert($logData);

        } catch (\Exception $e) {
            Log::error('记录邮件日志失败', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 创建发送结果
     *
     * @param bool $success 是否成功
     * @param string $message 消息
     * @return array
     */
    protected function createSendResult(bool $success, string $message): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'to' => $this->getToAddresses(),
            'subject' => $this->getMailer()->Subject ?? '',
            'time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 获取邮件发送统计
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getStatistics(int $days = 7): array
    {
        try {
            $startTime = date('Y-m-d H:i:s', time() - ($days * 86400));

            $total = Db::name('email_logs')
                ->where('send_time', '>=', $startTime)
                ->count();

            $success = Db::name('email_logs')
                ->where('send_time', '>=', $startTime)
                ->where('success', 1)
                ->count();

            $failed = $total - $success;

            $withAttachments = Db::name('email_logs')
                ->where('send_time', '>=', $startTime)
                ->where('has_attachment', 1)
                ->count();

            return [
                'total' => $total,
                'success' => $success,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                'with_attachments' => $withAttachments,
                'days' => $days
            ];

        } catch (\Exception $e) {
            Log::error('获取邮件统计失败', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 清理过期的邮件日志
     *
     * @return int 删除的记录数
     */
    public function cleanOldLogs(): int
    {
        try {
            $retentionDays = $this->config['log']['retention_days'] ?? 30;
            $cutoffDate = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));

            $count = Db::name('email_logs')
                ->where('send_time', '<', $cutoffDate)
                ->delete();

            Log::info('清理过期邮件日志', [
                'count' => $count,
                'cutoff_date' => $cutoffDate
            ]);

            return $count;

        } catch (\Exception $e) {
            Log::error('清理邮件日志失败', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 测试邮件配置
     *
     * @param string $to 测试收件人
     * @return array
     */
    public function test(string $to): array
    {
        try {
            $this->resetMailer();

            $this->setFrom(
                $this->config['from_address'],
                $this->config['from_name']
            );
            $this->addTo($to);
            $this->setSubject('邮件配置测试');
            $this->setBody(
                '<h1>测试邮件</h1><p>这是一封测试邮件，用于验证邮件配置是否正确。</p><p>如果您收到此邮件，说明配置成功！</p>',
                '这是一封测试邮件，用于验证邮件配置是否正确。如果您收到此邮件，说明配置成功！'
            );

            $result = $this->send();

            return $result;

        } catch (\Exception $e) {
            return $this->createSendResult(false, $e->getMessage());
        }
    }
}
