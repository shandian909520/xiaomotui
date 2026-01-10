<?php
declare (strict_types = 1);

namespace app\controller;

use think\App;
use think\Request;
use think\Validate;
use app\common\utils\ResponseFormatter;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     */
    protected Request $request;

    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 是否批量验证
     */
    protected bool $batchValidate = false;

    /**
     * 控制器中间件
     */
    protected array $middleware = [];

    /**
     * 构造方法
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 初始化ResponseFormatter
        ResponseFormatter::init();

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化
     */
    protected function initialize(): void
    {}

    /**
     * 验证数据
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false): bool
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * 成功响应
     */
    protected function success(mixed $data = null, string $message = 'success', int $code = 200, array $headers = []): \think\Response
    {
        return ResponseFormatter::success($data, $message, $code, $headers);
    }

    /**
     * 错误响应
     */
    protected function error(string $message = 'error', int $code = 400, string $error = null, mixed $errors = null, mixed $data = null, array $headers = []): \think\Response
    {
        return ResponseFormatter::error($message, $code, $error, $errors, $data, $headers);
    }

    /**
     * 分页响应
     */
    protected function paginate(array $list, int $total, int $currentPage = 1, int $perPage = 20, string $message = 'success'): \think\Response
    {
        return ResponseFormatter::paginate($list, $total, $currentPage, $perPage, $message);
    }

    /**
     * 验证错误响应
     */
    protected function validationError(array $errors, string $message = '数据验证失败'): \think\Response
    {
        return ResponseFormatter::validationError($errors, $message);
    }

    /**
     * 平台专用错误响应
     */
    protected function platformError(string $errorCode, mixed $data = null, int $httpCode = 400): \think\Response
    {
        return ResponseFormatter::platformError($errorCode, $data, $httpCode);
    }

    /**
     * NFC设备状态响应
     */
    protected function nfcDeviceStatus(array $deviceData, string $status): \think\Response
    {
        return ResponseFormatter::nfcDeviceStatus($deviceData, $status);
    }

    /**
     * 内容生成状态响应
     */
    protected function contentGenerationStatus(string $status, mixed $data = null): \think\Response
    {
        return ResponseFormatter::contentGenerationStatus($status, $data);
    }

    /**
     * 批量操作响应
     */
    protected function batchResponse(array $results, string $message = '批量操作完成'): \think\Response
    {
        return ResponseFormatter::batch($results, $message);
    }

    /**
     * 缓存响应（用于重计算场景）
     */
    protected function cachedResponse(string $cacheKey, callable $callback, int $ttl = 300, string $message = 'success'): \think\Response
    {
        return ResponseFormatter::cached($cacheKey, $callback, $ttl, $message);
    }
}