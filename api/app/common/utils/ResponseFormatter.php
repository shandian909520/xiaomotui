<?php
declare (strict_types = 1);

namespace app\common\utils;

use think\Response;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Config;

/**
 * 统一响应格式化类
 * 用于标准化API响应格式，支持多种响应类型和完整的错误处理
 */
class ResponseFormatter
{
    /**
     * HTTP状态码常量映射
     */
    const HTTP_CODES = [
        200 => '请求成功',
        201 => '创建成功',
        400 => '请求参数错误',
        401 => '未授权',
        403 => '权限不足',
        404 => '资源不存在',
        422 => '数据验证失败',
        429 => '请求频率超限',
        500 => '服务器内部错误'
    ];

    /**
     * xiaomotui平台专用错误码
     */
    const PLATFORM_ERROR_CODES = [
        'NFC_DEVICE_NOT_FOUND' => 'NFC设备未找到',
        'NFC_DEVICE_OFFLINE' => 'NFC设备离线',
        'CONTENT_GENERATING' => '内容生成中',
        'CONTENT_GENERATION_FAILED' => '内容生成失败',
        'PUBLISHING_FAILED' => '发布失败',
        'MERCHANT_NOT_VERIFIED' => '商户未认证',
        'USER_NOT_ACTIVATED' => '用户未激活',
        'ANALYTICS_DATA_UNAVAILABLE' => '分析数据暂不可用'
    ];

    /**
     * 请求开始时间（用于性能监控）
     */
    protected static float $startTime;

    /**
     * 是否启用调试模式
     */
    protected static bool $debug;

    /**
     * 是否启用响应缓存
     */
    protected static bool $cacheEnabled;

    /**
     * 缓存前缀
     */
    protected static string $cachePrefix = 'api_response_';

    /**
     * 初始化
     */
    public static function init(): void
    {
        self::$startTime = microtime(true);
        self::$debug = Config::get('app.debug', false);
        self::$cacheEnabled = Config::get('cache.default', '') !== '';
    }

    /**
     * 成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code HTTP状态码
     * @param array $headers 额外的响应头
     * @return Response
     */
    public static function success(
        mixed $data = null,
        string $message = 'success',
        int $code = 200,
        array $headers = []
    ): Response {
        $response = self::buildResponse($code, $message, $data);
        return self::createJsonResponse($response, $code, $headers);
    }

    /**
     * 错误响应
     *
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param string|null $error 错误代码
     * @param mixed $errors 详细错误信息
     * @param mixed $data 额外数据
     * @param array $headers 响应头
     * @return Response
     */
    public static function error(
        string $message = 'error',
        int $code = 400,
        string $error = null,
        mixed $errors = null,
        mixed $data = null,
        array $headers = []
    ): Response {
        $response = self::buildResponse($code, $message, $data, $error, $errors);

        // 记录错误日志
        self::logError($message, $code, $error, $errors);

        return self::createJsonResponse($response, $code, $headers);
    }

    /**
     * 验证错误响应
     *
     * @param array $errors 验证错误详情
     * @param string $message 主错误消息
     * @return Response
     */
    public static function validationError(
        array $errors,
        string $message = '数据验证失败'
    ): Response {
        return self::error($message, 422, 'validation_failed', $errors);
    }

    /**
     * 分页响应
     *
     * @param array $list 数据列表
     * @param int $total 总记录数
     * @param int $currentPage 当前页
     * @param int $perPage 每页记录数
     * @param string $message 响应消息
     * @return Response
     */
    public static function paginate(
        array $list,
        int $total,
        int $currentPage = 1,
        int $perPage = 20,
        string $message = 'success'
    ): Response {
        $lastPage = ceil($total / $perPage);

        $pagination = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => ($currentPage - 1) * $perPage + 1,
            'to' => min($currentPage * $perPage, $total)
        ];

        $data = [
            'list' => $list,
            'pagination' => $pagination
        ];

