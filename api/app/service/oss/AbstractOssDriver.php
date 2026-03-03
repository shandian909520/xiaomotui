<?php
declare (strict_types = 1);

namespace app\service\oss;

use think\facade\Log;
use think\Exception;

/**
 * OSS驱动抽象基类
 * 提供通用的辅助方法
 *
 * @package app\service\oss
 */
abstract class AbstractOssDriver implements OssDriverInterface
{
    /**
     * 驱动配置
     * @var array
     */
    protected array $config = [];

    /**
     * 全局配置
     * @var array
     */
    protected array $globalConfig = [];

    /**
     * 构造函数
     * @param array $config 驱动配置
     * @param array $globalConfig 全局配置
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        $this->config = $config;
        $this->globalConfig = $globalConfig;
    }

    /**
     * 获取配置项
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $this->globalConfig[$key] ?? $default;
    }

    /**
     * 记录调试日志
     * @param string $message 日志消息
     * @param array $context 上下文
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->getConfig('enable_log', true)) {
            Log::debug($message, $context);
        }
    }

    /**
     * 记录信息日志
     * @param string $message 日志消息
     * @param array $context 上下文
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->getConfig('enable_log', true)) {
            Log::info($message, $context);
        }
    }

    /**
     * 记录错误日志
     * @param string $message 日志消息
     * @param array $context 上下文
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * 格式化字节大小
     * @param int $bytes 字节数
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 生成唯一文件名
     * @param string $originalName 原始文件名
     * @param string $extension 文件扩展名
     * @return string
     */
    protected function generateFileName(string $originalName, string $extension): string
    {
        return md5($originalName . time() . uniqid()) . '.' . $extension;
    }

    /**
     * 报告上传进度
     * @param int $uploaded 已上传字节数
     * @param int $total 总字节数
     * @param callable|null $callback 回调函数
     * @return void
     */
    protected function reportProgress(int $uploaded, int $total, ?callable $callback = null): void
    {
        if ($callback && is_callable($callback)) {
            $percentage = $total > 0 ? round(($uploaded / $total) * 100, 2) : 0;
            call_user_func($callback, $uploaded, $total, $percentage);
        }
    }

    /**
     * 验证文件路径
     * @param string $filePath 文件路径
     * @return void
     * @throws Exception
     */
    protected function validateFilePath(string $filePath): void
    {
        if (empty($filePath)) {
            throw new Exception('文件路径不能为空');
        }
    }

    /**
     * 规范化OSS路径
     * @param string $path 文件路径
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // 移除开头的斜杠
        $path = ltrim($path, '/\\');

        // 统一使用正斜杠
        $path = str_replace('\\', '/', $path);

        // 移除多余的斜杠
        $path = preg_replace('#/+#', '/', $path);

        return $path;
    }
}
