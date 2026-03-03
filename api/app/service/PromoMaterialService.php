<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;
use think\facade\Filesystem;
use think\Exception;
use app\model\PromoMaterial;
use app\model\Merchant;

/**
 * 推广素材服务
 */
class PromoMaterialService
{
    /**
     * 上传目录
     */
    private const UPLOAD_PATH = 'uploads/promo/';

    /**
     * 上传单个素材
     *
     * @param int $merchantId 商家ID
     * @param \think\file\UploadedFile $file 上传的文件
     * @param string $type 素材类型
     * @param string|null $name 素材名称
     * @return array
     */
    public function upload(int $merchantId, $file, string $type, ?string $name = null): array
    {
        Log::info('开始上传推广素材', [
            'merchant_id' => $merchantId,
            'type' => $type,
            'file_name' => $file->getOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        try {
            // 验证商家
            $merchant = Merchant::find($merchantId);
            if (!$merchant) {
                throw new Exception('商家不存在');
            }

            // 验证素材类型
            if (!in_array($type, [PromoMaterial::TYPE_IMAGE, PromoMaterial::TYPE_VIDEO, PromoMaterial::TYPE_MUSIC])) {
                throw new Exception('不支持的素材类型');
            }

            // 验证文件扩展名
            $extension = strtolower($file->extension());
            $allowedExtensions = PromoMaterial::getAllowedExtensions($type);
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('不支持的文件格式: ' . $extension . '，支持: ' . implode(', ', $allowedExtensions));
            }

            // 验证文件大小
            $maxSize = PromoMaterial::getMaxFileSize($type);
            if ($file->getSize() > $maxSize) {
                $maxSizeMB = round($maxSize / 1024 / 1024, 2);
                throw new Exception("文件大小超过限制，最大允许 {$maxSizeMB}MB");
            }

            // 生成存储路径
            $saveName = $this->generateFileName($extension);
            $relativePath = self::UPLOAD_PATH . $type . '/' . date('Ym/d/') . $saveName;
            $fullPath = public_path() . $relativePath;

            // 确保目录存在
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 移动上传文件
            $file->move($dir, $saveName);

            // 提取文件信息
            $fileInfo = $this->extractFileInfo($fullPath, $type);

            // 生成缩略图（视频和图片）
            $thumbnailUrl = null;
            if (in_array($type, [PromoMaterial::TYPE_IMAGE, PromoMaterial::TYPE_VIDEO])) {
                $thumbnailUrl = $this->generateThumbnail($fullPath, $type);
            }

            // 创建数据库记录
            $material = new PromoMaterial();
            $material->merchant_id = $merchantId;
            $material->type = $type;
            $material->name = $name ?: pathinfo($file->getOriginalName(), PATHINFO_FILENAME);
            $material->file_url = '/' . $relativePath;
            $material->thumbnail_url = $thumbnailUrl ? '/' . $thumbnailUrl : null;
            $material->duration = $fileInfo['duration'] ?? null;
            $material->file_size = $file->getSize();
            $material->width = $fileInfo['width'] ?? null;
            $material->height = $fileInfo['height'] ?? null;
            $material->sort_order = 0;
            $material->status = PromoMaterial::STATUS_ENABLED;
            $material->save();

            Log::info('推广素材上传成功', [
                'material_id' => $material->id,
                'file_url' => $material->file_url,
            ]);

            return [
                'success' => true,
                'material_id' => $material->id,
                'file_url' => $material->file_url,
                'thumbnail_url' => $material->thumbnail_url,
                'message' => '上传成功',
            ];
        } catch (\Exception $e) {
            Log::error('推广素材上传失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 批量上传素材
     *
     * @param int $merchantId 商家ID
     * @param array $files 文件数组
     * @return array
     */
    public function batchUpload(int $merchantId, array $files): array
    {
        Log::info('开始批量上传推广素材', [
            'merchant_id' => $merchantId,
            'count' => count($files),
        ]);

        $results = [
            'total' => count($files),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($files as $fileData) {
            $file = $fileData['file'];
            $type = $fileData['type'];
            $name = $fileData['name'] ?? null;

            $result = $this->upload($merchantId, $file, $type, $name);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'file_name' => $file->getOriginalName(),
                'type' => $type,
                'success' => $result['success'],
                'message' => $result['message'],
                'material_id' => $result['material_id'] ?? null,
            ];
        }

        Log::info('批量上传完成', [
            'merchant_id' => $merchantId,
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * 获取素材列表
     *
     * @param int $merchantId 商家ID
     * @param string|null $type 素材类型
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getList(int $merchantId, ?string $type = null, int $page = 1, int $pageSize = 20): array
    {
        try {
            $query = PromoMaterial::where('merchant_id', $merchantId)
                ->where('status', PromoMaterial::STATUS_ENABLED);

            if ($type !== null) {
                $query->where('type', $type);
            }

            $total = $query->count();
            $list = $query->order('sort_order', 'asc')
                ->order('create_time', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 1,
                'list' => $list,
            ];
        } catch (\Exception $e) {
            Log::error('获取素材列表失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
                'pages' => 0,
                'list' => [],
            ];
        }
    }

    /**
     * 删除素材
     *
     * @param int $id 素材ID
     * @param int $merchantId 商家ID（用于权限验证）
     * @return array
     */
    public function delete(int $id, int $merchantId): array
    {
        try {
            $material = PromoMaterial::find($id);

            if (!$material) {
                throw new Exception('素材不存在');
            }

            // 验证权限
            if ($material->merchant_id !== $merchantId) {
                throw new Exception('无权删除此素材');
            }

            // 删除物理文件
            if ($material->file_url) {
                $filePath = public_path() . ltrim($material->file_url, '/');
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // 删除缩略图
            if ($material->thumbnail_url) {
                $thumbPath = public_path() . ltrim($material->thumbnail_url, '/');
                if (file_exists($thumbPath)) {
                    @unlink($thumbPath);
                }
            }

            // 删除数据库记录
            $material->delete();

            Log::info('删除推广素材成功', [
                'material_id' => $id,
                'merchant_id' => $merchantId,
            ]);

            return [
                'success' => true,
                'message' => '删除成功',
            ];
        } catch (\Exception $e) {
            Log::error('删除推广素材失败', [
                'material_id' => $id,
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 提取视频信息
     *
     * @param string $filePath 文件路径
     * @return array
     */
    public function extractVideoInfo(string $filePath): array
    {
        $info = [
            'duration' => null,
            'width' => null,
            'height' => null,
        ];

        try {
            // 尝试使用getID3库提取视频信息
            if (class_exists('getID3')) {
                $getID3 = new \getID3();
                $fileInfo = $getID3->analyze($filePath);

                if (isset($fileInfo['playtime_seconds'])) {
                    $info['duration'] = round($fileInfo['playtime_seconds'], 2);
                }

                if (isset($fileInfo['video']['resolution_x'])) {
                    $info['width'] = (int)$fileInfo['video']['resolution_x'];
                }

                if (isset($fileInfo['video']['resolution_y'])) {
                    $info['height'] = (int)$fileInfo['video']['resolution_y'];
                }
            } elseif (function_exists('shell_exec')) {
                // 使用FFmpeg作为备选方案
                $command = sprintf(
                    'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>/dev/null',
                    escapeshellarg($filePath)
                );
                $output = shell_exec($command);

                if ($output) {
                    $data = json_decode($output, true);

                    if (isset($data['format']['duration'])) {
                        $info['duration'] = round((float)$data['format']['duration'], 2);
                    }

                    foreach ($data['streams'] ?? [] as $stream) {
                        if ($stream['codec_type'] === 'video') {
                            $info['width'] = (int)($stream['width'] ?? 0);
                            $info['height'] = (int)($stream['height'] ?? 0);
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('提取视频信息失败', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return $info;
    }

    /**
     * 生成缩略图
     *
     * @param string $filePath 文件路径
     * @param string $type 素材类型
     * @return string|null 缩略图相对路径
     */
    public function generateThumbnail(string $filePath, string $type): ?string
    {
        try {
            $thumbDir = public_path() . self::UPLOAD_PATH . 'thumbnails/' . date('Ym/d/');
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            $thumbName = uniqid() . '.jpg';
            $thumbPath = $thumbDir . $thumbName;

            if ($type === PromoMaterial::TYPE_IMAGE) {
                // 图片缩略图
                $imageInfo = getimagesize($filePath);
                if (!$imageInfo) {
                    return null;
                }

                $srcImage = match ($imageInfo[2]) {
                    IMAGETYPE_JPEG => imagecreatefromjpeg($filePath),
                    IMAGETYPE_PNG => imagecreatefrompng($filePath),
                    IMAGETYPE_GIF => imagecreatefromgif($filePath),
                    IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
                    default => null,
                };

                if (!$srcImage) {
                    return null;
                }

                $srcWidth = imagesx($srcImage);
                $srcHeight = imagesy($srcImage);

                // 缩略图尺寸
                $maxWidth = 320;
                $maxHeight = 240;
                $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
                $dstWidth = (int)($srcWidth * $ratio);
                $dstHeight = (int)($srcHeight * $ratio);

                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
                imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
                imagejpeg($dstImage, $thumbPath, 85);

                imagedestroy($srcImage);
                imagedestroy($dstImage);

            } elseif ($type === PromoMaterial::TYPE_VIDEO) {
                // 视频缩略图 - 使用FFmpeg
                if (function_exists('shell_exec')) {
                    $command = sprintf(
                        'ffmpeg -i %s -ss 00:00:01 -vframes 1 -vf scale=320:-1 %s -y 2>/dev/null',
                        escapeshellarg($filePath),
                        escapeshellarg($thumbPath)
                    );
                    shell_exec($command);

                    if (!file_exists($thumbPath)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }

            return self::UPLOAD_PATH . 'thumbnails/' . date('Ym/d/') . $thumbName;
        } catch (\Exception $e) {
            Log::warning('生成缩略图失败', [
                'file_path' => $filePath,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 生成文件名
     *
     * @param string $extension 文件扩展名
     * @return string
     */
    private function generateFileName(string $extension): string
    {
        return date('His') . substr(md5(uniqid((string)mt_rand(), true)), 0, 16) . '.' . $extension;
    }

    /**
     * 提取文件信息
     *
     * @param string $filePath 文件路径
     * @param string $type 素材类型
     * @return array
     */
    private function extractFileInfo(string $filePath, string $type): array
    {
        $info = [];

        try {
            if ($type === PromoMaterial::TYPE_IMAGE) {
                $imageInfo = getimagesize($filePath);
                if ($imageInfo) {
                    $info['width'] = $imageInfo[0];
                    $info['height'] = $imageInfo[1];
                }
            } elseif ($type === PromoMaterial::TYPE_VIDEO) {
                $videoInfo = $this->extractVideoInfo($filePath);
                $info = array_merge($info, $videoInfo);
            } elseif ($type === PromoMaterial::TYPE_MUSIC) {
                // 音频时长提取
                if (class_exists('getID3')) {
                    $getID3 = new \getID3();
                    $fileInfo = $getID3->analyze($filePath);
                    if (isset($fileInfo['playtime_seconds'])) {
                        $info['duration'] = round($fileInfo['playtime_seconds'], 2);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('提取文件信息失败', [
                'file_path' => $filePath,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return $info;
    }
}
