<?php
declare (strict_types = 1);

namespace app\service\sms\driver;

/**
 * 短信驱动接口
 *
 * 所有短信驱动必须实现此接口
 */
interface SmsDriverInterface
{
    /**
     * 发送短信
     *
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @param array $data 额外参数
     * @return array 返回结果
     * @throws \Exception
     */
    public function send(string $phone, string $code, array $data = []): array;

    /**
     * 获取驱动名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 检查配置是否完整
     *
     * @return bool
     */
    public function checkConfig(): bool;
}
