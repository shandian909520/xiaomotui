<?php
declare (strict_types = 1);

namespace app;

// 应用请求对象类
class Request extends \think\Request
{
    /**
     * 当前用户ID
     */
    public $user_id = 0;

    /**
     * 当前用户信息
     */
    public $user_info = [];
}