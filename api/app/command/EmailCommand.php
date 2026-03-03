<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\EmailService;
use think\facade\Db;

/**
 * 邮件管理命令
 * 用法：
 * php think email test test@example.com          # 发送测试邮件
 * php think email stats                          # 查看邮件统计
 * php think email clean                          # 清理过期日志
 * php think email failures                       # 查看失败记录
 */
class EmailCommand extends Command
{
    protected function configure()
    {
        $this->setName('email')
            ->setDescription('邮件服务管理工具')
            ->addArgument('action', null, '操作类型：test/stats/clean/failures')
            ->addArgument('param', null, '操作参数（如测试邮箱地址）');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $param = $input->getArgument('param');

        switch ($action) {
            case 'test':
                $this->sendTestEmail($param, $output);
                break;

            case 'stats':
                $this->showStatistics($output);
                break;

            case 'clean':
                $this->cleanLogs($output);
                break;

            case 'failures':
                $this->showFailures($output);
                break;

            default:
                $this->showHelp($output);
                break;
        }
    }

    /**
     * 发送测试邮件
     */
    protected function sendTestEmail($email, Output $output)
    {
        if (empty($email)) {
            $output->writeln('<error>请指定测试邮箱地址</error>');
            $output->writeln('用法: php think email test test@example.com');
            return;
        }

        $output->writeln("正在发送测试邮件到: {$email}");

        try {
            $emailService = new EmailService();
            $result = $emailService->test($email);

            if ($result['success']) {
                $output->writeln('<info>✓ 测试邮件发送成功！</info>');
                $output->writeln("收件人: {$result['to'][0]}");
                $output->writeln("主题: {$result['subject']}");
                $output->writeln("时间: {$result['time']}");
            } else {
                $output->writeln('<error>✗ 测试邮件发送失败</error>');
                $output->writeln("错误信息: {$result['message']}");
            }

        } catch (\Exception $e) {
            $output->writeln('<error>发送测试邮件时发生异常</error>');
            $output->writeln("错误信息: {$e->getMessage()}");
            $output->writeln("堆栈跟踪:\n{$e->getTraceAsString()}");
        }
    }

    /**
     * 显示邮件统计
     */
    protected function showStatistics(Output $output)
    {
        $output->writeln("\n邮件发送统计（最近7天）：");
        $output->writeln(str_repeat('-', 60));

        try {
            $emailService = new EmailService();
            $stats = $emailService->getStatistics(7);

            if (empty($stats)) {
                $output->writeln('<error>获取统计信息失败</error>');
                return;
            }

            $output->writeln("总发送数: {$stats['total']}");
            $output->writeln("成功数: <info>{$stats['success']}</info>");
            $output->writeln("失败数: <error>{$stats['failed']}</error>");
            $output->writeln("成功率: <info>{$stats['success_rate']}%</info>");
            $output->writeln("带附件: {$stats['with_attachments']}");
            $output->writeln(str_repeat('-', 60));

            // 今日统计
            $todayStats = $emailService->getStatistics(1);
            $output->writeln("\n今日统计:");
            $output->writeln("总发送数: {$todayStats['total']}");
            $output->writeln("成功数: {$todayStats['success']}");
            $output->writeln("失败数: {$todayStats['failed']}");

        } catch (\Exception $e) {
            $output->writeln('<error>获取统计信息失败</error>');
            $output->writeln("错误信息: {$e->getMessage()}");
        }

        $output->writeln("");
    }

    /**
     * 清理过期日志
     */
    protected function cleanLogs(Output $output)
    {
        $output->writeln("正在清理过期的邮件日志...");

        try {
            $emailService = new EmailService();
            $count = $emailService->cleanOldLogs();

            $output->writeln("<info>✓ 已清理 {$count} 条过期记录</info>");

        } catch (\Exception $e) {
            $output->writeln('<error>清理日志失败</error>');
            $output->writeln("错误信息: {$e->getMessage()}");
        }
    }

    /**
     * 显示失败记录
     */
    protected function showFailures(Output $output)
    {
        $output->writeln("\n最近的邮件发送失败记录：");
        $output->writeln(str_repeat('-', 80));

        try {
            $failures = Db::name('email_failures')
                ->order('failed_time', 'desc')
                ->limit(20)
                ->select()
                ->toArray();

            if (empty($failures)) {
                $output->writeln('<info>没有失败记录</info>');
                return;
            }

            foreach ($failures as $failure) {
                $output->writeln("\nID: {$failure['id']}");
                $output->writeln("收件人: {$failure['to']}");
                $output->writeln("主题: {$failure['subject']}");
                $output->writeln("错误: <error>{$failure['error_message']}</error>");
                $output->writeln("重试: {$failure['attempts']}次");
                $output->writeln("时间: {$failure['failed_time']}");
            }

            $output->writeln("\n" . str_repeat('-', 80));
            $output->writeln("显示最近20条记录");

        } catch (\Exception $e) {
            $output->writeln('<error>获取失败记录失败</error>');
            $output->writeln("错误信息: {$e->getMessage()}");
        }

        $output->writeln("");
    }

    /**
     * 显示帮助信息
     */
    protected function showHelp(Output $output)
    {
        $output->writeln("\n邮件服务管理工具");
        $output->writeln(str_repeat('=', 80));
        $output->writeln("\n可用命令：");
        $output->writeln("  php think email test <email>      发送测试邮件到指定地址");
        $output->writeln("  php think email stats             显示邮件发送统计");
        $output->writeln("  php think email clean             清理过期的邮件日志");
        $output->writeln("  php think email failures          显示最近的失败记录");
        $output->writeln("\n示例：");
        $output->writeln("  php think email test test@example.com");
        $output->writeln("  php think email stats");
        $output->writeln("  php think email clean");
        $output->writeln("  php think email failures");
        $output->writeln("\n" . str_repeat('=', 80) . "\n");
    }
}
