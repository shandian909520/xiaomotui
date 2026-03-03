<?php
declare (strict_types = 1);

namespace app\service\oss;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use think\Exception;

/**
 * AWS S3驱动
 *
 * @package app\service\oss
 */
class AwsS3Driver extends AbstractOssDriver
{
    /**
     * S3客户端实例
     * @var S3Client
     */
    protected S3Client $client;

    /**
     * Bucket名称
     * @var string
     */
    protected string $bucket = '';

    /**
     * 地域
     * @var string
     */
    protected string $region = '';

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
     * 构造函数 - 初始化AWS S3客户端
     * @throws Exception
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        parent::__construct($config, $globalConfig);

        $this->bucket = $this->getConfig('bucket', '');
        $this->region = $this->getConfig('region', 'us-east-1');
        $this->prefix = $this->getConfig('prefix', 'uploads/');
        $this->cdnDomain = $this->getConfig('cdn_domain', '');

        if (empty($this->bucket)) {
            throw new Exception('AWS S3配置错误: bucket不能为空');
        }

        try {
            $accessKey = $this->getConfig('access_key', '');
            $secretKey = $this->getConfig('secret_key', '');

            if (empty($accessKey) || empty($secretKey)) {
                throw new Exception('AWS S3配置错误: access_key或secret_key不能为空');
            }

            // 初始化S3客户端
            $s3Config = [
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
                'http' => [
                    'timeout' => $this->getConfig('timeout', 60),
                    'connect_timeout' => 10,
                ],
            ];

            // 自定义endpoint
            $endpoint = $this->getConfig('endpoint', '');
            if (!empty($endpoint)) {
                $s3Config['endpoint'] = $endpoint;
            }

            // 是否使用路径样式
            if ($this->getConfig('path_style', false)) {
                $s3Config['use_path_style_endpoint'] = true;
            }

            // 是否使用加速端点
            if ($this->getConfig('use_accelerate_endpoint', false)) {
                $s3Config['accelerate'] = true;
            }

            $this->client = new S3Client($s3Config);

            // 测试连接
            $this->client->headBucket([
                'Bucket' => $this->bucket,
            ]);

            $this->logInfo('AWS S3客户端初始化成功', [
                'bucket' => $this->bucket,
                'region' => $this->region,
            ]);

        } catch (AwsException $e) {
            throw new Exception('AWS S3客户端初始化失败: ' . $e->getMessage());
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

            $this->logDebug('开始上传文件到AWS S3', [
                'local_path' => $localFilePath,
                's3_path' => $ossPath,
            ]);

            // 上传参数
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
                'SourceFile' => $localFilePath,
            ];

            // 设置ACL
            if (!empty($options['acl'])) {
                $params['ACL'] = $options['acl'];
            }

            // 设置Content-Type
            if (!empty($options['content_type'])) {
                $params['ContentType'] = $options['content_type'];
            }

            // 执行上传
            $result = $this->client->putObject($params);

