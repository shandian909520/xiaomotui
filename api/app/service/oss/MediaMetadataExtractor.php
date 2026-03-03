<?php
declare (strict_types = 1);

namespace app\service\oss;

use think\facade\Log;
use think\Exception;

/**
 * 媒体元数据提取服务
 * 支持提取视频、音频、图片的元数据信息
 *
 * @package app\service\oss
 */
class MediaMetadataExtractor
{
    /**
     * FFmpeg路径
     * @var string
     */
    protected string $ffmpegPath = 'ffmpeg';

    /**
     * FFprobe路径
     * @var string
     */
    protected string $ffprobePath = 'ffprobe';

    /**
     * 构造函数
     * @param array $config 配置
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['ffmpeg_path'])) {
            $this->ffmpegPath = $config['ffmpeg_path'];
        }

        if (!empty($config['ffprobe_path'])) {
            $this->ffprobePath = $config['ffprobe_path'];
        }
    }

    /**
     * 提取媒体文件元数据
     * @param string $filePath 文件路径
     * @param string $mediaType 媒体类型 (video, audio, image)
     * @return array
     */
    public function extract(string $filePath, string $mediaType): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('文件不存在: ' . $filePath);
            }

            return match($mediaType) {
                'video' => $this->extractVideoMetadata($filePath),
                'audio' => $this->extractAudioMetadata($filePath),
                'image' => $this->extractImageMetadata($filePath),
                default => throw new Exception('不支持的媒体类型: ' . $mediaType),
            };

        } catch (\Exception $e) {
            Log::error('提取媒体元数据失败', [
                'file_path' => $filePath,
                'media_type' => $mediaType,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 提取视频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractVideoMetadata(string $filePath): array
    {
        $metadata = [
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'bitrate' => 0,
            'codec' => '',
            'fps' => 0,
            'audio_codec' => '',
            'audio_bitrate' => 0,
            'audio_sample_rate' => 0,
            'format' => '',
            'file_size' => filesize($filePath),
        ];

        try {
            // 尝试使用FFprobe获取详细信息
            if ($this->commandExists($this->ffprobePath)) {
                return $this->extractVideoMetadataWithFFprobe($filePath);
            }

            // 降级使用PHP原生方法
            return $this->extractVideoMetadataBasic($filePath);

        } catch (\Exception $e) {
            Log::warning('提取视频元数据失败,使用基础信息', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return $metadata;
        }
    }

    /**
     * 使用FFprobe提取视频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractVideoMetadataWithFFprobe(string $filePath): array
    {
        $command = escapeshellcmd($this->ffprobePath) .
                   ' -v quiet -print_format json -show_format -show_streams ' .
                   escapeshellarg($filePath);

        $output = shell_exec($command);

        if (empty($output)) {
            throw new Exception('FFprobe执行失败');
        }

        $data = json_decode($output, true);

        if (!$data) {
            throw new Exception('解析FFprobe输出失败');
        }

        $metadata = [
            'duration' => (float)($data['format']['duration'] ?? 0),
            'bitrate' => (int)($data['format']['bit_rate'] ?? 0),
            'format' => $data['format']['format_name'] ?? '',
            'file_size' => (int)($data['format']['size'] ?? filesize($filePath)),
        ];

        // 提取视频流信息
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $metadata['width'] = (int)($stream['width'] ?? 0);
                $metadata['height'] = (int)($stream['height'] ?? 0);
                $metadata['codec'] = $stream['codec_name'] ?? '';
                $metadata['fps'] = $this->calculateFps($stream['r_frame_rate'] ?? '0/0');
                break;
            }
        }

        // 提取音频流信息
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'audio') {
                $metadata['audio_codec'] = $stream['codec_name'] ?? '';
                $metadata['audio_bitrate'] = (int)($stream['bit_rate'] ?? 0);
                $metadata['audio_sample_rate'] = (int)($stream['sample_rate'] ?? 0);
                break;
            }
        }

        return $metadata;
    }

    /**
     * 使用基础方法提取视频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractVideoMetadataBasic(string $filePath): array
    {
        $metadata = [
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'bitrate' => 0,
            'codec' => '',
            'fps' => 0,
            'format' => pathinfo($filePath, PATHINFO_EXTENSION),
            'file_size' => filesize($filePath),
        ];

        // 尝试使用getID3(如果已安装)
        if (class_exists('getid3')) {
            try {
                $getID3 = new \getID3();
                $info = $getID3->analyze($filePath);

                if (isset($info['video'])) {
                    $metadata['width'] = (int)($info['video']['resolution_x'] ?? 0);
                    $metadata['height'] = (int)($info['video']['resolution_y'] ?? 0);
                    $metadata['fps'] = (float)($info['video']['frame_rate'] ?? 0);
                    $metadata['codec'] = $info['video']['dataformat'] ?? '';
                }

                if (isset($info['playtime_seconds'])) {
                    $metadata['duration'] = (float)$info['playtime_seconds'];
                }

                if (isset($info['bitrate'])) {
                    $metadata['bitrate'] = (int)$info['bitrate'];
                }
            } catch (\Exception $e) {
                // 忽略错误,使用默认值
            }
        }

        return $metadata;
    }

    /**
     * 提取音频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractAudioMetadata(string $filePath): array
    {
        $metadata = [
            'duration' => 0,
            'bitrate' => 0,
            'sample_rate' => 0,
            'channels' => 0,
            'codec' => '',
            'format' => '',
            'file_size' => filesize($filePath),
            'artist' => '',
            'title' => '',
            'album' => '',
            'year' => '',
        ];

        try {
            // 尝试使用FFprobe
            if ($this->commandExists($this->ffprobePath)) {
                return $this->extractAudioMetadataWithFFprobe($filePath);
            }

            // 降级使用getID3
            if (class_exists('getid3')) {
                return $this->extractAudioMetadataWithGetID3($filePath);
            }

            // 基础信息
            $metadata['format'] = pathinfo($filePath, PATHINFO_EXTENSION);

        } catch (\Exception $e) {
            Log::warning('提取音频元数据失败,使用基础信息', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * 使用FFprobe提取音频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractAudioMetadataWithFFprobe(string $filePath): array
    {
        $command = escapeshellcmd($this->ffprobePath) .
                   ' -v quiet -print_format json -show_format -show_streams ' .
                   escapeshellarg($filePath);

        $output = shell_exec($command);

        if (empty($output)) {
            throw new Exception('FFprobe执行失败');
        }

        $data = json_decode($output, true);

        if (!$data) {
            throw new Exception('解析FFprobe输出失败');
        }

        $metadata = [
            'duration' => (float)($data['format']['duration'] ?? 0),
            'bitrate' => (int)($data['format']['bit_rate'] ?? 0),
            'format' => $data['format']['format_name'] ?? '',
            'file_size' => (int)($data['format']['size'] ?? filesize($filePath)),
        ];

        // 提取音频流信息
        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'audio') {
                $metadata['codec'] = $stream['codec_name'] ?? '';
                $metadata['sample_rate'] = (int)($stream['sample_rate'] ?? 0);
                $metadata['channels'] = (int)($stream['channels'] ?? 0);
                break;
            }
        }

        // 提取标签信息
        $tags = $data['format']['tags'] ?? [];
        $metadata['artist'] = $tags['artist'] ?? $tags['ARTIST'] ?? '';
        $metadata['title'] = $tags['title'] ?? $tags['TITLE'] ?? '';
        $metadata['album'] = $tags['album'] ?? $tags['ALBUM'] ?? '';
        $metadata['year'] = $tags['year'] ?? $tags['date'] ?? $tags['DATE'] ?? '';

        return $metadata;
    }

    /**
     * 使用getID3提取音频元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractAudioMetadataWithGetID3(string $filePath): array
    {
        $getID3 = new \getID3();
        $info = $getID3->analyze($filePath);

        return [
            'duration' => (float)($info['playtime_seconds'] ?? 0),
            'bitrate' => (int)($info['bitrate'] ?? 0),
            'sample_rate' => (int)($info['audio']['sample_rate'] ?? 0),
            'channels' => (int)($info['audio']['channels'] ?? 0),
            'codec' => $info['audio']['dataformat'] ?? '',
            'format' => $info['fileformat'] ?? '',
            'file_size' => (int)($info['filesize'] ?? filesize($filePath)),
            'artist' => $info['tags']['id3v2']['artist'][0] ?? '',
            'title' => $info['tags']['id3v2']['title'][0] ?? '',
            'album' => $info['tags']['id3v2']['album'][0] ?? '',
            'year' => $info['tags']['id3v2']['year'][0] ?? '',
        ];
    }

    /**
     * 提取图片元数据
     * @param string $filePath 文件路径
     * @return array
     */
    protected function extractImageMetadata(string $filePath): array
    {
        $imageInfo = @getimagesize($filePath);

        if (!$imageInfo) {
            throw new Exception('无法读取图片信息');
        }

        $metadata = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => image_type_to_extension($imageInfo[2], false),
            'mime' => $imageInfo['mime'],
            'file_size' => filesize($filePath),
            'channels' => 0,
            'bits' => 0,
        ];

        // 尝试读取EXIF信息
        if (function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($filePath);
                if ($exif) {
                    $metadata['exif'] = [
                        'camera_make' => $exif['Make'] ?? '',
                        'camera_model' => $exif['Model'] ?? '',
                        'datetime' => $exif['DateTime'] ?? '',
                        'iso' => $exif['ISOSpeedRatings'] ?? 0,
                        'exposure_time' => $exif['ExposureTime'] ?? '',
                        'fnumber' => $exif['FNumber'] ?? '',
                        'focal_length' => $exif['FocalLength'] ?? '',
                        'gps' => $exif['GPSLatitude'] ?? '',
                    ];
                }
            } catch (\Exception $e) {
                // 忽略EXIF读取错误
            }
        }

        // 获取色彩通道数
        if (isset($imageInfo['channels'])) {
            $metadata['channels'] = $imageInfo['channels'];
        }

        // 获取位深度
        if (isset($imageInfo['bits'])) {
            $metadata['bits'] = $imageInfo['bits'];
        }

        return $metadata;
    }

    /**
     * 从视频中提取缩略图
     * @param string $videoPath 视频文件路径
     * @param string $outputPath 输出路径
     * @param int $timeOffset 时间偏移(秒)
     * @param array $options 选项
     * @return bool
     */
    public function extractVideoThumbnail(string $videoPath, string $outputPath, int $timeOffset = 5, array $options = []): bool
    {
        try {
            if (!$this->commandExists($this->ffmpegPath)) {
                throw new Exception('FFmpeg未安装');
            }

            $width = $options['width'] ?? 320;
            $height = $options['height'] ?? 240;
            $quality = $options['quality'] ?? 85;

            $command = escapeshellcmd($this->ffmpegPath) .
                       ' -i ' . escapeshellarg($videoPath) .
                       ' -ss ' . escapeshellarg((string)$timeOffset) .
                       ' -vframes 1' .
                       ' -vf scale=' . escapeshellarg($width . ':' . $height) .
                       ' -q:v ' . escapeshellarg((string)$quality) .
                       ' -y ' . escapeshellarg($outputPath) .
                       ' 2>&1';

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('FFmpeg执行失败: ' . implode("\n", $output));
            }

            return file_exists($outputPath);

        } catch (\Exception $e) {
            Log::error('提取视频缩略图失败', [
                'video_path' => $videoPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 计算帧率
     * @param string $frameRate 帧率字符串 (如 "25/1")
     * @return float
     */
    protected function calculateFps(string $frameRate): float
    {
        $parts = explode('/', $frameRate);

        if (count($parts) === 2) {
            $numerator = (float)$parts[0];
            $denominator = (float)$parts[1];

            return $denominator > 0 ? $numerator / $denominator : 0;
        }

        return (float)$frameRate;
    }

    /**
     * 检查命令是否存在
     * @param string $command 命令
     * @return bool
     */
    protected function commandExists(string $command): bool
    {
        $whereIsCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';

        exec($whereIsCommand . ' ' . escapeshellcmd($command), $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * 获取媒体文件时长(秒)
     * @param string $filePath 文件路径
     * @return float
     */
    public function getDuration(string $filePath): float
    {
        try {
            $metadata = $this->extract($filePath, 'video');
            return (float)($metadata['duration'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
