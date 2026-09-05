<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 碰一碰任务引擎建表命令（幂等，可重复执行）
 * 用法: php think task:engine-install
 * 说明: 不走 migrate:run，避免旧迁移（orders等已存在的表）导致中断
 */
class TaskEngineInstall extends Command
{
    protected function configure()
    {
        $this->setName('task:engine-install')
            ->setDescription('创建碰一碰任务引擎所需数据表（幂等）');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->createTaskBundles($output);
        $this->createTaskActions($output);
        $this->createTaskInstances($output);
        $this->createTaskProofs($output);
        $this->extendDeviceTriggers($output);
        $output->info('任务引擎数据表安装完成');
        return 0;
    }

    private function tableExists(string $table): bool
    {
        $db = Db::query('SELECT DATABASE() AS db')[0]['db'] ?? '';
        $row = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$db, $table]
        );
        return ((int)$row[0]['c']) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $db = Db::query('SELECT DATABASE() AS db')[0]['db'] ?? '';
        $row = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$db, $table, $column]
        );
        return ((int)$row[0]['c']) > 0;
    }

    private function createTaskBundles(Output $output): void
    {
        if ($this->tableExists('xmt_task_bundles')) {
            $output->writeln('- xmt_task_bundles 已存在，跳过');
            return;
        }
        Db::execute("CREATE TABLE xmt_task_bundles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
            device_id INT UNSIGNED NULL COMMENT '绑定的NFC设备ID NULL=商家默认包',
            bundle_name VARCHAR(100) NOT NULL COMMENT '任务包名称',
            title VARCHAR(200) NOT NULL COMMENT '落地页标题',
            subtitle VARCHAR(300) NULL COMMENT '落地页副标题',
            cover VARCHAR(500) NULL COMMENT '封面图',
            completion_rule VARCHAR(10) NOT NULL DEFAULT 'ALL' COMMENT '完成规则 ALL/ANY/COUNT',
            completion_count INT UNSIGNED NULL COMMENT 'COUNT规则时需完成的动作数',
            reward_type VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT '奖励类型 redpacket/coupon/points/none',
            reward_config TEXT NULL COMMENT '奖励配置',
            lander_config TEXT NULL COMMENT '落地页自定义配置',
            expire_hours INT NOT NULL DEFAULT 24 COMMENT '任务有效时长(小时)',
            status TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
            create_time DATETIME NOT NULL COMMENT '创建时间',
            update_time DATETIME NOT NULL COMMENT '更新时间',
            INDEX idx_merchant (merchant_id),
            INDEX idx_device (device_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='碰一碰任务包配置'");
        $output->writeln('+ xmt_task_bundles 创建成功');
    }

    private function createTaskActions(Output $output): void
    {
        if ($this->tableExists('xmt_task_actions')) {
            $output->writeln('- xmt_task_actions 已存在，跳过');
            return;
        }
        Db::execute("CREATE TABLE xmt_task_actions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bundle_id BIGINT UNSIGNED NOT NULL COMMENT '所属任务包ID',
            plugin_key VARCHAR(50) NOT NULL COMMENT '插件标识',
            sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
            action_name VARCHAR(100) NOT NULL COMMENT '动作显示名',
            action_icon VARCHAR(500) NULL COMMENT '动作图标',
            action_config TEXT NULL COMMENT '插件私有配置',
            required TINYINT NOT NULL DEFAULT 1 COMMENT '是否必做',
            create_time DATETIME NOT NULL COMMENT '创建时间',
            update_time DATETIME NOT NULL COMMENT '更新时间',
            INDEX idx_bundle (bundle_id),
            INDEX idx_plugin_key (plugin_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务包内动作'");
        $output->writeln('+ xmt_task_actions 创建成功');
    }

    private function createTaskInstances(Output $output): void
    {
        if ($this->tableExists('xmt_task_instances')) {
            $output->writeln('- xmt_task_instances 已存在，跳过');
            return;
        }
        Db::execute("CREATE TABLE xmt_task_instances (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bundle_id BIGINT UNSIGNED NOT NULL COMMENT '任务包ID',
            device_id INT UNSIGNED NULL COMMENT 'NFC设备ID',
            merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
            user_id INT UNSIGNED NULL COMMENT '用户ID',
            openid VARCHAR(64) NULL COMMENT '微信openid',
            unionid VARCHAR(64) NULL COMMENT '微信unionid',
            status VARCHAR(20) NOT NULL DEFAULT 'CREATED' COMMENT 'CREATED/IN_PROGRESS/COMPLETED/EXPIRED/ABANDONED',
            progress TEXT NOT NULL COMMENT '动作进度',
            reward_status VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING/ISSUED/FAILED/SKIPPED',
            reward_data TEXT NULL COMMENT '奖励发放结果',
            expired_at DATETIME NOT NULL COMMENT '过期时间',
            create_time DATETIME NOT NULL COMMENT '创建时间',
            update_time DATETIME NOT NULL COMMENT '更新时间',
            INDEX idx_user (user_id),
            INDEX idx_openid (openid),
            INDEX idx_bundle (bundle_id),
            INDEX idx_merchant (merchant_id),
            INDEX idx_status_expired (status, expired_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户碰一碰任务实例'");
        $output->writeln('+ xmt_task_instances 创建成功');
    }

    private function createTaskProofs(Output $output): void
    {
        if ($this->tableExists('xmt_task_proofs')) {
            $output->writeln('- xmt_task_proofs 已存在，跳过');
            return;
        }
        Db::execute("CREATE TABLE xmt_task_proofs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_instance_id BIGINT UNSIGNED NOT NULL COMMENT '任务实例ID',
            action_id BIGINT UNSIGNED NOT NULL COMMENT '动作ID',
            merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
            file_url VARCHAR(500) NOT NULL COMMENT '凭证文件URL',
            file_hash VARCHAR(64) NOT NULL COMMENT '文件SHA256用于查重',
            audit_status VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING/APPROVED/REJECTED',
            audit_remark VARCHAR(255) NULL COMMENT '审核备注',
            auditor_id INT UNSIGNED NULL COMMENT '审核人ID',
            audited_at DATETIME NULL COMMENT '审核时间',
            create_time DATETIME NOT NULL COMMENT '创建时间',
            update_time DATETIME NOT NULL COMMENT '更新时间',
            INDEX idx_instance (task_instance_id),
            INDEX idx_action (action_id),
            INDEX idx_hash (file_hash),
            INDEX idx_audit (audit_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务完成凭证'");
        $output->writeln('+ xmt_task_proofs 创建成功');
    }

    private function extendDeviceTriggers(Output $output): void
    {
        if (!$this->tableExists('xmt_device_triggers')) {
            $output->error('xmt_device_triggers 表不存在，无法扩展');
            return;
        }
        if (!$this->columnExists('xmt_device_triggers', 'bundle_id')) {
            Db::execute('ALTER TABLE xmt_device_triggers ADD COLUMN bundle_id BIGINT UNSIGNED NULL COMMENT \'命中的任务包ID\' AFTER trigger_mode, ADD INDEX idx_bundle (bundle_id)');
            $output->writeln('+ xmt_device_triggers.bundle_id 添加成功');
        } else {
            $output->writeln('- xmt_device_triggers.bundle_id 已存在，跳过');
        }
        if (!$this->columnExists('xmt_device_triggers', 'task_instance_id')) {
            Db::execute('ALTER TABLE xmt_device_triggers ADD COLUMN task_instance_id BIGINT UNSIGNED NULL COMMENT \'对应任务实例ID\' AFTER bundle_id');
            $output->writeln('+ xmt_device_triggers.task_instance_id 添加成功');
        } else {
            $output->writeln('- xmt_device_triggers.task_instance_id 已存在，跳过');
        }
    }
}
