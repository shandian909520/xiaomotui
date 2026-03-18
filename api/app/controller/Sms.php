<?php
namespace app\controller;

use think\Response;

class Sms
{
    // 验证码存储路径
    private function getCodeFile($phone): string
    {
        return runtime_path() . '/sms_' . md5($phone) . '.txt';
    }

    /**
     * 发送验证码
     */
    public function send(): Response
    {
        $phone = request()->post('phone');

        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return json([
                'code' => 400,
                'msg' => '手机号格式不正确',
                'data' => null
            ]);
        }

        // 模拟验证码 (固定为 123456)
        $code = '123456';

        // 使用文件存储验证码
        $file = $this->getCodeFile($phone);
        $data = json_encode(['code' => $code, 'expire' => time() + 300]);
        file_put_contents($file, $data);

        return json([
            'code' => 200,
            'msg' => '验证码已发送',
            'data' => [
                'phone' => $phone,
                'code' => $code
            ]
        ]);
    }

    /**
     * 验证验证码
     */
    public function verify(): Response
    {
        $phone = request()->post('phone');
        $code = request()->post('code');

        $file = $this->getCodeFile($phone);

        if (!file_exists($file)) {
            return json([
                'code' => 400,
                'msg' => '验证码不存在或已过期',
                'data' => null
            ]);
        }

        $data = json_decode(file_get_contents($file), true);

        // 检查是否过期
        if ($data['expire'] < time()) {
            unlink($file);
            return json([
                'code' => 400,
                'msg' => '验证码已过期',
                'data' => null
            ]);
        }

        // 验证码比对
        if ($data['code'] === $code) {
            unlink($file); // 验证成功后删除
            return json([
                'code' => 200,
                'msg' => '验证成功',
                'data' => null
            ]);
        }

        return json([
            'code' => 400,
            'msg' => '验证码错误',
            'data' => null
        ]);
    }
}
