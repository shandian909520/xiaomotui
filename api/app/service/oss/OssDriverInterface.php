<?php
declare (strict_types = 1);

namespace app\service\oss;

/**
 * OSS驱动接口
 * 所有OSS驱动必须实现此接口
 *
 * @package app\service\oss
 */
interface OssDriverInterface
{
    /**
     * 上传文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array 返回结果包含: url, path, bucket等信息
     */
    public function upload(string $localFilePath, string $ossPath, array $options = []): array;

    /**
     * 分片上传大文件
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array
     */
    public function multipartUpload(string $localFilePath, string $ossPath, array $options = []): array;

    /**
     * 删除文件
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function delete(string $ossPath): bool;

    /**
     * 批量删除文件
     * @param array $ossPaths OSS文件路径数组
     * @return array 每个文件的删除结果
     */
    public function batchDelete(array $ossPaths): array;

    /**
     * 判断文件是否存在
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function exists(string $ossPath): bool;

    /**
     * 获取文件URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒),0表示永久URL
     * @return string
     */
    public function getUrl(string $ossPath, int $expires = 0): string;

    /**
     * 生成私有文件的签名URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒)
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $expires = 3600): string;

    /**
     * 获取文件信息
     * @param string $ossPath OSS文件路径
     * @return array 文件信息包含: size, type, last_modified等
     */
    public function getFileInfo(string $ossPath): array;

    /**
     * 列出指定前缀的文件
     * @param string $prefix 文件前缀
     * @param int $maxKeys 最大返回数量
     * @param string|null $marker 分页标记
     * @return array
     */
    public function listFiles(string $prefix = '', int $maxKeys = 100, ?string $marker = null): array;

    /**
     * 复制文件
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @return bool
     */
    public function copy(string $sourcePath, string $destPath): bool;

    /**
     * 移动/重命名文件
     * @param string $sourcePath 源文件路径
     * @param string $destPath 目标文件路径
     * @return bool
     */
    public function move(string $sourcePath, string $destPath): bool;
}
