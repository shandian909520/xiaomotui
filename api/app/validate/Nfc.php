<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * NFC验证器
 */
class Nfc extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'device_code' => 'require|max:32|alphaNum',
        'trigger_mode' => 'require|in:VIDEO,COUPON,WIFI,CONTACT,MENU,GROUP_BUY',
        'openid' => 'require|length:20,50',
        'status' => 'require|in:0,1,2',
        'battery_level' => 'integer|between:0,100',
        'signal_strength' => 'integer|between:0,100',
        'temperature' => 'float|between:-20,80',
        'location' => 'max:200',
        'error_code' => 'max:20',
        'error_message' => 'max:500',
        'template_id' => 'integer|>:0',
        'merchant_id' => 'integer|>:0'
    ];

    /**
     * 定义错误信息
     */
    protected $message = [
        'device_code.require' => '设备编码不能为空',
        'device_code.max' => '设备编码长度不能超过32个字符',
        'device_code.alphaNum' => '设备编码只能包含字母和数字',
        'trigger_mode.require' => '触发模式不能为空',
        'trigger_mode.in' => '触发模式值无效，必须是VIDEO、COUPON、WIFI、CONTACT、MENU或GROUP_BUY',
        'openid.require' => '用户openid不能为空',
        'openid.length' => '用户openid长度必须在20-50个字符之间',
        'status.require' => '设备状态不能为空',
        'status.in' => '设备状态值无效，必须是0(离线)、1(在线)或2(维护)',
        'battery_level.integer' => '电池电量必须是整数',
        'battery_level.between' => '电池电量必须在0-100之间',
        'signal_strength.integer' => '信号强度必须是整数',
        'signal_strength.between' => '信号强度必须在0-100之间',
        'temperature.float' => '温度必须是数字',
        'temperature.between' => '温度必须在-20至80度之间',
        'location.max' => '位置信息长度不能超过200个字符',
        'error_code.max' => '错误代码长度不能超过20个字符',
        'error_message.max' => '错误消息长度不能超过500个字符',
        'template_id.integer' => '模板ID必须是整数',
        'template_id.>' => '模板ID必须大于0',
        'merchant_id.integer' => '商家ID必须是整数',
        'merchant_id.>' => '商家ID必须大于0'
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'trigger' => ['device_code', 'trigger_mode', 'openid'],
        'deviceStatus' => ['device_code', 'status', 'battery_level', 'signal_strength', 'temperature', 'location', 'error_code', 'error_message'],
        'getConfig' => ['device_code'],
        'create' => ['device_code', 'merchant_id', 'trigger_mode'],
        'update' => ['device_code', 'trigger_mode', 'template_id']
    ];

    /**
     * 自定义验证规则 - 验证设备编码格式
     */
    protected function deviceCode($value, $rule, $data = [])
    {
        // 设备编码格式：字母数字组合，长度8-32位
        if (!preg_match('/^[A-Za-z0-9]{8,32}$/', $value)) {
            return '设备编码格式不正确，应为8-32位字母数字组合';
        }
        return true;
    }

    /**
     * 自定义验证规则 - 验证触发模式
     */
    protected function triggerModeValid($value, $rule, $data = [])
    {
        $validModes = ['VIDEO', 'COUPON', 'WIFI', 'CONTACT', 'MENU', 'GROUP_BUY'];
        if (!in_array(strtoupper($value), $validModes)) {
            return '触发模式无效，支持的模式：' . implode('、', $validModes);
        }
        return true;
    }

    /**
     * 自定义验证规则 - 验证设备状态
     */
    protected function deviceStatus($value, $rule, $data = [])
    {
        $validStatus = [0, 1, 2]; // 0:离线 1:在线 2:维护
        if (!in_array((int)$value, $validStatus)) {
            return '设备状态无效，0-离线，1-在线，2-维护';
        }
        return true;
    }

    /**
     * 自定义验证规则 - 验证openid格式
     */
    protected function openidValid($value, $rule, $data = [])
    {
        // 微信openid通常是28位字符
        if (!preg_match('/^[a-zA-Z0-9_-]{20,50}$/', $value)) {
            return 'OpenID格式不正确';
        }
        return true;
    }

    /**
     * 自定义验证规则 - 验证错误代码格式
     */
    protected function errorCode($value, $rule, $data = [])
    {
        if (empty($value)) {
            return true; // 空值允许，由其他规则处理
        }

        // 错误代码格式：大写字母和数字，可包含下划线
        if (!preg_match('/^[A-Z0-9_]+$/', $value)) {
            return '错误代码格式不正确，应为大写字母、数字和下划线组合';
        }
        return true;
    }

    /**
     * 验证场景定制规则 - 设备触发场景
     */
    public function sceneTrigger()
    {
        return $this->only(['device_code', 'trigger_mode', 'openid'])
                   ->append('device_code', 'deviceCode')
                   ->append('trigger_mode', 'triggerModeValid')
                   ->append('openid', 'openidValid');
    }

    /**
     * 验证场景定制规则 - 设备状态上报场景
     */
    public function sceneDeviceStatus()
    {
        return $this->only(['device_code', 'status', 'battery_level', 'signal_strength', 'temperature', 'location', 'error_code', 'error_message'])
                   ->append('device_code', 'deviceCode')
                   ->append('status', 'deviceStatus')
                   ->append('error_code', 'errorCode');
    }

    /**
     * 验证场景定制规则 - 获取设备配置场景
     */
    public function sceneGetConfig()
    {
        return $this->only(['device_code'])
                   ->append('device_code', 'deviceCode');
    }

    /**
     * 验证场景定制规则 - 创建设备场景
     */
    public function sceneCreate()
    {
        return $this->only(['device_code', 'merchant_id', 'trigger_mode'])
                   ->append('device_code', 'deviceCode')
                   ->append('trigger_mode', 'triggerModeValid');
    }

    /**
     * 验证场景定制规则 - 更新设备场景
     */
    public function sceneUpdate()
    {
        return $this->only(['device_code', 'trigger_mode', 'template_id'])
                   ->append('device_code', 'deviceCode')
                   ->append('trigger_mode', 'triggerModeValid');
    }
}