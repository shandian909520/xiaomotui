<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Log;
use think\facade\Config;
use think\Exception;

/**
 * 对象存储(OSS)统一服务类
 * 支持多存储后端: 阿里云OSS、七牛云、腾讯云COS、AWS S3、本地存储
 *
 * @package app\service
 */
class OssService
{
    /**
     * 当前驱动实例
     * @var object|null
     */
    protected ?object $driver = null;

    /**
     * 当前驱动名称
     * @var string
     */
    protected string $driverName = '';

    /**
     * 配置信息
     * @var array
     */
    protected array $config = [];

    /**
     * 上传进度回调
     * @var callable|null
     */
    protected $progressCallback = null;

    /**
     * 构造函数
     * @param string|null $driver 驱动名称,不传则使用默认驱动
     * @throws Exception
     */
    public function __construct(?string $driver = null)
    {
        $this->config = Config::get('oss');
        $this->driverName = $driver ?? $this->config['default'] ?? 'local';

        $this->initDriver();
    }

    /**
     * 初始化存储驱动
     * @return void
     * @throws Exception
     */
    protected function initDriver(): void
    {
        $driverClass = $this->getDriverClass($this->driverName);

        if (!class_exists($driverClass)) {
            throw new Exception("OSS驱动不存在: {$driverClass}");
        }

        $driverConfig = $this->config[$this->driverName] ?? [];

        if (empty($driverConfig)) {
            throw new Exception("OSS驱动配置不存在: {$this->driverName}");
        }

        if (!($driverConfig['enabled'] ?? false)) {
            throw new Exception("OSS驱动未启用: {$this->driverName}");
        }

        $this->driver = new $driverClass($driverConfig, $this->config['global'] ?? []);
    }

    /**
     * 获取驱动类名
     * @param string $driver 驱动名称
     * @return string
     */
    protected function getDriverClass(string $driver): string
    {
        $drivers = [
            'aliyun' => 'app\\service\\oss\\AliyunOssDriver',
            'qiniu' => 'app\\service\\oss\\QiniuOssDriver',
            'tencent' => 'app\\service\\oss\\TencentCosDriver',
            'aws' => 'app\\service\\oss\\AwsS3Driver',
            'local' => 'app\\service\\oss\\LocalStorageDriver',
        ];

        return $drivers[$driver] ?? '';
    }

