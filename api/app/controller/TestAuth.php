<?php
declare(strict_types=1);

namespace app\controller;

use think\Request;

/**
 * 测试控制器 - 用于调试
 */
class TestAuth
{
    public function checkRole(Request $request): \think\Response
    {
        return json([
            'code' => 200,
            'data' => [
                'user_id' => $request->user_id,
                'user_role' => $request->getUserRole(),
                'is_admin' => $request->isAdmin(),
                'user_info' => $request->user_info,
                'jwt_payload' => $request->jwt_payload,
            ],
            'timestamp' => time()
        ]);
    }

    public function checkMerchantAccess(Request $request): \think\Response
    {
        $merchantId = $request->param('merchant_id/d', 0);

        $result = [
            'merchant_id_param' => $merchantId,
            'merchant_id_is_null' => $merchantId === null,
            'merchant_id_is_zero' => $merchantId === 0,
            'user_role' => $request->getUserRole(),
            'is_admin' => $request->isAdmin(),
            'app_debug' => env('APP_DEBUG', false),
        ];

        // 模拟validateMerchantAccess逻辑
        if (env('APP_DEBUG', false) === true) {
            $result['access'] = 'allowed (debug mode)';
        } elseif ($merchantId === null || $merchantId === 0) {
            $result['access'] = 'allowed (null or zero merchant_id)';
        } elseif ($request->isAdmin()) {
            $result['access'] = 'allowed (admin)';
        } else {
            $result['access'] = 'denied';
        }

        return json([
            'code' => 200,
            'data' => $result,
            'timestamp' => time()
        ]);
    }
}
