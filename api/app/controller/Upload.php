<?php
declare(strict_types=1);

namespace app\controller;

use app\service\OssService;
use think\facade\Log;
use think\facade\Config;

class Upload extends BaseController
{
    protected OssService $ossService;

    protected array $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    protected array $videoExt = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'];
    protected int $imageMaxSize = 5242880;   // 5MB
    protected int $videoMaxSize = 104857600; // 100MB
    protected int $fileMaxSize  = 52428800;  // 50MB
    protected int $avatarMaxSize = 2097152;  // 2MB

    protected function initialize(): void
    {
        parent::initialize();
        $this->ossService = new OssService();

        $uploadConfig = Config::get('upload', []);
        if (!empty($uploadConfig)) {
            $this->imageMaxSize = $uploadConfig['image_max_size'] ?? $this->imageMaxSize;
            $this->videoMaxSize = $uploadConfig['video_max_size'] ?? $this->videoMaxSize;
            $this->fileMaxSize  = $uploadConfig['file_max_size'] ?? $this->fileMaxSize;
            $this->avatarMaxSize = $uploadConfig['avatar_max_size'] ?? $this->avatarMaxSize;
        }
    }

    /**
     * 图片上传
     * POST /api/upload/image
     */
    public function image()
    {
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('请选择要上传的图片', 400, 'file_required');
        }

        try {
            $this->validateFile($file, $this->imageExt, $this->imageMaxSize);

            $userId = $this->request->user_id ?? 0;
            $ossPath = $this->buildPath('images', $file, $userId);

            $result = $this->ossService->upload($file->getPathname(), $ossPath);

            return $this->success([
                'url' => $result['url'] ?? '',
                'path' => $ossPath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMime(),
            ], '图片上传成功');
        } catch (\Exception $e) {
            Log::error('图片上传失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'upload_failed');
        }
    }

    /**
     * 视频上传
     * POST /api/upload/video
     */
    public function video()
    {
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('请选择要上传的视频', 400, 'file_required');
        }

        try {
            $this->validateFile($file, $this->videoExt, $this->videoMaxSize);

            $userId = $this->request->user_id ?? 0;
            $ossPath = $this->buildPath('videos', $file, $userId);

            $fileSize = $file->getSize();
            if ($fileSize > 20971520) {
                $result = $this->ossService->multipartUpload($file->getPathname(), $ossPath);
            } else {
                $result = $this->ossService->upload($file->getPathname(), $ossPath);
            }

            return $this->success([
                'url' => $result['url'] ?? '',
                'path' => $ossPath,
                'size' => $fileSize,
                'mime_type' => $file->getMime(),
            ], '视频上传成功');
        } catch (\Exception $e) {
            Log::error('视频上传失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'upload_failed');
        }
    }

    /**
     * 通用文件上传
     * POST /api/upload/file
     */
    public function file()
    {
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('请选择要上传的文件', 400, 'file_required');
        }

        $blockedExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'pht', 'phar', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'sh', 'bat', 'exe', 'com', 'vbs'];

        try {
            $ext = strtolower($file->getOriginalExtension());
            if (in_array($ext, $blockedExt)) {
                return $this->error('禁止上传该类型的文件', 400, 'file_type_blocked');
            }

            if ($file->getSize() > $this->fileMaxSize) {
                return $this->error('文件大小超过限制', 400, 'file_too_large');
            }

            $userId = $this->request->user_id ?? 0;
            $ossPath = $this->buildPath('files', $file, $userId);

            $result = $this->ossService->upload($file->getPathname(), $ossPath);

            return $this->success([
                'url' => $result['url'] ?? '',
                'path' => $ossPath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMime(),
                'original_name' => $file->getOriginalName(),
            ], '文件上传成功');
        } catch (\Exception $e) {
            Log::error('文件上传失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'upload_failed');
        }
    }

    /**
     * 头像上传
     * POST /api/upload/avatar
     */
    public function avatar()
    {
        $file = $this->request->file('file');

        if (empty($file)) {
            return $this->error('请选择要上传的头像', 400, 'file_required');
        }

        $userId = $this->request->user_id ?? null;
        if (!$userId) {
            return $this->error('用户未登录', 401, 'unauthorized');
        }

        try {
            $this->validateFile($file, $this->imageExt, $this->avatarMaxSize);

            $ossPath = sprintf(
                'avatars/%s/%s.%s',
                date('Ymd'),
                md5((string)$userId . microtime(true)),
                strtolower($file->getOriginalExtension())
            );

            $result = $this->ossService->upload($file->getPathname(), $ossPath);

            $avatarUrl = $result['url'] ?? '';
            if ($avatarUrl) {
                \app\model\User::where('id', $userId)->update(['avatar' => $avatarUrl]);
            }

            return $this->success([
                'url' => $avatarUrl,
                'path' => $ossPath,
            ], '头像上传成功');
        } catch (\Exception $e) {
            Log::error('头像上传失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'upload_failed');
        }
    }

    protected function validateFile($file, array $allowedExt, int $maxSize): void
    {
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, $allowedExt)) {
            throw new \Exception('不支持的文件类型，允许的类型: ' . implode(',', $allowedExt));
        }

        if ($file->getSize() > $maxSize) {
            throw new \Exception('文件大小超过限制');
        }
    }

    protected function buildPath(string $type, $file, int $userId = 0): string
    {
        return sprintf(
            '%s/%s/%d_%s.%s',
            $type,
            date('Ymd'),
            $userId ?: 0,
            md5(uniqid((string)mt_rand(), true)),
            strtolower($file->getOriginalExtension())
        );
    }
}
