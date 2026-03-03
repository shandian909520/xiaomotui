<?php
declare(strict_types=1);

namespace app\service\queue;

use think\queue\Job;
use think\facade\Log;
use app\service\EmailService;

/**
 * 邮件发送队列任务
 * 支持失败重试机制
 */
class EmailJob
{
    /**
     * 最大重试次数
     */
    const MAX_RETRIES = 3;

    /**
     * 重试延迟（秒）
     */
    const RETRY_DELAY = 60;

    /**
     * 执行队列任务
     *
     * @param Job $job 队列任务对象
     * @param array $data 邮件数据
     * @return void
     */
    public function fire(Job $job, array $data)
    {
        try {
            Log::info('开始处理邮件队列任务', [
                'job_id' => $job->getJobId(),
                'to' => $this->extractEmails($data['to'] ?? [])
            ]);

            // 创建邮件服务实例
            $emailService = EmailService::create();

            // 配置邮件
            $this->configureEmail($emailService, $data);

            // 发送邮件
            $result = $emailService->send();

            if ($result['success']) {
                // 发送成功，删除任务
                $job->delete();

                Log::info('邮件队列任务执行成功', [
                    'job_id' => $job->getJobId(),
                    'to' => $this->extractEmails($data['to'] ?? [])
                ]);

            } else {
                // 发送失败，处理重试逻辑
                $this->handleFailure($job, $data, $result['message']);
            }

        } catch (\Exception $e) {
            // 异常处理
            $this->handleFailure($job, $data, $e->getMessage());

            Log::error('邮件队列任务执行异常', [
                'job_id' => $job->getJobId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 配置邮件
     *
     * @param EmailService $emailService
     * @param array $data
     * @return void
     */
    protected function configureEmail(EmailService $emailService, array $data): void
    {
        // 设置发件人
        if (!empty($data['from'])) {
            $emailService->setFrom($data['from'], $data['from_name'] ?? '');
        }

        // 设置收件人
        foreach ($data['to'] ?? [] as $to) {
            $emailService->addTo($to[0], $to[1] ?? '');
        }

        // 设置抄送
        foreach ($data['cc'] ?? [] as $cc) {
            $emailService->addCc($cc[0], $cc[1] ?? '');
        }

        // 设置密送
        foreach ($data['bcc'] ?? [] as $bcc) {
            $emailService->addBcc($bcc[0], $bcc[1] ?? '');
        }

        // 设置回复地址
        foreach ($data['reply_to'] ?? [] as $replyTo) {
            $emailService->setReplyTo($replyTo[0], $replyTo[1] ?? '');
        }

        // 设置主题
        $emailService->setSubject($data['subject'] ?? '');

        // 设置正文
        if ($data['is_html'] ?? false) {
            $emailService->setBody(
                $data['body'] ?? '',
                $data['alt_body'] ?? ''
            );
        } else {
            $emailService->setTextBody($data['body'] ?? '');
        }

        // 添加附件
        foreach ($data['attachments'] ?? [] as $attachment) {
            if ($attachment['type'] ?? '' === 'string') {
                // 字符串附件
                $emailService->addStringAttachment(
                    $attachment['content'] ?? '',
                    $attachment['name'] ?? '',
                    $attachment['encoding'] ?? 'base64',
                    $attachment['mime_type'] ?? 'application/octet-stream'
                );
            } else {
                // 文件附件
                $path = $attachment['path'] ?? '';
                if (file_exists($path)) {
                    $emailService->addAttachment($path, $attachment['name'] ?? '');
                }
            }
        }
    }

    /**
     * 处理发送失败
     *
     * @param Job $job
     * @param array $data
     * @param string $errorMessage
     * @return void
     */
    protected function handleFailure(Job $job, array $data, string $errorMessage): void
    {
        $attempts = $job->attempts();

        Log::warning('邮件发送失败', [
            'job_id' => $job->getJobId(),
            'attempts' => $attempts,
            'to' => $this->extractEmails($data['to'] ?? []),
            'error' => $errorMessage
        ]);

        if ($attempts >= self::MAX_RETRIES) {
            // 达到最大重试次数，删除任务
            $job->delete();

            Log::error('邮件发送失败：已达到最大重试次数', [
                'job_id' => $job->getJobId(),
                'to' => $this->extractEmails($data['to'] ?? []),
                'max_retries' => self::MAX_RETRIES
            ]);

            // 可以在这里记录失败日志到数据库或发送告警通知
            $this->logPermanentFailure($data, $errorMessage);

        } else {
            // 重新发布任务，延迟重试
            $delay = self::RETRY_DELAY * $attempts; // 延迟时间随重试次数增加
            $job->release($delay);

            Log::info('邮件任务重新入队', [
                'job_id' => $job->getJobId(),
                'attempts' => $attempts,
                'delay' => $delay
            ]);
        }
    }

    /**
     * 记录永久失败
     *
     * @param array $data
     * @param string $errorMessage
     * @return void
     */
    protected function logPermanentFailure(array $data, string $errorMessage): void
    {
        try {
            $failureData = [
                'to' => $this->extractEmails($data['to'] ?? []),
                'subject' => $data['subject'] ?? '',
                'error_message' => $errorMessage,
                'attempts' => self::MAX_RETRIES,
                'failed_time' => date('Y-m-d H:i:s'),
                'email_data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ];

            // 记录到失败日志表
            \think\facade\Db::name('email_failures')->insert($failureData);

        } catch (\Exception $e) {
            Log::error('记录邮件永久失败日志失败', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 提取邮箱地址
     *
     * @param array $addresses
     * @return string
     */
    protected function extractEmails(array $addresses): string
    {
        $emails = [];
        foreach ($addresses as $address) {
            $emails[] = is_array($address) ? ($address[0] ?? '') : $address;
        }
        return implode(', ', $emails);
    }

    /**
     * 推送任务到队列（静态方法）
     *
     * @param string $class 任务类名
     * @param array $data 任务数据
     * @param int $delay 延迟时间（秒）
     * @return bool
     */
    public static function push(string $class, array $data, int $delay = 0): bool
    {
        try {
            $queue = think\facade\Queue::later($delay, $class, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('推送邮件任务到队列失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
