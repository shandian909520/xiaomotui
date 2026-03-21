<?php
use think\facade\Route;

// 短信发送接口（模拟）
Route::post('api/common/sms/send', function () {
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
        'data' => ['code' => $code]
    ]);
});

// 短信验证接口
Route::post('api/common/sms/verify', function () {
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
