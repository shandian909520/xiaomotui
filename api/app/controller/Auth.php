<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\AuthService;
use app\service\SmsService;
use app\validate\WechatAuth;
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
     * 微信小程序登录
     * POST /api/auth/login
     */
    public function login()
    {
        $data = $this->request->post();

        try {
            if (!empty($data['username']) && !empty($data['password'])) {
                $this->validate($data, 'AdminAuth.login');

                $result = $this->authService->adminLogin($data['username'], $data['password']);

                return $this->success($result, '登录成功');
            }

            // 微信小程序登录
            $scene = (!empty($data['encrypted_data']) && !empty($data['iv'])) ? 'loginWithUserInfo' : 'login';
            $this->validate($data, 'WechatAuth.' . $scene);

            $result = $this->authService->wechatLogin(
                $data['code'],
                $data['encrypted_data'] ?? '',
                $data['iv'] ?? ''
            );

            return $this->success($result, '登录成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'login_failed');
        }
    }

    /**
     * 用户注册 (微信小程序不需要单独注册，登录时自动创建)
     * 保留此接口作为预留
     */
    public function register()
    {
        return $this->error('微信小程序不需要单独注册，请使用登录接口', 400, 'not_supported');
    }

    /**
     * 刷新令牌
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        $data = $this->request->post();

        try {
            $this->validate($data, 'WechatAuth.refresh');

            $result = $this->authService->refreshToken($data['refresh_token']);

            return $this->success($result, '刷新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 401, 'token_refresh_failed');
        }
    }

    /**
     * 用户登出
     * POST /api/auth/logout
     */
    public function logout()
    {
        try {
            $token = $this->request->header('Authorization');

            if (empty($token)) {
                return $this->error('未找到授权令牌', 401, 'token_missing');
            }

            $this->authService->logout($token);

            return $this->success(null, '登出成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'logout_failed');
        }
    }

    /**
     * 获取用户信息
     * GET /api/auth/info
     * 需要授权访问
     */
    public function info()
    {
        try {
            // 从请求中获取用户ID(由中间件设置)
            $userId = $this->request->user_id ?? null;

            // 检查是否为null而不是!$userId，因为管理员user_id可能为0
            if ($userId === null) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

            // 获取用户信息（支持user_id=0的管理员）
            $userInfo = $this->authService->getUserInfo($userId);

            return $this->success($userInfo, '获取成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'get_user_info_failed');
        }
    }

    /**
     * 更新用户信息
     * POST /api/auth/update
     */
    public function update()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            // 验证更新数据
            $this->validate($data, [
                'nickname' => 'length:1,50',
                'phone' => 'mobile',
                'avatar' => 'url'
            ]);

            $result = $this->authService->updateUserInfo($userId, $data);

            return $this->success($result, '更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'update_failed');
        }
    }

    /**
     * 绑定手机号
     * POST /api/auth/bind-phone
     */
    public function bindPhone()
    {
        $data = $this->request->post();
        $userId = $this->request->user_id ?? null;

        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $this->validate($data, [
                'phone' => 'require|mobile',
                'code' => 'require|length:6,6'
            ]);

            // 创建短信服务实例验证验证码
            $smsService = new SmsService();

            // 验证验证码
            if (!$smsService->verifyCode($data['phone'], $data['code'])) {
                return $this->error('验证码错误或已过期', 400, 'invalid_code');
            }

            $result = $this->authService->bindPhone($userId, $data['phone']);

            return $this->success($result, '绑定成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'bind_phone_failed');
        }
    }

    /**
     * 发送验证码（H5登录）
     * POST /api/auth/send-code
     */
    public function sendCode()
    {
        $data = $this->request->post();

        try {
            // 验证手机号格式
            $this->validate($data, [
                'phone' => 'require|mobile'
            ]);

            // 创建短信服务实例
            $smsService = new SmsService();

            // 发送验证码
            $result = $smsService->sendCode($data['phone']);

            return $this->success($result, '验证码已发送');
        } catch (\Exception $e) {
            // 记录错误日志
            \think\facade\Log::error('发送验证码失败', [
                'phone' => $data['phone'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), 400, 'send_code_failed');
        }
    }

    /**
     * 手机号验证码登录（H5）
     * POST /api/auth/phone-login
     */
    public function phoneLogin()
    {
        $data = $this->request->post();

        try {
            $this->validate($data, [
                'phone' => 'require|mobile',
                'code' => 'require|length:6,6'
            ]);

            // 创建短信服务实例
            $smsService = new SmsService();

            // 验证验证码
            if (!$smsService->verifyCode($data['phone'], $data['code'])) {
                return $this->error('验证码错误或已过期', 400, 'invalid_code');
            }

            // 查找或创建用户
            $result = $this->authService->phoneLogin($data['phone']);

            return $this->success($result, '登录成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'phone_login_failed');
        }
    }
}