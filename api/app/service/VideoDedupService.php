<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;
use think\Exception;
use app\model\PromoTemplate;
use app\model\PromoVariant;
use app\model\PromoMaterial;

/**
 * 视频去重服务
 * 生成视频变体以避免平台重复检测
 */
class VideoDedupService
{
    /**
     * 视频合成服务
     */
    private VideoComposeService $composeService;

    /**
     * 输出目录
     */
    private const OUTPUT_PATH = 'uploads/promo/variants/';

    /**
     * 临时目录
     */
    private const TEMP_PATH = 'runtime/temp/variants/';

    /**
     * FFmpeg可执行文件路径
     */
    private string $ffmpegPath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->composeService = new VideoComposeService();
        $this->ffmpegPath = config('video.ffmpeg_path', 'ffmpeg');
    }

    /**
     * 生成随机去重参数
     *
     * @return array 去重参数
     */
    public function randomizeParams(): array
    {
        $defaults = PromoVariant::DEFAULT_DEDUP_PARAMS;

        return [
            'brightness' => $this->randomFloat(
                $defaults['brightness']['min'],
                $defaults['brightness']['max']
            ),
            'contrast' => $this->randomFloat(
                $defaults['contrast']['min'],
                $defaults['contrast']['max']
            ),
            'saturation' => $this->randomFloat(
                $defaults['saturation']['min'],
                $defaults['saturation']['max']
            ),
            'noise_level' => mt_rand(
                (int)($defaults['noise_level']['min'] * 100),
                (int)($defaults['noise_level']['max'] * 100)
            ) / 100,
            'speed_variation' => $this->randomFloat(
                $defaults['speed_variation']['min'],
                $defaults['speed_variation']['max']
            ),
            'mirror' => mt_rand(0, 100) / 100 < ($defaults['mirror_chance'] ?? 0.1),
            'crop' => $this->randomFloat(
                $defaults['crop_range']['min'],
                $defaults['crop_range']['max']
            ),
            'timestamp' => time(),
            'random_seed' => mt_rand(),
        ];
    }

    /**
     * 应用帧级变换
     *
     * @param string $videoPath 输入视频路径
     * @param array $params 去重参数
     * @return string 输出视频路径
     * @throws Exception
     */
    public function modifyFrame(string $videoPath, array $params): string
    {
        $fullPath = $this->getFullPath($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        // 创建临时目录
        $tempDir = public_path() . self::TEMP_PATH . date('Ymd') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputFile = $tempDir . $this->generateFileName('mp4');

        // 构建滤镜链
        $filters = $this->buildFrameFilters($params);
        $filterChain = implode(',', $filters);

        // 构建FFmpeg命令
        $command = sprintf(
            '%s -y -i %s -vf "%s" -c:v libx264 -preset medium -crf 23 -c:a copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($fullPath),
            $filterChain,
            escapeshellarg($outputFile)
        );

        Log::debug('应用帧级变换', [
            'params' => $params,
            'command' => $command,
        ]);

        $output = shell_exec($command);

        if (!file_exists($outputFile)) {
            throw new Exception("帧级变换失败: " . $output);
        }

        return str_replace(public_path(), '', $outputFile);
    }

    /**
     * 构建帧级滤镜
     *
     * @param array $params 去重参数
     * @return array 滤镜数组
     */
    private function buildFrameFilters(array $params): array
    {
        $filters = [];

        // 亮度调整 (eq=brightness=X)
        if (isset($params['brightness']) && $params['brightness'] != 0) {
            $brightness = (float)$params['brightness'];
            $filters[] = sprintf('eq=brightness=%.4f', $brightness);
        }

        // 对比度调整 (eq=contrast=X)
        if (isset($params['contrast']) && $params['contrast'] != 1) {
            $contrast = (float)$params['contrast'];
            $filters[] = sprintf('eq=contrast=%.4f', $contrast);
        }

        // 饱和度调整 (eq=saturation=X)
        if (isset($params['saturation']) && $params['saturation'] != 1) {
            $saturation = (float)$params['saturation'];
            $filters[] = sprintf('eq=saturation=%.4f', $saturation);
        }

        // 速度变化
        if (isset($params['speed_variation']) && $params['speed_variation'] != 1) {
            $speed = (float)$params['speed_variation'];
            // 使用setpts进行速度调整
            $filters[] = sprintf('setpts=%.4f*PTS', 1 / $speed);
        }

        // 镜像翻转
        if (!empty($params['mirror'])) {
            $filters[] = 'hflip';
        }

        // 轻微裁剪和缩放
        if (isset($params['crop']) && $params['crop'] > 0) {
            $cropRatio = (float)$params['crop'];
            // 裁剪后再缩放到原尺寸
            $filters[] = sprintf(
                'crop=iw-%d:ih-%d:%d:%d,scale=iw+%d:ih+%d',
                (int)($cropRatio * 1920 * 2),
                (int)($cropRatio * 1080 * 2),
                (int)($cropRatio * 1920),
                (int)($cropRatio * 1080),
                (int)($cropRatio * 1920 * 2),
                (int)($cropRatio * 1080 * 2)
            );
        }

        // 如果没有滤镜，添加null
        if (empty($filters)) {
            $filters[] = 'null';
        }

        return $filters;
    }

    /**
     * 添加随机噪声
     *
     * @param string $videoPath 输入视频路径
     * @param float $level 噪声级别 (0-10)
     * @return string 输出视频路径
     * @throws Exception
     */
    public function addNoise(string $videoPath, float $level = 1.0): string
    {
        $fullPath = $this->getFullPath($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        // 创建临时目录
        $tempDir = public_path() . self::TEMP_PATH . date('Ymd') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputFile = $tempDir . $this->generateFileName('mp4');

        // 限制噪声级别
        $level = max(0, min(10, $level));

        // 使用noise滤镜添加噪声
        // noise滤镜参数: allf (所有帧的噪声强度)
        $noiseValue = (int)($level * 4); // 转换为0-40范围

        $command = sprintf(
            '%s -y -i %s -vf "noise=allf=%d:alls=%d" -c:v libx264 -preset medium -crf 23 -c:a copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($fullPath),
            $noiseValue,
            $noiseValue,
            escapeshellarg($outputFile)
        );

        Log::debug('添加随机噪声', [
            'level' => $level,
            'noise_value' => $noiseValue,
            'command' => $command,
        ]);

        $output = shell_exec($command);

        if (!file_exists($outputFile)) {
            throw new Exception("添加噪声失败: " . $output);
        }

        return str_replace(public_path(), '', $outputFile);
    }

    /**
     * 修改文件MD5
     * 通过在文件末尾添加随机数据来改变MD5值
     *
     * @param string $videoPath 输入视频路径
     * @return string 输出视频路径
     * @throws Exception
     */
    public function modifyMd5(string $videoPath): string
    {
        $fullPath = $this->getFullPath($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        // 创建临时目录
        $tempDir = public_path() . self::TEMP_PATH . date('Ymd') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputFile = $tempDir . $this->generateFileName('mp4');

        // 方法1: 使用FFmpeg重新编码（保持质量但改变文件结构）
        // 添加随机元数据
        $randomMetadata = md5(uniqid((string)mt_rand(), true));

        $command = sprintf(
            '%s -y -i %s -c copy -metadata variant_id="%s" -metadata creation_time="%s" %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($fullPath),
            $randomMetadata,
            date('c'),
            escapeshellarg($outputFile)
        );

        Log::debug('修改视频MD5', [
            'command' => $command,
        ]);

        $output = shell_exec($command);

        if (!file_exists($outputFile)) {
            throw new Exception("修改MD5失败: " . $output);
        }

        // 如果FFmpeg复制方式没有改变MD5，则在文件末尾追加随机字节
        $originalMd5 = md5_file($fullPath);
        $newMd5 = md5_file($outputFile);

        if ($originalMd5 === $newMd5) {
            // 复制文件并追加随机数据
            copy($fullPath, $outputFile);

            $handle = fopen($outputFile, 'ab');
            fwrite($handle, chr(0) . chr(0) . chr(0) . chr(1)); // 添加少量数据
            fwrite($handle, $randomMetadata);
            fclose($handle);

            Log::debug('通过追加数据修改MD5', [
                'original_md5' => $originalMd5,
                'new_md5' => md5_file($outputFile),
            ]);
        }

        return str_replace(public_path(), '', $outputFile);
    }

    /**
     * 生成单个变体
     *
     * @param string $videoPath 输入视频路径
     * @param array|null $params 去重参数（可选，不传则随机生成）
     * @return array 包含文件路径和参数的数组
     * @throws Exception
     */
    public function generateVariant(string $videoPath, ?array $params = null): array
    {
        // 生成随机参数
        if ($params === null) {
            $params = $this->randomizeParams();
        }

        Log::info('开始生成视频变体', [
            'input' => $videoPath,
            'params' => $params,
        ]);

        try {
            $currentPath = $videoPath;

            // 步骤1: 应用帧级变换
            if ($params['brightness'] != 0 || $params['contrast'] != 1 ||
                $params['saturation'] != 1 || $params['speed_variation'] != 1 ||
                !empty($params['mirror']) || $params['crop'] > 0) {
                $currentPath = $this->modifyFrame($currentPath, $params);
            }

            // 步骤2: 添加噪声
            if ($params['noise_level'] > 0) {
                $currentPath = $this->addNoise($currentPath, $params['noise_level']);
            }

            // 步骤3: 修改MD5
            $currentPath = $this->modifyMd5($currentPath);

            // 获取文件信息
            $fullPath = $this->getFullPath($currentPath);
            $fileSize = filesize($fullPath);
            $md5 = md5_file($fullPath);

            // 检查MD5是否已存在
            $attempts = 0;
            $maxAttempts = 5;

            while (PromoVariant::isMd5Exists($md5) && $attempts < $maxAttempts) {
                Log::warning('MD5已存在，重新生成', [
                    'md5' => $md5,
                    'attempt' => $attempts + 1,
                ]);

                // 重新生成参数并处理
                $params = $this->randomizeParams();
                $currentPath = $this->modifyFrame($videoPath, $params);

                if ($params['noise_level'] > 0) {
                    $currentPath = $this->addNoise($currentPath, $params['noise_level']);
                }

                $currentPath = $this->modifyMd5($currentPath);
                $fullPath = $this->getFullPath($currentPath);
                $md5 = md5_file($fullPath);
                $fileSize = filesize($fullPath);

                $attempts++;
            }

            if (PromoVariant::isMd5Exists($md5)) {
                throw new Exception('无法生成唯一的变体，请尝试调整参数范围');
            }

            Log::info('视频变体生成成功', [
                'output' => $currentPath,
                'md5' => $md5,
                'file_size' => $fileSize,
            ]);

            return [
                'file_path' => $currentPath,
                'file_size' => $fileSize,
                'md5' => $md5,
                'params' => $params,
            ];
        } catch (\Exception $e) {
            Log::error('生成视频变体失败', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }

    /**
     * 批量生成变体
     *
     * @param int $templateId 模板ID
     * @param int $count 生成数量
     * @return array 生成结果
     * @throws Exception
     */
    public function generateVariants(int $templateId, int $count): array
    {
        Log::info('开始批量生成视频变体', [
            'template_id' => $templateId,
            'count' => $count,
        ]);

        // 获取模板
        $template = PromoTemplate::find($templateId);
        if (!$template) {
            throw new Exception('模板不存在');
        }

        if (!$template->isEnabled()) {
            throw new Exception('模板已禁用');
        }

        // 获取素材列表
        $materials = $template->getMaterials();
        if (empty($materials)) {
            throw new Exception('模板没有关联素材');
        }

        // 提取图片素材
        $imagePaths = [];
        foreach ($materials as $material) {
            if ($material['type'] === PromoMaterial::TYPE_IMAGE) {
                $imagePaths[] = $material['file_url'];
            }
        }

        if (empty($imagePaths)) {
            throw new Exception('模板没有图片素材');
        }

        $results = [
            'total' => $count,
            'success' => 0,
            'failed' => 0,
            'variants' => [],
        ];

        // 创建输出目录
        $outputDir = public_path() . self::OUTPUT_PATH . date('Ym/d/');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        for ($i = 0; $i < $count; $i++) {
            try {
                Log::info("生成第 {$i} 个变体");

                // 步骤1: 使用随机参数合成基础视频
                $config = $template->config;
                $basePath = $this->composeService->composeFromImages($imagePaths, $config);

                // 步骤2: 生成去重变体
                $params = $this->randomizeParams();
                $variantResult = $this->generateVariant($basePath, $params);

                // 移动到最终目录
                $finalPath = $outputDir . $this->generateFileName('mp4');
                $fullBasePath = $this->getFullPath($variantResult['file_path']);
                rename($fullBasePath, $finalPath);

                $relativePath = self::OUTPUT_PATH . date('Ym/d/') . basename($finalPath);

                // 获取视频时长
                $duration = $this->composeService->getVideoDuration($finalPath);

                // 创建变体记录
                $variant = new PromoVariant();
                $variant->template_id = $templateId;
                $variant->merchant_id = $template->merchant_id;
                $variant->file_url = '/' . $relativePath;
                $variant->file_size = $variantResult['file_size'];
                $variant->duration = $duration;
                $variant->md5 = $variantResult['md5'];
                $variant->params_json = $variantResult['params'];
                $variant->use_count = 0;
                $variant->status = PromoVariant::STATUS_ENABLED;
                $variant->save();

                $results['success']++;
                $results['variants'][] = [
                    'variant_id' => $variant->id,
                    'file_url' => $variant->file_url,
                    'md5' => $variant->md5,
                    'file_size' => $variant->file_size,
                ];

                // 清理基础视频
                $fullBaseOriginal = $this->getFullPath($basePath);
                if (file_exists($fullBaseOriginal)) {
                    @unlink($fullBaseOriginal);
                }

                Log::info("第 {$i} 个变体生成成功", ['variant_id' => $variant->id]);
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error("第 {$i} 个变体生成失败", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('批量生成变体完成', $results);

        return $results;
    }

    /**
     * 获取完整路径
     *
     * @param string $path 路径
     * @return string 完整路径
     */
    private function getFullPath(string $path): string
    {
        if (strpos($path, '/') === 0 && !file_exists($path)) {
            return public_path() . ltrim($path, '/');
        }
        return $path;
    }

    /**
     * 生成随机浮点数
     *
     * @param float $min 最小值
     * @param float $max 最大值
     * @param int $precision 精度
     * @return float
     */
    private function randomFloat(float $min, float $max, int $precision = 4): float
    {
        $value = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return round($value, $precision);
    }

    /**
     * 生成文件名
     *
     * @param string $extension 文件扩展名
     * @return string 文件名
     */
    private function generateFileName(string $extension): string
    {
        return date('His') . substr(md5(uniqid((string)mt_rand(), true)), 0, 16) . '.' . $extension;
    }

    /**
     * 清理过期的临时文件
     *
     * @param int $days 保留天数
     * @return int 删除的文件数量
     */
    public function cleanupOldTempFiles(int $days = 1): int
    {
        $tempDir = public_path() . self::TEMP_PATH;
        if (!is_dir($tempDir)) {
            return 0;
        }

        $count = 0;
        $cutoffTime = time() - ($days * 86400);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                @unlink($file->getPathname());
                $count++;
            }
        }

        Log::info('清理临时文件完成', [
            'deleted_count' => $count,
            'days' => $days,
        ]);

        return $count;
    }
}
