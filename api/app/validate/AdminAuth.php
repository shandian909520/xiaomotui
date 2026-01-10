<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class AdminAuth extends Validate
{
    protected $rule = [
        'username' => 'require|max:50',
        'password' => 'require|min:6|max:100',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.max' => '用户名长度不能超过50个字符',
        'password.require' => '密码不能为空',
        'password.min' => '密码长度不能少于6位',
        'password.max' => '密码长度不能超过100个字符',
    ];

    protected $scene = [
        'login' => ['username', 'password'],
    ];
}
