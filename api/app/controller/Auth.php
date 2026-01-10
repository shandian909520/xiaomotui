<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\AuthService;
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

                return $this->success($result, '????');
            }

            // ???????????????
            $scene = (!empty($data['encrypted_data']) && !empty($data['iv'])) ? 'loginWithUserInfo' : 'login';
            $this->validate($data, 'WechatAuth.' . $scene);

            $result = $this->authService->wechatLogin(
                $data['code'],
                $data['encrypted_data'] ?? '',
                $data['iv'] ?? ''
            );

            return $this->success($result, '????');
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

            if (!$userId) {
                return $this->error('用户未登录', 401, 'unauthorized');
            }

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

            $result = $this->authService->bindPhone($userId, $data['phone'], $data['code']);

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
            $this->validate($data, [
                'phone' => 'require|mobile'
            ]);

            // 生成6位数验证码
            $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // 将验证码存储到缓存，有效期5分钟
            $cacheKey = 'sms_code:' . $data['phone'];
            cache($cacheKey, $code, 300);

            // TODO: 实际项目中应该调用短信服务发送验证码
            // 开发环境直接返回验证码（生产环境应该删除此行）
            $responseData = [
                'message' => '验证码已发送',
                'code' => $code // 生产环境删除此行
            ];

            return $this->success($responseData, '验证码已发送');
        } catch (\Exception $e) {
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

            // 测试模式：允许特定验证码直接登录
            $testMode = env('app.debug', false);
            $testCode = '123456';

            if ($testMode && $data['code'] === $testCode) {
                // 测试模式下直接登录
                $result = $this->authService->phoneLogin($data['phone']);
                return $this->success($result, '登录成功(测试模式)');
            }

            // 正常模式：验证验证码
            $cacheKey = 'sms_code:' . $data['phone'];
            $cachedCode = cache($cacheKey);

            if (!$cachedCode || $cachedCode !== $data['code']) {
                return $this->error('验证码错误或已过期', 400, 'invalid_code');
            }

            // 删除已使用的验证码
            cache($cacheKey, null);

            // 查找或创建用户
            $result = $this->authService->phoneLogin($data['phone']);

            return $this->success($result, '登录成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400, 'phone_login_failed');
        }
    }
}