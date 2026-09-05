<?php
// +----------------------------------------------------------------------
// | 应用路由设置
// +----------------------------------------------------------------------

use think\facade\Route;

// 碰一碰任务引擎路由（顶层注册）
// 注：线上PHP8.3环境对组内变量规则匹配异常，task 相关路由统一在顶层注册，勿移入 api 组
Route::get('api/task/instance/:id', '\app\controller\TaskInstance@read');
Route::post('api/task/instance/:id/action/:action_id/start', '\app\controller\TaskInstance@startAction');
Route::post('api/task/instance/:id/action/:action_id/proof', '\app\controller\TaskInstance@submitProof');
Route::post('api/task/instance/:id/claim-reward', '\app\controller\TaskInstance@claimReward');
Route::get('api/task/callback/wework/:instance_id/:action_id', '\app\controller\TaskCallback@wework');
Route::post('api/task/callback/wework/:instance_id/:action_id', '\app\controller\TaskCallback@wework');
Route::get('api/task/callback/official/:instance_id/:action_id', '\app\controller\TaskCallback@official');
Route::post('api/task/callback/official/:instance_id/:action_id', '\app\controller\TaskCallback@official');

// 碰一碰任务包管理路由（商家鉴权，顶层注册）
Route::group('api/task', function () {
    Route::get('bundle/list', '\app\controller\TaskBundle@index');
    Route::get('bundle/plugins', '\app\controller\TaskBundle@plugins');
    Route::get('bundle/:id', '\app\controller\TaskBundle@read');
    Route::post('bundle/create', '\app\controller\TaskBundle@save');
    Route::put('bundle/:id/update', '\app\controller\TaskBundle@update');
    Route::delete('bundle/:id/delete', '\app\controller\TaskBundle@delete');
    Route::post('bundle/:id/action', '\app\controller\TaskBundle@addAction');
    Route::put('bundle/action/:action_id/update', '\app\controller\TaskBundle@updateAction');
    Route::delete('bundle/action/:action_id/delete', '\app\controller\TaskBundle@deleteAction');
    Route::get('proof/list', '\app\controller\TaskBundle@proofList');
    Route::post('proof/:id/audit', '\app\controller\TaskBundle@proofAudit');
    Route::get('instance/list', '\app\controller\TaskBundle@instanceList');
})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\Auth::class, \app\middleware\OperationLog::class, \app\middleware\ApiThrottle::class]);

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

