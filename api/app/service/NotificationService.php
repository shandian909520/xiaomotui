<?php
declare (strict_types = 1);

namespace app\service;

use app\model\DeviceAlert;
use app\model\NfcDevice;
use think\facade\Log;
use think\facade\Config;
use think\facade\Cache;

/**
 * 告警通知服务
 */
class NotificationService
{
    /**
     * 发送告警通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    public function sendAlert(DeviceAlert $alert): bool
    {
        if (!$alert->needsNotification()) {
            return true;
        }

        $success = true;
        $channels = $alert->notification_channels ?: ['system'];

        foreach ($channels as $channel) {
            try {
                $result = $this->sendToChannel($alert, $channel);
                $alert->recordNotification($channel, $result, $result ? '发送成功' : '发送失败');

                if (!$result) {
                    $success = false;
                }
            } catch (\Exception $e) {
                $success = false;
                $alert->recordNotification($channel, false, $e->getMessage());
                Log::error('告警通知发送失败', [
                    'alert_id' => $alert->id,
                    'channel' => $channel,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $success;
    }

    /**
     * 发送到指定渠道
     *
     * @param DeviceAlert $alert
     * @param string $channel
     * @return bool
     */
    protected function sendToChannel(DeviceAlert $alert, string $channel): bool
    {
        switch ($channel) {
            case DeviceAlert::CHANNEL_WECHAT:
                return $this->sendWechatNotification($alert);
            case DeviceAlert::CHANNEL_SMS:
                return $this->sendSmsNotification($alert);
            case DeviceAlert::CHANNEL_EMAIL:
                return $this->sendEmailNotification($alert);
            case DeviceAlert::CHANNEL_WEBHOOK:
                return $this->sendWebhookNotification($alert);
            case DeviceAlert::CHANNEL_SYSTEM:
                return $this->sendSystemNotification($alert);
            default:
                Log::warning('不支持的通知渠道', ['channel' => $channel]);
                return false;
        }
    }

