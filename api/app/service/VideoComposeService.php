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
        $resolution = $config['resolution'] ?? '1080x1920';
        // 如果是简短名称如 1080p，转换为实际分辨率
        if (!str_contains($resolution, 'x')) {
            $resolution = $this->getResolution($resolution);
        }
        $fps = (int)($config['fps'] ?? 30);

        // 构建FFmpeg命令
        // resolution 格式为 WxH（如 1080x1920），需转为 W:H
        $resParts = explode('x', $resolution);
        $width = $resParts[0] ?? '1080';
        $height = $resParts[1] ?? '1920';
        $scaleFilter = "scale={$width}:{$height}";

        $command = sprintf(
            '%s -y -loop 1 -i %s -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -c:v libx264 -t %f -pix_fmt yuv420p -vf "%s" -r %d -c:a aac -shortest %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($imagePath),
            $duration,
            $scaleFilter,
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
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s ' . (DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null'),
            $this->ffprobePath,
            escapeshellarg($videoPath)
        );

        $output = shell_exec($command);
        return $output ? (float)trim($output) : 0.0;
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
            '%s -v quiet -print_format json -show_format -show_streams %s ' . (DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null'),
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

    // ==================== TTS 配音 + 字幕 + 混流 ====================

    /**
     * 带转场效果拼接多个视频片段（公共方法）
     * 支持 fade/slide/zoom/wipe/random 转场类型，无转场时用 concat demuxer
     *
     * @param array $clipPaths 视频片段绝对路径数组
     * @param string $transitionType 转场类型：none/fade/slide/zoom/wipe/random
     * @param string $outputPath 输出文件绝对路径
     * @return string 输出文件路径
     * @throws Exception
     */
    public function concatWithTransitions(array $clipPaths, string $transitionType, string $outputPath): string
    {
        if (count($clipPaths) < 1) {
            throw new Exception('视频片段不能为空');
        }

        // 单个片段直接复制
        if (count($clipPaths) === 1) {
            if ($clipPaths[0] !== $outputPath) {
                copy($clipPaths[0], $outputPath);
            }
            return $outputPath;
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 无转场，用 concat demuxer 直接拼接
        if ($transitionType === 'none' || empty($transitionType)) {
            $this->concatVideos($clipPaths, $outputPath);
            return $outputPath;
        }

        // random 模式：每个转场随机选择一种
        $transitionDuration = 0.5;

        // 获取每个片段的时长
        $durations = [];
        foreach ($clipPaths as $clip) {
            $durations[] = $this->getVideoDuration($clip);
        }

        // 支持的 xfade 转场类型
        $xfadeTypes = [
            'fade'   => 'fade',
            'slide'  => 'slideleft',
            'zoom'   => 'zoomin',
            'wipe'   => 'wipeleft',
        ];

        $count = count($clipPaths);

        // 构建输入参数
        $inputs = '';
        foreach ($clipPaths as $clip) {
            $inputs .= ' -i ' . escapeshellarg($clip);
        }

        // 构建 xfade 滤镜链
        $filterParts = [];
        $currentInput = '[0:v]';
        $audioInputs = '[0:a]';

        for ($i = 1; $i < $count; $i++) {
            $offset = array_sum(array_slice($durations, 0, $i)) - $transitionDuration * $i;
            $offset = max(0, $offset);

            // 选择转场类型
            if ($transitionType === 'random') {
                $xfadeName = $xfadeTypes[array_rand($xfadeTypes)];
            } else {
                $xfadeName = $xfadeTypes[$transitionType] ?? 'fade';
            }

            if ($i < $count - 1) {
                $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition={$xfadeName}:duration={$transitionDuration}:offset=" . round($offset, 3) . "[v{$i}]";
                $currentInput = "[v{$i}]";
            } else {
                $filterParts[] = "{$currentInput}[{$i}:v]xfade=transition={$xfadeName}:duration={$transitionDuration}:offset=" . round($offset, 3) . "[vout]";
            }
        }

        $filterComplex = implode(';', $filterParts);

        // xfade 只处理视频流，需要补充静音音频
        $command = sprintf(
            '%s -y %s -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -filter_complex "%s" -map "[vout]" -map %d:a -c:v libx264 -pix_fmt yuv420p -c:a aac -shortest %s 2>&1',
            $this->ffmpegPath,
            $inputs,
            $filterComplex,
            $count,
            escapeshellarg($outputPath)
        );

        Log::debug('转场拼接', ['command' => $command]);

        $output = shell_exec($command);

        if (!file_exists($outputPath)) {
            throw new Exception('带转场拼接失败: ' . $output);
        }

        return $outputPath;
    }

    /**
     * 生成 SRT 字幕文件并烧录到视频上
     *
     * @param string $videoPath 输入视频绝对路径
     * @param array $subtitles 字幕数组 [{start: 0.0, end: 3.0, text: '你好'}, ...]
     * @param array $style 样式配置 [fontsize, color, ...]
     * @param string $outputPath 输出文件绝对路径
     * @return string 输出文件路径
     * @throws Exception
     */
    public function addSubtitles(string $videoPath, array $subtitles, array $style, string $outputPath): string
    {
        if (!file_exists($videoPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 无字幕时直接复制
        if (empty($subtitles)) {
            if ($videoPath !== $outputPath) {
                copy($videoPath, $outputPath);
            }
            return $outputPath;
        }

        // 生成 SRT 文件
        $srtPath = dirname($outputPath) . '/sub_' . uniqid() . '.srt';
        $this->generateSrtFile($subtitles, $srtPath);

        // 字幕样式参数
        $fontSize = intval($style['fontsize'] ?? 24);
        $fontColor = $style['color'] ?? '&HFFFFFF';
        $outlineColor = $style['outline_color'] ?? '&H000000';

        // 使用 FFmpeg subtitles 滤镜烧录 SRT 字幕
        $srtPathEsc = str_replace('\\', '/', $srtPath);
        // Windows 路径转义: D:/... -> D\\:/...
        if (DIRECTORY_SEPARATOR === '\\') {
            $srtPathEsc = preg_replace('/^([A-Za-z]):/', '$1\\\\:', $srtPathEsc);
        } else {
            $srtPathEsc = str_replace(':', '\\:', $srtPathEsc);
        }

        // 构建 force_style，用双引号包裹（兼容 Windows 和 Linux）
        $forceStyle = "FontSize={$fontSize},PrimaryColour={$fontColor},OutlineColour={$outlineColor},Outline=2,Shadow=1";
        $filterStr = "subtitles='{$srtPathEsc}':force_style='{$forceStyle}'";

        $command = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -pix_fmt yuv420p -c:a copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($videoPath),
            escapeshellarg($filterStr),
            escapeshellarg($outputPath)
        );

        Log::debug('烧录字幕', ['command' => $command]);

        $output = shell_exec($command);

        // 清理 SRT 临时文件
        @unlink($srtPath);

        // subtitles 滤镜在 Windows 可能因 fontconfig 缺失而失败
        // 回退：直接复制视频，不烧录字幕
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::warning('字幕烧录失败（可能缺少 fontconfig），跳过字幕');
            copy($videoPath, $outputPath);
        }

        return $outputPath;
    }

    /**
     * 混合背景音乐到视频（保留视频原音）
     *
     * @param string $videoPath 视频绝对路径
     * @param string $musicPath 背景音乐绝对路径
     * @param int $volume 背景音乐音量（0-100）
     * @param string $outputPath 输出文件绝对路径
     * @return string 输出文件路径
     * @throws Exception
     */
    public function mixBackgroundMusic(string $videoPath, string $musicPath, int $volume, string $outputPath): string
    {
        if (!file_exists($videoPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }
        if (!file_exists($musicPath)) {
            throw new Exception("背景音乐文件不存在: {$musicPath}");
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $volumeNormalized = max(0, min(100, $volume)) / 100;

        // 获取视频时长，用于限制音乐长度
        $videoDuration = $this->getVideoDuration($videoPath);

        // 检测视频是否有音频流
        $probeCmd = sprintf('%s -v quiet -print_format json -show_streams %s 2>&1',
            $this->ffmpegPath, escapeshellarg($videoPath));
        $probeRaw = shell_exec($probeCmd);
        $probe = json_decode($probeRaw ?: '{}', true);
        $hasAudio = false;
        foreach ($probe['streams'] ?? [] as $s) {
            if (($s['codec_type'] ?? '') === 'audio') {
                $hasAudio = true;
                break;
            }
        }

        if ($hasAudio) {
            // 视频有音频：混合原音 + 背景音乐
            $command = sprintf(
                '%s -y -i %s -stream_loop -1 -i %s -filter_complex "[0:a]volume=1.0[original];[1:a]volume=%f,atrim=0:%f[audio];[original][audio]amix=inputs=2:duration=longest:dropout_transition=2[aout]" -map 0:v -map "[aout]" -c:v copy -c:a aac -b:a 128k -t %f %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($videoPath),
                escapeshellarg($musicPath),
                $volumeNormalized,
                $videoDuration,
                $videoDuration,
                escapeshellarg($outputPath)
            );
        } else {
            // 视频无音频：直接添加背景音乐
            $command = sprintf(
                '%s -y -i %s -stream_loop -1 -i %s -filter_complex "[1:a]volume=%f,atrim=0:%f[aout]" -map 0:v -map "[aout]" -c:v copy -c:a aac -b:a 128k -t %f %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($videoPath),
                escapeshellarg($musicPath),
                $volumeNormalized,
                $videoDuration,
                $videoDuration,
                escapeshellarg($outputPath)
            );
        }

        Log::debug('混合背景音乐', ['command' => $command, 'hasAudio' => $hasAudio]);

        $output = shell_exec($command);

        if (!file_exists($outputPath)) {
            throw new Exception('背景音乐混流失败: ' . $output);
        }

        return $outputPath;
    }

    /**
     * 将 TTS 配音叠加到视频上
     *
     * @param string $videoPath 视频绝对路径
     * @param string $voicePath 配音音频绝对路径
     * @param string $outputPath 输出文件绝对路径
     * @return string 输出文件路径
     * @throws Exception
     */
    public function addVoiceover(string $videoPath, string $voicePath, string $outputPath): string
    {
        if (!file_exists($videoPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }
        if (!file_exists($voicePath)) {
            throw new Exception("配音文件不存在: {$voicePath}");
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 使用 amix 混合：视频原音 + 配音，配音音量略高
        $command = sprintf(
            '%s -y -i %s -i %s -filter_complex "[0:a]volume=0.6[original];[1:a]volume=1.2[voice];[original][voice]amix=inputs=2:duration=first:dropout_transition=2[aout]" -map 0:v -map "[aout]" -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($videoPath),
            escapeshellarg($voicePath),
            escapeshellarg($outputPath)
        );

        $output = shell_exec($command);

        // 如果 amix 失败，改用直接替换音频
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::warning('amix失败，尝试直接替换音频');
            $command = sprintf(
                '%s -y -i %s -i %s -map 0:v -map 1:a -c:v copy -c:a aac -b:a 128k -shortest %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($videoPath),
                escapeshellarg($voicePath),
                escapeshellarg($outputPath)
            );
            $output = shell_exec($command);
        }

        clearstatcache(true, $outputPath);
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new Exception('配音叠加失败: ' . $output);
        }

        return $outputPath;
    }

    /**
     * 调整视频色彩（对比度、饱和度、亮度、色度）
     *
     * @param string $videoPath 输入视频绝对路径
     * @param array $colorConfig 色彩配置 [contrast, saturation, brightness, hue]
     *                           值范围 -100 到 100，0 为不调整
     * @param string $outputPath 输出文件绝对路径
     * @return string 输出文件路径
     * @throws Exception
     */
    public function adjustColor(string $videoPath, array $colorConfig, string $outputPath): string
    {
        if (!file_exists($videoPath)) {
            throw new Exception("视频文件不存在: {$videoPath}");
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 所有值都为 0 时跳过
        $contrast    = intval($colorConfig['contrast'] ?? 0);
        $saturation  = intval($colorConfig['saturation'] ?? 0);
        $brightness  = intval($colorConfig['brightness'] ?? 0);
        $hue         = intval($colorConfig['hue'] ?? 0);

        if ($contrast === 0 && $saturation === 0 && $brightness === 0 && $hue === 0) {
            if ($videoPath !== $outputPath) {
                copy($videoPath, $outputPath);
            }
            return $outputPath;
        }

        // 将 -100~100 映射到 FFmpeg eq 滤镜的参数范围
        // contrast: 0.0~2.0 (1.0=原始), 映射: 1.0 + value/100
        // brightness: -1.0~1.0 (0=原始), 映射: value/200
        // saturation: 0.0~3.0 (1.0=原始), 映射: 1.0 + value/100
        // gamma: 0.1~10.0 (1.0=原始)
        $eqContrast   = round(1.0 + $contrast / 100, 2);
        $eqBrightness = round($brightness / 200, 2);
        $eqSaturation = round(1.0 + $saturation / 100, 2);

        $filters = [];
        $filters[] = "eq=contrast={$eqContrast}:brightness={$eqBrightness}:saturation={$eqSaturation}";

        // hue 滤镜：值单位为度数，-100~100 映射到 -180~180 度
        if ($hue !== 0) {
            $hueDegrees = round($hue * 1.8, 1);
            $filters[] = "hue=h={$hueDegrees}";
        }

        $filterStr = implode(',', $filters);

        $command = sprintf(
            '%s -y -i %s -vf "%s" -c:v libx264 -pix_fmt yuv420p -c:a copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($videoPath),
            $filterStr,
            escapeshellarg($outputPath)
        );

        Log::debug('调色', ['command' => $command]);

        $output = shell_exec($command);

        if (!file_exists($outputPath)) {
            throw new Exception('色彩调整失败: ' . $output);
        }

        return $outputPath;
    }

    /**
     * 完整渲染流程
     * 下载素材 -> 生成TTS配音 -> 拼接视频 -> 烧字幕 -> 混音乐 -> 调色 -> 输出
     *
     * @param array $projectData 工程数据（来自 ClipProject 模型）
     * @param array $shots 分镜数据（来自 ClipShot 模型，已按 sort_order 排序）
     * @return string 最终输出视频的相对路径（相对于 public/）
     * @throws Exception
     */
    public function fullCompose(array $projectData, array $shots): string
    {
        $projectId = $projectData['id'] ?? 0;
        $config = $projectData['config'] ?? [];
        if ($config instanceof \stdClass) {
            $config = json_decode(json_encode($config), true);
        }
        $globalConfig = $config['globalConfig'] ?? [];
        if ($globalConfig instanceof \stdClass) {
            $globalConfig = json_decode(json_encode($globalConfig), true);
        }

        Log::info('开始完整渲染流程', ['project_id' => $projectId, 'shots_count' => count($shots)]);

        // 创建临时工作目录
        $tempBase = root_path() . config('video.temp_path', 'runtime/temp/clip/');
        $tempDir = $tempBase . $projectId . '_' . date('YmdHis') . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 输出目录
        $outputBase = public_path() . config('video.output_path', 'uploads/clip-projects/');
        $outputDir = $outputBase . $projectId . '/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $ttsService = new TTSService();
            $tempFiles = []; // 需要清理的临时文件

            // ========== 步骤1：为每个分镜准备素材 ==========
            $clipPaths = [];
            $allSubtitles = [];

            foreach ($shots as $index => $shot) {
                $shotTempDir = $tempDir . "shot_{$index}/";
                if (!is_dir($shotTempDir)) {
                    mkdir($shotTempDir, 0755, true);
                }

                $materialUrl = $shot['material_url'] ?? '';
                $materialType = $shot['material_type'] ?? 'image';
                $duration = floatval($shot['duration'] ?? 3.0);
                $subtitle = $shot['subtitle'] ?? '';
                $voiceText = $shot['voice_text'] ?? '';

                // 准备素材文件（本地路径或下载远程文件）
                $materialPath = $this->prepareMaterialFile($materialUrl, $shotTempDir, $tempFiles);

                // 如果是图片，先转为视频片段
                if ($materialType === 'image' || $this->isImagePath($materialPath)) {
                    $segmentPath = $shotTempDir . "segment_{$index}.mp4";
                    $this->createImageSegment($materialPath, $segmentPath, $duration, [
                        'resolution' => $this->resolutionFromRatio($globalConfig['aspectRatio'] ?? '9:16'),
                        'fps' => intval($globalConfig['frameRate'] ?? 30),
                    ]);
                    $clipPaths[] = $segmentPath;
                } else {
                    // 视频素材：如果需要消除原声，去掉音频流
                    $muteOriginal = !empty($shot['mute_original']);
                    if ($muteOriginal) {
                        $mutedPath = $shotTempDir . "muted_{$index}.mp4";
                        $muteCmd = sprintf('%s -y -i %s -c:v copy -an %s 2>&1',
                            $this->ffmpegPath, escapeshellarg($materialPath), escapeshellarg($mutedPath));
                        shell_exec($muteCmd);
                        $clipPaths[] = file_exists($mutedPath) ? $mutedPath : $materialPath;
                    } else {
                        $clipPaths[] = $materialPath;
                    }
                }

                // 收集字幕（计算时间偏移）
                if (!empty($subtitle)) {
                    $prevDuration = 0;
                    for ($j = 0; $j < $index; $j++) {
                        $prevDuration += floatval($shots[$j]['duration'] ?? 3.0);
                    }
                    $allSubtitles[] = [
                        'start' => $prevDuration,
                        'end'   => $prevDuration + $duration,
                        'text'  => $subtitle,
                    ];
                }

                // 生成 TTS 配音
                if (!empty($voiceText)) {
                    $voiceName = $ttsService->resolveVoiceName($globalConfig['voice']['actor'] ?? 'female1');
                    $voicePath = $shotTempDir . "voice_{$index}.mp3";
                    try {
                        $ttsService->synthesize($voiceText, $voiceName, $voicePath);
                        $tempFiles[] = $voicePath;
                    } catch (\Exception $e) {
                        Log::warning('TTS合成跳过', ['shot' => $index, 'error' => $e->getMessage()]);
                    }
                }
            }

            if (empty($clipPaths)) {
                throw new Exception('没有可用的视频片段');
            }

            // ========== 步骤2：拼接视频（带转场） ==========
            $transitionType = $shots[0]['transition_type'] ?? 'none';
            // 如果第一个镜头没有转场，检查全局配置
            if ($transitionType === 'none') {
                $transitionType = $config['batch']['transition'] ?? 'none';
            }
            // 对所有分镜的 transition_type 取最多的那种
            $transitionCounts = [];
            foreach ($shots as $shot) {
                $tt = $shot['transition_type'] ?? 'none';
                $transitionCounts[$tt] = ($transitionCounts[$tt] ?? 0) + 1;
            }
            arsort($transitionCounts);
            $dominantTransition = array_key_first($transitionCounts);
            if ($dominantTransition === 'none' && count($transitionCounts) > 1) {
                // 取第二个最常见的转场
                $keys = array_keys($transitionCounts);
                $dominantTransition = $keys[1] ?? 'none';
            }

            $concatOutput = $tempDir . 'step2_concat.mp4';
            $this->concatWithTransitions($clipPaths, $dominantTransition, $concatOutput);
            $currentVideo = $concatOutput;

            // ========== 步骤3：叠加配音 ==========
            // 收集所有生成的配音文件，合并为一个
            $voiceFiles = [];
            foreach ($shots as $index => $shot) {
                $voicePath = $tempDir . "shot_{$index}/voice_{$index}.mp3";
                if (file_exists($voicePath)) {
                    $voiceFiles[] = $voicePath;
                }
            }

            if (!empty($voiceFiles)) {
                // 将多个配音文件合并为一个
                $mergedVoicePath = $tempDir . 'step3_merged_voice.mp3';
                $this->mergeAudioFiles($voiceFiles, $mergedVoicePath);

                $voiceoverOutput = $tempDir . 'step3_voiceover.mp4';
                $this->addVoiceover($currentVideo, $mergedVoicePath, $voiceoverOutput);
                $currentVideo = $voiceoverOutput;
            }

            // ========== 步骤4：烧录字幕 ==========
            $subtitleConfig = $globalConfig['subtitle'] ?? [];
            $showSubtitle = $subtitleConfig['show'] ?? true;

            if ($showSubtitle && !empty($allSubtitles)) {
                $subtitleStyle = [
                    'fontsize'      => intval($subtitleConfig['fontSize'] ?? 24),
                    'color'         => $this->colorToAss($subtitleConfig['color'] ?? '#ffffff'),
                    'outline_color' => '&H000000',
                ];

                $subtitleOutput = $tempDir . 'step4_subtitle.mp4';
                $this->addSubtitles($currentVideo, $allSubtitles, $subtitleStyle, $subtitleOutput);
                $currentVideo = $subtitleOutput;
            }

            // ========== 步骤5：混合背景音乐 ==========
            $musicSrc = $globalConfig['music']['src'] ?? '';
            $musicVolume = intval($globalConfig['music']['volume'] ?? 50);

            if (!empty($musicSrc)) {
                $musicPath = $this->resolveMusicPath($musicSrc, $tempDir, $tempFiles);
                if ($musicPath && file_exists($musicPath)) {
                    $musicOutput = $tempDir . 'step5_music.mp4';
                    $this->mixBackgroundMusic($currentVideo, $musicPath, $musicVolume, $musicOutput);
                    $currentVideo = $musicOutput;
                }
            }

            // ========== 步骤6：调色 ==========
            $colorConfig = $globalConfig['color'] ?? [];

            if (!empty($colorConfig)) {
                $colorOutput = $tempDir . 'step6_color.mp4';
                $this->adjustColor($currentVideo, $colorConfig, $colorOutput);
                $currentVideo = $colorOutput;
            }

            // ========== 步骤7：最终输出 ==========
            $finalOutput = $outputDir . 'output.mp4';
            if ($currentVideo !== $finalOutput) {
                copy($currentVideo, $finalOutput);
            }

            // 获取最终视频时长
            $duration = $this->getVideoDuration($finalOutput);

            Log::info('完整渲染完成', [
                'project_id' => $projectId,
                'output' => $finalOutput,
                'duration' => $duration,
                'size' => filesize($finalOutput),
            ]);

            // 清理临时文件
            $this->cleanupDirectory($tempDir);

            // 返回相对路径
            return config('video.output_path', 'uploads/clip-projects/') . $projectId . '/output.mp4';

        } catch (\Exception $e) {
            // 调试模式：不清理临时文件以便排查问题
            // $this->cleanupDirectory($tempDir);
            Log::error('渲染失败，保留临时文件', ['tempDir' => $tempDir]);

            Log::error('完整渲染失败', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // ==================== fullCompose 辅助方法 ====================

    /**
     * 准备素材文件，如果是远程 URL 则下载到本地
     *
     * @param string $url 素材 URL 或本地路径
     * @param string $tempDir 临时目录
     * @param array $tempFiles 临时文件引用数组
     * @return string 本地文件绝对路径
     */
    private function prepareMaterialFile(string $url, string $tempDir, array &$tempFiles): string
    {
        if (empty($url)) {
            throw new Exception('素材地址为空');
        }

        // 已经是本地绝对路径
        $fullPath = $this->getFullPath($url);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // 远程 URL 下载
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $localPath = $tempDir . 'material_' . uniqid() . '.' . $ext;

            $content = @file_get_contents($url);
            if ($content === false) {
                throw new Exception("素材下载失败: {$url}");
            }

            file_put_contents($localPath, $content);
            $tempFiles[] = $localPath;
            return $localPath;
        }

        throw new Exception("素材文件不存在: {$url}");
    }

    /**
     * 判断文件是否为图片
     */
    private function isImagePath(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    /**
     * 根据宽高比返回分辨率字符串
     */
    private function resolutionFromRatio(string $ratio): string
    {
        return match ($ratio) {
            '9:16'  => '1080x1920',
            '16:9'  => '1920x1080',
            '1:1'   => '1080x1080',
            default => '1080x1920',
        };
    }

    /**
     * 生成 SRT 字幕文件
     *
     * @param array $subtitles 字幕数组
     * @param string $filePath SRT 文件路径
     */
    private function generateSrtFile(array $subtitles, string $filePath): void
    {
        $content = '';
        $index = 1;

        foreach ($subtitles as $sub) {
            $start = $this->secondsToSrtTime(floatval($sub['start'] ?? 0));
            $end = $this->secondsToSrtTime(floatval($sub['end'] ?? 0));
            $text = $sub['text'] ?? '';

            $content .= $index . "\n";
            $content .= $start . ' --> ' . $end . "\n";
            $content .= $text . "\n\n";
            $index++;
        }

        file_put_contents($filePath, $content);
    }

    /**
     * 将秒数转为 SRT 时间格式 HH:MM:SS,mmm
     */
    private function secondsToSrtTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $millis = round(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }

    /**
     * 将十六进制颜色转为 ASS 格式（&HBBGGRR）
     */
    private function colorToAss(string $hexColor): string
    {
        $hexColor = ltrim($hexColor, '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }
        $r = substr($hexColor, 0, 2);
        $g = substr($hexColor, 2, 2);
        $b = substr($hexColor, 4, 2);
        return '&H' . strtoupper($b . $g . $r);
    }

    /**
     * 合并多个音频文件为一个
     *
     * @param array $audioFiles 音频文件路径数组
     * @param string $outputPath 输出文件路径
     */
    private function mergeAudioFiles(array $audioFiles, string $outputPath): void
    {
        if (empty($audioFiles)) {
            return;
        }

        if (count($audioFiles) === 1) {
            copy($audioFiles[0], $outputPath);
            return;
        }

        // 使用 FFmpeg concat demuxer 拼接音频
        $listFile = dirname($outputPath) . '/audio_list_' . uniqid() . '.txt';
        $listContent = '';
        foreach ($audioFiles as $file) {
            $listContent .= "file '" . addslashes($file) . "'\n";
        }
        file_put_contents($listFile, $listContent);

        $command = sprintf(
            '%s -y -f concat -safe 0 -i %s -c copy %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($listFile),
            escapeshellarg($outputPath)
        );

        Log::debug('合并音频', ['command' => $command]);

        shell_exec($command);
        @unlink($listFile);

        if (!file_exists($outputPath)) {
            // 降级：只使用第一个音频
            copy($audioFiles[0], $outputPath);
        }
    }

    /**
     * 解析背景音乐路径
     * 支持内置标识（light/dynamic/warm/tense）和实际文件路径
     */
    private function resolveMusicPath(string $src, string $tempDir, array &$tempFiles): ?string
    {
        // 如果是实际文件路径
        $fullPath = $this->getFullPath($src);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // 如果是远程 URL
        if (filter_var($src, FILTER_VALIDATE_URL)) {
            $localPath = $tempDir . 'bg_music.mp3';
            $content = @file_get_contents($src);
            if ($content !== false) {
                file_put_contents($localPath, $content);
                $tempFiles[] = $localPath;
                return $localPath;
            }
        }

        // 内置音乐标识：查找 public/uploads/music/ 目录下对应文件
        $musicMap = [
            'light'   => 'light.mp3',
            'dynamic' => 'dynamic.mp3',
            'warm'    => 'warm.mp3',
            'tense'   => 'tense.mp3',
        ];

        $fileName = $musicMap[$src] ?? null;
        if ($fileName) {
            $musicPath = public_path() . 'uploads/music/' . $fileName;
            if (file_exists($musicPath)) {
                return $musicPath;
            }
        }

        Log::warning('背景音乐文件未找到', ['src' => $src]);
        return null;
    }

    /**
     * 递归清理目录
     */
    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->cleanupDirectory($file . '/');
            } else {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }
}
