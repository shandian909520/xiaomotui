<?php
use think\facade\Route;
use think\facade\Cache;

// 短信发送接口（模拟）
Route::post('api/sms/send', function () {
    $phone = request()->post('phone');

    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        return json(['code' => 400, 'msg' => '手机号格式不正确']);
    }

    $code = '123456';
    Cache::set('sms_code:' . $phone, $code, 300);

    return json([
        'code' => 200,
        'msg' => '验证码已发送',
        'data' => ['code' => $code]
    ]);
});

// 短信验证接口
Route::post('api/sms/verify', function () {
    $phone = request()->post('phone');
    $code = request()->post('code');

    $cached = Cache::get('sms_code:' . $phone);

    if ($cached && $cached === $code) {
        Cache::delete('sms_code:' . $phone);
        return json(['code' => 200, 'msg' => '验证成功']);
    }

    return json(['code' => 400, 'msg' => '验证码错误或已过期']);
});
