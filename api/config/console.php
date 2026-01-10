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
    ],
];