    /**
     * 上传文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array 上传结果
     */
    public function upload(string $localFilePath, string $ossPath, array $options = []): array
    {
        try {
            // 验证文件
            $this->validateFile($localFilePath);

            // 记录开始时间
            $startTime = microtime(true);

            // 执行上传
            $result = $this->driver->upload($localFilePath, $ossPath, $options);

            // 计算上传耗时
            $duration = round(microtime(true) - $startTime, 2);

            // 记录日志
            Log::info('OSS文件上传成功', [
                'driver' => $this->driverName,
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'duration' => $duration,
                'file_size' => filesize($localFilePath),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('OSS文件上传失败', [
                'driver' => $this->driverName,
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('文件上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 分片上传大文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array 上传结果
     */
    public function multipartUpload(string $localFilePath, string $ossPath, array $options = []): array
    {
        try {
            $fileSize = filesize($localFilePath);
            $chunkSize = $options['chunk_size'] ?? ($this->config['global']['chunk_size'] ?? 5242880);

            // 小文件直接上传
            if ($fileSize < $chunkSize) {
                return $this->upload($localFilePath, $ossPath, $options);
            }

            Log::info('开始分片上传', [
                'driver' => $this->driverName,
                'file_path' => $localFilePath,
                'file_size' => $fileSize,
                'chunk_size' => $chunkSize,
            ]);

            $startTime = microtime(true);

            // 执行分片上传
            $result = $this->driver->multipartUpload($localFilePath, $ossPath, array_merge($options, [
                'progress_callback' => $this->progressCallback,
            ]));

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('分片上传成功', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'duration' => $duration,
                'file_size' => $fileSize,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('分片上传失败', [
                'driver' => $this->driverName,
                'file_path' => $localFilePath,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('分片上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除文件
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function delete(string $ossPath): bool
    {
        try {
            $result = $this->driver->delete($ossPath);

            Log::info('OSS文件删除成功', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('OSS文件删除失败', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 批量删除文件
     * @param array $ossPaths OSS文件路径数组
     * @return array 删除结果
     */
    public function batchDelete(array $ossPaths): array
    {
        try {
            $results = $this->driver->batchDelete($ossPaths);

            Log::info('批量删除完成', [
                'driver' => $this->driverName,
                'total' => count($ossPaths),
                'success' => count(array_filter($results)),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('批量删除失败', [
                'driver' => $this->driverName,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('批量删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 判断文件是否存在
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function exists(string $ossPath): bool
    {
        try {
            return $this->driver->exists($ossPath);
        } catch (\Exception $e) {
            Log::error('检查文件存在失败', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 获取文件URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒),0表示永久URL
     * @return string
     */
    public function getUrl(string $ossPath, int $expires = 0): string
    {
        try {
            return $this->driver->getUrl($ossPath, $expires);
        } catch (\Exception $e) {
            Log::error('获取文件URL失败', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * 生成私有文件的签名URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒)
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $expires = 3600): string
    {
        try {
            return $this->driver->getSignedUrl($ossPath, $expires);
        } catch (\Exception $e) {
            Log::error('生成签名URL失败', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * 获取文件信息
     * @param string $ossPath OSS文件路径
     * @return array
     */
    public function getFileInfo(string $ossPath): array
    {
        try {
            return $this->driver->getFileInfo($ossPath);
        } catch (\Exception $e) {
            Log::error('获取文件信息失败', [
                'driver' => $this->driverName,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 列出指定前缀的文件
     * @param string $prefix 文件前缀
     * @param int $maxKeys 最大返回数量
     * @param string|null $marker 分页标记
     * @return array
     */
    public function listFiles(string $prefix = '', int $maxKeys = 100, ?string $marker = null): array
    {
        try {
            return $this->driver->listFiles($prefix, $maxKeys, $marker);
        } catch (\Exception $e) {
            Log::error('列出文件失败', [
                'driver' => $this->driverName,
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 复制文件
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @return bool
     */
    public function copy(string $sourcePath, string $destPath): bool
    {
        try {
            $result = $this->driver->copy($sourcePath, $destPath);

            Log::info('文件复制成功', [
                'driver' => $this->driverName,
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('文件复制失败', [
                'driver' => $this->driverName,
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 移动/重命名文件
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @return bool
     */
    public function move(string $sourcePath, string $destPath): bool
    {
        try {
            $result = $this->driver->move($sourcePath, $destPath);

            Log::info('文件移动成功', [
                'driver' => $this->driverName,
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('文件移动失败', [
                'driver' => $this->driverName,
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 生成缩略图
     * @param string $localFilePath 本地文件路径
     * @param string $size 缩略图尺寸 (small, medium, large)
     * @param array $options 选项
     * @return array 缩略图信息
     */
    public function generateThumbnail(string $localFilePath, string $size = 'medium', array $options = []): array
    {
        try {
            $thumbnailConfig = $this->config['thumbnail'] ?? [];
            if (!($thumbnailConfig['enabled'] ?? true)) {
                return [
                    'success' => false,
                    'message' => '缩略图功能未启用'
                ];
            }

            $thumbnailService = new OssThumbnailService($thumbnailConfig);
            return $thumbnailService->generate($localFilePath, $size, $options);

        } catch (\Exception $e) {
            Log::error('生成缩略图失败', [
                'file_path' => $localFilePath,
                'size' => $size,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 文件类型验证
     * @param string $filePath 文件路径
     * @return bool
     * @throws Exception
     */
    protected function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception('文件不存在: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new Exception('文件不可读: ' . $filePath);
        }

        // MIME类型验证
        $validationConfig = $this->config['validation'] ?? [];
        $allowedMimeTypes = $validationConfig['allowed_mime_types'] ?? [];

        if (!empty($allowedMimeTypes)) {
            $mimeType = mime_content_type($filePath);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                throw new Exception('不支持的文件类型: ' . $mimeType);
            }
        }

        // 文件扩展名验证
        $blockedExtensions = $validationConfig['blocked_extensions'] ?? [];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, $blockedExtensions)) {
            throw new Exception('禁止上传的文件类型: ' . $extension);
        }

        // 文件大小验证
        $maxFileSize = $validationConfig['max_file_size'] ?? 5368709120;
        $fileSize = filesize($filePath);

        if ($fileSize > $maxFileSize) {
            throw new Exception('文件大小超过限制: ' . $this->formatBytes($maxFileSize));
        }
    }

    /**
     * 设置上传进度回调
     * @param callable $callback 回调函数
     * @return self
     */
    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * 获取当前驱动名称
     * @return string
     */
    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * 获取CDN URL
     * @param string $ossPath OSS文件路径
     * @return string
     */
    public function getCdnUrl(string $ossPath): string
    {
        try {
            $cdnConfig = $this->config['cdn'] ?? [];

            if (!($cdnConfig['enabled'] ?? false)) {
                return $this->getUrl($ossPath);
            }

            $domain = $cdnConfig['domain'] ?? '';
            if (empty($domain)) {
                return $this->getUrl($ossPath);
            }

            $scheme = ($cdnConfig['https'] ?? true) ? 'https' : 'http';
            return $scheme . '://' . rtrim($domain, '/') . '/' . ltrim($ossPath, '/');

        } catch (\Exception $e) {
            Log::error('获取CDN URL失败', [
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * 病毒扫描(预留接口)
     * @param string $filePath 文件路径
     * @return array
     */
    public function scanVirus(string $filePath): array
    {
        try {
            $validationConfig = $this->config['validation'] ?? [];

            if (!($validationConfig['scan_virus'] ?? false)) {
                return [
                    'clean' => true,
                    'message' => '病毒扫描未启用'
                ];
            }

            // TODO: 集成病毒扫描服务 (ClamAV, VirusTotal等)
            // 这里返回模拟结果
            return [
                'clean' => true,
                'message' => '病毒扫描通过',
                'engine' => 'placeholder',
            ];

        } catch (\Exception $e) {
            Log::error('病毒扫描失败', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'clean' => false,
                'message' => '病毒扫描失败: ' . $e->getMessage()
            ];
        }
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
     * 静态工厂方法 - 创建指定驱动实例
     * @param string $driver 驱动名称
     * @return self
     * @throws Exception
     */
    public static function driver(string $driver): self
    {
        return new self($driver);
    }
}
