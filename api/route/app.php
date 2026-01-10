<?php
// +----------------------------------------------------------------------
// | 应用路由设置
// +----------------------------------------------------------------------

use think\facade\Route;

// 首页路由
Route::get('/', function () {
    return json([
        'code' => 200,
        'msg' => '欢迎使用小磨推API',
        'data' => [
            'version' => '1.0.0',
            'timestamp' => time(),
        ]
    ]);
});

// 测试AI内容生成API（临时，无需认证）
Route::post('api/test/ai-generate', function () {
    try {
        $wenxinService = new \app\service\WenxinService();
        $result = $wenxinService->generateText([
            'scene' => '咖啡店营销',
            'style' => '温馨',
            'platform' => 'douyin',
            'category' => '餐饮'
        ]);

        return json([
            'code' => 200,
            'message' => 'AI内容生成成功',
            'data' => $result
        ]);
    } catch (\Exception $e) {
        return json([
            'code' => 500,
            'message' => 'AI内容生成失败: ' . $e->getMessage(),
            'data' => null
        ]);
    }
})->middleware([\app\middleware\AllowCrossDomain::class]);

// API首页路由 (精确匹配，移到最后避免拦截其他路由)

// API路由组 - 无需认证的路由
Route::group('api', function () {
    // 认证相关路由（无需认证）
    Route::group('auth', function () {
        Route::post('login', '\app\controller\Auth@login');
        Route::post('register', '\app\controller\Auth@register');
        Route::post('refresh', '\app\controller\Auth@refresh');
        Route::post('send-code', '\app\controller\Auth@sendCode');
        Route::post('phone-login', '\app\controller\Auth@phoneLogin');
        Route::post('wechat_login', '\app\controller\Auth@phoneLogin');  // 微信登录复用手机号登录
    });

    // NFC设备触发（无需认证）
    Route::group('nfc', function () {
        Route::post('trigger', '\app\controller\Nfc@trigger');
        Route::get('device/config', '\app\controller\Nfc@getConfig');
        Route::get('device/status', '\app\controller\Nfc@deviceStatus');
        Route::post('device/batch-status', '\app\controller\Nfc@batchDeviceStatus');
        Route::get('device/health', '\app\controller\Nfc@healthCheck');
        Route::post('device/clear-cache', '\app\controller\Nfc@clearConfigCache');
    });

    // 公共路由（无需认证）
    Route::group('public', function () {
        Route::get('config', 'Public/getConfig');
        Route::post('feedback', 'Public/feedback');
        Route::get('version', 'Public/version');
    });

    // 内容查看（公开内容，无需认证）
    Route::group('content', function () {
        Route::get('view/:id', 'Content/view');
        Route::get('public', 'Content/public');
    });

})->middleware([\app\middleware\AllowCrossDomain::class]);