        return self::success($data, $message);
    }

    /**
     * 平台专用错误响应
     *
     * @param string $errorCode 平台错误码
     * @param mixed $data 额外数据
     * @param int $httpCode HTTP状态码
     * @return Response
     */
    public static function platformError(
        string $errorCode,
        mixed $data = null,
        int $httpCode = 400
    ): Response {
        $message = self::PLATFORM_ERROR_CODES[$errorCode] ?? '未知错误';
        return self::error($message, $httpCode, $errorCode, null, $data);
    }

    /**
     * NFC设备状态响应
     *
     * @param array $deviceData NFC设备数据
     * @param string $status 设备状态 (online|offline|error)
     * @return Response
     */
    public static function nfcDeviceStatus(array $deviceData, string $status): Response
    {
        $statusMap = [
            'online' => '设备在线',
            'offline' => 'NFC设备离线',
            'error' => '设备错误'
        ];

        $message = $statusMap[$status] ?? '设备状态未知';
        $code = $status === 'offline' ? 503 : 200;

        if ($status === 'offline') {
            return self::platformError('NFC_DEVICE_OFFLINE', $deviceData, $code);
        }

        return self::success($deviceData, $message, $code);
    }

    /**
     * 内容生成状态响应
     *
     * @param string $status 生成状态 (pending|processing|completed|failed)
     * @param mixed $data 相关数据
     * @return Response
     */
    public static function contentGenerationStatus(string $status, mixed $data = null): Response
    {
        $statusMap = [
            'pending' => ['message' => '内容生成等待中', 'code' => 202],
            'processing' => ['message' => '内容生成中', 'code' => 202],
            'completed' => ['message' => '内容生成完成', 'code' => 200],
            'failed' => ['message' => '内容生成失败', 'code' => 500]
        ];

        $config = $statusMap[$status] ?? ['message' => '状态未知', 'code' => 400];

        if ($status === 'failed') {
            return self::platformError('CONTENT_GENERATION_FAILED', $data, $config['code']);
        }

        return self::success($data, $config['message'], $config['code']);
    }

    /**
     * 缓存响应（用于分析数据等重计算场景）
     *
     * @param string $cacheKey 缓存键
     * @param callable $callback 数据获取回调
     * @param int $ttl 缓存时间（秒）
     * @param string $message 响应消息
     * @return Response
     */
    public static function cached(
        string $cacheKey,
        callable $callback,
        int $ttl = 300,
        string $message = 'success'
    ): Response {
        if (!self::$cacheEnabled) {
            $data = $callback();
            return self::success($data, $message);
        }

        $fullKey = self::$cachePrefix . md5($cacheKey);
        $data = Cache::get($fullKey);

        if ($data === false) {
            $data = $callback();
            Cache::set($fullKey, $data, $ttl);
        }

        return self::success($data, $message);
    }

    /**
     * 构建基础响应数组
     *
     * @param int $code HTTP状态码
     * @param string $message 消息
     * @param mixed $data 数据
     * @param string|null $error 错误码
     * @param mixed $errors 详细错误
     * @return array
     */
    protected static function buildResponse(
        int $code,
        string $message,
        mixed $data = null,
        string $error = null,
        mixed $errors = null
    ): array {
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time()
        ];

        // 添加数据（成功或部分错误响应）
        if ($data !== null) {
            $response['data'] = $data;
        }

        // 添加错误信息（错误响应）
        if ($error !== null) {
            $response['error'] = $error;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // 添加性能信息（调试模式）
        if (self::$debug && isset(self::$startTime)) {
            $response['performance'] = [
                'execution_time' => round((microtime(true) - self::$startTime) * 1000, 2) . 'ms',
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
                'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB'
            ];
        }

        return $response;
    }

    /**
     * 创建JSON响应
     *
     * @param array $data 响应数据
     * @param int $status HTTP状态码
     * @param array $headers 响应头
     * @return Response
     */
    protected static function createJsonResponse(array $data, int $status, array $headers = []): Response
    {
        // 默认安全响应头
        $defaultHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];

        // 合并自定义响应头
        $headers = array_merge($defaultHeaders, $headers);

        // 支持CORS（如果配置启用）
        if (Config::get('app.cors_enabled', true)) {
            $headers['Access-Control-Allow-Origin'] = '*';
            $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS';
            $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With';
        }

        return Response::create($data, 'json', $status)->header($headers);
    }

    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param int $code 状态码
     * @param string|null $error 错误码
     * @param mixed $errors 详细错误
     */
    protected static function logError(
        string $message,
        int $code,
        string $error = null,
        mixed $errors = null
    ): void {
        $logData = [
            'message' => $message,
            'code' => $code,
            'error' => $error,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => request()->url(true),
            'request_method' => request()->method(),
            'user_agent' => request()->header('User-Agent', ''),
            'ip' => request()->ip()
        ];

        // 根据错误严重程度选择日志级别
        if ($code >= 500) {
            Log::error('API Error', $logData);
        } elseif ($code >= 400) {
            Log::warning('API Warning', $logData);
        } else {
            Log::info('API Info', $logData);
        }
    }

    /**
     * 获取HTTP状态码对应的默认消息
     *
     * @param int $code HTTP状态码
     * @return string
     */
    public static function getHttpMessage(int $code): string
    {
        return self::HTTP_CODES[$code] ?? '未知状态';
    }

    /**
     * 批量响应（用于批量操作结果）
     *
     * @param array $results 批量结果 [['success' => true/false, 'data' => ..., 'message' => ...], ...]
     * @param string $message 总体消息
     * @return Response
     */
    public static function batch(array $results, string $message = '批量操作完成'): Response
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($results as $result) {
            if ($result['success'] ?? false) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        $summary = [
            'total' => count($results),
            'success' => $successCount,
            'failure' => $failureCount,
            'results' => $results
        ];

        $code = $failureCount > 0 ? 207 : 200; // 207: Multi-Status

        return self::success($summary, $message, $code);
    }
}