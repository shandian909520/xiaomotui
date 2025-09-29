<?php
declare (strict_types = 1);

namespace app\controller;

use think\App;
use think\Request;
use think\Validate;

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
    protected function success(mixed $data = null, string $msg = 'success', int $code = 200): \think\Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 错误响应
     */
    protected function error(string $msg = 'error', int $code = 400, mixed $data = null): \think\Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ]);
    }

    /**
     * 分页响应
     */
    protected function paginate(array $data, int $total, int $page = 1, int $limit = 20): \think\Response
    {
        return $this->success([
            'list' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
    }
}