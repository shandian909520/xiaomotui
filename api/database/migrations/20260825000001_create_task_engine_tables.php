<?php

use think\migration\Migrator;

/**
 * 碰一碰任务引擎核心表
 * task_bundles / task_actions / task_instances / task_proofs
 */
class CreateTaskEngineTables extends Migrator
{
    public function up()
    {
        // 任务包配置（商家配置）
        if (!$this->hasTable('task_bundles')) {
            $this->table('task_bundles', [
                'id' => 'id',
                'engine' => 'InnoDB',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '碰一碰任务包配置',
            ])
                ->addColumn('merchant_id', 'integer', ['null' => false, 'comment' => '商家ID'])
                ->addColumn('device_id', 'integer', ['null' => true, 'comment' => '绑定的NFC设备ID NULL=商家默认包'])
                ->addColumn('bundle_name', 'string', ['limit' => 100, 'null' => false, 'comment' => '任务包名称'])
                ->addColumn('title', 'string', ['limit' => 200, 'null' => false, 'comment' => '落地页标题'])
                ->addColumn('subtitle', 'string', ['limit' => 300, 'null' => true, 'comment' => '落地页副标题'])
                ->addColumn('cover', 'string', ['limit' => 500, 'null' => true, 'comment' => '封面图'])
                ->addColumn('completion_rule', 'string', ['limit' => 10, 'null' => false, 'default' => 'ALL', 'comment' => '完成规则 ALL/ANY/COUNT'])
                ->addColumn('completion_count', 'integer', ['null' => true, 'comment' => 'COUNT规则时需完成的动作数'])
                ->addColumn('reward_type', 'string', ['limit' => 20, 'null' => false, 'default' => 'none', 'comment' => '奖励类型 redpacket/coupon/points/none'])
                ->addColumn('reward_config', 'text', ['null' => true, 'comment' => '奖励配置'])
                ->addColumn('lander_config', 'text', ['null' => true, 'comment' => '落地页自定义配置'])
                ->addColumn('expire_hours', 'integer', ['null' => false, 'default' => 24, 'comment' => '任务有效时长(小时)'])
                ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'null' => false, 'default' => 1, 'comment' => '1=启用 0=停用'])
                ->addColumn('create_time', 'datetime', ['null' => false, 'comment' => '创建时间'])
                ->addColumn('update_time', 'datetime', ['null' => false, 'comment' => '更新时间'])
                ->addIndex(['merchant_id'], ['name' => 'idx_merchant'])
                ->addIndex(['device_id'], ['name' => 'idx_device'])
                ->addIndex(['status'], ['name' => 'idx_status'])
                ->create();
        }

        // 任务包内动作
        if (!$this->hasTable('task_actions')) {
            $this->table('task_actions', [
                'id' => 'id',
                'engine' => 'InnoDB',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '任务包内动作',
            ])
                ->addColumn('bundle_id', 'integer', ['null' => false, 'comment' => '所属任务包ID'])
                ->addColumn('plugin_key', 'string', ['limit' => 50, 'null' => false, 'comment' => '插件标识'])
                ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 0, 'comment' => '排序'])
                ->addColumn('action_name', 'string', ['limit' => 100, 'null' => false, 'comment' => '动作显示名'])
                ->addColumn('action_icon', 'string', ['limit' => 500, 'null' => true, 'comment' => '动作图标'])
                ->addColumn('action_config', 'text', ['null' => true, 'comment' => '插件私有配置'])
                ->addColumn('required', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'null' => false, 'default' => 1, 'comment' => '是否必做'])
                ->addColumn('create_time', 'datetime', ['null' => false, 'comment' => '创建时间'])
                ->addColumn('update_time', 'datetime', ['null' => false, 'comment' => '更新时间'])
                ->addIndex(['bundle_id'], ['name' => 'idx_bundle'])
                ->addIndex(['plugin_key'], ['name' => 'idx_plugin_key'])
                ->create();
        }

        // 用户任务实例（每次碰一碰产生一条）
        if (!$this->hasTable('task_instances')) {
            $this->table('task_instances', [
                'id' => 'id',
                'engine' => 'InnoDB',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '用户碰一碰任务实例',
            ])
                ->addColumn('bundle_id', 'integer', ['null' => false, 'comment' => '任务包ID'])
                ->addColumn('device_id', 'integer', ['null' => true, 'comment' => 'NFC设备ID'])
                ->addColumn('merchant_id', 'integer', ['null' => false, 'comment' => '商家ID'])
                ->addColumn('user_id', 'integer', ['null' => true, 'comment' => '用户ID'])
                ->addColumn('openid', 'string', ['limit' => 64, 'null' => true, 'comment' => '微信openid'])
                ->addColumn('unionid', 'string', ['limit' => 64, 'null' => true, 'comment' => '微信unionid'])
                ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'CREATED', 'comment' => 'CREATED/IN_PROGRESS/COMPLETED/EXPIRED/ABANDONED'])
                ->addColumn('progress', 'text', ['null' => false, 'comment' => '动作进度 {action_id:{state,proof_id,completed_at}}'])
                ->addColumn('reward_status', 'string', ['limit' => 20, 'null' => false, 'default' => 'PENDING', 'comment' => 'PENDING/ISSUED/FAILED/SKIPPED'])
                ->addColumn('reward_data', 'text', ['null' => true, 'comment' => '奖励发放结果'])
                ->addColumn('expired_at', 'datetime', ['null' => false, 'comment' => '过期时间'])
                ->addColumn('create_time', 'datetime', ['null' => false, 'comment' => '创建时间'])
                ->addColumn('update_time', 'datetime', ['null' => false, 'comment' => '更新时间'])
                ->addIndex(['user_id'], ['name' => 'idx_user'])
                ->addIndex(['openid'], ['name' => 'idx_openid'])
                ->addIndex(['bundle_id'], ['name' => 'idx_bundle'])
                ->addIndex(['merchant_id'], ['name' => 'idx_merchant'])
                ->addIndex(['status', 'expired_at'], ['name' => 'idx_status_expired'])
                ->create();
        }

        // 完成凭证（信任模式审核）
        if (!$this->hasTable('task_proofs')) {
            $this->table('task_proofs', [
                'id' => 'id',
                'engine' => 'InnoDB',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => '任务完成凭证',
            ])
                ->addColumn('task_instance_id', 'integer', ['null' => false, 'comment' => '任务实例ID'])
                ->addColumn('action_id', 'integer', ['null' => false, 'comment' => '动作ID'])
                ->addColumn('merchant_id', 'integer', ['null' => false, 'comment' => '商家ID'])
                ->addColumn('file_url', 'string', ['limit' => 500, 'null' => false, 'comment' => '凭证文件URL'])
                ->addColumn('file_hash', 'string', ['limit' => 64, 'null' => false, 'comment' => '文件SHA256用于查重'])
                ->addColumn('audit_status', 'string', ['limit' => 20, 'null' => false, 'default' => 'PENDING', 'comment' => 'PENDING/APPROVED/REJECTED'])
                ->addColumn('audit_remark', 'string', ['limit' => 255, 'null' => true, 'comment' => '审核备注'])
                ->addColumn('auditor_id', 'integer', ['null' => true, 'comment' => '审核人ID'])
                ->addColumn('audited_at', 'datetime', ['null' => true, 'comment' => '审核时间'])
                ->addColumn('create_time', 'datetime', ['null' => false, 'comment' => '创建时间'])
                ->addColumn('update_time', 'datetime', ['null' => false, 'comment' => '更新时间'])
                ->addIndex(['task_instance_id'], ['name' => 'idx_instance'])
                ->addIndex(['action_id'], ['name' => 'idx_action'])
                ->addIndex(['file_hash'], ['name' => 'idx_hash'])
                ->addIndex(['audit_status'], ['name' => 'idx_audit'])
                ->create();
        }
    }

    public function down()
    {
        foreach (['task_proofs', 'task_instances', 'task_actions', 'task_bundles'] as $t) {
            if ($this->hasTable($t)) {
                $this->table($t)->drop()->save();
            }
        }
    }
}
