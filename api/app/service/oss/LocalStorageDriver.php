<?php
declare (strict_types = 1);

namespace app\service\oss;

use think\Exception;

/**
 * 本地存储驱动(开发环境)
 *
 * @package app\service\oss
 */
class LocalStorageDriver extends AbstractOssDriver
{
    /**
     * 根目录
     * @var string
     */
    protected string $rootPath = '';

    /**
     * URL前缀
     * @var string
     */
    protected string $urlPrefix = '';

    /**
     * 前缀
     * @var string
     */
    protected string $prefix = '';

    /**
     * 目录权限
     * @var int
     */
    protected int $dirPermissions = 0755;

    /**
     * 文件权限
     * @var int
     */
    protected int $filePermissions = 0644;

    /**
     * 构造函数 - 初始化本地存储
     * @throws Exception
     */
    public function __construct(array $config = [], array $globalConfig = [])
    {
        parent::__construct($config, $globalConfig);

        $this->rootPath = $this->getConfig('root_path', public_path() . 'uploads');
        $this->urlPrefix = $this->getConfig('url_prefix', '/uploads');
        $this->prefix = $this->getConfig('prefix', '');
        $this->dirPermissions = $this->getConfig('directory_permissions', 0755);
        $this->filePermissions = $this->getConfig('file_permissions', 0644);

        // 确保根目录存在
        if (!is_dir($this->rootPath)) {
            if (!mkdir($this->rootPath, $this->dirPermissions, true)) {
                throw new Exception('无法创建本地存储目录: ' . $this->rootPath);
            }
        }

        // 检查目录是否可写
        if (!is_writable($this->rootPath)) {
            throw new Exception('本地存储目录不可写: ' . $this->rootPath);
        }

        $this->logInfo('本地存储初始化成功', [
            'root_path' => $this->rootPath,
            'url_prefix' => $this->urlPrefix,
        ]);
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
            $targetPath = $this->rootPath . DIRECTORY_SEPARATOR . $ossPath;

            $this->logDebug('开始上传文件到本地存储', [
                'local_path' => $localFilePath,
                'target_path' => $targetPath,
            ]);

            // 确保目标目录存在
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, $this->dirPermissions, true)) {
                    throw new Exception('无法创建目标目录: ' . $targetDir);
                }
            }

            // 复制文件
            if (!copy($localFilePath, $targetPath)) {
                throw new Exception('文件复制失败');
            }

            // 设置文件权限
            chmod($targetPath, $this->filePermissions);

            $this->logInfo('文件上传到本地存储成功', [
                'target_path' => $targetPath,
                'file_size' => filesize($targetPath),
            ]);

            return [
                'success' => true,
                'path' => $ossPath,
                'url' => $this->getUrl($ossPath),
                'local_path' => $targetPath,
                'size' => filesize($targetPath),
            ];

        } catch (\Exception $e) {
            $this->logError('上传到本地存储失败', [
                'local_path' => $localFilePath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('上传到本地存储失败: ' . $e->getMessage());
        }
    }

    /**
     * 分片上传大文件(本地存储直接复制)
     * @param string $localFilePath 本地文件路径
     * @param string $ossPath OSS存储路径
     * @param array $options 上传选项
     * @return array
     */
    public function multipartUpload(string $localFilePath, string $ossPath, array $options = []): array
    {
        // 本地存储不需要分片,直接调用upload
        return $this->upload($localFilePath, $ossPath, $options);
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
            $targetPath = $this->rootPath . DIRECTORY_SEPARATOR . $ossPath;

            if (file_exists($targetPath)) {
                if (!unlink($targetPath)) {
                    throw new Exception('文件删除失败');
                }

                $this->logInfo('本地存储文件删除成功', [
                    'target_path' => $targetPath,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            $this->logError('删除本地存储文件失败', [
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
        $results = [];
        foreach ($ossPaths as $path) {
            $results[] = $this->delete($path);
        }
        return $results;
    }

    /**
     * 判断文件是否存在
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function exists(string $ossPath): bool
    {
        $ossPath = $this->normalizePath($this->prefix . $ossPath);
        $targetPath = $this->rootPath . DIRECTORY_SEPARATOR . $ossPath;
        return file_exists($targetPath);
    }

    /**
     * 获取文件URL
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒),0表示永久URL(本地存储不支持签名URL)
     * @return string
     */
    public function getUrl(string $ossPath, int $expires = 0): string
    {
        $ossPath = $this->normalizePath($this->prefix . $ossPath);
        $url = rtrim($this->urlPrefix, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $ossPath);

        // 本地存储不支持签名URL,忽略expires参数
        return $url;
    }

    /**
     * 生成私有文件的签名URL(本地存储不支持)
     * @param string $ossPath OSS文件路径
     * @param int $expires 过期时间(秒)
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $expires = 3600): string
    {
        // 本地存储不支持签名URL,返回普通URL
        return $this->getUrl($ossPath);
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
            $targetPath = $this->rootPath . DIRECTORY_SEPARATOR . $ossPath;

            if (!file_exists($targetPath)) {
                throw new Exception('文件不存在');
            }

            return [
                'size' => filesize($targetPath),
                'type' => mime_content_type($targetPath),
                'last_modified' => date('Y-m-d H:i:s', filemtime($targetPath)),
                'etag' => md5_file($targetPath),
                'local_path' => $targetPath,
            ];

        } catch (\Exception $e) {
            $this->logError('获取本地存储文件信息失败', [
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
     * @param string|null $marker 分页标记(本地存储不支持)
     * @return array
     */
    public function listFiles(string $prefix = '', int $maxKeys = 100, ?string $marker = null): array
    {
        try {
            $searchPath = $this->rootPath;
            if (!empty($prefix)) {
                $searchPath .= DIRECTORY_SEPARATOR . $this->normalizePath($this->prefix . $prefix);
            }

            if (!is_dir($searchPath)) {
                return [
                    'files' => [],
                    'is_truncated' => false,
                    'next_marker' => null,
                    'prefix' => $prefix,
                ];
            }

            $files = [];
            $count = 0;

            // 递归扫描目录
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($searchPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $count < $maxKeys) {
                    $relativePath = str_replace($this->rootPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $files[] = [
                        'key' => $relativePath,
                        'size' => $file->getSize(),
                        'last_modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'local_path' => $file->getPathname(),
                    ];
                    $count++;
                }
            }

            return [
                'files' => $files,
                'is_truncated' => false,
                'next_marker' => null,
                'prefix' => $prefix,
            ];

        } catch (\Exception $e) {
            $this->logError('列出本地存储文件失败', [
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

            $sourceFile = $this->rootPath . DIRECTORY_SEPARATOR . $sourcePath;
            $destFile = $this->rootPath . DIRECTORY_SEPARATOR . $destPath;

            if (!file_exists($sourceFile)) {
                throw new Exception('源文件不存在');
            }

            // 确保目标目录存在
            $destDir = dirname($destFile);
            if (!is_dir($destDir)) {
                mkdir($destDir, $this->dirPermissions, true);
            }

            if (!copy($sourceFile, $destFile)) {
                throw new Exception('文件复制失败');
            }

            chmod($destFile, $this->filePermissions);

            $this->logInfo('本地存储文件复制成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('复制本地存储文件失败', [
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

            $sourceFile = $this->rootPath . DIRECTORY_SEPARATOR . $sourcePath;
            $destFile = $this->rootPath . DIRECTORY_SEPARATOR . $destPath;

            if (!file_exists($sourceFile)) {
                throw new Exception('源文件不存在');
            }

            // 确保目标目录存在
            $destDir = dirname($destFile);
            if (!is_dir($destDir)) {
                mkdir($destDir, $this->dirPermissions, true);
            }

            if (!rename($sourceFile, $destFile)) {
                throw new Exception('文件移动失败');
            }

            $this->logInfo('本地存储文件移动成功', [
                'source' => $sourcePath,
                'destination' => $destPath,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logError('移动本地存储文件失败', [
                'source' => $sourcePath,
                'destination' => $destPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 规范化路径 - 将路径转换为本地路径分隔符
     * @param string $path 文件路径
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // 移除开头的斜杠
        $path = ltrim($path, '/\\');

        // 统一使用本地路径分隔符
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);

        // 移除多余的路径分隔符
        $path = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);

        return $path;
    }
}
