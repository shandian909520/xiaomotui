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

// API路由组
Route::group('api', function () {
    // 认证相关路由
    Route::group('auth', function () {
        Route::post('login', 'auth/login');
        Route::post('register', 'auth/register');
        Route::post('logout', 'auth/logout');
        Route::post('refresh', 'auth/refresh');
        Route::get('info', 'auth/info');
    });

    // 用户相关路由
    Route::group('user', function () {
        Route::get('profile', 'user/profile');
        Route::post('profile', 'user/updateProfile');
        Route::post('avatar', 'user/updateAvatar');
        Route::post('password', 'user/changePassword');
        Route::get('posts', 'user/getPosts');
        Route::get('followers', 'user/getFollowers');
        Route::get('following', 'user/getFollowing');
    });

    // 内容相关路由
    Route::group('content', function () {
        Route::get('posts', 'content/getPosts');
        Route::post('posts', 'content/createPost');
        Route::get('posts/:id', 'content/getPost');
        Route::put('posts/:id', 'content/updatePost');
        Route::delete('posts/:id', 'content/deletePost');
        Route::post('posts/:id/like', 'content/likePost');
        Route::post('posts/:id/share', 'content/sharePost');
        Route::get('posts/:id/comments', 'content/getComments');
        Route::post('posts/:id/comments', 'content/addComment');
    });

    // AI功能相关路由
    Route::group('ai', function () {
        Route::post('generate', 'ai/generate');
        Route::post('optimize', 'ai/optimize');
        Route::post('analyze', 'ai/analyze');
        Route::get('templates', 'ai/getTemplates');
    });

    // 文件上传路由
    Route::group('upload', function () {
        Route::post('image', 'upload/image');
        Route::post('video', 'upload/video');
        Route::post('file', 'upload/file');
    });

    // 统计分析路由
    Route::group('analytics', function () {
        Route::get('overview', 'analytics/overview');
        Route::get('posts', 'analytics/posts');
        Route::get('users', 'analytics/users');
        Route::get('engagement', 'analytics/engagement');
    });

})->middleware(['api_auth', 'api_throttle']);

// 微信小程序相关路由
Route::group('wechat', function () {
    Route::post('login', 'wechat/login');
    Route::post('decrypt', 'wechat/decrypt');
    Route::get('config', 'wechat/getConfig');
});

// 公共路由（无需认证）
Route::group('public', function () {
    Route::get('config', 'public/getConfig');
    Route::post('feedback', 'public/feedback');
    Route::get('version', 'public/version');
});