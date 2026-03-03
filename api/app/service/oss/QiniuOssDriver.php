<?php
declare (strict_types = 1);

namespace app\service\oss;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\UploadBuilder;
use Qiniu\Config;
use Qiniu\Zone;
use think\Exception;

/**
 * 七牛云存储驱动
 *
 * @package app\service\oss
 */
class QiniuOssDriver extends AbstractOssDriver
{
    /**
     * 认证实例
     * @var Auth
     */
    protected Auth $auth;

    /**
     * 上传管理器
     * @var UploadManager
     */
    protected UploadManager $uploadManager;

    /**
     * Bucket管理器
     * @var BucketManager
     */
    protected BucketManager $bucketManager;

    /**
     * Bucket名称
     * @var string
     */
    protected string $bucket = '';

    /**
     * 域名
     * @var string
     */
    protected string $domain = '';

    /**
     * 前缀
     * @var string
     */
    protected string $prefix = '';

    /**
     * 构造函数 - 初始化七牛云客户端
     * @throws Exception
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        parent::__construct($config, $globalConfig);

        $this->bucket = $this->getConfig('bucket', '');
        $this->domain = $this->getConfig('domain', '');
        $this->prefix = $this->getConfig('prefix', 'uploads/');

        if (empty($this->bucket)) {
            throw new Exception('七牛云配置错误: bucket不能为空');
        }

        if (empty($this->domain)) {
            throw new Exception('七牛云配置错误: domain不能为空');
        }

        try {
            $accessKey = $this->getConfig('access_key', '');
            $secretKey = $this->getConfig('secret_key', '');

            if (empty($accessKey) || empty($secretKey)) {
                throw new Exception('七牛云配置错误: access_key或secret_key不能为空');
            }

            // 初始化认证
            $this->auth = new Auth($accessKey, $secretKey);

            // 初始化上传管理器
            $this->uploadManager = new UploadManager();

            // 初始化Bucket管理器
            $this->bucketManager = new BucketManager($this->auth);

            $this->logInfo('七牛云客户端初始化成功', [
                'bucket' => $this->bucket,
                'domain' => $this->domain,
            ]);

        } catch (\Exception $e) {
            throw new Exception('七牛云客户端初始化失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array
     */
    public function upload(string $localFilePath, string $ossPath, array $options = []): array
    {
        try {
            $this->validateFilePath($localFilePath);

            $ossPath = $this->normalizePath($this->prefix . $ossPath);

            $this->logDebug('开始上传文件到七牛云', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
            ]);

            // 生成上传token
            $token = $this->auth->uploadToken($this->bucket);

            // 上传选项
            $uploadOptions = [];
            if (!empty($options['mime_type'])) {
                $uploadOptions['mimeType'] = $options['mime_type'];
            }

            // 执行上传
            list($result, $error) = $this->uploadManager->putFile(
                $token,
                $ossPath,
                $localFilePath,
                $uploadOptions
            );

            if ($error !== null) {
                throw new Exception($error->message());
            }

            $this->logInfo('文件上传到七牛云成功', [
                'oss_path' => $ossPath,
                'file_size' => filesize($localFilePath),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => filesize($localFilePath),
                'hash' => $result['hash'] ?? '',
            ];

        } catch (\Exception $e) {
            $this->logError('上传到七牛云失败', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('上传到七牛云失败: ' . $e->getMessage());
        }
    }

    /**
     * 分片上传大文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array
     */
    public function multipartUpload(string $localFilePath, string $ossPath, array $options = []): array
    {
        try {
            $this->validateFilePath($localFilePath);

            $ossPath = $this->normalizePath($this->prefix . $ossPath);
            $fileSize = filesize($localFilePath);

            $this->logInfo('开始分片上传到七牛云', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'file_size' => $fileSize,
            ]);

            // 七牛云的resumable upload
            $token = $this->auth->uploadToken($this->bucket);
            $uploader = UploadBuilder::build($token, $ossPath, $localFilePath);

            // 设置分片大小
            $chunkSize = $options['chunk_size'] ?? $this->getConfig('chunk_size', 4194304);
            $uploader->chunkSize = $chunkSize;

            // 执行上传
            $result = $uploader->upload();

            if ($result['code'] !== 0) {
                throw new Exception($result['error'] ?? '上传失败');
            }

            $this->logInfo('分片上传到七牛云成功', [
                'oss_path' => $ossPath,
                'file_size' => $fileSize,
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => $fileSize,
            ];

        } catch (\Exception $e) {
            $this->logError('分片上传到七牛云失败', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
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
            $ossPath = $this->normalizePath($this->prefix . $ossPath);

            $error = $this->bucketManager->delete($this->bucket, $ossPath);

            if ($error !== null) {
                throw new Exception($error->message());
            }

            $this->logInfo('七牛云文件删除成功', [
                'oss_path' => $ossPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('删除七牛云文件失败', [
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 批量删除文件
     * @param array $ossPaths OSS文件路径数组
     * @return array
     */
    public function batchDelete(array $ossPaths): array
    {
        try {
            $paths = array_map(function($path) {
                return $this->normalizePath($this->prefix . $path);
            }, $ossPaths);

            $operations = [];
            foreach ($paths as $path) {
                $operations[] = $this->bucketManager->delete($this->bucket, $path);
            }

            list($result, $error) = $this->bucketManager->batch($operations);

            $this->logInfo('批量删除七牛云文件', [
                'count' => count($paths),
            ]);

            // 返回每个文件的结果
            $results = [];
            if (isset($result[0])) {
                foreach ($result as $item) {
                    $results[] = ($item['code'] ?? 0) === 200;
                }
            }

            return $results ?: array_fill(0, count($paths), true);

        } catch (\Exception $e) {
            $this->logError('批量删除七牛云文件失败', [
                'error' => $e->getMessage(),
            ]);
            return array_fill(0, count($ossPaths), false);
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
            $ossPath = $this->normalizePath($this->prefix . $ossPath);
            list($stat, $error) = $this->bucketManager->stat($this->bucket, $ossPath);
            return $error === null;
        } catch (\Exception $e) {
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
        $ossPath = $this->normalizePath($this->prefix . $ossPath);

        $scheme = $this->getConfig('use_https', true) ? 'https' : 'http';
        $baseUrl = $scheme . '://' . rtrim($this->domain, '/') . '/' . $ossPath;

        // 如果需要私有下载,生成签名URL
        if ($expires > 0) {
            return $this->auth->privateDownloadUrl($baseUrl, $expires);
        }

        return $baseUrl;
    }

    /**
     * 生成私有文件的签名URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒)
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $expires = 3600): string
    {
        return $this->getUrl($ossPath, $expires);
    }

    /**
     * 获取文件信息
     * @param string $ossPath OSS文件路径
     * @return array
     */
    public function getFileInfo(string $ossPath): array
    {
        try {
            $ossPath = $this->normalizePath($this->prefix . $ossPath);
            list($stat, $error) = $this->bucketManager->stat($this->bucket, $ossPath);

            if ($error !== null) {
                throw new Exception($error->message());
            }

            return [
                'size' => (int)($stat['fsize'] ?? 0),
                'type' => $stat['mimeType'] ?? '',
                'hash' => $stat['hash'] ?? '',
                'put_time' => $stat['putTime'] ?? 0,
                'metadata' => $stat,
            ];

        } catch (\Exception $e) {
            $this->logError('获取七牛云文件信息失败', [
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
            $prefix = $this->normalizePath($this->prefix . $prefix);

            list($result, $error) = $this->bucketManager->listFiles(
                $this->bucket,
                $prefix,
                $marker,
                $maxKeys
            );

            if ($error !== null) {
                throw new Exception($error->message());
            }

            $files = [];
            if (isset($result['items'])) {
                foreach ($result['items'] as $item) {
                    $files[] = [
                        'key' => $item['key'],
                        'size' => (int)($item['fsize'] ?? 0),
                        'hash' => $item['hash'] ?? '',
                        'mime_type' => $item['mimeType'] ?? '',
                        'put_time' => $item['putTime'] ?? 0,
                    ];
                }
            }

            return [
                'files' => $files,
                'is_truncated' => !empty($result['marker']),
                'next_marker' => $result['marker'] ?? null,
                'prefix' => $prefix,
            ];

        } catch (\Exception $e) {
            $this->logError('列出七牛云文件失败', [
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
            $sourcePath = $this->normalizePath($this->prefix . $sourcePath);
            $destPath = $this->normalizePath($this->prefix . $destPath);

            $error = $this->bucketManager->copy(
                $this->bucket,
                $sourcePath,
                $this->bucket,
                $destPath
            );

            if ($error !== null) {
                throw new Exception($error->message());
            }

            $this->logInfo('七牛云文件复制成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('复制七牛云文件失败', [
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
            $sourcePath = $this->normalizePath($this->prefix . $sourcePath);
            $destPath = $this->normalizePath($this->prefix . $destPath);

            $error = $this->bucketManager->move(
                $this->bucket,
                $sourcePath,
                $this->bucket,
                $destPath
            );

            if ($error !== null) {
                throw new Exception($error->message());
            }

            $this->logInfo('七牛云文件移动成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('移动七牛云文件失败', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
