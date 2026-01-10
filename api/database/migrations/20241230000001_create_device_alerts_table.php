<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDeviceAlertsTable extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('device_alerts', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '设备告警记录表'
        ]);

        $table
            ->addColumn('device_id', 'integer', [
                'null' => false,
                'comment' => '设备ID'
            ])
            ->addColumn('device_code', 'string', [
                'limit' => 32,
                'null' => false,
                'comment' => '设备编码'
            ])
            ->addColumn('merchant_id', 'integer', [
                'null' => false,
                'comment' => '商家ID'
            ])
            ->addColumn('alert_type', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => '告警类型'
            ])
            ->addColumn('alert_level', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => '告警级别'
            ])
            ->addColumn('alert_title', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => '告警标题'
            ])
            ->addColumn('alert_message', 'text', [
                'null' => false,
                'comment' => '告警内容'
            ])
            ->addColumn('alert_data', 'json', [
                'null' => true,
                'comment' => '告警数据'
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => '告警状态'
            ])
            ->addColumn('trigger_time', 'datetime', [
                'null' => false,
                'comment' => '触发时间'
            ])
            ->addColumn('resolve_time', 'datetime', [
                'null' => true,
                'comment' => '解决时间'
            ])
            ->addColumn('resolve_user_id', 'integer', [
                'null' => true,
                'comment' => '解决者ID'
            ])
            ->addColumn('resolve_note', 'text', [
                'null' => true,
                'comment' => '解决备注'
            ])
            ->addColumn('notification_sent', 'integer', [
                'limit' => 1,
                'null' => false,
                'default' => 0,
                'comment' => '是否已发送通知'
            ])
            ->addColumn('notification_channels', 'json', [
                'null' => true,
                'comment' => '通知渠道'
            ])
            ->addColumn('notification_logs', 'json', [
                'null' => true,
                'comment' => '通知日志'
            ])
            ->addColumn('create_time', 'datetime', [
                'null' => false,
                'comment' => '创建时间'
            ])
            ->addColumn('update_time', 'datetime', [
                'null' => false,
                'comment' => '更新时间'
            ])
            ->addIndex(['device_id'], ['name' => 'idx_device_id'])
            ->addIndex(['merchant_id'], ['name' => 'idx_merchant_id'])
            ->addIndex(['alert_type'], ['name' => 'idx_alert_type'])
            ->addIndex(['alert_level'], ['name' => 'idx_alert_level'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['trigger_time'], ['name' => 'idx_trigger_time'])
            ->addIndex(['create_time'], ['name' => 'idx_create_time'])
            ->addIndex(['device_id', 'alert_type', 'status'], ['name' => 'idx_device_type_status'])
            ->addIndex(['merchant_id', 'status'], ['name' => 'idx_merchant_status'])
            ->create();
    }
}