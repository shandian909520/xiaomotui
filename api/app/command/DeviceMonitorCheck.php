<?php
declare(strict_types=1);

namespace app\command;

use app\service\DeviceMonitorService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * 设备监控检查命令
 * 用于定时检查设备在线状态并触发离线告警
 *
 * 使用方式:
 * php think device:monitor:check
 *
 * Crontab配置:
 * */5 * * * * cd /path/to/api && php think device:monitor:check >> /dev/null 2>&1
 */
class DeviceMonitorCheck extends Command
{
    protected function configure()
    {
        $this->setName('device:monitor:check')
            ->setDescription('检查所有设备在线状态并触发离线告警');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('====================================');
        $output->writeln('设备监控检查任务开始');
        $output->writeln('时间: ' . date('Y-m-d H:i:s'));
        $output->writeln('====================================');

        try {
            // 执行设备状态检查
            $stats = DeviceMonitorService::checkAllDevices();

            // 输出统计信息
            $output->writeln('');
            $output->writeln('检查完成:');
            $output->writeln("  - 总设备数: {$stats['total']}");
            $output->writeln("  - 在线设备: {$stats['online']}");
            $output->writeln("  - 离线设备: {$stats['offline']}");
            $output->writeln("  - 触发告警: {$stats['alerts_triggered']}");
            $output->writeln("  - 错误数量: {$stats['errors']}");
            $output->writeln("  - 执行耗时: {$stats['execution_time']}ms");
            $output->writeln('');

            // 记录日志
            Log::info('设备监控检查任务完成', $stats);

            $output->writeln('<info>✓ 任务执行成功</info>');
            return 0;

        } catch (\Exception $e) {
            $error = '设备监控检查任务失败: ' . $e->getMessage();
            $output->writeln("<error>✗ {$error}</error>");
            $output->writeln('错误详情: ' . $e->getTraceAsString());

            Log::error($error, [
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}
