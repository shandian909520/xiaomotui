<?php
declare (strict_types = 1);

namespace app\service\oss;

use OSS\OssClient;
use OSS\Core\OssException;
use think\Exception;

/**
 * 阿里云OSS驱动
 *
 * @package app\service\oss
 */
class AliyunOssDriver extends AbstractOssDriver
{
    /**
     * OSS客户端实例
     * @var OssClient|null
     */
    protected ?OssClient $client = null;

    /**
     * Bucket名称
     * @var string
     */
    protected string $bucket = '';

    /**
     * 前缀
     * @var string
     */
    protected string $prefix = '';

    /**
     * CDN域名
     * @var string
     */
    protected string $cdnDomain = '';

    /**
     * 构造函数 - 初始化阿里云OSS客户端
     * @throws Exception
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        parent::__construct($config, $globalConfig);

        $this->bucket = $this->getConfig('bucket', '');
        $this->prefix = $this->getConfig('prefix', 'uploads/');
        $this->cdnDomain = $this->getConfig('cdn_domain', '');

        if (empty($this->bucket)) {
            throw new Exception('阿里云OSS配置错误: bucket不能为空');
        }

        try {
            $accessKey = $this->getConfig('access_key', '');
            $secretKey = $this->getConfig('secret_key', '');
            $endpoint = $this->getConfig('endpoint', 'oss-cn-hangzhou.aliyuncs.com');
            $isCname = $this->getConfig('is_cname', false);
            $securityToken = $this->getConfig('security_token', '');

            if (empty($accessKey) || empty($secretKey)) {
                throw new Exception('阿里云OSS配置错误: access_key或secret_key不能为空');
            }

            // 初始化OSS客户端
            $this->client = new OssClient($accessKey, $secretKey, $endpoint, $isCname, $securityToken);

            // 设置超时时间
            $timeout = $this->getConfig('timeout', 60);
            $this->client->setTimeout($timeout);
            $this->client->setConnectTimeout(10);

            // 测试连接
            $this->client->listObjects($this->bucket, ['max-keys' => 1]);

            $this->logInfo('阿里云OSS客户端初始化成功', [
                'bucket' => $this->bucket,
                'endpoint' => $endpoint,
            ]);

        } catch (OssException $e) {
            throw new Exception('阿里云OSS客户端初始化失败: ' . $e->getMessage());
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

            $this->logDebug('开始上传文件到阿里云OSS', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
            ]);

            // 上传选项
            $uploadOptions = [
                OssClient::OSS_CHECK_MD5 => true,
                OssClient::OSS_PART_SIZE => $this->getConfig('chunk_size', 5242880),
            ];

            // 设置自定义header
            if (!empty($options['headers'])) {
                $uploadOptions[OssClient::OSS_HEADERS] = $options['headers'];
            }

            // 设置ACL
            if (!empty($options['acl'])) {
                $uploadOptions[OssClient::OSS_FILE_UPLOAD] = $options['acl'];
            }

            // 执行上传
            $result = $this->client->uploadFile(
                $this->bucket,
                $ossPath,
                $localFilePath,
                $uploadOptions
            );

            $this->logInfo('文件上传到阿里云OSS成功', [
                'oss_path' => $ossPath,
                'file_size' => filesize($localFilePath),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => filesize($localFilePath),
            ];

        } catch (OssException $e) {
            $this->logError('上传到阿里云OSS失败', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('上传到阿里云OSS失败: ' . $e->getMessage());
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
            $chunkSize = $options['chunk_size'] ?? $this->getConfig('chunk_size', 5242880);

            $this->logInfo('开始分片上传到阿里云OSS', [
                'local_path' => $localFilePath,
                'oss_path' => $ossPath,
                'file_size' => $fileSize,
                'chunk_size' => $chunkSize,
            ]);

            // 初始化分片上传
            $uploadId = $this->client->initiateMultipartUpload($this->bucket, $ossPath);
            $parts = [];
            $partNumber = 1;
            $uploadedSize = 0;

            // 分片上传
            $fileHandle = fopen($localFilePath, 'rb');
            while (!feof($fileHandle)) {
                $data = fread($fileHandle, $chunkSize);
                if (empty($data)) {
                    break;
                }

                $result = $this->client->uploadPart(
                    $this->bucket,
                    $ossPath,
                    $uploadId,
                    $partNumber,
                    $data
                );

                $parts[] = [
                    'PartNumber' => $partNumber,
                    'ETag' => $result['etag'],
                ];

                $uploadedSize += strlen($data);

                // 报告进度
                $this->reportProgress($uploadedSize, $fileSize, $options['progress_callback'] ?? null);

                $partNumber++;
            }
            fclose($fileHandle);

            // 完成分片上传
            $this->client->completeMultipartUpload(
                $this->bucket,
                $ossPath,
                $uploadId,
                $parts
            );

            $this->logInfo('分片上传到阿里云OSS成功', [
                'oss_path' => $ossPath,
                'file_size' => $fileSize,
                'parts' => count($parts),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => $fileSize,
                'parts' => count($parts),
            ];

        } catch (OssException $e) {
            $this->logError('分片上传到阿里云OSS失败', [
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

            $this->client->deleteObject($this->bucket, $ossPath);

            $this->logInfo('阿里云OSS文件删除成功', [
                'oss_path' => $ossPath,
            ]);

            return true;

        } catch (OssException $e) {
            $this->logError('删除阿里云OSS文件失败', [
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

            $this->client->deleteObjects($this->bucket, $paths);

            $this->logInfo('批量删除阿里云OSS文件成功', [
                'count' => count($paths),
            ]);

            return array_fill(0, count($paths), true);

        } catch (OssException $e) {
            $this->logError('批量删除阿里云OSS文件失败', [
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
            return $this->client->doesObjectExist($this->bucket, $ossPath);
        } catch (OssException $e) {
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

        // 使用CDN域名
        if (!empty($this->cdnDomain)) {
            $scheme = $this->getConfig('use_https', true) ? 'https' : 'http';
            return $scheme . '://' . rtrim($this->cdnDomain, '/') . '/' . $ossPath;
        }

        // 生成签名URL或公开URL
        if ($expires > 0) {
            return $this->client->signUrl($this->bucket, $ossPath, $expires);
        }

        // 公开URL
        $endpoint = $this->getConfig('endpoint', '');
        $scheme = $this->getConfig('use_https', true) ? 'https' : 'http';
        return $scheme . '://' . $this->bucket . '.' . $endpoint . '/' . $ossPath;
    }

    /**
     * 生成私有文件的签名URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒)
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $expires = 3600): string
    {
        $ossPath = $this->normalizePath($this->prefix . $ossPath);
        return $this->client->signUrl($this->bucket, $ossPath, $expires);
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
            $meta = $this->client->getObjectMeta($this->bucket, $ossPath);

            return [
                'size' => (int)($meta['content-length'] ?? 0),
                'type' => $meta['content-type'] ?? '',
                'last_modified' => $meta['last-modified'] ?? '',
                'etag' => $meta['etag'] ?? '',
                'metadata' => $meta,
            ];

        } catch (OssException $e) {
            $this->logError('获取阿里云OSS文件信息失败', [
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

            $options = [
                'max-keys' => $maxKeys,
                'prefix' => $prefix,
            ];

            if ($marker !== null) {
                $options['marker'] = $marker;
            }

            $result = $this->client->listObjects($this->bucket, $options);

            $files = [];
            if (isset($result->getObjectList())) {
                foreach ($result->getObjectList() as $object) {
                    $files[] = [
                        'key' => $object->getKey(),
                        'size' => $object->getSize(),
                        'last_modified' => $object->getLastModified(),
                        'etag' => $object->getETag(),
                    ];
                }
            }

            return [
                'files' => $files,
                'is_truncated' => $result->getIsTruncated(),
                'next_marker' => $result->getNextMarker(),
                'prefix' => $prefix,
            ];

        } catch (OssException $e) {
            $this->logError('列出阿里云OSS文件失败', [
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

            $this->client->copyObject(
                $this->bucket,
                $sourcePath,
                $this->bucket,
                $destPath
            );

            $this->logInfo('阿里云OSS文件复制成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (OssException $e) {
            $this->logError('复制阿里云OSS文件失败', [
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

            // 先复制后删除
            $this->client->copyObject(
                $this->bucket,
                $sourcePath,
                $this->bucket,
                $destPath
            );

            $this->client->deleteObject($this->bucket, $sourcePath);

            $this->logInfo('阿里云OSS文件移动成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (OssException $e) {
            $this->logError('移动阿里云OSS文件失败', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
