<?php
declare(strict_types=1);

namespace app\common\service;

use think\Request;

/**
 * Request服务扩展
 */
class RequestService
{
    /**
     * 设置用户信息到请求中
     * @param Request $request
     * @param array $userInfo
     */
    public static function setUserInfo(Request &$request, array $userInfo): void
    {
        $request->userInfo = $userInfo;
        $request->user_id = $userInfo['sub'] ?? null;
        $request->user_info = $userInfo;
    }
}