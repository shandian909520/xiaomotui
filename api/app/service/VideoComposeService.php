<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;
use think\Exception;
use app\model\PromoMaterial;
use app\model\PromoTemplate;

/**
 * 视频合成服务
 * 基于FFmpeg实现图片轮播合成、转场效果、背景音乐添加等功能
 */
class VideoComposeService
{
    /**
     * 输出目录
     */
    private const OUTPUT_PATH = 'uploads/promo/videos/';

    /**
     * 临时目录
     */
    private const TEMP_PATH = 'runtime/temp/videos/';

    /**
     * FFmpeg可执行文件路径
     */
    private string $ffmpegPath;

    /**
     * FFprobe可执行文件路径
     */
    private string $ffprobePath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->ffmpegPath = config('video.ffmpeg_path', 'ffmpeg');
        $this->ffprobePath = config('video.ffprobe_path', 'ffprobe');
    }

    /**
     * 从图片合成视频
     *
     * @param array $imagePaths 图片路径数组（绝对路径或相对public的路径）
     * @param array $config 合成配置
     * @return string 生成的视频路径
     * @throws Exception
     */
    public function composeFromImages(array $imagePaths, array $config = []): string
    {
        Log::info('开始合成视频', [
            'image_count' => count($imagePaths),
            'config' => $config,
        ]);

        // 合并默认配置
        $config = array_merge(PromoTemplate::DEFAULT_CONFIG, $config);

        // 验证图片
        if (empty($imagePaths)) {
            throw new Exception('图片列表不能为空');
        }

        foreach ($imagePaths as $path) {
            $fullPath = $this->getFullPath($path);
            if (!file_exists($fullPath)) {
                throw new Exception("图片文件不存在: {$path}");
            }
        }

        // 创建临时目录
        $tempDir = public_path() . self::TEMP_PATH . date('Ymd') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 生成输出文件名
        $outputFileName = $this->generateFileName('mp4');
        $outputPath = public_path() . self::OUTPUT_PATH . date('Ym/d/');
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }
        $outputFile = $outputPath . $outputFileName;
        $relativePath = self::OUTPUT_PATH . date('Ym/d/') . $outputFileName;

        try {
            // 步骤1: 为每张图片创建视频片段
            $segments = [];
            foreach ($imagePaths as $index => $imagePath) {
                $segmentFile = $tempDir . "segment_{$index}.mp4";
                $this->createImageSegment(
                    $this->getFullPath($imagePath),
                    $segmentFile,
                    (float)$config['duration_per_image'],
                    $config
                );
                $segments[] = $segmentFile;
            }

            // 步骤2: 合并所有片段（带转场效果）
            if (count($segments) > 1 && $config['transition_type'] !== PromoTemplate::TRANSITION_NONE) {
                $mergedFile = $tempDir . 'merged_' . $outputFileName;
                $this->mergeWithTransition($segments, $mergedFile, $config);
                rename($mergedFile, $outputFile);
            } else {
                // 无转场，直接拼接
                $this->concatVideos($segments, $outputFile);
            }

            // 步骤3: 添加背景音乐
            if (!empty($config['music_id'])) {
                $musicMaterial = PromoMaterial::find($config['music_id']);
                if ($musicMaterial && $musicMaterial->type === PromoMaterial::TYPE_MUSIC) {
                    $tempWithMusic = $tempDir . 'with_music_' . $outputFileName;
                    $this->addMusic(
                        $outputFile,
                        $this->getFullPath($musicMaterial->file_url),
                        $tempWithMusic,
                        (float)$config['music_volume']
                    );
                    rename($tempWithMusic, $outputFile);
                }
            }

            // 清理临时文件
            $this->cleanupTempFiles($segments);

            Log::info('视频合成成功', [
                'output_file' => $relativePath,
                'file_size' => filesize($outputFile),
            ]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('视频合成失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('视频合成失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建图片视频片段
     *
     * @param string $imagePath 图片路径
     * @param string $outputFile 输出文件路径
     * @param float $duration 时长(秒)
     * @param array $config 配置
     */
    private function createImageSegment(string $imagePath, string $outputFile, float $duration, array $config): void
    {
        $resolution = $this->getResolution($config['resolution'] ?? '1080p');
        $fps = (int)($config['fps'] ?? 30);

        // 构建FFmpeg命令
        $command = sprintf(
            '%s -y -loop 1 -i %s -c:v libx264 -t %f -pix_fmt yuv420p -vf "scale=%s:force_original_aspect_ratio=decrease,pad=%s:(ow-iw)/2:(oh-ih)/2" -r %d %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($imagePath),
            $duration,
            $resolution,
            $resolution,
            $fps,
            escapeshellarg($outputFile)
        );

        Log::debug('创建图片片段', ['command' => $command]);

        $output = shell_exec($command);
        if (!file_exists($outputFile)) {
            throw new Exception("创建图片片段失败: " . $output);
        }
    }

    /**
     * 带转场效果合并视频
     *
     * @param array $segments 视频片段路径数组
     * @param string $outputFile 输出文件路径
     * @param array $config 配置
     */
    private function mergeWithTransition(array $segments, string $outputFile, array $config): void
    {
        $transitionType = $config['transition_type'] ?? PromoTemplate::TRANSITION_FADE;
        $transitionDuration = (float)($config['transition_duration'] ?? 0.5);

        // 生成转场滤镜
        $filterComplex = $this->buildTransitionFilter($segments, $transitionType, $transitionDuration);

        // 构建输入参数
        $inputs = '';
        foreach ($segments as $segment) {
            $inputs .= ' -i ' . escapeshellarg($segment);
        }

        // 构建FFmpeg命令
        $command = sprintf(
            '%s -y %s -filter_complex "%s" -c:v libx264 -pix_fmt yuv420p %s 2>&1',
            $this->ffmpegPath,
            $inputs,
            $filterComplex,
            escapeshellarg($outputFile)
        );

        Log::debug('带转场合并视频', ['command' => $command]);

        $output = shell_exec($command);
        if (!file_exists($outputFile)) {
            throw new Exception("带转场合并失败: " . $output);
        }
    }

    /**
     * 构建转场滤镜
     *
     * @param array $segments 视频片段
     * @param string $transitionType 转场类型
     * @param float $duration 转场时长
     * @return string 滤镜字符串
     */
    private function buildTransitionFilter(array $segments, string $transitionType, float $duration): string
    {
        $count = count($segments);
        if ($count < 2) {
            return '';
        }

        // 获取每个片段的时长
        $durations = [];
        foreach ($segments as $segment) {
            $durations[] = $this->getVideoDuration($segment);
        }

        // 构建滤镜链
        $filterParts = [];
        $currentInput = '[0:v]';

        for ($i = 1; $i < $count; $i++) {
            $offset = array_sum(array_slice($durations, 0, $i)) - $duration * $i;

            switch ($transitionType) {
                case PromoTemplate::TRANSITION_FADE:
                    $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition=fade:duration={$duration}:offset={$offset}";
                    break;
                case PromoTemplate::TRANSITION_SLIDE:
                    $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition=slideleft:duration={$duration}:offset={$offset}";
                    break;
                case PromoTemplate::TRANSITION_ZOOM:
                    $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition=zoomin:duration={$duration}:offset={$offset}";
                    break;
                case PromoTemplate::TRANSITION_WIPE:
                    $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition=wipeleft:duration={$duration}:offset={$offset}";
                    break;
                default:
                    $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition=fade:duration={$duration}:offset={$offset}";
            }

            $currentInput = "[v{$i}]";
        }

        // 修改最后一个输出标签
        $lastIndex = $count - 1;
        $filterParts[$lastIndex - 1] .= "[vout]";

        return implode(';', $filterParts) . ';[vout]copy';
    }

    /**
     * 简单拼接视频（无转场）
     *
     * @param array $segments 视频片段路径数组
     * @param string $outputFile 输出文件路径
     */
    private function concatVideos(array $segments, string $outputFile): void
    {
        // 创建临时文件列表
        $tempDir = dirname($segments[0]);
        $listFile = $tempDir . '/filelist.txt';

        $listContent = '';
        foreach ($segments as $segment) {
            $listContent .= "file '" . addslashes($segment) . "'\n";
        }

        file_put_contents($listFile, $listContent);

        // 构建FFmpeg命令
        $command = sprintf(
            '%s -y -f concat -safe 0 -i %s -c copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($listFile),
            escapeshellarg($outputFile)
        );

        Log::debug('拼接视频', ['command' => $command]);

        $output = shell_exec($command);

        // 删除临时文件列表
        @unlink($listFile);

        if (!file_exists($outputFile)) {
            throw new Exception("视频拼接失败: " . $output);
        }
    }

    /**
     * 添加背景音乐
     *
     * @param string $videoPath 视频路径
     * @param string $musicPath 音乐路径
     * @param string $outputFile 输出文件路径
     * @param float $volume 音量(0-1)
     */
    public function addMusic(string $videoPath, string $musicPath, string $outputFile, float $volume = 0.5): void
    {
        if (!file_exists($videoPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        if (!file_exists($musicPath)) {
            throw new Exception("音乐文件不存在: {$musicPath}");
        }

        // 获取视频时长
        $videoDuration = $this->getVideoDuration($videoPath);

        // 构建FFmpeg命令
        // -stream_loop -1 表示循环音频，-t 表示截取到视频时长
        $command = sprintf(
            '%s -y -i %s -stream_loop -1 -i %s -c:v copy -c:a aac -b:a 128k -t %f -filter_complex "[1:a]volume=%f[audio]" -map 0:v -map "[audio]" %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($videoPath),
            escapeshellarg($musicPath),
            $videoDuration,
            $volume,
            escapeshellarg($outputFile)
        );

        Log::debug('添加背景音乐', ['command' => $command]);

        $output = shell_exec($command);
        if (!file_exists($outputFile)) {
            throw new Exception("添加背景音乐失败: " . $output);
        }
    }

    /**
     * 添加转场效果到现有视频
     *
     * @param string $videoPath 视频路径
     * @param string $transitionType 转场类型
     * @param float $duration 转场时长
     * @return string 处理后的视频路径
     */
    public function addTransition(string $videoPath, string $transitionType, float $duration = 0.5): string
    {
        // 此方法用于对单个视频添加入场/出场效果
        $fullPath = $this->getFullPath($videoPath);

        if (!file_exists($fullPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        $tempDir = public_path() . self::TEMP_PATH . date('Ymd') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputFile = $tempDir . $this->generateFileName('mp4');

        // 根据转场类型构建滤镜
        $filter = match ($transitionType) {
            PromoTemplate::TRANSITION_FADE => 'fade=t=in:st=0:d=' . $duration . ',fade=t=out:st=' . ($this->getVideoDuration($fullPath) - $duration) . ':d=' . $duration,
            default => 'null',
        };

        if ($filter === 'null') {
            return $videoPath;
        }

        $command = sprintf(
            '%s -y -i %s -vf "%s" -c:v libx264 -pix_fmt yuv420p -c:a copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($fullPath),
            $filter,
            escapeshellarg($outputFile)
        );

        Log::debug('添加转场效果', ['command' => $command]);

        $output = shell_exec($command);
        if (!file_exists($outputFile)) {
            throw new Exception("添加转场效果失败: " . $output);
        }

        // 返回相对路径
        return str_replace(public_path(), '', $outputFile);
    }

    /**
     * 获取视频时长
     *
     * @param string $videoPath 视频路径
     * @return float 时长(秒)
     */
    public function getVideoDuration(string $videoPath): float
    {
        $command = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            $this->ffprobePath,
            escapeshellarg($videoPath)
        );

        $output = shell_exec($command);
        return (float)trim($output);
    }

    /**
     * 获取视频信息
     *
     * @param string $videoPath 视频路径
     * @return array 视频信息
     */
    public function getVideoInfo(string $videoPath): array
    {
        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>/dev/null',
            $this->ffprobePath,
            escapeshellarg($videoPath)
        );

        $output = shell_exec($command);
        $data = json_decode($output, true);

        $info = [
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'fps' => 0,
            'bitrate' => 0,
        ];

        if (isset($data['format']['duration'])) {
            $info['duration'] = round((float)$data['format']['duration'], 2);
        }

        if (isset($data['format']['bit_rate'])) {
            $info['bitrate'] = (int)$data['format']['bit_rate'];
        }

        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $info['width'] = (int)($stream['width'] ?? 0);
                $info['height'] = (int)($stream['height'] ?? 0);

                // 解析帧率
                if (isset($stream['r_frame_rate'])) {
                    $fpsParts = explode('/', $stream['r_frame_rate']);
                    if (count($fpsParts) === 2 && (int)$fpsParts[1] > 0) {
                        $info['fps'] = (int)round((int)$fpsParts[0] / (int)$fpsParts[1]);
                    }
                }
                break;
            }
        }

        return $info;
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
     * 获取分辨率字符串
     *
     * @param string $resolution 分辨率名称
     * @return string 分辨率字符串 (如 1920x1080)
     */
    private function getResolution(string $resolution): string
    {
        $resolutions = PromoTemplate::getResolutionOptions();
        return $resolutions[$resolution] ?? $resolutions['1080p'];
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
     * 清理临时文件
     *
     * @param array $files 文件列表
     */
    private function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * 检查FFmpeg是否可用
     *
     * @return bool
     */
    public function isFFmpegAvailable(): bool
    {
        $command = $this->ffmpegPath . ' -version 2>&1';
        $output = shell_exec($command);
        return strpos($output, 'ffmpeg version') !== false;
    }

    /**
     * 获取FFmpeg版本
     *
     * @return string|null
     */
    public function getFFmpegVersion(): ?string
    {
        $command = $this->ffmpegPath . ' -version 2>&1';
        $output = shell_exec($command);

        if (preg_match('/ffmpeg version (\S+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
