<?php

use think\migration\Migrator;

class CreateOrdersTable extends Migrator
{
    public function change()
    {
        $table = $this->table('orders', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '支付订单表'
        ]);

        $table
            ->addColumn('order_no', 'string', [
                'limit' => 32,
                'null' => false,
                'comment' => '订单号'
            ])
            ->addColumn('user_id', 'integer', [
                'null' => false,
                'comment' => '用户ID'
            ])
            ->addColumn('merchant_id', 'integer', [
                'null' => false,
                'comment' => '商家ID'
            ])
            ->addColumn('amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => false,
                'default' => '0.00',
                'comment' => '订单金额'
            ])
            ->addColumn('pay_method', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'wechat',
                'comment' => '支付方式 wechat/alipay'
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => '订单状态 pending/paid/refunded/closed'
            ])
            ->addColumn('package_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'basic',
                'comment' => '套餐类型 basic/standard/chain'
            ])
            ->addColumn('duration', 'integer', [
                'null' => false,
                'default' => 1,
                'comment' => '订阅时长(月)'
            ])
            ->addColumn('transaction_id', 'string', [
                'limit' => 64,
                'null' => true,
                'comment' => '第三方交易号'
            ])
            ->addColumn('paid_at', 'datetime', [
                'null' => true,
                'comment' => '支付时间'
            ])
            ->addColumn('create_time', 'datetime', [
                'null' => false,
                'comment' => '创建时间'
            ])
            ->addColumn('update_time', 'datetime', [
                'null' => false,
                'comment' => '更新时间'
            ])
            ->addIndex(['order_no'], ['unique' => true, 'name' => 'uk_order_no'])
            ->addIndex(['user_id'], ['name' => 'idx_user_id'])
            ->addIndex(['merchant_id'], ['name' => 'idx_merchant_id'])
            ->addIndex(['status'], ['name' => 'idx_status'])
            ->addIndex(['pay_method'], ['name' => 'idx_pay_method'])
            ->addIndex(['package_type'], ['name' => 'idx_package_type'])
            ->addIndex(['transaction_id'], ['name' => 'idx_transaction_id'])
            ->addIndex(['create_time'], ['name' => 'idx_create_time'])
            ->addIndex(['merchant_id', 'status'], ['name' => 'idx_merchant_status'])
            ->create();
    }
}
