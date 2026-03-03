<?php
/**
 * 短信服务使用示例
 *
 * 本文件展示了如何使用 SmsService 进行短信发送和验证码验证
 */

declare (strict_types = 1);

namespace app\service\sms;

use app\service\SmsService;

/**
 * 示例1: 基本使用 - 发送验证码
 */
function example1()
{
    try {
        // 创建短信服务实例(使用默认配置的短信服务商)
        $smsService = new SmsService();

        // 发送验证码
        $result = $smsService->sendCode('13800138000');

        // 输出发送结果
        print_r($result);

        // 返回格式:
        // [
        //     'driver' => 'aliyun',           // 使用的短信服务商
        //     'success' => true,              // 发送是否成功
        //     'request_id' => 'xxx',          // 请求ID
        //     'biz_id' => 'xxx',              // 业务ID
        //     'message' => '发送成功',         // 消息
        // ]

        echo "验证码发送成功\n";
    } catch (\Exception $e) {
        echo "验证码发送失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例2: 验证验证码
 */
function example2()
{
    try {
        $smsService = new SmsService();

        // 假设用户输入的验证码是 "123456"
        $userCode = '123456';
        $phone = '13800138000';

        // 验证验证码
        $isValid = $smsService->verifyCode($phone, $userCode);

        if ($isValid) {
            echo "验证码验证成功\n";
            // 验证成功后,验证码会自动删除
        } else {
            echo "验证码错误或已过期\n";
        }
    } catch (\Exception $e) {
        echo "验证失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例3: 验证验证码但不删除(可重复验证)
 */
function example3()
{
    try {
        $smsService = new SmsService();

        $userCode = '123456';
        $phone = '13800138000';

        // 验证验证码但不删除(第三个参数为false)
        $isValid = $smsService->verifyCode($phone, $userCode, false);

        if ($isValid) {
            echo "验证码正确\n";
            // 验证码仍然保留,可以再次验证
        } else {
            echo "验证码错误或已过期\n";
        }
    } catch (\Exception $e) {
        echo "验证失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例4: 手动获取和删除验证码
 */
function example4()
{
    try {
        $smsService = new SmsService();
        $phone = '13800138000';

        // 获取缓存的验证码
        $code = $smsService->getCachedCode($phone);

        if ($code !== null) {
            echo "缓存的验证码: " . $code . "\n";

            // 可以进行自定义验证逻辑
            if ($code === '123456') {
                echo "验证成功\n";

                // 手动删除验证码
                $smsService->deleteCachedCode($phone);
                echo "验证码已删除\n";
            }
        } else {
            echo "验证码不存在或已过期\n";
        }
    } catch (\Exception $e) {
        echo "操作失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例5: 指定短信服务商
 */
function example5()
{
    try {
        // 使用阿里云发送
        $aliyunService = new SmsService('aliyun');
        $result1 = $aliyunService->sendCode('13800138000');
        echo "使用阿里云发送: " . $result1['message'] . "\n";

        // 使用腾讯云发送
        $tencentService = new SmsService('tencent');
        $result2 = $tencentService->sendCode('13800138000');
        echo "使用腾讯云发送: " . $result2['message'] . "\n";
    } catch (\Exception $e) {
        echo "发送失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例6: 在控制器中使用
 */
function example6()
{
    // 假设这是控制器方法
    $request = \think\facade\Request::instance();
    $data = $request->post();

    try {
        // 验证手机号格式
        validate($data, [
            'phone' => 'require|mobile'
        ]);

        // 创建短信服务实例
        $smsService = new SmsService();

        // 发送验证码
        $result = $smsService->sendCode($data['phone']);

        // 返回成功响应
        return json([
            'code' => 200,
            'message' => '验证码已发送',
            'data' => $result
        ]);
    } catch (\Exception $e) {
        // 记录错误日志
        \think\facade\Log::error('发送验证码失败', [
            'phone' => $data['phone'] ?? '',
            'error' => $e->getMessage(),
        ]);

        // 返回错误响应
        return json([
            'code' => 400,
            'message' => $e->getMessage(),
        ]);
    }
}

/**
 * 示例7: 完整的登录流程
 */
function example7()
{
    $request = \think\facade\Request::instance();
    $data = $request->post();
    $smsService = new SmsService();

    try {
        if (isset($data['action']) && $data['action'] === 'send_code') {
            // 步骤1: 发送验证码
            validate($data, [
                'phone' => 'require|mobile'
            ]);

            $result = $smsService->sendCode($data['phone']);

            return json([
                'code' => 200,
                'message' => '验证码已发送',
                'data' => $result
            ]);

        } elseif (isset($data['action']) && $data['action'] === 'login') {
            // 步骤2: 验证验证码并登录
            validate($data, [
                'phone' => 'require|mobile',
                'code' => 'require|length:6,6'
            ]);

            // 验证验证码
            if (!$smsService->verifyCode($data['phone'], $data['code'])) {
                return json([
                    'code' => 400,
                    'message' => '验证码错误或已过期',
                ]);
            }

            // 验证成功,执行登录逻辑
            // ... 执行登录操作 ...

            return json([
                'code' => 200,
                'message' => '登录成功',
                'data' => [
                    'token' => 'xxx',
                    'user' => []
                ]
            ]);
        }
    } catch (\Exception $e) {
        return json([
            'code' => 400,
            'message' => $e->getMessage(),
        ]);
    }
}

/**
 * 示例8: 批量发送(需要循环调用)
 */
function example8()
{
    try {
        $smsService = new SmsService();
        $phones = ['13800138000', '13800138001', '13800138002'];
        $results = [];

        foreach ($phones as $phone) {
            try {
                $result = $smsService->sendCode($phone);
                $results[$phone] = ['success' => true, 'data' => $result];
            } catch (\Exception $e) {
                $results[$phone] = ['success' => false, 'error' => $e->getMessage()];
            }

            // 避免触发频率限制,每次发送间隔1秒
            sleep(1);
        }

        print_r($results);
    } catch (\Exception $e) {
        echo "批量发送失败: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例9: 错误处理
 */
function example9()
{
    $smsService = new SmsService();

    // 场景1: 手机号格式错误
    try {
        $smsService->sendCode('123'); // 格式错误
    } catch (\Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        // 输出: 错误: 手机号码格式不正确
    }

    // 场景2: 发送过于频繁
    try {
        $smsService->sendCode('13800138000');
        $smsService->sendCode('13800138000'); // 立即再次发送
    } catch (\Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        // 输出: 错误: 发送过于频繁,请60秒后再试
    }

    // 场景3: 验证码错误
    try {
        $smsService->sendCode('13800138000');
        $isValid = $smsService->verifyCode('13800138000', '000000'); // 错误的验证码
        echo "验证结果: " . ($isValid ? '成功' : '失败') . "\n";
    } catch (\Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
}

/**
 * 示例10: 配置检查
 */
function example10()
{
    try {
        $smsService = new SmsService();

        // 获取配置信息
        $config = config('sms');

        echo "默认短信服务商: " . $config['default'] . "\n";
        echo "验证码长度: " . $config['code']['length'] . "\n";
        echo "验证码有效期: " . $config['code']['expire'] . "秒\n";
        echo "发送频率限制: " . $config['code']['interval'] . "秒\n";
        echo "每日最大发送次数: " . $config['code']['max_daily'] . "次\n";

        // 检查驱动配置
        echo "\n驱动配置状态:\n";
        echo "阿里云: " . (config('sms.aliyun.access_key_id') ? '已配置' : '未配置') . "\n";
        echo "腾讯云: " . (config('sms.tencent.app_id') ? '已配置' : '未配置') . "\n";
    } catch (\Exception $e) {
        echo "配置检查失败: " . $e->getMessage() . "\n";
    }
}

// 运行示例(仅用于演示,实际使用时请根据需要调用)
// example1();
// example2();
// example3();
// ... 等等