    /**
     * 发送微信通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    protected function sendWechatNotification(DeviceAlert $alert): bool
    {
        try {
            // 获取微信配置
            $config = Config::get('wechat.notification');
            if (empty($config['enabled']) || empty($config['webhook_url'])) {
                Log::info('微信通知未配置或已禁用');
                return false;
            }

            // 构建消息内容
            $message = $this->buildWechatMessage($alert);

            // 发送请求
            $response = $this->sendHttpRequest($config['webhook_url'], [
                'msgtype' => 'markdown',
                'markdown' => [
                    'content' => $message
                ]
            ]);

            return $this->isHttpResponseSuccess($response);

        } catch (\Exception $e) {
            Log::error('微信通知发送失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送短信通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    protected function sendSmsNotification(DeviceAlert $alert): bool
    {
        try {
            // 获取短信配置
            $config = Config::get('sms.alert');
            if (empty($config['enabled'])) {
                Log::info('短信通知未配置或已禁用');
                return false;
            }

            // 获取接收手机号
            $phoneNumbers = $this->getAlertPhoneNumbers($alert);
            if (empty($phoneNumbers)) {
                Log::info('未配置告警短信接收手机号');
                return false;
            }

            // 构建短信内容
            $message = $this->buildSmsMessage($alert);

            // 发送短信
            $success = true;
            foreach ($phoneNumbers as $phoneNumber) {
                $result = $this->sendSms($phoneNumber, $message, $config);
                if (!$result) {
                    $success = false;
                }
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('短信通知发送失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送邮件通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    protected function sendEmailNotification(DeviceAlert $alert): bool
    {
        try {
            // 获取邮件配置
            $config = Config::get('email.alert');
            if (empty($config['enabled'])) {
                Log::info('邮件通知未配置或已禁用');
                return false;
            }

            // 获取接收邮箱
            $emails = $this->getAlertEmails($alert);
            if (empty($emails)) {
                Log::info('未配置告警邮件接收地址');
                return false;
            }

            // 构建邮件内容
            $subject = $this->buildEmailSubject($alert);
            $content = $this->buildEmailContent($alert);

            // 发送邮件
            $success = true;
            foreach ($emails as $email) {
                $result = $this->sendEmail($email, $subject, $content, $config);
                if (!$result) {
                    $success = false;
                }
            }

            return $success;

        } catch (\Exception $e) {
            Log::error('邮件通知发送失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送Webhook通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    protected function sendWebhookNotification(DeviceAlert $alert): bool
    {
        try {
            // 获取Webhook配置
            $config = Config::get('webhook.alert');
            if (empty($config['enabled']) || empty($config['url'])) {
                Log::info('Webhook通知未配置或已禁用');
                return false;
            }

            // 构建Webhook数据
            $data = $this->buildWebhookData($alert);

            // 发送请求
            $response = $this->sendHttpRequest($config['url'], $data, [
                'Content-Type: application/json',
                'User-Agent: NFC-Alert-System/1.0'
            ]);

            return $this->isHttpResponseSuccess($response);

        } catch (\Exception $e) {
            Log::error('Webhook通知发送失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送系统内通知
     *
     * @param DeviceAlert $alert
     * @return bool
     */
    protected function sendSystemNotification(DeviceAlert $alert): bool
    {
        try {
            // 将告警信息存储到系统消息表或缓存中
            $cacheKey = "system_notification:merchant_{$alert->merchant_id}";
            $notifications = Cache::get($cacheKey, []);

            $notifications[] = [
                'id' => $alert->id,
                'type' => 'device_alert',
                'title' => $alert->alert_title,
                'message' => $alert->alert_message,
                'level' => $alert->alert_level,
                'data' => [
                    'alert_id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_code' => $alert->device_code,
                    'alert_type' => $alert->alert_type
                ],
                'read' => false,
                'create_time' => date('Y-m-d H:i:s')
            ];

            // 保留最近100条通知
            if (count($notifications) > 100) {
                $notifications = array_slice($notifications, -100);
            }

            Cache::set($cacheKey, $notifications, 7 * 24 * 3600); // 保存7天

            Log::info('系统通知已创建', [
                'alert_id' => $alert->id,
                'merchant_id' => $alert->merchant_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('系统通知创建失败', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 构建微信消息
     *
     * @param DeviceAlert $alert
     * @return string
     */
    protected function buildWechatMessage(DeviceAlert $alert): string
    {
        $levelEmoji = $this->getLevelEmoji($alert->alert_level);
        $levelText = $alert->getAlertLevelTextAttr(null, $alert->getData());

        $message = "## {$levelEmoji} 设备告警通知\n\n";
        $message .= "**告警级别：** <font color=\"{$alert->getLevelColorAttr(null, $alert->getData())}\">$levelText</font>\n\n";
        $message .= "**告警类型：** {$alert->getAlertTypeTextAttr(null, $alert->getData())}\n\n";
        $message .= "**设备信息：** {$alert->device_code}\n\n";
        $message .= "**告警内容：** {$alert->alert_message}\n\n";
        $message .= "**触发时间：** {$alert->trigger_time}\n\n";

        if (!empty($alert->alert_data['device_location'])) {
            $message .= "**设备位置：** {$alert->alert_data['device_location']}\n\n";
        }

        $message .= "> 请及时处理此告警";

        return $message;
    }

    /**
     * 构建短信消息
     *
     * @param DeviceAlert $alert
     * @return string
     */
    protected function buildSmsMessage(DeviceAlert $alert): string
    {
        $levelText = $alert->getAlertLevelTextAttr(null, $alert->getData());
        $typeText = $alert->getAlertTypeTextAttr(null, $alert->getData());

        return "【设备告警】{$levelText}告警：{$typeText}，设备：{$alert->device_code}，{$alert->alert_message}，时间：{$alert->trigger_time}";
    }

    /**
     * 构建邮件主题
     *
     * @param DeviceAlert $alert
     * @return string
     */
    protected function buildEmailSubject(DeviceAlert $alert): string
    {
        $levelText = $alert->getAlertLevelTextAttr(null, $alert->getData());
        $typeText = $alert->getAlertTypeTextAttr(null, $alert->getData());

        return "[{$levelText}告警] {$typeText} - {$alert->device_code}";
    }

    /**
     * 构建邮件内容
     *
     * @param DeviceAlert $alert
     * @return string
     */
    protected function buildEmailContent(DeviceAlert $alert): string
    {
        $levelText = $alert->getAlertLevelTextAttr(null, $alert->getData());
        $typeText = $alert->getAlertTypeTextAttr(null, $alert->getData());

        $html = "<html><body>";
        $html .= "<h2 style='color: {$alert->getLevelColorAttr(null, $alert->getData())}'>{$levelText}告警通知</h2>";
        $html .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
        $html .= "<tr><td><strong>告警类型</strong></td><td>{$typeText}</td></tr>";
        $html .= "<tr><td><strong>告警级别</strong></td><td style='color: {$alert->getLevelColorAttr(null, $alert->getData())}'>{$levelText}</td></tr>";
        $html .= "<tr><td><strong>设备编码</strong></td><td>{$alert->device_code}</td></tr>";
        $html .= "<tr><td><strong>告警内容</strong></td><td>{$alert->alert_message}</td></tr>";
        $html .= "<tr><td><strong>触发时间</strong></td><td>{$alert->trigger_time}</td></tr>";

        if (!empty($alert->alert_data['device_location'])) {
            $html .= "<tr><td><strong>设备位置</strong></td><td>{$alert->alert_data['device_location']}</td></tr>";
        }

        if (!empty($alert->alert_data)) {
            $html .= "<tr><td><strong>详细信息</strong></td><td><pre>" . json_encode($alert->alert_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre></td></tr>";
        }

        $html .= "</table>";
        $html .= "<p><em>此邮件由设备告警系统自动发送，请及时处理相关告警。</em></p>";
        $html .= "</body></html>";

        return $html;
    }

    /**
     * 构建Webhook数据
     *
     * @param DeviceAlert $alert
     * @return array
     */
    protected function buildWebhookData(DeviceAlert $alert): array
    {
        return [
            'event' => 'device_alert',
            'timestamp' => time(),
            'alert' => [
                'id' => $alert->id,
                'device_id' => $alert->device_id,
                'device_code' => $alert->device_code,
                'merchant_id' => $alert->merchant_id,
                'type' => $alert->alert_type,
                'level' => $alert->alert_level,
                'title' => $alert->alert_title,
                'message' => $alert->alert_message,
                'data' => $alert->alert_data,
                'status' => $alert->status,
                'trigger_time' => $alert->trigger_time
            ]
        ];
    }

    /**
     * 获取告警电话号码列表
     *
     * @param DeviceAlert $alert
     * @return array
     */
    protected function getAlertPhoneNumbers(DeviceAlert $alert): array
    {
        // 可以从商家配置、用户配置等地方获取
        $config = Config::get('alert.phone_numbers', []);

        // 根据商家ID获取特定配置
        $merchantConfig = Config::get("alert.merchants.{$alert->merchant_id}.phone_numbers", []);

        return array_merge($config, $merchantConfig);
    }

    /**
     * 获取告警邮箱列表
     *
     * @param DeviceAlert $alert
     * @return array
     */
    protected function getAlertEmails(DeviceAlert $alert): array
    {
        // 可以从商家配置、用户配置等地方获取
        $config = Config::get('alert.emails', []);

        // 根据商家ID获取特定配置
        $merchantConfig = Config::get("alert.merchants.{$alert->merchant_id}.emails", []);

        return array_merge($config, $merchantConfig);
    }

    /**
     * 发送HTTP请求
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array
     */
    protected function sendHttpRequest(string $url, array $data, array $headers = []): array
    {
        $ch = curl_init();

        $defaultHeaders = [
            'Content-Type: application/json',
            'User-Agent: NFC-Alert-System/1.0'
        ];

        $headers = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => empty($error) && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }

    /**
     * 判断HTTP响应是否成功
     *
     * @param array $response
     * @return bool
     */
    protected function isHttpResponseSuccess(array $response): bool
    {
        return $response['success'] && $response['http_code'] >= 200 && $response['http_code'] < 300;
    }

    /**
     * 发送短信
     *
     * @param string $phoneNumber
     * @param string $message
     * @param array $config
     * @return bool
     */
    protected function sendSms(string $phoneNumber, string $message, array $config): bool
    {
        try {
            // 这里可以集成具体的短信服务商SDK
            // 如阿里云短信、腾讯云短信等

            Log::info('短信发送模拟', [
                'phone' => $phoneNumber,
                'message' => $message
            ]);

            // 模拟发送成功
            return true;

        } catch (\Exception $e) {
            Log::error('短信发送失败', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送邮件
     *
     * @param string $email
     * @param string $subject
     * @param string $content
     * @param array $config
     * @return bool
     */
    protected function sendEmail(string $email, string $subject, string $content, array $config): bool
    {
        try {
            // 这里可以集成具体的邮件服务
            // 如PHPMailer、SwiftMailer等

            Log::info('邮件发送模拟', [
                'email' => $email,
                'subject' => $subject
            ]);

            // 模拟发送成功
            return true;

        } catch (\Exception $e) {
            Log::error('邮件发送失败', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取级别对应的emoji
     *
     * @param string $level
     * @return string
     */
    protected function getLevelEmoji(string $level): string
    {
        $emojis = [
            DeviceAlert::LEVEL_LOW => '🟢',
            DeviceAlert::LEVEL_MEDIUM => '🟡',
            DeviceAlert::LEVEL_HIGH => '🟠',
            DeviceAlert::LEVEL_CRITICAL => '🔴'
        ];

        return $emojis[$level] ?? '⚪';
    }

    /**
     * 获取系统通知列表
     *
     * @param int $merchantId
     * @param bool $unreadOnly
     * @return array
     */
    public function getSystemNotifications(int $merchantId, bool $unreadOnly = false): array
    {
        $cacheKey = "system_notification:merchant_{$merchantId}";
        $notifications = Cache::get($cacheKey, []);

        if ($unreadOnly) {
            $notifications = array_filter($notifications, function($notification) {
                return !$notification['read'];
            });
        }

        // 按时间倒序
        usort($notifications, function($a, $b) {
            return strtotime($b['create_time']) - strtotime($a['create_time']);
        });

        return array_values($notifications);
    }

    /**
     * 标记通知为已读
     *
     * @param int $merchantId
     * @param int $alertId
     * @return bool
     */
    public function markNotificationAsRead(int $merchantId, int $alertId): bool
    {
        $cacheKey = "system_notification:merchant_{$merchantId}";
        $notifications = Cache::get($cacheKey, []);

        foreach ($notifications as &$notification) {
            if ($notification['id'] == $alertId) {
                $notification['read'] = true;
                break;
            }
        }

        return Cache::set($cacheKey, $notifications, 7 * 24 * 3600);
    }

    /**
     * 清除已读通知
     *
     * @param int $merchantId
     * @return bool
     */
    public function clearReadNotifications(int $merchantId): bool
    {
        $cacheKey = "system_notification:merchant_{$merchantId}";
        $notifications = Cache::get($cacheKey, []);

        $notifications = array_filter($notifications, function($notification) {
            return !$notification['read'];
        });

        return Cache::set($cacheKey, array_values($notifications), 7 * 24 * 3600);
    }
}