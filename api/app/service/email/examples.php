<?php
/**
 * 邮件服务使用示例
 * 演示各种邮件发送场景
 */

use app\service\EmailService;

// ========== 场景1：发送简单HTML邮件 ==========
function sendSimpleEmail()
{
    $result = EmailService::create()
        ->setFrom('noreply@example.com', '小魔推')
        ->addTo('user@example.com', '用户')
        ->setSubject('欢迎使用小魔推')
        ->setHtmlBody('<h1>欢迎</h1><p>感谢您注册我们的服务！</p>')
        ->send();

    if ($result['success']) {
        echo "邮件发送成功\n";
    } else {
        echo "邮件发送失败: " . $result['message'] . "\n";
    }
}

// ========== 场景2：发送带附件的邮件 ==========
function sendEmailWithAttachment()
{
    $result = EmailService::create()
        ->setFrom('noreply@example.com', '小魔推')
        ->addTo('user@example.com')
        ->setSubject('月度报表')
        ->setHtmlBody('<p>请查收附件中的月度报表。</p>')
        ->addAttachment('/path/to/report.pdf', '2024年1月报表.pdf')
        ->send();

    return $result;
}

// ========== 场景3：发送欢迎邮件 ==========
function sendWelcomeEmail($email, $username)
{
    $emailService = new EmailService();
    $result = $emailService->sendWelcomeEmail($email, $username, [
        'welcome_url' => 'https://example.com/welcome',
        'tutorial_url' => 'https://example.com/tutorial'
    ]);

    return $result;
}

// ========== 场景4：发送商家审核通知 ==========
function sendMerchantAuditEmail($email, $merchantData, $status, $reason)
{
    $emailService = new EmailService();
    $result = $emailService->sendMerchantAuditEmail(
        $email,
        $merchantData,
        $status,
        $reason
    );

    return $result;
}

// ========== 场景5：发送设备告警邮件 ==========
function sendDeviceAlertEmail($email, $alertData)
{
    $emailService = new EmailService();
    $result = $emailService->sendDeviceAlertEmail($email, $alertData);

    return $result;
}

// ========== 场景6：发送优惠券过期提醒 ==========
function sendCouponExpiryEmail($email, $couponData)
{
    $emailService = new EmailService();
    $result = $emailService->sendCouponExpiryEmail($email, $couponData);

    return $result;
}

// ========== 场景7：批量发送邮件 ==========
function sendBatchEmails($recipients)
{
    $emailService = new EmailService();
    $successCount = 0;
    $failCount = 0;

    foreach ($recipients as $recipient) {
        $result = $emailService
            ->resetMailer()
            ->setFrom('noreply@example.com', '小魔推')
            ->addTo($recipient['email'], $recipient['name'])
            ->setSubject('批量邮件测试')
            ->setHtmlBody('<p>这是一封批量发送的邮件。</p>')
            ->sendAsync(); // 使用异步发送

        if ($result) {
            $successCount++;
        } else {
            $failCount++;
        }

        // 避免发送过快
        usleep(100000); // 延迟0.1秒
    }

    echo "批量发送完成: 成功 {$successCount} 封, 失败 {$failCount} 封\n";
}

// ========== 场景8：使用自定义模板 ==========
function sendCustomTemplateEmail($email, $template, $vars)
{
    $result = EmailService::create()
        ->setFrom('noreply@example.com', '小魔推')
        ->addTo($email)
        ->setSubject('自定义模板邮件')
        ->useTemplate($template, $vars)
        ->send();

    return $result;
}

// ========== 场景9：在Controller中使用 ==========
class EmailController
{
    /**
     * 发送测试邮件
     */
    public function sendTest()
    {
        $email = input('email');

        $emailService = new EmailService();
        $result = $emailService->test($email);

        return json([
            'code' => $result['success'] ? 200 : 400,
            'message' => $result['message'],
            'data' => $result
        ]);
    }

    /**
     * 批量发送欢迎邮件
     */
    public function batchWelcome()
    {
        $users = [
            ['email' => 'user1@example.com', 'name' => '张三'],
            ['email' => 'user2@example.com', 'name' => '李四'],
        ];

        $emailService = new EmailService();
        $results = [];

        foreach ($users as $user) {
            $results[] = $emailService->sendWelcomeEmail(
                $user['email'],
                $user['name']
            );
        }

        return json([
            'code' => 200,
            'message' => '批量发送完成',
            'data' => $results
        ]);
    }

    /**
     * 发送带附件的报表
     */
    public function sendReport()
    {
        $email = input('email');
        $reportPath = '/path/to/report.pdf';

        if (!file_exists($reportPath)) {
            return json([
                'code' => 400,
                'message' => '报表文件不存在'
            ]);
        }

        $result = EmailService::create()
            ->setFrom('reports@example.com', '报表系统')
            ->addTo($email)
            ->setSubject('月度报表')
            ->setHtmlBody('<p>请查收附件中的月度报表。</p>')
            ->addAttachment($reportPath, '月度报表.pdf')
            ->sendAsync();

        return json([
            'code' => 200,
            'message' => $result ? '报表已发送' : '发送失败'
        ]);
    }
}

// ========== 场景10：定时任务发送优惠券过期提醒 ==========
function sendCouponExpiryReminders()
{
    // 查询即将过期的优惠券（3天内过期）
    $coupons = Db::name('coupons')
        ->where('expiry_date', 'between', [
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', strtotime('+3 days'))
        ])
        ->where('status', 'active')
        ->select();

    $emailService = new EmailService();
    $sentCount = 0;

    foreach ($coupons as $coupon) {
        $user = Db::name('users')->where('id', $coupon['user_id'])->find();
        if (empty($user['email'])) {
            continue;
        }

        $daysLeft = ceil((strtotime($coupon['expiry_date']) - time()) / 86400);

        $result = $emailService->sendCouponExpiryEmail($user['email'], [
            'name' => $coupon['name'],
            'code' => $coupon['code'],
            'expiry_date' => $coupon['expiry_date'],
            'days_left' => $daysLeft,
            'discount' => $coupon['discount'],
            'merchant_name' => $coupon['merchant_name']
        ]);

        if ($result['success']) {
            $sentCount++;
        }
    }

    echo "已发送 {$sentCount} 封优惠券过期提醒邮件\n";
}

// ========== 使用示例 ==========
/*
// 发送简单邮件
sendSimpleEmail();

// 发送欢迎邮件
sendWelcomeEmail('user@example.com', '张三');

// 发送商家审核通知
sendMerchantAuditEmail(
    'merchant@example.com',
    ['name' => '测试商家', 'id' => 123],
    'approved',
    '审核通过，欢迎入驻！'
);

// 发送设备告警
sendDeviceAlertEmail('admin@example.com', [
    'device_code' => 'NFC001',
    'device_name' => '1号设备',
    'alert_type' => 'offline',
    'alert_level' => 'error',
    'alert_message' => '设备已离线',
    'trigger_time' => date('Y-m-d H:i:s'),
    'location' => '一楼大厅',
    'suggestions' => ['检查电源', '检查网络']
]);

// 发送优惠券过期提醒
sendCouponExpiryEmail('user@example.com', [
    'name' => '满100减20券',
    'code' => 'COUPON2024',
    'expiry_date' => '2024-01-31 23:59:59',
    'days_left' => 3,
    'discount' => '¥20',
    'merchant_name' => 'XX餐厅'
]);
*/