// API路由组 - 无需认证的路由（应用频率限制）
Route::group('api', function () {
    // 认证相关路由（无需认证，严格限流）
    Route::group('auth', function () {
        Route::post('login', '\app\controller\Auth@login');
        Route::post('register', '\app\controller\Auth@register');
        Route::post('refresh', '\app\controller\Auth@refresh');
        Route::post('send-code', '\app\controller\Auth@sendCode');
        Route::post('phone-login', '\app\controller\Auth@phoneLogin');
        Route::post('wechat_login', '\app\controller\Auth@login');  // 微信登录
    });

    // NFC设备触发（无需认证，标准限流）
    Route::group('nfc', function () {
        Route::post('trigger', '\app\controller\Nfc@trigger');
        Route::get('device/config', '\app\controller\Nfc@getConfig');
        Route::get('device/status', '\app\controller\Nfc@deviceStatus');
        Route::post('device/batch-status', '\app\controller\Nfc@batchDeviceStatus');
        Route::get('device/health', '\app\controller\Nfc@healthCheck');
        Route::post('device/clear-cache', '\app\controller\Nfc@clearConfigCache');
    });

    // 碰一碰任务引擎路由已移至文件顶层注册（见顶部 api/task 规则与 api/task 组）

    // 推广发布确认（无需认证，消费者直接调用）
    Route::group('promo', function () {
        Route::post('confirm-publish', '\app\controller\Promo@confirmPublish');
        Route::get('reward-status', '\app\controller\Promo@rewardStatus');
    });

    // 支付回调（无需认证，微信服务器调用）
    Route::group('payment', function () {
        Route::post('wechat-notify', '\app\controller\Payment@wechatNotify');
    });

    // 公共路由（无需认证）
    Route::group('public', function () {
        Route::get('config', 'PublicController/getConfig');
        Route::post('feedback', 'PublicController/feedback');
        Route::get('version', 'PublicController/version');
    });

    // 兼容前端 index.js 中 nfcApi 路径（仅保留真正面向顾客端 H5 的只读接口）
    // P0 安全修复（2026-09-05）：/api/nfc/devices 与 /api/nfc/devices/:id 原映射到 AdminCompat/stores，
    // 该实现在公开组下会返回全量门店敏感数据（手机号/地址）。已下放到 /api/admin/nfc/stores。
    // /api/merchants POST 同问题，已下放到 /api/admin/merchants（GET 通过 Merchant@list 401 保护）。
    Route::group('nfc', function () {
        // ====== 模块1:聚合页(顾客端 H5/uni-app) ======
        Route::get('aggregation-page', '\app\controller\Nfc@getAggregationPage');
        // ====== 模块5:团购商品列表(顾客端) ======
        Route::get('group-buy-items', '\app\controller\Nfc@getGroupBuyItems');
    });

    // ====== H5 聚合页兼容路径(顾客端,修复断链) ======
    // aggregate.html 历史调用 /api/publish/copywriting 与 /api/wifi/mobileconfig
    Route::group('publish', function () {
        Route::get('copywriting', '\app\controller\Nfc@getPublishCopywriting'); // ?device_id=&rotate_token=
    });
    Route::group('wifi', function () {
        Route::get('mobileconfig', '\app\controller\Nfc@getWifiMobileconfig');  // ?device_code=
    });

    // ====== 模块6:大转盘抽奖(顾客端,匿名 user_hash) ======
    Route::group('lottery', function () {
        Route::get('by-device',   '\app\controller\Lottery@getLotteryByDevice'); // ?device_code=
        Route::post('draw',       '\app\controller\Lottery@draw');              // {activity_id, user_hash, device_id}
        Route::get('my-records',  '\app\controller\Lottery@myRecords');          // ?device_id=&user_hash=&limit=
    });

    // ====== 模块4:点评(顾客端,合规:仅返回草稿 + 埋点) ======
    Route::group('review', function () {
        Route::get('config',      '\app\controller\Review@getReviewConfig');    // ?device_id=
        Route::get('draft',       '\app\controller\Review@getReviewDraft');     // ?device_id=&platform=&count=
        Route::post('action',     '\app\controller\Review@recordReviewAction'); // {device_id, platform, action, draft_index, extra}
    });

    // ====== 模块7:QQ 联系方式(顾客端读 + 公开埋点;写接口在下方鉴权组,Agent C) ======
    Route::group('contact', function () {
        Route::get('qq-config',   '\app\controller\ContactQq@getQqConfig');    // ?device_id=
        Route::post('qq-action',  '\app\controller\ContactQq@recordQqAction'); // {device_id, action, user_hash?}
    });

    // ====== 模块3:文案池(顾客端 rotate 公开) ======
    Route::group('copywriting', function () {
        Route::get('rotate', '\app\controller\CopywritingPool@rotate'); // ?device_id=&scene=&rotate_token=&rotate=0/1
    });

    // ====== Agent E:漏斗埋点(顾客端打点公开) ======
    Route::group('funnel', function () {
        Route::post('record', '\app\controller\Funnel@record'); // {device_id?,user_hash?,step,block,action,meta}
    });

    // 兼容前端 index.js 中 couponApi 路径
    Route::group('coupons', function () {
        Route::get('', 'Coupon/my');
        Route::get('users', 'Coupon/my');
    });

    // 兼容前端 index.js 中 merchantApi 路径（P0 安全修复：2026-09-05 原 GET/POST 在公开组会泄漏商户敏感数据，
    // POST 已移至下方 /api/admin/merchants；GET 已迁回 /api/merchant/list 走 401 鉴权路径）
    Route::group('merchants', function () {
        // 已清空,所有命中将由下方的 Merchant@list (401) 兜底
    });

    // 兼容前端 index.js 中 statsApi 路径
    Route::group('stats', function () {
        Route::get('dashboard', '\app\controller\Statistics@dashboard');
        Route::get('trends', '\app\controller\Statistics@trendAnalysis');
    });

    // 通用路由（无需认证）
    Route::group('common', function () {
        // 短信发送（模拟）
        Route::post('sms/send', function () {
            $phone = request()->post('phone');

            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                return json(['code' => 400, 'msg' => '手机号格式不正确']);
            }

            $code = '123456';
            $file = runtime_path() . '/sms_' . md5($phone) . '.txt';
            $data = json_encode(['code' => $code, 'expire' => time() + 300]);
            file_put_contents($file, $data);

            return json([
                'code' => 200,
                'msg' => '验证码已发送',
                'data' => ['phone' => $phone, 'code' => $code]
            ]);
        });

        // 短信验证
        Route::post('sms/verify', function () {
            $phone = request()->post('phone');
            $code = request()->post('code');

            $file = runtime_path() . '/sms_' . md5($phone) . '.txt';
            if (!file_exists($file)) {
                return json(['code' => 400, 'msg' => '验证码不存在或已过期']);
            }

            $data = json_decode(file_get_contents($file), true);
            if ($data['expire'] < time()) {
                unlink($file);
                return json(['code' => 400, 'msg' => '验证码已过期']);
            }

            if ($data['code'] === $code) {
                unlink($file);
                return json(['code' => 200, 'msg' => '验证成功']);
            }

            return json(['code' => 400, 'msg' => '验证码错误']);
        });
    });

    // 内容查看（公开内容，无需认证）
    Route::group('content', function () {
        Route::get('view/:id', 'Content/view');
        Route::get('public', 'Content/public');
    });

    // 一次性诊断（用完即删）：缺表排查 / 在线迁移 / 前端 dist 写入 pengh5 目录
    Route::group('info', function () {
        Route::get('db-status', 'Info/dbStatus');
        Route::get('run-migrations', 'Info/runMigrations');
        Route::get('discover-frontend-root', 'Info/discoverFrontendRoot');
        Route::post('deploy-admin', 'Info/deployAdmin');
        Route::post('deploy-batch', 'Info/deployBatch');
    });

})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\ApiThrottle::class]);

