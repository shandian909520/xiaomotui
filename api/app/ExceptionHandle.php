<?php
declare (strict_types = 1);

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制

        // 参数验证错误
        if ($e instanceof ValidateException) {
            return json([
                'code' => 422,
                'msg' => $e->getError(),
                'data' => null,
                'timestamp' => time()
            ], 422);
        }

        // HTTP异常
        if ($e instanceof HttpException && $request->isAjax()) {
            return json([
                'code' => $e->getStatusCode(),
                'msg' => $e->getMessage(),
                'data' => null,
                'timestamp' => time()
            ], $e->getStatusCode());
        }

        // 数据库异常
        if ($e instanceof \think\db\exception\DbException) {
            if (env('app.debug', false)) {
                return json([
                    'code' => 500,
                    'msg' => $e->getMessage(),
                    'data' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace()
                    ],
                    'timestamp' => time()
                ], 500);
            } else {
                return json([
                    'code' => 500,
                    'msg' => '数据库操作失败',
                    'data' => null,
                    'timestamp' => time()
                ], 500);
            }
        }

        // 模型未找到异常
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return json([
                'code' => 404,
                'msg' => '数据不存在',
                'data' => null,
                'timestamp' => time()
            ], 404);
        }

        // JWT相关异常
        if (strpos($e->getMessage(), 'JWT') !== false || strpos($e->getMessage(), 'Token') !== false) {
            return json([
                'code' => 401,
                'msg' => '身份验证失败',
                'data' => null,
                'timestamp' => time()
            ], 401);
        }

        // 其他异常
        if (env('app.debug', false)) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace()
                ],
                'timestamp' => time()
            ], 500);
        } else {
            return json([
                'code' => 500,
                'msg' => '服务器内部错误',
                'data' => null,
                'timestamp' => time()
            ], 500);
        }
    }
}