// API路由组 - 需要认证的路由
Route::group('api', function () {
    // 用户认证后的路由
    Route::group('auth', function () {
        Route::post('logout', '\app\controller\Auth@logout');
        Route::get('info', '\app\controller\Auth@info');
        Route::post('update', '\app\controller\Auth@update');
        Route::post('bind-phone', '\app\controller\Auth@bindPhone');
    });

    // 用户相关路由
    Route::group('user', function () {
        Route::get('profile', 'User/profile');
        Route::post('profile', 'User/updateProfile');
        Route::post('avatar', 'User/updateAvatar');
        Route::post('password', 'User/changePassword');
        Route::get('posts', 'User/getPosts');
        Route::get('followers', 'User/getFollowers');
        Route::get('following', 'User/getFollowing');
    });

    // 内容相关路由
    Route::group('content', function () {
        Route::post('generate', '\app\controller\Content@generate');
        Route::get('task/:task_id/status', '\app\controller\Content@taskStatus');
        Route::post('task/:task_id/regenerate', '\app\controller\Content@regenerate');
        Route::post('task/:task_id/cancel', '\app\controller\Content@cancelTask');
        Route::post('feedback', '\app\controller\Content@submitFeedback');
        Route::get('feedback/stats', '\app\controller\Content@feedbackStats');
        Route::get('templates', '\app\controller\Content@templates');
        Route::get('my', '\app\controller\Content@my');
        Route::put('task/:id', '\app\controller\Content@updateTask');
        Route::delete('task/:id', '\app\controller\Content@deleteTask');
    });

    // 模板管理路由
    Route::group('template', function () {
        Route::get('list', 'TemplateManage/list');
        Route::get('detail/:id', 'TemplateManage/detail');
        Route::post('create', 'TemplateManage/create');
        Route::post('update/:id', 'TemplateManage/update');
        Route::post('delete/:id', 'TemplateManage/delete');
        Route::post('copy/:id', 'TemplateManage/copy');
        Route::get('hot', 'TemplateManage/hot');
        Route::get('categories', 'TemplateManage/categories');
        Route::get('styles', 'TemplateManage/styles');
        Route::get('statistics', 'TemplateManage/statistics');
        Route::post('toggle-status/:id', 'TemplateManage/toggleStatus');
        Route::get('preview/:id', 'TemplateManage/preview');
        Route::post('batch-delete', 'TemplateManage/batchDelete');
    });

    // 平台发布相关路由
    Route::group('publish', function () {
        // 发布任务管理
        Route::post('', 'Publish/publish');
        Route::get('tasks', 'Publish/tasks');
        Route::get('task/:id', 'Publish/taskStatus');
        Route::post('task/:id/retry', 'Publish/retryTask');
        Route::put('task/:id/schedule', 'Publish/updateScheduledTask');
        Route::post('task/:id/cancel', 'Publish/cancelTask');

        // OAuth授权相关
        Route::get('oauth/url/:platform', 'Publish/getPlatformAuthUrl');
        Route::get('oauth/callback/:platform', 'Publish/authCallback');

        // 平台账号管理
        Route::get('accounts', 'Publish/accounts');
        Route::delete('account/:id', 'Publish/deleteAccount');
        Route::post('account/:id/refresh', 'Publish/refreshAccountToken');
    });

    // 平台账号管理
    Route::group('platform', function () {
        Route::group('account', function () {
            Route::get('list', 'Platform/accountList');
            Route::delete(':id', 'Platform/removeAccount');
            Route::post(':id/refresh', 'Platform/refreshToken');
        });
    });

    // 商家功能路由（商家角色专用）
    Route::group('merchant', function () {
        Route::get('info', '\app\controller\Merchant@info');
        Route::post('update', '\app\controller\Merchant@update');
        Route::get('statistics', '\app\controller\Merchant@statistics');

        // 设备管理 - 使用DeviceManage控制器
        Route::group('device', function () {
            // CRUD操作
            Route::get('list', 'DeviceManage/index');
            Route::get(':id', 'DeviceManage/read');
            Route::post('create', 'DeviceManage/create');
            Route::put(':id/update', 'DeviceManage/update');
            Route::delete(':id/delete', 'DeviceManage/delete');

            // 设备绑定
            Route::post(':id/bind', 'DeviceManage/bind');
            Route::post(':id/unbind', 'DeviceManage/unbind');

            // 状态和配置
            Route::put(':id/status', 'DeviceManage/updateStatus');
            Route::put(':id/config', 'DeviceManage/updateConfig');
            Route::get(':id/status', 'DeviceManage/getStatus');

            // 统计和监控
            Route::get(':id/statistics', 'DeviceManage/statistics');
            Route::get(':id/triggers', 'DeviceManage/getTriggerHistory');
            Route::get(':id/health', 'DeviceManage/checkHealth');

            // 批量操作
            Route::post('batch/update', 'DeviceManage/batchUpdate');
            Route::post('batch/delete', 'DeviceManage/batchDelete');
            Route::post('batch/enable', 'DeviceManage/batchEnable');
            Route::post('batch/disable', 'DeviceManage/batchDisable');
        });

        // NFC设备管理
        Route::group('nfc', function () {
            Route::get('devices', 'Nfc/deviceList');
            Route::get('stats', 'Nfc/deviceStats');
            Route::get('trigger-records', 'Merchant/getTriggerRecords');
            Route::get('device/:id/records', 'Merchant/getDeviceTriggerRecords');
            Route::get('device/:id/stats', 'Merchant/getDeviceStats');

            // 团购配置管理
            Route::put('device/:device_id/group-buy', 'Nfc/configureGroupBuy');
            Route::get('device/:device_id/group-buy', 'Nfc/getGroupBuyConfig');
        });

        // 团购统计
        Route::get('group-buy/statistics', 'Nfc/getGroupBuyStatistics');

        // 模板管理
        Route::group('template', function () {
            Route::get('list', 'Merchant/templateList');
            Route::post('create', 'Merchant/createTemplate');
            Route::put(':id', 'Merchant/updateTemplate');
            Route::delete(':id', 'Merchant/deleteTemplate');
        });

        // 优惠券管理
        Route::group('coupon', function () {
            Route::get('list', 'Merchant/couponList');
            Route::post('create', 'Merchant/createCoupon');
            Route::put(':id', 'Merchant/updateCoupon');
            Route::delete(':id', 'Merchant/deleteCoupon');
            Route::get(':id/usage', 'Merchant/couponUsage');
        });
    });

    // 优惠券用户功能
    Route::group('coupon', function () {
        Route::post('receive', 'Coupon/receive');
        Route::get('my', 'Coupon/my');
        Route::post('use', 'Coupon/use');
    });

    // 文件上传路由
    Route::group('upload', function () {
        Route::post('image', 'Upload/image');
        Route::post('video', 'Upload/video');
        Route::post('file', 'Upload/file');
        Route::post('avatar', 'Upload/avatar');
    });

    // AI内容生成路由（需要认证）
    Route::group('ai', function () {
        // 文案生成
        Route::post('generate-text', '\app\controller\AiContent@generateText');
        Route::post('batch-generate', '\app\controller\AiContent@batchGenerateText');

        // 服务管理
        Route::get('status', '\app\controller\AiContent@getStatus');
        Route::get('config', '\app\controller\AiContent@getConfig');
        Route::post('test-connection', '\app\controller\AiContent@testConnection');
        Route::post('clear-cache', '\app\controller\AiContent@clearCache');

        // 辅助接口
        Route::get('styles', '\app\controller\AiContent@getStyles');
        Route::get('platforms', '\app\controller\AiContent@getPlatforms');
    });

    // 统计分析路由（商家专用）
    Route::group('statistics', function () {
        Route::get('dashboard', '\app\controller\Statistics@dashboard');
        Route::get('overview', '\app\controller\Statistics@overview');
        Route::get('devices', '\app\controller\Statistics@deviceStats');
        Route::get('content', '\app\controller\Statistics@contentStats');
        Route::get('publish', '\app\controller\Statistics@publishStats');
        Route::get('users', '\app\controller\Statistics@userStats');
        Route::get('trend', '\app\controller\Statistics@trendAnalysis');
        Route::get('realtime', '\app\controller\Statistics@realtimeMetrics');
        Route::get('export', '\app\controller\Statistics@exportReport');
    });

    // 设备告警路由（需要认证）
    Route::group('alert', function () {
        // 告警列表和管理
        Route::get('list', 'Alert/index');
        Route::get(':id', 'Alert/read');
        Route::post(':id/acknowledge', 'Alert/acknowledge');
        Route::post(':id/resolve', 'Alert/resolve');
        Route::post(':id/ignore', 'Alert/ignore');
        Route::post('batch-action', 'Alert/batchAction');

        // 告警统计
        Route::get('stats', 'Alert/stats');

        // 手动检测告警
        Route::post('check', 'Alert/check');

        // 告警规则管理
        Route::group('rules', function () {
            Route::get('', 'Alert/rules');
            Route::post('update', 'Alert/updateRule');
            Route::post('batch-update', 'Alert/updateBatchRules');
            Route::post('reset', 'Alert/resetRule');
            Route::get('templates', 'Alert/ruleTemplates');
            Route::post('apply-template', 'Alert/applyTemplate');
        });

        // 系统通知
        Route::group('notifications', function () {
            Route::get('', 'Alert/notifications');
            Route::post('mark-read', 'Alert/markAsRead');
            Route::post('clear-read', 'Alert/clearReadNotifications');
        });
    });

    // 智能推荐系统路由（需要认证）
    Route::group('recommendation', function () {
        // 推荐列表
        Route::get('list', '\\app\\controller\\Recommendation@index');
        Route::post('batch', '\\app\\controller\\Recommendation@batch');

        // 用户画像
        Route::get('profile', '\\app\\controller\\Recommendation@profile');

        // 相似度计算
        Route::get('similarity', '\\app\\controller\\Recommendation@similarity');
        Route::get('user-similarity', '\\app\\controller\\Recommendation@userSimilarity');

        // 评估报告
        Route::get('evaluation', '\\app\\controller\\Recommendation@evaluation');
        Route::get('algorithm-comparison', '\\app\\controller\\Recommendation@algorithmComparison');
        Route::get('ab-test', '\\app\\controller\\Recommendation@abTest');
        Route::get('coverage', '\\app\\controller\\Recommendation@coverage');

        // 缓存管理
        Route::get('cache-stats', '\\app\\controller\\Recommendation@cacheStats');
        Route::post('clear-cache', '\\app\\controller\\Recommendation@clearCache');

        // 行为追踪
        Route::post('track', '\\app\\controller\\Recommendation@track');
    });

})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\Auth::class]);

