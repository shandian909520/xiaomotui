<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 微信认证验证器
 */
class WechatAuth extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'code' => 'require|length:10,50|wechatCode',
        'encrypted_data' => 'base64|length:0,2048',
        'iv' => 'base64|length:0,50',
        'refresh_token' => 'require|length:20,2048|jwt',
        'phone' => 'mobile',
        'sms_code' => 'require|length:4,6|number',
        'nickname' => 'length:1,50',
        'avatar' => 'url|length:0,500'
    ];

    /**
     * 定义错误信息
     */
    protected $message = [
        'code.require' => '微信code参数不能为空',
        'code.length' => '微信code参数长度不正确',
        'code.wechatCode' => '微信code格式不正确',
        'encrypted_data.base64' => '加密数据必须是有效的Base64格式',
        'encrypted_data.length' => '加密数据长度超出限制',
        'iv.base64' => '初始向量必须是有效的Base64格式',
        'iv.length' => '初始向量长度超出限制',
        'refresh_token.require' => '刷新令牌不能为空',
        'refresh_token.length' => '刷新令牌格式不正确',
        'refresh_token.jwt' => '刷新令牌格式无效',
        'phone.mobile' => '手机号格式不正确',
        'sms_code.require' => '短信验证码不能为空',
        'sms_code.length' => '短信验证码长度不正确',
        'sms_code.number' => '短信验证码必须是数字',
        'nickname.length' => '昵称长度必须在1-50个字符之间',
        'avatar.url' => '头像必须是有效的URL地址',
        'avatar.length' => '头像URL长度超出限制',
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'login' => ['code'],
        'loginWithUserInfo' => ['code', 'encrypted_data', 'iv'],
        'refresh' => ['refresh_token'],
        'bindPhone' => ['phone', 'sms_code'],
        'updateProfile' => ['nickname', 'avatar'],
    ];

    /**
     * 自定义验证规则 - 验证微信code格式
     */
    protected function wechatCode($value, $rule, $data = [])
    {
        // 微信code通常是10-50位字符串，包含字母、数字、下划线、中划线
        if (!preg_match('/^[a-zA-Z0-9_-]{10,50}$/', $value)) {
            return '微信code格式不正确，应为10-50位字母数字组合';
        }
        return true;
    }

    /**
     * 自定义验证规则 - 验证Base64格式
     */
    protected function base64($value, $rule, $data = [])
    {
        if (empty($value)) {
            return true; // 空值允许，由其他规则处理
        }

        // Base64格式验证
        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $value)) {
            return false;
        }

        // 尝试解码验证
        $decoded = base64_decode($value, true);
        return $decoded !== false;
    }

    /**
     * 自定义验证规则 - 验证JWT格式
     */
    protected function jwt($value, $rule, $data = [])
    {
        // JWT格式：header.payload.signature
        $parts = explode('.', $value);
        if (count($parts) !== 3) {
            return 'JWT格式错误，应包含3个部分';
        }

        // 验证每个部分是否为有效的Base64URL编码
        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z0-9_-]*$/', $part)) {
                return 'JWT编码格式错误';
            }
        }

        return true;
    }

    /**
     * 验证手机号是否已绑定
     */
    protected function phoneUnique($value, $rule, $data = [])
    {
        if (empty($value)) {
            return true;
        }

        // 检查手机号是否已被其他用户绑定
        $userId = $data['user_id'] ?? 0;
        $existUser = \app\model\User::where('phone', $value)
            ->where('id', '<>', $userId)
            ->find();

        if ($existUser) {
            return '该手机号已被其他用户绑定';
        }

        return true;
    }

    /**
     * 验证昵称敏感词
     */
    protected function nicknameSafe($value, $rule, $data = [])
    {
        if (empty($value)) {
            return true;
        }

        // 简单的敏感词检查（实际项目中可以接入更完善的内容审核服务）
        $bannedWords = ['admin', '管理员', '系统', '客服', '官方'];

        foreach ($bannedWords as $word) {
            if (stripos($value, $word) !== false) {
                return '昵称包含敏感词汇，请重新输入';
            }
        }

        return true;
    }

    /**
     * 验证场景定制规则
     */
    public function sceneBindPhone()
    {
        return $this->only(['phone', 'sms_code'])
                   ->append('phone', 'phoneUnique');
    }

    /**
     * 验证场景定制规则
     */
    public function sceneUpdateProfile()
    {
        return $this->only(['nickname', 'avatar'])
                   ->append('nickname', 'nicknameSafe');
    }
}
