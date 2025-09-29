<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\AuthService;
use think\Request;

/**
 * 认证控制器
 */
class Auth extends BaseController
{
    protected AuthService $authService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->authService = new AuthService();
    }

    /**
     * 用户登录
     */
    public function login()
    {
        $data = $this->request->post();

        try {
            $this->validate($data, [
                'username' => 'require|length:3,50',
                'password' => 'require|length:6,32',
            ]);

            $result = $this->authService->login($data['username'], $data['password']);

            return $this->success($result, '登录成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 用户注册
     */
    public function register()
    {
        $data = $this->request->post();

        try {
            $this->validate($data, [
                'username' => 'require|length:3,50|unique:user',
                'password' => 'require|length:6,32',
                'email' => 'require|email|unique:user',
                'phone' => 'mobile|unique:user',
            ]);

            $result = $this->authService->register($data);

            return $this->success($result, '注册成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 刷新令牌
     */
    public function refresh()
    {
        $refreshToken = $this->request->post('refresh_token');

        try {
            $result = $this->authService->refreshToken($refreshToken);

            return $this->success($result, '刷新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 用户登出
     */
    public function logout()
    {
        try {
            $token = $this->request->header('Authorization');
            $this->authService->logout($token);

            return $this->success(null, '登出成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取用户信息
     */
    public function info()
    {
        try {
            $userId = $this->request->user_id;
            $userInfo = $this->authService->getUserInfo($userId);

            return $this->success($userInfo);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}