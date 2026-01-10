<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 是否启用事件
    'with_event'       => true,
    // 开启应用快速访问
    'app_express'      => true,
    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => env('app.show_error_msg', false),

    // 应用调试模式
    'app_debug'        => env('app.debug', true),
    // 应用Trace
    'app_trace'        => env('app.trace', false),
    // 是否支持多应用
    'auto_multi_app'   => false,

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------
    'log'              => [
        // 默认日志记录通道
        'default'      => env('log.channel', 'file'),
        // 日志记录级别
        'level'        => [],
        // 日志类型记录的通道 ['error'=>'email',...]
        'type_channel' => [],
        // 关闭全局日志写入
        'close'        => false,
        // 全局日志处理 支持闭包
        'processor'    => null,

        // 日志通道列表
        'channels'     => [
            'file' => [
                // 日志记录方式
                'type'           => 'File',
                // 日志保存目录
                'path'           => '',
                // 单文件日志写入
                'single'         => false,
                // 独立日志级别
                'apart_level'    => [],
                // 最大日志文件数量
                'max_files'      => 0,
                // 使用JSON格式记录
                'json'           => false,
                // 日志处理
                'processor'      => null,
                // 关闭通道日志写入
                'close'          => false,
                // 日志输出格式化
                'format'         => '[%s][%s] %s',
                // 是否实时写入
                'realtime_write' => false,
            ],
            // 其它日志通道配置
        ],
    ],
];