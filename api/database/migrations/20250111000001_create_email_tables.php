<?php

use think\migration\Migrator;
use think\migration\db\Column;

/**
 * 邮件相关表迁移
 * 包含邮件日志表和邮件失败记录表
 */
class CreateEmailTables extends Migrator
{
    /**
     * 执行迁移
     */
    public function up()
    {
        // 创建邮件日志表
        $this->table('email_logs')
            ->addColumn('from', 'string', ['limit' => 255, 'comment' => '发件人邮箱'])
            ->addColumn('to', 'string', ['limit' => 500, 'comment' => '收件人邮箱（多个用逗号分隔）'])
            ->addColumn('cc', 'string', ['limit' => 500, 'null' => true, 'comment' => '抄送邮箱'])
            ->addColumn('bcc', 'string', ['limit' => 500, 'null' => true, 'comment' => '密送邮箱'])
            ->addColumn('subject', 'string', ['limit' => 500, 'comment' => '邮件主题'])
            ->addColumn('body', 'text', ['null' => true, 'comment' => '邮件正文（HTML）'])
            ->addColumn('alt_body', 'text', ['null' => true, 'comment' => '邮件正文（纯文本）'])
            ->addColumn('is_html', 'boolean', ['default' => true, 'comment' => '是否为HTML邮件'])
            ->addColumn('success', 'boolean', ['default' => false, 'comment' => '是否发送成功'])
            ->addColumn('error_message', 'text', ['null' => true, 'comment' => '错误信息'])
            ->addColumn('has_attachment', 'boolean', ['default' => false, 'comment' => '是否有附件'])
            ->addColumn('attachment_count', 'integer', ['default' => 0, 'comment' => '附件数量'])
            ->addColumn('attachments', 'text', ['null' => true, 'comment' => '附件信息（JSON）'])
            ->addColumn('template', 'string', ['limit' => 50, 'null' => true, 'comment' => '使用的模板'])
            ->addColumn('send_time', 'datetime', ['comment' => '发送时间'])
            ->addColumn('duration', 'integer', ['default' => 0, 'comment' => '发送耗时（毫秒）'])
            ->addColumn('create_time', 'datetime', ['comment' => '创建时间'])
            ->addIndex(['to'])
            ->addIndex(['success'])
            ->addIndex(['send_time'])
            ->create();

        // 创建邮件失败记录表
        $this->table('email_failures')
            ->addColumn('to', 'string', ['limit' => 500, 'comment' => '收件人邮箱'])
            ->addColumn('subject', 'string', ['limit' => 500, 'comment' => '邮件主题'])
            ->addColumn('error_message', 'text', ['comment' => '错误信息'])
            ->addColumn('attempts', 'integer', ['default' => 0, 'comment' => '重试次数'])
            ->addColumn('failed_time', 'datetime', ['comment' => '最终失败时间'])
            ->addColumn('email_data', 'text', ['null' => true, 'comment' => '邮件数据（JSON）'])
            ->addColumn('create_time', 'datetime', ['comment' => '创建时间'])
            ->addIndex(['to'])
            ->addIndex(['failed_time'])
            ->create();
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->dropTable('email_logs');
        $this->dropTable('email_failures');
    }
}
