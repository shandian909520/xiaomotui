<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'local'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 阿里云OSS
        'oss'    => [
            'type'         => 'oss',
            'accessId'     => env('oss.access_id', ''),
            'accessSecret' => env('oss.access_secret', ''),
            'bucket'       => env('oss.bucket', ''),
            'endpoint'     => env('oss.endpoint', ''),
            'url'          => env('oss.url', ''),
            'prefix'       => env('oss.prefix', ''),
            'isCName'      => env('oss.is_cname', false),
        ],
        // 七牛云
        'qiniu'  => [
            'type'      => 'qiniu',
            'accessKey' => env('qiniu.access_key', ''),
            'secretKey' => env('qiniu.secret_key', ''),
            'bucket'    => env('qiniu.bucket', ''),
            'url'       => env('qiniu.url', ''),
        ],
    ],
];