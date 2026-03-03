<?php
declare (strict_types = 1);

namespace app\service\oss;

use think\facade\Log;
use think\Exception;

/**
 * OSS缩略图生成服务
 * 支持GD和Imagick两种驱动
 *
 * @package app\service\oss
 */
class OssThumbnailService
{
    /**
     * 配置
     * @var array
     */
    protected array $config = [];

    /**
     * 图片处理驱动 (gd, imagick)
     * @var string
     */
    protected string $driver = 'gd';

    /**
     * 构造函数
     * @param array $config 缩略图配置
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->driver = $config['driver'] ?? 'gd';

        // 检查驱动是否可用
        if ($this->driver === 'imagick' && !extension_loaded('imagick')) {
            Log::warning('Imagick扩展未加载,降级使用GD驱动');
            $this->driver = 'gd';
        }

        if ($this->driver === 'gd' && !extension_loaded('gd')) {
            throw new Exception('GD扩展未加载,无法生成缩略图');
        }
    }

    /**
     * 生成缩略图
     * @param string $localFilePath 本地文件路径
     * @param string $size 尺寸类型 (small, medium, large)
     * @param array $options 选项
     * @return array
     */
    public function generate(string $localFilePath, string $size = 'medium', array $options = []): array
    {
        try {
            // 验证文件是否为图片
            if (!$this->isImage($localFilePath)) {
                return [
                    'success' => false,
                    'message' => '文件不是图片类型'
                ];
            }

            // 获取目标尺寸
            $targetSize = $this->getSize($size, $options);
            if (!$targetSize) {
                return [
                    'success' => false,
                    'message' => '无效的缩略图尺寸: ' . $size
                ];
            }

            // 生成缩略图
            $thumbnailPath = $this->createThumbnail($localFilePath, $targetSize, $options);

            if (!$thumbnailPath) {
                return [
                    'success' => false,
                    'message' => '缩略图生成失败'
                ];
            }

            return [
                'success' => true,
                'thumbnail_path' => $thumbnailPath,
                'width' => $targetSize[0],
                'height' => $targetSize[1],
                'size' => filesize($thumbnailPath),
                'size_type' => $size,
            ];

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
     * 批量生成多种尺寸缩略图
     * @param string $localFilePath 本地文件路径
     * @param array $sizes 尺寸类型数组
     * @param array $options 选项
     * @return array
     */
    public function generateBatch(string $localFilePath, array $sizes = ['small', 'medium', 'large'], array $options = []): array
    {
        $results = [];

        foreach ($sizes as $size) {
            $results[$size] = $this->generate($localFilePath, $size, $options);
        }

        return $results;
    }

    /**
     * 创建缩略图
     * @param string $localFilePath 本地文件路径
     * @param array $targetSize 目标尺寸 [width, height]
     * @param array $options 选项
     * @return string|false 缩略图保存路径
     */
    protected function createThumbnail(string $localFilePath, array $targetSize, array $options = [])
    {
        try {
            if ($this->driver === 'imagick') {
                return $this->createThumbnailWithImagick($localFilePath, $targetSize, $options);
            } else {
                return $this->createThumbnailWithGD($localFilePath, $targetSize, $options);
            }
        } catch (\Exception $e) {
            Log::error('创建缩略图失败', [
                'driver' => $this->driver,
                'file_path' => $localFilePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 使用GD创建缩略图
     * @param string $localFilePath 本地文件路径
     * @param array $targetSize 目标尺寸
     * @param array $options 选项
     * @return string|false
     */
    protected function createThumbnailWithGD(string $localFilePath, array $targetSize, array $options = [])
    {
        // 获取图片信息
        $imageInfo = getimagesize($localFilePath);
        if (!$imageInfo) {
            throw new Exception('无法读取图片信息');
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $imageType = $imageInfo[2];

        // 计算缩略图尺寸
        list($thumbWidth, $thumbHeight) = $this->calculateSize(
            $originalWidth,
            $originalHeight,
            $targetSize[0],
            $targetSize[1],
            $options['mode'] ?? 'fit'
        );

        // 创建源图像
        $sourceImage = $this->imageCreateFromType($localFilePath, $imageType);
        if (!$sourceImage) {
            throw new Exception('创建源图像失败');
        }

        // 创建目标图像
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // 处理透明度
        $this->handleTransparency($thumbImage, $imageType);

        // 调整图片大小
        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0, 0, 0, 0,
            $thumbWidth,
            $thumbHeight,
            $originalWidth,
            $originalHeight
        );

        // 生成缩略图保存路径
        $thumbnailPath = $this->generateThumbnailPath($localFilePath, $targetSize);

        // 确保目录存在
        $thumbnailDir = dirname($thumbnailPath);
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // 保存缩略图
        $quality = $options['quality'] ?? $this->config['quality'] ?? 85;
        $format = $options['format'] ?? $this->config['format'] ?? 'jpg';

        $result = false;
        switch (strtolower($format)) {
            case 'png':
                $result = imagepng($thumbImage, $thumbnailPath, round(9 * $quality / 100));
                break;
            case 'gif':
                $result = imagegif($thumbImage, $thumbnailPath);
                break;
            case 'webp':
                $result = imagewebp($thumbImage, $thumbnailPath, $quality);
                break;
            case 'jpg':
            case 'jpeg':
            default:
                $result = imagejpeg($thumbImage, $thumbnailPath, $quality);
                break;
        }

        // 释放内存
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return $result ? $thumbnailPath : false;
    }

    /**
     * 使用Imagick创建缩略图
     * @param string $localFilePath 本地文件路径
     * @param array $targetSize 目标尺寸
     * @param array $options 选项
     * @return string|false
     */
    protected function createThumbnailWithImagick(string $localFilePath, array $targetSize, array $options = [])
    {
        try {
            $image = new \Imagick($localFilePath);

            // 计算缩略图尺寸
            $originalWidth = $image->getImageWidth();
            $originalHeight = $image->getImageHeight();

            list($thumbWidth, $thumbHeight) = $this->calculateSize(
                $originalWidth,
                $originalHeight,
                $targetSize[0],
                $targetSize[1],
                $options['mode'] ?? 'fit'
            );

            // 调整大小
            $mode = $options['mode'] ?? 'fit';
            if ($mode === 'crop') {
                $image->cropThumbnailImage($thumbWidth, $thumbHeight);
            } else {
                $image->resizeImage($thumbWidth, $thumbHeight, \Imagick::FILTER_LANCZOS, 1, true);
            }

            // 设置质量
            $quality = $options['quality'] ?? $this->config['quality'] ?? 85;
            $image->setImageCompressionQuality($quality);

            // 生成缩略图保存路径
            $thumbnailPath = $this->generateThumbnailPath($localFilePath, $targetSize);

            // 确保目录存在
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // 保存缩略图
            $format = $options['format'] ?? $this->config['format'] ?? 'jpg';
            $image->setImageFormat($format);
            $image->writeImage($thumbnailPath);

            $image->destroy();

            return $thumbnailPath;

        } catch (\ImagickException $e) {
            Log::error('Imagick创建缩略图失败', [
                'file_path' => $localFilePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 根据类型创建图像资源
     * @param string $filePath 文件路径
     * @param int $imageType 图像类型
     * @return resource|false
     */
    protected function imageCreateFromType(string $filePath, int $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filePath);
            case IMAGETYPE_BMP:
                return imagecreatefrombmp($filePath);
            default:
                return false;
        }
    }

    /**
     * 处理图像透明度
     * @param resource $image 图像资源
     * @param int $imageType 图像类型
     * @return void
     */
    protected function handleTransparency($image, int $imageType): void
    {
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }

    /**
     * 计算缩略图尺寸
     * @param int $originalWidth 原始宽度
     * @param int $originalHeight 原始高度
     * @param int $targetWidth 目标宽度
     * @param int $targetHeight 目标高度
     * @param string $mode 缩放模式 (fit, crop, exact)
     * @return array [width, height]
     */
    protected function calculateSize(int $originalWidth, int $originalHeight, int $targetWidth, int $targetHeight, string $mode = 'fit'): array
    {
        if ($mode === 'exact') {
            return [$targetWidth, $targetHeight];
        }

        $aspectRatio = $originalWidth / $originalHeight;
        $targetAspectRatio = $targetWidth / $targetHeight;

        if ($mode === 'crop') {
            // 裁剪模式: 保持比例,居中裁剪
            if ($aspectRatio > $targetAspectRatio) {
                // 原图更宽,以高度为准
                $newHeight = $targetHeight;
                $newWidth = (int)($newHeight * $aspectRatio);
            } else {
                // 原图更高,以宽度为准
                $newWidth = $targetWidth;
                $newHeight = (int)($newWidth / $aspectRatio);
            }
            return [$newWidth, $newHeight];
        }

        // 适应模式: 保持比例,完整显示
        if ($aspectRatio > $targetAspectRatio) {
            // 以宽度为准
            $newWidth = $targetWidth;
            $newHeight = (int)($newWidth / $aspectRatio);
        } else {
            // 以高度为准
            $newHeight = $targetHeight;
            $newWidth = (int)($newHeight * $aspectRatio);
        }

        return [$newWidth, $newHeight];
    }

    /**
     * 获取预设尺寸
     * @param string $size 尺寸类型
     * @param array $options 选项
     * @return array|null [width, height]
     */
    protected function getSize(string $size, array $options = []): ?array
    {
        // 自定义尺寸
        if (isset($options['width']) && isset($options['height'])) {
            return [(int)$options['width'], (int)$options['height']];
        }

        // 预设尺寸
        $sizes = $this->config['sizes'] ?? [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [800, 600],
        ];

        return $sizes[$size] ?? null;
    }

    /**
     * 生成缩略图保存路径
     * @param string $originalPath 原始文件路径
     * @param array $size 尺寸
     * @return string
     */
    protected function generateThumbnailPath(string $originalPath, array $size): string
    {
        $pathInfo = pathinfo($originalPath);
        $dirName = $pathInfo['dirname'] ?? '.';
        $fileName = $pathInfo['filename'] ?? 'thumbnail';
        $extension = $pathInfo['extension'] ?? 'jpg';

        $thumbnailName = $fileName . '_' . $size[0] . 'x' . $size[1] . '.' . $extension;

        return $dirName . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . $thumbnailName;
    }

    /**
     * 判断文件是否为图片
     * @param string $filePath 文件路径
     * @return bool
     */
    protected function isImage(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $imageInfo = @getimagesize($filePath);
        return $imageInfo !== false;
    }
}