// 管理员路由组（需要管理员权限）
Route::group('admin', function () {
    // 告警监控管理
    Route::group('alert-monitor', function () {
        Route::get('status', function () {
            $monitorService = new \app\service\AlertMonitorService();
            return json([
                'code' => 200,
                'message' => '获取监控状态成功',
                'data' => $monitorService->getMonitorStatus()
            ]);
        });

        Route::post('run', function () {
            $monitorService = new \app\service\AlertMonitorService();
            $result = $monitorService->runMonitorTask();
            return json([
                'code' => 200,
                'message' => '监控任务执行完成',
                'data' => $result
            ]);
        });

        Route::post('cleanup', function () {
            $monitorService = new \app\service\AlertMonitorService();
            $result = $monitorService->runCleanupTask();
            return json([
                'code' => 200,
                'message' => '清理任务执行完成',
                'data' => $result
            ]);
        });

        Route::post('stats', function () {
            $monitorService = new \app\service\AlertMonitorService();
            $result = $monitorService->runStatsTask();
            return json([
                'code' => 200,
                'message' => '统计任务执行完成',
                'data' => $result
            ]);
        });
    });
})->middleware([\app\middleware\AllowCrossDomain::class]);

// 微信小程序专用路由（兼容旧版本）
Route::group('wechat', function () {
    Route::post('login', 'Auth/login');
    Route::post('decrypt', 'Wechat/decrypt');
    Route::get('config', 'Wechat/getConfig');
})->middleware([\app\middleware\AllowCrossDomain::class]);

// 健康检查路由
Route::get('health/check', function () {
    return json([
        'code' => 200,
        'message' => 'OK',
        'data' => [
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => '1.0.0'
        ]
    ]);
});

// API首页路由 (精确匹配，放在最后)
Route::get('api', function () {
    return json([
        'code' => 200,
        'msg' => '小磨推API服务',
        'data' => [
            'version' => '1.0.0',
            'timestamp' => time(),
            'status' => 'running'
        ]
    ]);
});
