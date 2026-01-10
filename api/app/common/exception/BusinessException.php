<?php
declare(strict_types=1);

namespace app\common\exception;

use Exception;

/**
 * 业务异常类
 * 用于处理业务逻辑中的异常情况
 */
class BusinessException extends Exception
{
    /**
     * 异常数据
     *
     * @var mixed
     */
    protected $data;

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @param mixed $data 异常数据
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(string $message = "", int $code = 400, $data = null, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * 获取异常数据
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 设置异常数据
     *
     * @param mixed $data 异常数据
     * @return self
     */
    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 创建认证失败异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function authFailed(string $message = '认证失败'): self
    {
        return new self($message, 401);
    }

    /**
     * 创建权限不足异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function forbidden(string $message = '权限不足'): self
    {
        return new self($message, 403);
    }

    /**
     * 创建资源不存在异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function notFound(string $message = '资源不存在'): self
    {
        return new self($message, 404);
    }

    /**
     * 创建参数错误异常
     *
     * @param string $message 错误消息
     * @param mixed $data 验证错误数据
     * @return self
     */
    public static function invalidParameter(string $message = '参数错误', $data = null): self
    {
        return new self($message, 422, $data);
    }

    /**
     * 创建服务不可用异常
     *
     * @param string $message 错误消息
     * @return self
     */
    public static function serviceUnavailable(string $message = '服务不可用'): self
    {
        return new self($message, 503);
    }

    /**
     * 创建操作失败异常
     *
     * @param string $message 错误消息
     * @param mixed $data 相关数据
     * @return self
     */
    public static function operationFailed(string $message = '操作失败', $data = null): self
    {
        return new self($message, 400, $data);
    }

    /**
     * 创建频率限制异常
     *
     * @param string $message 错误消息
     * @param mixed $data 限制信息
     * @return self
     */
    public static function rateLimited(string $message = '请求过于频繁', $data = null): self
    {
        return new self($message, 429, $data);
    }

    /**
     * 转换为数组格式
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'data' => $this->data,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}