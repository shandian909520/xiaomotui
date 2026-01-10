<?php
declare(strict_types=1);

namespace app\middleware;

use think\facade\Log;
use think\Response;

/**
 * 全局错误处理中间件
 * 统一处理应用中的异常和错误
 */
class ErrorHandler
{
    /**
     * 处理请求
     *
     * @param \think\Request $request 请求对象
     * @param \Closure $next 下一个中间件
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * 处理异常
     *
     * @param \Throwable $e 异常对象
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleException(\Throwable $e, $request): Response
    {
        // 记录详细错误日志
        $this->logError($e, $request);

        // 处理各种异常类型
        if ($e instanceof \think\exception\HttpException) {
            return $this->handleHttpException($e, $request);
        }

        if ($e instanceof \think\exception\ValidateException) {
            return $this->handleValidationException($e, $request);
        }

        if ($e instanceof \app\common\exception\BusinessException) {
            return $this->handleBusinessException($e, $request);
        }

        // 处理ClassNotFoundException等特定错误
        if ($e instanceof \Error) {
            return $this->handleError($e, $request);
        }

        // 处理ReflectionException（验证器不存在等）
        if ($e instanceof \ReflectionException) {
            return $this->handleReflectionException($e, $request);
        }

        // 默认错误处理
        return $this->handleGeneralException($e, $request);
    }

    /**
     * 处理HTTP异常
     *
     * @param \think\exception\HttpException $e HTTP异常
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleHttpException(\think\exception\HttpException $e, $request): Response
    {
        $statusCode = $e->getStatusCode();

        // 根据状态码返回相应的错误信息
        $errorMessages = [
            404 => '请求的资源不存在',
            403 => '访问被拒绝',
            401 => '未授权访问',
            405 => '请求方法不被允许',
            500 => '服务器内部错误',
        ];

        $message = $errorMessages[$statusCode] ?? $e->getMessage();

        return json([
            'code' => $statusCode,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], $statusCode);
    }

    /**
     * 处理验证异常
     *
     * @param \think\exception\ValidateException $e 验证异常
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleValidationException(\think\exception\ValidateException $e, $request): Response
    {
        return json([
            'code' => 422,
            'message' => '参数验证失败',
            'data' => [
                'errors' => $e->getData() ?? [],
            ],
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], 422);
    }

    /**
     * 处理业务异常
     *
     * @param \app\common\exception\BusinessException $e 业务异常
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleBusinessException(\app\common\exception\BusinessException $e, $request): Response
    {
        return json([
            'code' => $e->getCode() ?: 400,
            'message' => $e->getMessage(),
            'data' => $e->getData() ?? null,
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], 400);
    }

    /**
     * 处理通用异常
     *
     * @param \Throwable $e 异常对象
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleGeneralException(\Throwable $e, $request): Response
    {
        // 开发环境下返回详细错误信息
        if (env('APP_DEBUG', false)) {
            return json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->map(function ($trace) {
                        return array_only($trace, ['file', 'line', 'function', 'class']);
                    })->toArray(),
                ],
                'timestamp' => time(),
                'path' => $request->pathinfo(),
            ], 500);
        }

        // 生产环境下返回通用错误信息
        return json([
            'code' => 500,
            'message' => '服务器内部错误，请稍后重试',
            'data' => null,
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], 500);
    }

    /**
     * 处理Error类型异常
     *
     * @param \Error $e Error异常
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleError(\Error $e, $request): Response
    {
        $message = $this->isDevelopment() ? $e->getMessage() : '系统内部错误';

        return json([
            'code' => 500,
            'message' => $message,
            'data' => $this->isDevelopment() ? [
                'type' => 'Error',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ] : null,
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], 500);
    }

    /**
     * 处理反射异常
     *
     * @param \ReflectionException $e 反射异常
     * @param \think\Request $request 请求对象
     * @return Response
     */
    protected function handleReflectionException(\ReflectionException $e, $request): Response
    {
        // 通常是因为类或方法不存在
        $message = $this->isDevelopment() ? $e->getMessage() : '请求的资源不存在或服务异常';

        return json([
            'code' => 404,
            'message' => $message,
            'data' => $this->isDevelopment() ? [
                'type' => 'ReflectionException',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ] : null,
            'timestamp' => time(),
            'path' => $request->pathinfo(),
        ], 404);
    }

    /**
     * 记录错误日志
     *
     * @param \Throwable $e 异常对象
     * @param \think\Request $request 请求对象
     */
    protected function logError(\Throwable $e, $request): void
    {
        $context = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'url' => $request->url(true),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('user-agent'),
            'params' => $request->param(),
            'exception_type' => get_class($e),
        ];

        // 获取用户信息（如果有）
        if (isset($request->user) && $request->user) {
            $context['user_id'] = $request->user->id ?? null;
        }

        Log::error('系统异常捕获', $context);
    }

    /**
     * 判断是否为开发环境
     *
     * @return bool
     */
    protected function isDevelopment(): bool
    {
        return env('APP_DEBUG', false);
    }
}