            $this->logInfo('文件上传到AWS S3成功', [
                's3_path' => $ossPath,
                'file_size' => filesize($localFilePath),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => filesize($localFilePath),
                'object_url' => $result['ObjectURL'] ?? '',
            ];

        } catch (S3Exception $e) {
            $this->logError('上传到AWS S3失败', [
                'local_path' => $localFilePath,
                's3_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('上传到AWS S3失败: ' . $e->getMessage());
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

            $this->logInfo('开始分片上传到AWS S3', [
                'local_path' => $localFilePath,
                's3_path' => $ossPath,
                'file_size' => $fileSize,
                'chunk_size' => $chunkSize,
            ]);

            // 使用S3的分片上传
            $result = $this->client->upload(
                $this->bucket,
                $ossPath,
                $localFilePath,
                [
                    'part_size' => $chunkSize,
                    'before_upload' => function(\Aws\Command $command) use ($fileSize, $options) {
                        // 可以在这里设置进度回调
                    },
                ]
            );

            $this->logInfo('分片上传到AWS S3成功', [
                's3_path' => $ossPath,
                'file_size' => $fileSize,
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => $fileSize,
                'object_url' => $result['ObjectURL'] ?? '',
            ];

        } catch (S3Exception $e) {
            $this->logError('分片上传到AWS S3失败', [
                'local_path' => $localFilePath,
                's3_path' => $ossPath,
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

            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
            ]);

            $this->logInfo('AWS S3文件删除成功', [
                's3_path' => $ossPath,
            ]);

            return true;

        } catch (S3Exception $e) {
            $this->logError('删除AWS S3文件失败', [
                's3_path' => $ossPath,
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
            $keys = [];
            foreach ($ossPaths as $path) {
                $keys[] = [
                    'Key' => $this->normalizePath($this->prefix . $path),
                ];
            }

            $result = $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $keys,
                ],
            ]);

            $this->logInfo('批量删除AWS S3文件', [
                'count' => count($keys),
                'deleted' => count($result['Deleted'] ?? []),
            ]);

            // 返回每个文件的结果
            $results = [];
            $deletedKeys = array_column($result['Deleted'] ?? [], 'Key');
            foreach ($ossPaths as $path) {
                $normalizedPath = $this->normalizePath($this->prefix . $path);
                $results[] = in_array($normalizedPath, $deletedKeys);
            }

            return $results ?: array_fill(0, count($ossPaths), true);

        } catch (S3Exception $e) {
            $this->logError('批量删除AWS S3文件失败', [
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
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
            ]);
            return true;
        } catch (S3Exception $e) {
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
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
            ]);

            return (string) $this->client->createPresignedRequest($command, "+{$expires} seconds")->getUri();
        }

        // 公开URL
        $endpoint = $this->getConfig('endpoint', 'https://s3.amazonaws.com');
        $scheme = $this->getConfig('use_https', true) ? 'https' : 'http';

        // 如果是自定义endpoint
        if (!empty($endpoint) && strpos($endpoint, 's3.amazonaws.com') === false) {
            return $scheme . '://' . $this->bucket . '.' . parse_url($endpoint, PHP_URL_HOST) . '/' . $ossPath;
        }

        return $scheme . '://' . $this->bucket . '.s3.' . $this->region . '.amazonaws.com/' . $ossPath;
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
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
            ]);

            return [
                'size' => (int)($result['ContentLength'] ?? 0),
                'type' => $result['ContentType'] ?? '',
                'last_modified' => $result['LastModified'] ?? '',
                'etag' => $result['ETag'] ?? '',
                'metadata' => $result->toArray(),
            ];

        } catch (S3Exception $e) {
            $this->logError('获取AWS S3文件信息失败', [
                's3_path' => $ossPath,
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

            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
                'MaxKeys' => $maxKeys,
            ];

            if ($marker !== null) {
                $params['Marker'] = $marker;
            }

            $result = $this->client->listObjects($params);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'key' => $object['Key'],
                        'size' => (int)($object['Size'] ?? 0),
                        'last_modified' => $object['LastModified'] ?? '',
                        'etag' => $object['ETag'] ?? '',
                    ];
                }
            }

            return [
                'files' => $files,
                'is_truncated' => ($result['IsTruncated'] ?? false),
                'next_marker' => $result['NextMarker'] ?? null,
                'prefix' => $prefix,
            ];

        } catch (S3Exception $e) {
            $this->logError('列出AWS S3文件失败', [
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

            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destPath,
                'CopySource' => $this->bucket . '/' . $sourcePath,
            ]);

            $this->logInfo('AWS S3文件复制成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (S3Exception $e) {
            $this->logError('复制AWS S3文件失败', [
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
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destPath,
                'CopySource' => $this->bucket . '/' . $sourcePath,
            ]);

            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $sourcePath,
            ]);

            $this->logInfo('AWS S3文件移动成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (S3Exception $e) {
            $this->logError('移动AWS S3文件失败', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
