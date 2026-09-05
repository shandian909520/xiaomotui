<?php
declare(strict_types=1);

namespace app\controller;

use think\facade\Log;

class Wechat extends BaseController
{
    public function decrypt()
    {
        try {
            $data = $this->request->post();
            $encryptedData = $data['encrypted_data'] ?? '';
            $iv = $data['iv'] ?? '';
            $sessionKey = $data['session_key'] ?? '';

            if (empty($encryptedData) || empty($iv) || empty($sessionKey)) {
                return $this->error('参数不完整', 400);
            }

            $result = openssl_decrypt(
                base64_decode($encryptedData),
                'AES-128-CBC',
                base64_decode($sessionKey),
                OPENSSL_RAW_DATA,
                base64_decode($iv)
            );

            if ($result === false) {
                return $this->error('解密失败', 400, 'decrypt_failed');
            }

            $decrypted = json_decode($result, true);

            return $this->success($decrypted, '解密成功');
        } catch (\Exception $e) {
            Log::error('微信数据解密失败', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('解密失败: ' . $e->getMessage());
        }
    }

    public function getConfig()
    {
        try {
            $config = [
                'appid' => env('wechat.appid', ''),
                'version' => '1.0.0',
            ];

            return $this->success($config, '获取配置成功');
        } catch (\Exception $e) {
            return $this->error('获取配置失败: ' . $e->getMessage());
        }
    }
}
