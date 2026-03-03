<?php
declare (strict_types = 1);

namespace app\service\oss;

use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;
use think\Exception;

/**
 * 腾讯云COS驱动
 *
 * @package app\service\oss
 */
class TencentCosDriver extends AbstractOssDriver
{
    /**
     * COS客户端实例
     * @var Client
     */
    protected Client $client;

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
     * 构造函数 - 初始化腾讯云COS客户端
     * @throws Exception
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        parent::__construct($config, $globalConfig);

        $this->bucket = $this->getConfig('bucket', '');
        $this->region = $this->getConfig('region', 'ap-guangzhou');
        $this->prefix = $this->getConfig('prefix', 'uploads/');
        $this->cdnDomain = $this->getConfig('cdn_domain', '');

        if (empty($this->bucket)) {
            throw new Exception('腾讯云COS配置错误: bucket不能为空');
        }

        try {
            $secretId = $this->getConfig('secret_id', '');
            $secretKey = $this->getConfig('secret_key', '');

            if (empty($secretId) || empty($secretKey)) {
                throw new Exception('腾讯云COS配置错误: secret_id或secret_key不能为空');
            }

            // 初始化COS客户端
            $this->client = new Client([
                'region' => $this->region,
                'schema' => $this->getConfig('schema', 'https'),
                'credentials' => [
                    'secretId' => $secretId,
                    'secretKey' => $secretKey,
                ],
                'timeout' => $this->getConfig('timeout', 60),
                'connect_timeout' => 10,
            ]);

            // 测试连接
            $this->client->listObjects([
                'Bucket' => $this->bucket,
                'MaxKeys' => 1,
            ]);

            $this->logInfo('腾讯云COS客户端初始化成功', [
                'bucket' => $this->bucket,
                'region' => $this->region,
            ]);

        } catch (ServiceResponseException $e) {
            throw new Exception('腾讯云COS客户端初始化失败: ' . $e->getMessage());
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

            $this->logDebug('开始上传文件到腾讯云COS', [
                'local_path' => $localFilePath,
                'cos_path' => $ossPath,
            ]);

            // 上传参数
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
                'Body' => fopen($localFilePath, 'rb'),
            ];

            // 设置Content-Type
            if (!empty($options['content_type'])) {
                $params['ContentType'] = $options['content_type'];
            } else {
                $params['ContentType'] = mime_content_type($localFilePath);
            }

            // 执行上传
            $result = $this->client->putObject($params);

            $this->logInfo('文件上传到腾讯云COS成功', [
                'cos_path' => $ossPath,
                'file_size' => filesize($localFilePath),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'bucket' => $this->bucket,
                'size' => filesize($localFilePath),
                'request_id' => $result['RequestId'] ?? '',
            ];

        } catch (ServiceResponseException $e) {
            $this->logError('上传到腾讯云COS失败', [
                'local_path' => $localFilePath,
                'cos_path' => $ossPath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('上传到腾讯云COS失败: ' . $e->getMessage());
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

            $this->logInfo('开始分片上传到腾讯云COS', [
                'local_path' => $localFilePath,
                'cos_path' => $ossPath,
                'file_size' => $fileSize,
                'chunk_size' => $chunkSize,
            ]);

            // 初始化分片上传
            $initResult = $this->client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
            ]);

            $uploadId = $initResult['UploadId'];
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

                $result = $this->client->uploadPart([
                    'Bucket' => $this->bucket,
                    'Key' => $ossPath,
                    'Body' => $data,
                    'PartNumber' => $partNumber,
                    'UploadId' => $uploadId,
                ]);

                $parts[] = [
                    'PartNumber' => $partNumber,
                    'ETag' => $result['ETag'],
                ];

                $uploadedSize += strlen($data);

                // 报告进度
                $this->reportProgress($uploadedSize, $fileSize, $options['progress_callback'] ?? null);

                $partNumber++;
            }
            fclose($fileHandle);

            // 完成分片上传
            $this->client->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $ossPath,
                'UploadId' => $uploadId,
                'Parts' => $parts,
            ]);

            $this->logInfo('分片上传到腾讯云COS成功', [
                'cos_path' => $ossPath,
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

        } catch (ServiceResponseException $e) {
            $this->logError('分片上传到腾讯云COS失败', [
                'local_path' => $localFilePath,
                'cos_path' => $ossPath,
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

            $this->logInfo('腾讯云COS文件删除成功', [
                'cos_path' => $ossPath,
            ]);

            return true;

        } catch (ServiceResponseException $e) {
            $this->logError('删除腾讯云COS文件失败', [
                'cos_path' => $ossPath,
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
            $objects = [];
            foreach ($ossPaths as $path) {
                $objects[] = [
                    'Key' => $this->normalizePath($this->prefix . $path),
                ];
            }

            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Objects' => $objects,
            ]);

            $this->logInfo('批量删除腾讯云COS文件成功', [
                'count' => count($objects),
            ]);

            return array_fill(0, count($ossPaths), true);

        } catch (ServiceResponseException $e) {
            $this->logError('批量删除腾讯云COS文件失败', [
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
        } catch (ServiceResponseException $e) {
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
            $signedUrl = $this->client->getPresignedUrl(
                'getObject',
                [
                    'Bucket' => $this->bucket,
                    'Key' => $ossPath,
                ],
                '+' . $expires . ' seconds'
            );
            return $signedUrl;
        }

        // 公开URL
        $scheme = $this->getConfig('schema', 'https');
        return $scheme . '://' . $this->bucket . '.cos.' . $this->region . '.myqcloud.com/' . $ossPath;
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

        } catch (ServiceResponseException $e) {
            $this->logError('获取腾讯云COS文件信息失败', [
                'cos_path' => $ossPath,
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
                'is_truncated' => ($result['IsTruncated'] ?? false) === 'true',
                'next_marker' => $result['NextMarker'] ?? null,
                'prefix' => $prefix,
            ];

        } catch (ServiceResponseException $e) {
            $this->logError('列出腾讯云COS文件失败', [
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

            $this->logInfo('腾讯云COS文件复制成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (ServiceResponseException $e) {
            $this->logError('复制腾讯云COS文件失败', [
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

            $this->logInfo('腾讯云COS文件移动成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (ServiceResponseException $e) {
            $this->logError('移动腾讯云COS文件失败', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
