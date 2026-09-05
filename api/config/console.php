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
        'content:generate-queue' => \app\command\ContentGenerateQueue::class,

        // 定时发布任务
        'publish:scheduled' => \app\command\ScheduledPublish::class,

        // 邮件管理工具
        'email' => \app\command\EmailCommand::class,

        // 创建测试数据
        'create:test-data' => \app\command\CreateTestData::class,

        // 设备监控检查
        'device:monitor:check' => \app\command\DeviceMonitorCheck::class,

        // 超时任务检查
        'check:timeout-task' => \app\command\CheckTimeoutTask::class,

        // 导入下载素材
        'import:materials' => \app\command\ImportDownloadedMaterials::class,

        // 上传视频到七牛云
        'upload:videos' => \app\command\UploadVideosToQiniu::class,

        // 碰一碰任务引擎建表（幂等）
        'task:engine-install' => \app\command\TaskEngineInstall::class,
    ],
];