// API路由组 - 需要认证的路由（应用频率限制）
Route::group('api', function () {
    // 用户认证后的路由
    Route::group('auth', function () {
        Route::post('logout', '\app\controller\Auth@logout');
        Route::get('info', '\app\controller\Auth@info');
        Route::get('userinfo', '\app\controller\Auth@info');  // 兼容前端 /auth/userinfo 调用
        Route::get('permissions', '\app\controller\Auth@permissions');
        Route::post('update', '\app\controller\Auth@update');
        Route::post('bind-phone', '\app\controller\Auth@bindPhone');
    });

    // 账号管理路由
    Route::group('account', function () {
        Route::post('change-password', '\app\controller\Account@changePassword');
        Route::post('activate-card', '\app\controller\Account@activateCard');
        Route::post('switch-version', '\app\controller\Account@switchVersion');
        Route::get('benefits', '\app\controller\Account@benefits');
    });

    // 支付路由（需要认证）
    Route::group('payment', function () {
        Route::post('create-order', '\app\controller\Payment@createOrder');
        Route::post('wechat-pay', '\app\controller\Payment@wechatPay');
        Route::get('order/:id', '\app\controller\Payment@orderDetail');
        Route::get('orders', '\app\controller\Payment@orders');
        Route::get('packages', '\app\controller\Payment@packages');
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
        Route::get('task/:id', '\app\controller\Content@getTaskDetail');  // 获取任务详情
        Route::post('task/:task_id/regenerate', '\app\controller\Content@regenerate');
        Route::post('task/:task_id/cancel', '\app\controller\Content@cancelTask');
        Route::post('feedback', '\app\controller\Content@submitFeedback');
        Route::get('feedback/stats', '\app\controller\Content@feedbackStats');
        Route::get('templates', '\app\controller\Content@templates');
        Route::get('my', '\app\controller\Content@my');
        Route::get('tasks', '\app\controller\Content@my');  // 兼容前端 /content/tasks 调用
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

    // 视频库路由
    Route::group('video-library', function () {
        Route::get('list', 'VideoLibrary/list');
        Route::get('detail/:id', 'VideoLibrary/detail');
        Route::post('create', 'VideoLibrary/create');
        Route::post('use/:id', 'VideoLibrary/useTemplate');
        Route::get('categories', 'VideoLibrary/categories');
        Route::get('filters', 'VideoLibrary/filters');
        Route::get('hot', 'VideoLibrary/hot');
        Route::get('statistics', 'VideoLibrary/statistics');
    });

    // 平台发布相关路由
    Route::group('publish', function () {
        // 发布任务管理
        Route::post('', 'Publish/publish');
        Route::post('create', 'Publish/publish');  // 别名路由，兼容前端调用
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

    // ====== 模块6:抽奖后台管理(商家鉴权) ======
    Route::group('lottery/admin', function () {
        // 活动
        Route::get('activities',           '\app\controller\LotteryAdmin@activityList');
        Route::post('activities',          '\app\controller\LotteryAdmin@createActivity');
        Route::put('activities/:id',       '\app\controller\LotteryAdmin@updateActivity');
        Route::post('activities/:id/toggle','\app\controller\LotteryAdmin@toggleActivity');
        // 奖品
        Route::get('prizes',               '\app\controller\LotteryAdmin@prizes');          // ?activity_id=
        Route::post('prizes',              '\app\controller\LotteryAdmin@createPrize');
        Route::put('prizes/:id',           '\app\controller\LotteryAdmin@updatePrize');
        Route::delete('prizes/:id',        '\app\controller\LotteryAdmin@deletePrize');
        // 记录
        Route::get('records',              '\app\controller\LotteryAdmin@recordList');
        Route::post('records/:id/claim',   '\app\controller\LotteryAdmin@claimRecord');
    });

    // ====== 模块3:文案池后台管理(商家鉴权,Agent C) ======
    Route::group('copywriting/admin', function () {
        Route::get('list',          '\app\controller\CopywritingPool@list');         // ?device_id=&scene=
        Route::post('',             '\app\controller\CopywritingPool@create');       // {device_id,scene,content,weight,sort,status}
        Route::put(':id',           '\app\controller\CopywritingPool@update');       // {content?,weight?,status?,sort?,scene?}
        Route::delete(':id',        '\app\controller\CopywritingPool@delete');
        Route::post('batch-import', '\app\controller\CopywritingPool@batchImport');   // {device_id,scene,weight,lines}
    });

    // ====== 模块4:点评商家后台(商家鉴权,Agent C) ======
    Route::group('review/admin', function () {
        Route::post('config',                '\app\controller\Review@updateConfig');         // {device_id, enabled?, ai_draft_enabled?, default_count?, platforms?}
        Route::get('draft-templates',        '\app\controller\Review@getDraftTemplates');   // ?device_id=&platform=&scope=
        Route::post('draft-template',        '\app\controller\Review@addDraftTemplate');     // {device_id, platform, title, prompt, ...}
        Route::delete('draft-template/:id',  '\app\controller\Review@deleteDraftTemplate');  // ?device_id=
    });

    // ====== 模块7:QQ 联系方式写入(商家鉴权,Agent C;从公开组迁移) ======
    Route::group('contact/admin', function () {
        Route::put('qq-config',   '\app\controller\ContactQq@setQqConfig');    // {device_id, qq_number, ...}
    });

    // ====== 模块5:团购商品后台管理(商家鉴权) ======
    Route::group('groupbuy/admin', function () {
        Route::get('items',                '\app\controller\GroupBuyAdmin@list');
        Route::get('items/:id',            '\app\controller\GroupBuyAdmin@detail');
        Route::post('items',               '\app\controller\GroupBuyAdmin@create');
        Route::put('items/:id',            '\app\controller\GroupBuyAdmin@update');
        Route::delete('items/:id',         '\app\controller\GroupBuyAdmin@delete');
    });

    // ====== Agent E:漏斗后台(商家鉴权) ======
    Route::group('funnel', function () {
        Route::get('funnel',    '\app\controller\Funnel@funnel');         // ?device_id=&date_from=&date_to=
        Route::get('daily',     '\app\controller\Funnel@dailyStat');      // ?device_id=&days=7
        Route::get('merchant',  '\app\controller\Funnel@merchantFunnel'); // ?merchant_id=&date_from=&date_to=
    });

    // ====== Agent E:NFC 设备配置页(商家后台鉴权,3 tab) ======
    Route::group('admin/nfc/device', function () {
        Route::get(':id/config',           '\app\controller\NfcConfig@getConfig');
        Route::put(':id/config',           '\app\controller\NfcConfig@saveConfig');
        Route::get(':id/aggregation',      '\app\controller\NfcConfig@getAggregationSnapshot');
    });

    // 商家功能路由（商家角色专用）
    Route::group('merchant', function () {
        Route::get('list', '\app\controller\Merchant@list');
        Route::get('info', '\app\controller\Merchant@info');
        Route::post('update', '\app\controller\Merchant@update');
        Route::get('statistics', '\app\controller\Merchant@statistics');
        Route::get(':id', '\app\controller\Merchant@read')->pattern(['id' => '\d+']);
        Route::post(':id/approve', '\app\controller\Merchant@approve');
        Route::post(':id/reject', '\app\controller\Merchant@reject');

        // 设备管理 - 使用DeviceManage控制器
        Route::group('device', function () {
            // CRUD操作
            Route::get('list', 'DeviceManage/index');
            Route::get(':id', 'DeviceManage/read')->pattern(['id' => '\d+']);
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

            // 兼容前端 /merchant/device/alerts 路径
            Route::get('alerts', 'AlertController/index');
            Route::put('alerts/:id/resolve', 'AlertController/resolve');
            Route::put('alerts/:id/ignore', 'AlertController/ignore');
            Route::post('alerts/batch/resolve', 'AlertController/batchAction');
            Route::post('alerts/batch/ignore', 'AlertController/batchAction');
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

        // 推广素材管理
        Route::group('promo', function () {
            Route::post('materials', 'PromoMaterial/upload');
            Route::post('materials/batch', 'PromoMaterial/batchUpload');
            Route::get('materials', 'PromoMaterial/list');
            Route::get('materials/stats', 'PromoMaterial/stats');
            Route::put('materials/sort', 'PromoMaterial/sort');
            Route::get('materials/:id', 'PromoMaterial/detail');
            Route::put('materials/:id/status', 'PromoMaterial/updateStatus');
            Route::delete('materials/:id', 'PromoMaterial/delete');

            // 兼容前端 /merchant/promo/templates 路径
            Route::post('templates', 'PromoTemplate/create');
            Route::get('templates', 'PromoTemplate/list');
            Route::get('templates/options', 'PromoTemplate/options');
            Route::get('templates/:id', 'PromoTemplate/detail');
            Route::put('templates/:id', 'PromoTemplate/update');
            Route::put('templates/:id/status', 'PromoTemplate/updateStatus');
            Route::delete('templates/:id', 'PromoTemplate/delete');
            Route::post('templates/:id/generate', 'PromoTemplate/generate');

            // 兼容前端 /merchant/promo/variants 路径
            Route::get('variants', 'PromoVariant/list');
            Route::get('variants/stats', 'PromoVariant/stats');
            Route::get('variants/strategies', 'PromoVariant/strategies');
            Route::get('variants/next', 'PromoVariant/getNext');
            Route::post('variants/batch-delete', 'PromoVariant/batchDelete');
            Route::get('variants/:id', 'PromoVariant/detail');
            Route::post('variants/:id/record-use', 'PromoVariant/recordUse');
            Route::put('variants/:id/status', 'PromoVariant/updateStatus');
            Route::delete('variants/:id', 'PromoVariant/delete');

            // 兼容前端 /merchant/promo/campaigns 路径
            Route::post('campaigns', 'PromoCampaign/create');
            Route::get('campaigns', 'PromoCampaign/list');
            Route::get('campaigns/available-devices', 'PromoCampaign/availableDevices');
            Route::get('campaigns/:id', 'PromoCampaign/detail');
            Route::put('campaigns/:id', 'PromoCampaign/update');
            Route::delete('campaigns/:id', 'PromoCampaign/delete');
            Route::post('campaigns/:id/devices', 'PromoCampaign/bindDevices');
            Route::delete('campaigns/:id/devices/:device_id', 'PromoCampaign/unbindDevice');
            Route::get('campaigns/:id/stats', 'PromoCampaign/getStats');
            Route::get('campaigns/:id/distributions', 'PromoCampaign/distributions');
        });

        // 视频模板管理
        Route::group('promo-template', function () {
            Route::post('create', 'PromoTemplate/create');
            Route::get('list', 'PromoTemplate/list');
            Route::get('options', 'PromoTemplate/options');
            Route::get(':id', 'PromoTemplate/detail');
            Route::put(':id', 'PromoTemplate/update');
            Route::put(':id/status', 'PromoTemplate/updateStatus');
            Route::delete(':id', 'PromoTemplate/delete');
            Route::post(':id/generate', 'PromoTemplate/generate');
        });

        // 视频变体管理
        Route::group('promo-variant', function () {
            Route::get('list', 'PromoVariant/list');
            Route::get('stats', 'PromoVariant/stats');
            Route::get('strategies', 'PromoVariant/strategies');
            Route::get('next', 'PromoVariant/getNext');
            Route::post('batch-delete', 'PromoVariant/batchDelete');
            Route::get(':id', 'PromoVariant/detail');
            Route::post(':id/record-use', 'PromoVariant/recordUse');
            Route::put(':id/status', 'PromoVariant/updateStatus');
            Route::delete(':id', 'PromoVariant/delete');
        });

        // 推广活动管理
        Route::group('promo-campaign', function () {
            Route::post('create', 'PromoCampaign/create');
            Route::get('list', 'PromoCampaign/list');
            Route::get('available-devices', 'PromoCampaign/availableDevices');
            Route::get(':id', 'PromoCampaign/detail');
            Route::put(':id', 'PromoCampaign/update');
            Route::delete(':id', 'PromoCampaign/delete');
            Route::post(':id/devices', 'PromoCampaign/bindDevices');
            Route::delete(':id/devices/:device_id', 'PromoCampaign/unbindDevice');
            Route::get(':id/stats', 'PromoCampaign/getStats');
            Route::get(':id/distributions', 'PromoCampaign/distributions');
        });

        // 碰一碰任务包管理路由已移至文件顶层注册（见 api/task 组，带 Auth 中间件）


        // 推广数据统计
        Route::group('promo-stats', function () {
            Route::get('overview', '\app\controller\PromoStats@overview');
            Route::get('trend', '\app\controller\PromoStats@trend');
            Route::get('platform', '\app\controller\PromoStats@platform');
            Route::get('device-ranking', '\app\controller\PromoStats@deviceRanking');
            Route::get('campaign-comparison', '\app\controller\PromoStats@campaignComparison');
            Route::get('today', '\app\controller\PromoStats@today');
            Route::get('campaign-list', '\app\controller\PromoStats@campaignList');
            Route::get('campaign/:id', '\app\controller\PromoStats@campaignDetail');
        });
    });

    // 剪辑工程管理
    Route::group('clip-project', function () {
        Route::get('list', '\app\controller\ClipProject@list');
        Route::post('create', '\app\controller\ClipProject@create');
        Route::get('detail', '\app\controller\ClipProject@detail');
        Route::post('update', '\app\controller\ClipProject@update');
        Route::post('delete', '\app\controller\ClipProject@delete');
        Route::post('save-as-template', '\app\controller\ClipProject@saveAsTemplate');
        Route::get('my-templates', '\app\controller\ClipProject@myTemplates');

        // 分镜管理
        Route::get('shots', '\app\controller\ClipProject@shotList');
        Route::post('shot/add', '\app\controller\ClipProject@addShot');
        Route::post('shot/update', '\app\controller\ClipProject@updateShot');
        Route::post('shot/delete', '\app\controller\ClipProject@deleteShot');
        Route::post('shot/sort', '\app\controller\ClipProject@sortShots');

        // 配置查询
        Route::get('voice-actors', '\app\controller\ClipProject@voiceActors');
        Route::get('transitions', '\app\controller\ClipProject@transitions');
        Route::get('filters', '\app\controller\ClipProject@filters');
        Route::get('aspect-ratios', '\app\controller\ClipProject@aspectRatios');
        Route::get('frame-rates', '\app\controller\ClipProject@frameRates');

        // 一键成片
        Route::post('generate-auto-shots', '\app\controller\ClipProject@generateAutoShots');
        // 批量混剪
        Route::post('batch-remix', '\app\controller\ClipProject@batchRemix');
        // 批量导出
        Route::post('batch-export', '\app\controller\ClipProject@batchExport');

        // 导出
        Route::post('export', '\app\controller\ClipProject@export');
    });

    // 场景配置矩阵
    Route::group('scene-config', function () {
        Route::get('list', '\app\controller\SceneConfig@list');
        Route::get('detail', '\app\controller\SceneConfig@detail');
        Route::post('save', '\app\controller\SceneConfig@save');
        Route::post('batch-save', '\app\controller\SceneConfig@batchSave');
        Route::post('toggle-status', '\app\controller\SceneConfig@toggleStatus');
        Route::get('platforms', '\app\controller\SceneConfig@platforms');
    });

    // 优惠券用户功能
    Route::group('coupon', function () {
        Route::post('receive', 'Coupon/receive');
        Route::get('my', 'Coupon/my');
        Route::post('use', 'Coupon/use');
    });

    // 文件上传路由（上传限流）
    Route::group('upload', function () {
        Route::post('image', 'Upload/image')->middleware([\app\middleware\ApiThrottle::class, 'upload']);
        Route::post('video', 'Upload/video')->middleware([\app\middleware\ApiThrottle::class, 'upload']);
        Route::post('file', 'Upload/file')->middleware([\app\middleware\ApiThrottle::class, 'upload']);
        Route::post('avatar', 'Upload/avatar')->middleware([\app\middleware\ApiThrottle::class, 'upload']);
    });

    // 素材管理升级路由
    Route::group('material', function () {
        Route::get('folders', 'Material/getFolders');
        Route::post('folder-create', 'Material/createFolder');
        Route::post('folder-rename', 'Material/renameFolder');
        Route::post('folder-delete', 'Material/deleteFolder');
        Route::get('list', 'Material/getList');
        Route::post('move', 'Material/move');
        Route::post('batch-delete', 'Material/batchDelete');
        Route::post('soft-delete', 'Material/softDelete');
        Route::get('trash', 'Material/getTrash');
        Route::post('restore', 'Material/restore');
        Route::post('permanent-delete', 'Material/permanentDelete');
    });

    // AI内容生成路由（需要认证，AI限流）
    Route::group('ai-content', function () {
        // 文案生成
        Route::post('generate-text', '\app\controller\AiContent@generateText');
        Route::post('batch-generate', '\app\controller\AiContent@batchGenerateText');
        Route::get('history', '\app\controller\AiContent@history');

        // 服务管理
        Route::get('status', '\app\controller\AiContent@getStatus');
        Route::get('config', '\app\controller\AiContent@getConfig');
        Route::post('test-connection', '\app\controller\AiContent@testConnection');
        Route::post('clear-cache', '\app\controller\AiContent@clearCache');

        // 辅助接口
        Route::get('styles', '\app\controller\AiContent@getStyles');
        Route::get('platforms', '\app\controller\AiContent@getPlatforms');
    });

    // 智能员工路由（需要认证）
    Route::group('ai-staff', function () {
        Route::get('groups', '\app\controller\AiStaff@groups');
        Route::get('list', '\app\controller\AiStaff@list');
        Route::get('detail', '\app\controller\AiStaff@detail');
        Route::post('create', '\app\controller\AiStaff@create');
        Route::put('update', '\app\controller\AiStaff@update');
        Route::delete('delete', '\app\controller\AiStaff@delete');
        Route::post('assign', '\app\controller\AiStaff@assign');
        Route::get('usage', '\app\controller\AiStaff@usage');
    });

    // 连锁版员工管理路由
    Route::group('employee-stats', function () {
        Route::get('stats-by-employee', 'EmployeeStats/statsByEmployee');
        Route::get('stats-by-store', 'EmployeeStats/statsByStore');
        Route::get('stats-by-task', 'EmployeeStats/statsByTask');
        Route::get('rankings', 'EmployeeStats/rankings');
        Route::get('publish-details', 'EmployeeStats/publishDetails');
    });

    // 红包活动路由
    Route::group('redpacket-activity', function () {
        Route::get('list', 'RedpacketActivity/list');
        Route::get('detail', 'RedpacketActivity/detail');
        Route::post('create', 'RedpacketActivity/create');
        Route::post('update', 'RedpacketActivity/update');
        Route::post('toggle-status', 'RedpacketActivity/toggleStatus');
        Route::get('stats', 'RedpacketActivity/stats');
        Route::get('balance-overview', 'RedpacketActivity/balanceOverview');
    });

    // 话题监控路由
    Route::group('topic-monitor', function () {
        Route::get('list', 'TopicMonitor/list');
        Route::post('add', 'TopicMonitor/add');
        Route::get('detail', 'TopicMonitor/detail');
        Route::post('cancel', 'TopicMonitor/cancel');
        Route::get('daily-trend', 'TopicMonitor/dailyTrend');
    });

    // 门店管理增强路由
    Route::group('store-manage', function () {
        Route::get('list', 'StoreManage/list');
        Route::get('detail', 'StoreManage/detail');
        Route::post('update', 'StoreManage/update');
        Route::post('batch-import', 'StoreManage/batchImport');
        Route::post('batch-import-poi', 'StoreManage/batchImportPoi');
        Route::get('import-status', 'StoreManage/importStatus');
        Route::get('qr-code', 'StoreManage/qrCode');
        Route::get('nfc-path', 'StoreManage/nfcPath');
        Route::post('decoration', 'StoreManage/decoration');
        Route::post('table-sticker', 'StoreManage/tableSticker');
    });

    // 统计分析路由（商家专用，统计限流）
    Route::group('statistics', function () {
        Route::get('dashboard', '\app\controller\Statistics@dashboard');
        Route::get('overview', '\app\controller\Statistics@overview');
        Route::get('device', '\app\controller\Statistics@deviceStats'); // Changed from devices to device
        Route::get('content', '\app\controller\Statistics@contentStats');
        Route::get('publish', '\app\controller\Statistics@publishStats');
        Route::get('users', '\app\controller\Statistics@userStats');
        Route::get('trend', '\app\controller\Statistics@trendAnalysis');
        Route::get('conversion', '\app\controller\Statistics@conversionStats'); // Added
        Route::get('user-behavior', '\app\controller\Statistics@userBehavior'); // Added
        Route::get('realtime', '\app\controller\Statistics@realtimeMetrics');
        Route::get('export', '\app\controller\Statistics@exportReport');
        Route::post('export', '\app\controller\Statistics@exportReport');
        Route::get('insights', '\app\controller\Statistics@insights'); // 兼容前端 statistics.js
        Route::get('alerts', '\app\controller\Statistics@statisticsAlerts'); // 兼容前端 statistics.js
    });

    // 设备告警路由（需要认证）
    Route::group('alert', function () {
        // 告警列表和管理
        Route::get('list', 'AlertController/index');
        Route::get(':id', 'AlertController/read');
        Route::post(':id/acknowledge', 'AlertController/acknowledge');
        Route::post(':id/resolve', 'AlertController/resolve');
        Route::post(':id/ignore', 'AlertController/ignore');
        Route::post('batch-action', 'AlertController/batchAction');

        // 告警统计
        Route::get('stats', 'AlertController/stats');

        // 手动检测告警
        Route::post('check', 'AlertController/check');

        // 告警规则管理
        Route::group('rules', function () {
            Route::get('', 'AlertController/rules');
            Route::post('update', 'AlertController/updateRule');
            Route::post('batch-update', 'AlertController/updateBatchRules');
            Route::post('reset', 'AlertController/resetRule');
            Route::get('templates', 'AlertController/ruleTemplates');
            Route::post('apply-template', 'AlertController/applyTemplate');
        });

        // 系统通知
        Route::group('notifications', function () {
            Route::get('', 'AlertController/notifications');
            Route::post('mark-read', 'AlertController/markAsRead');
            Route::post('clear-read', 'AlertController/clearReadNotifications');
        });
    });

    // 智能推荐系统路由（需要认证）
    Route::group('recommendation', function () {
        // 推荐列表
        Route::get('list', '\app\controller\Recommendation@index');
        Route::post('batch', '\app\controller\Recommendation@batch');

        // 用户画像
        Route::get('profile', '\app\controller\Recommendation@profile');

        // 相似度计算
        Route::get('similarity', '\app\controller\Recommendation@similarity');
        Route::get('user-similarity', '\app\controller\Recommendation@userSimilarity');

        // 评估报告
        Route::get('evaluation', '\app\controller\Recommendation@evaluation');
        Route::get('algorithm-comparison', '\app\controller\Recommendation@algorithmComparison');
        Route::get('ab-test', '\app\controller\Recommendation@abTest');
        Route::get('coverage', '\app\controller\Recommendation@coverage');

        // 缓存管理
        Route::get('cache-stats', '\app\controller\Recommendation@cacheStats');
        Route::post('clear-cache', '\app\controller\Recommendation@clearCache');

        // 行为追踪
        Route::post('track', '\app\controller\Recommendation@track');
    });

    // 通知路由
    Route::group('notification', function () {
        Route::get('list', 'Notification/list');
        Route::get('detail', 'Notification/detail');
        Route::post('mark-read', 'Notification/markRead');
        Route::post('mark-all-read', 'Notification/markAllRead');
        Route::get('unread-count', 'Notification/unreadCount');
        Route::post('create', 'Notification/create');
    });

    // 任务中心路由
    Route::group('user-task', function () {
        Route::get('list', 'UserTask/list');
        Route::get('detail', 'UserTask/detail');
        Route::post('create', 'UserTask/create');
        Route::post('update-progress', 'UserTask/updateProgress');
        Route::post('complete', 'UserTask/complete');
        Route::post('fail', 'UserTask/fail');
        Route::get('summary', 'UserTask/summary');
    });

    // 首页驾驶舱路由
    Route::group('dashboard', function () {
        Route::get('flow-steps', 'Dashboard/flowSteps');
        Route::get('data-stats', 'Dashboard/dataStats');
        Route::get('consumption', 'Dashboard/consumption');
        Route::get('quick-entries', 'Dashboard/quickEntries');
        Route::get('qr-code', 'Dashboard/qrCode');
    });

    // 物料设计场景路由
    Route::group('design-scene', function () {
        Route::get('list', 'DesignScene/list');
        Route::get('detail', 'DesignScene/detail');
        Route::get('templates', 'DesignScene/templates');
        Route::post('preview', 'DesignScene/preview');
        Route::post('generate', 'DesignScene/generate');
    });

    // AI成品库路由
    Route::group('content-library', function () {
        // 统计
        Route::get('statistics', 'ContentLibrary/statistics');

        // 通用：预警邮箱、删除条目
        Route::post(':id/warning-email', 'ContentLibrary/setWarningEmail');
        Route::delete('item/:id', 'ContentLibrary/deleteItem');

        // 视频库
        Route::group('video', function () {
            Route::get('list', 'ContentLibrary/videoList');
            Route::post('create', 'ContentLibrary/videoCreate');
            Route::get(':id', 'ContentLibrary/videoDetail');
            Route::put(':id', 'ContentLibrary/videoUpdate');
            Route::delete(':id', 'ContentLibrary/videoDelete');
            Route::post(':id/add-local', 'ContentLibrary/videoAddLocal');
            Route::post(':id/import', 'ContentLibrary/videoImport');
        });

        // 图文库
        Route::group('graphic', function () {
            Route::get('list', 'ContentLibrary/graphicList');
            Route::post('create', 'ContentLibrary/graphicCreate');
            Route::get(':id', 'ContentLibrary/graphicDetail');
            Route::put(':id', 'ContentLibrary/graphicUpdate');
            Route::delete(':id', 'ContentLibrary/graphicDelete');
            Route::post(':id/add-content', 'ContentLibrary/graphicAddContent');
        });

        // 图片库
        Route::group('image', function () {
            Route::get('list', 'ContentLibrary/imageList');
            Route::post('create', 'ContentLibrary/imageCreate');
            Route::get('detail/:id', 'ContentLibrary/imageDetail');
            Route::put('update/:id', 'ContentLibrary/imageUpdate');
            Route::delete('delete/:id', 'ContentLibrary/imageDelete');
            Route::post(':id/add', 'ContentLibrary/imageAdd');
        });

        // 文案库
        Route::group('text', function () {
            Route::get('list', 'ContentLibrary/textList');
            Route::post('create', 'ContentLibrary/textCreate');
            Route::get('detail/:id', 'ContentLibrary/textDetail');
            Route::put('update/:id', 'ContentLibrary/textUpdate');
            Route::delete('delete/:id', 'ContentLibrary/textDelete');
            Route::post(':id/add', 'ContentLibrary/textAdd');
        });

        // 话题库
        Route::group('topic', function () {
            Route::get('list', 'ContentLibrary/topicList');
            Route::post('create', 'ContentLibrary/topicCreate');
            Route::get('detail/:id', 'ContentLibrary/topicDetail');
            Route::post(':id/add', 'ContentLibrary/topicAdd');
            Route::put(':id/rename', 'ContentLibrary/topicRename');
            Route::delete(':id', 'ContentLibrary/topicDelete');
        });
    });

    // IP黑名单管理路由（管理员专用）
    Route::group('admin/blacklist', function () {
        Route::get('list', '\app\controller\IpBlacklist@index');
        Route::get('overview', '\app\controller\IpBlacklist@overview');
        Route::get('check', '\app\controller\IpBlacklist@check');
        Route::get('stats', '\app\controller\IpBlacklist@stats');
        Route::get('export', '\app\controller\IpBlacklist@export');
        Route::post('add', '\app\controller\IpBlacklist@add');
        Route::post('batch-add', '\app\controller\IpBlacklist@batchAdd');
        Route::post('remove', '\app\controller\IpBlacklist@remove');
        Route::post('batch-remove', '\app\controller\IpBlacklist@batchRemove');
        Route::post('clear', '\app\controller\IpBlacklist@clear');
    });

})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\Auth::class, \app\middleware\OperationLog::class, \app\middleware\ApiThrottle::class]);

// 兼容前端 /api/admin/* 路径的路由（映射到 AdminCompat 控制器）
Route::group('api/admin', function () {
    // 视频任务 (video.js)
    Route::get('video/tasks', 'AdminCompat/videoTasks');
    Route::post('video/tasks', 'AdminCompat/createVideoTask');
    Route::get('video/tasks/:id', 'AdminCompat/videoTaskDetail');
    Route::post('video/tasks/:id/retry', 'AdminCompat/retryVideoTask');

    // 门店 (stores.js)
    Route::get('stores', 'AdminCompat/stores');
    Route::get('stores/simple', 'AdminCompat/storesSimple');
    Route::get('stores/:id', 'AdminCompat/storeDetail');
    Route::post('stores', 'AdminCompat/createStore');
    Route::put('stores/:id', 'AdminCompat/updateStore');
    Route::delete('stores/:id', 'AdminCompat/deleteStore');

    // 商家列表 (merchants.js) —— P0 安全修复 2026-09-05：从公开组迁入鉴权组
    Route::get('merchants', 'Merchant/list');
    Route::post('merchants', 'Merchant/create');
    Route::put('merchants/:id', 'Merchant/update');
    Route::delete('merchants/:id', 'Merchant/delete');

    // 门店 NFC 兼容路径 (前端 nfcApi.devices / devices/:id) —— P0 安全修复 2026-09-05
    Route::group('nfc', function () {
        Route::get('devices', 'AdminCompat/stores');
        Route::get('devices/:id', 'AdminCompat/storeDetail');
    });

    // 任务 (tasks.js)
    Route::get('tasks', 'AdminCompat/tasks');
    Route::get('tasks/:id', 'AdminCompat/taskDetail');
    Route::post('tasks/:id/retry', 'AdminCompat/retryTask');
    Route::post('tasks/:id/cancel', 'AdminCompat/cancelTask');

    // 成品库 (library.js)
    Route::get('library/videos', 'AdminCompat/libraryVideos');
    Route::get('library/images', 'AdminCompat/libraryImages');
    Route::get('library/topics', 'AdminCompat/libraryTopics');
    Route::get('library/stores', 'AdminCompat/libraryStores');
    Route::get('library/platforms', 'AdminCompat/libraryPlatforms');
    Route::delete('library/videos/:id', 'AdminCompat/deleteLibraryVideo');
    Route::delete('library/images/:id', 'AdminCompat/deleteLibraryImage');
    Route::delete('library/topics/:id', 'AdminCompat/deleteLibraryTopic');

    // 监控 (monitor.js)
    Route::get('monitor/topics', 'AdminCompat/monitorTopics');
    Route::get('monitor/topics/:id', 'AdminCompat/monitorTopicDetail');
    Route::get('monitor/topics/:id/trend', 'AdminCompat/monitorTopicTrend');
    Route::get('monitor/topics/:id/export', 'AdminCompat/monitorTopicExport');
    Route::get('monitor/platforms', 'AdminCompat/monitorPlatforms');

    // 物料设计 (design.js)
    Route::get('design/materials', 'AdminCompat/designMaterials');
    Route::get('design/materials/:id', 'AdminCompat/designMaterialDetail');

    // 素材管理 (materials.js)
    Route::get('materials', 'AdminCompat/materials');
    Route::post('materials/upload', 'AdminCompat/materialsUpload');
    Route::put('materials/:id', 'AdminCompat/materialsUpdate');
    Route::delete('materials/:id', 'AdminCompat/materialsDelete');
    Route::delete('materials/batch', 'AdminCompat/materialsBatchDelete');
    Route::get('materials/storage', 'AdminCompat/materialsStorage');

    // 模板列表 (video.js)
    Route::get('templates', 'AdminCompat/templates');

    // 活动 (activity.js)
    Route::get('activity/scenes', 'AdminCompat/activityScenes');
    Route::post('activity/scenes', 'AdminCompat/createActivityScene');
    Route::put('activity/scenes/:id', 'AdminCompat/updateActivityScene');
    Route::delete('activity/scenes/:id', 'AdminCompat/deleteActivityScene');
    Route::put('activity/scenes/:id/toggle', 'AdminCompat/toggleActivityScene');
    Route::get('activity/redpackets', 'AdminCompat/activityRedpackets');
    Route::get('activity/redpackets/balance', 'AdminCompat/redpacketBalance');
    Route::post('activity/redpackets/send', 'AdminCompat/redpacketSend');
    Route::get('activity/redpackets/rules', 'AdminCompat/redpacketRules');
    Route::post('activity/redpackets/rules', 'AdminCompat/redpacketSetRules');
})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\Auth::class, \app\middleware\OperationLog::class, \app\middleware\ApiThrottle::class]);

// 管理员路由组（需要管理员权限，应用管理员限流）
Route::group('api/admin', function () {
    // 用户管理
    Route::get('users', '\app\controller\AdminUser@list');
    Route::put('users/:id/status', '\app\controller\AdminUser@updateStatus');

    // 系统设置
    Route::get('settings', '\app\controller\AdminUser@getSettings');
    Route::put('settings', '\app\controller\AdminUser@updateSettings');

    // 操作日志
    Route::get('operation-logs', '\app\controller\AdminUser@operationLogs');
    Route::get('operation-logs/export', '\app\controller\AdminUser@exportOperationLogs');

    // AI 大模型配置
    Route::group('ai', function () {
        Route::get('config', '\app\controller\AdminAi@getConfig');
        Route::put('config', '\app\controller\AdminAi@updateConfig');
        Route::post('test', '\app\controller\AdminAi@testConnection');
        Route::get('models', '\app\controller\AdminAi@getModels');
    });

    // AI 智能员工管理
    Route::group('ai-staff', function () {
        Route::get('groups', '\app\controller\AdminAi@staffGroups');
        Route::get('list', '\app\controller\AdminAi@staffList');
        Route::post('create', '\app\controller\AdminAi@staffCreate');
        Route::put('update', '\app\controller\AdminAi@staffUpdate');
        Route::delete('delete', '\app\controller\AdminAi@staffDelete');
    });

    // 兼容前端 ai.js 中的 /api/admin/ai/staff 等路径
    Route::group('ai', function () {
        Route::get('staff', '\app\controller\AdminAi@staffList');
        Route::get('staff/:id', '\app\controller\AdminAi@staffDetail');
        Route::post('staff/:id/assign', '\app\controller\AiStaff@assign');
        Route::get('staff/:id/abilities', '\app\controller\AdminAi@staffDetail');
        Route::post('generate', '\app\controller\AiContent@generateText');
    });

    // 卡密管理（管理员专用）
    Route::group('cardkey', function () {
        Route::post('generate', '\app\controller\AdminCardKey@generate');
        Route::get('list', '\app\controller\AdminCardKey@lists');
    });
})->middleware([\app\middleware\AllowCrossDomain::class, \app\middleware\Auth::class, \app\middleware\OperationLog::class, \app\middleware\ApiThrottle::class]);

// 管理员路由组 - 告警监控（旧路由保持兼容）
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


// 加载短信路由
include __DIR__ . '/sms.php';
