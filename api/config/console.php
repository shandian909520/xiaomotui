<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------

return [
    // 指令定义
    'commands' => [
        // 数据库健康检查
        'health:database' => \app\command\DatabaseHealthCheck::class,

        // 内容生成队列
        'content:generate' => \app\command\ContentGenerateQueue::class,

        // 定时发布任务
        'publish:scheduled' => \app\command\ScheduledPublish::class,

        // 邮件管理工具
        'email' => \app\command\EmailCommand::class,

        // 创建测试数据
        'create:test-data' => \app\command\CreateTestData::class,
    ],
];