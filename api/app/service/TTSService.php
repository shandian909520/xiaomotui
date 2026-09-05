<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;
use think\Exception;

/**
 * TTS 语音合成服务
 * 基于 Microsoft Edge TTS（edge-tts 命令行工具）
 * 免费、无需 API Key、支持多种中文语音角色
 */
class TTSService
{
    /**
     * edge-tts 可执行文件路径
     */
    private string $edgeTtsPath;

    /**
     * TTS 输出目录（绝对路径）
     */
    private string $ttsOutputDir;

    /**
     * 可用的中文语音角色列表
     */
    private const VOICES = [
        // 女声
        ['id' => 'zh-CN-XiaoxiaoNeural',       'name' => '晓晓（女声，自然）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaoyiNeural',         'name' => '晓伊（女声，温柔）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaohanNeural',        'name' => '晓涵（女声，甜美）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaomengNeural',       'name' => '晓梦（女声，可爱）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaomoNeural',         'name' => '晓墨（女声，知性）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaoruiNeural',        'name' => '晓瑞（女声，沉稳）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaoshuangNeural',     'name' => '晓双（女声，童声）',     'gender' => 'Female'],
        ['id' => 'zh-CN-XiaozhenNeural',       'name' => '晓甄（女声，御姐）',     'gender' => 'Female'],
        // 男声
        ['id' => 'zh-CN-YunxiNeural',          'name' => '云希（男声，阳光）',     'gender' => 'Male'],
        ['id' => 'zh-CN-YunjianNeural',        'name' => '云健（男声，沉稳）',     'gender' => 'Male'],
        ['id' => 'zh-CN-YunyangNeural',        'name' => '云扬（男声，新闻）',     'gender' => 'Male'],
        ['id' => 'zh-CN-YunxiaNeural',         'name' => '云夏（男声，少年）',     'gender' => 'Male'],
        ['id' => 'zh-CN-YunzeNeural',          'name' => '云泽（男声，成熟）',     'gender' => 'Male'],
        // 台湾地区
        ['id' => 'zh-TW-HsiaoChenNeural',      'name' => '晓辰（台湾女声）',       'gender' => 'Female'],
        ['id' => 'zh-TW-YunJheNeural',         'name' => '云哲（台湾男声）',       'gender' => 'Male'],
    ];

    /**
     * 语音角色 ID 到全局配置中 voice.actor 的映射
     * 用于前端 radio 值 -> edge-tts voice 名称的转换
     */
    private const VOICE_ACTOR_MAP = [
        'female1' => 'zh-CN-XiaoxiaoNeural',
        'female2' => 'zh-CN-XiaoyiNeural',
        'male1'   => 'zh-CN-YunxiNeural',
        'male2'   => 'zh-CN-YunjianNeural',
    ];

    public function __construct()
    {
        $this->edgeTtsPath = config('video.edge_tts_path', 'edge-tts');
        $this->ttsOutputDir = root_path() . config('video.tts_path', 'runtime/tts/');
    }

    /**
     * 语音合成：将文本转为 mp3 文件
     *
     * @param string $text 要合成的文本内容
     * @param string $voice 语音角色（edge-tts voice name）
     * @param string $outputPath 输出文件的绝对路径，为空则自动生成
     * @return string 生成的 mp3 文件绝对路径
     * @throws Exception
     */
    public function synthesize(string $text, string $voice = 'zh-CN-XiaoxiaoNeural', string $outputPath = ''): string
    {
        // 检查 edge-tts 是否可用
        if (!$this->isEdgeTtsAvailable()) {
            throw new Exception('edge-tts 工具不可用，请先安装：pip install edge-tts');
        }

        if (empty(trim($text))) {
            throw new Exception('合成文本不能为空');
        }

        // 自动生成输出路径
        if (empty($outputPath)) {
            if (!is_dir($this->ttsOutputDir)) {
                mkdir($this->ttsOutputDir, 0755, true);
            }
            $outputPath = $this->ttsOutputDir . date('Ymd_') . uniqid('tts_') . '.mp3';
        }

        // 确保输出目录存在
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 构建 edge-tts 命令
        $command = sprintf(
            '%s --voice %s --text %s --write-media %s 2>&1',
            $this->edgeTtsPath,
            escapeshellarg($voice),
            escapeshellarg($text),
            escapeshellarg($outputPath)
        );

        Log::info('TTS 开始合成', [
            'voice' => $voice,
            'text_length' => mb_strlen($text),
            'output' => $outputPath,
        ]);

        Log::debug('TTS 命令', ['command' => $command]);

        $output = shell_exec($command);

        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error('TTS 合成失败', [
                'output' => $output,
                'voice' => $voice,
            ]);
            throw new Exception('TTS 语音合成失败: ' . $output);
        }

        Log::info('TTS 合成成功', [
            'file' => $outputPath,
            'size' => filesize($outputPath),
        ]);

        return $outputPath;
    }

    /**
     * 获取可用的中文语音角色列表
     *
     * @return array 语音角色列表
     */
    public function getAvailableVoices(): array
    {
        return self::VOICES;
    }

    /**
     * 根据前端 voice.actor 值获取 edge-tts voice 名称
     *
     * @param string $actor 前端传入的角色标识（如 female1, male1）
     * @return string edge-tts voice 名称
     */
    public function resolveVoiceName(string $actor): string
    {
        return self::VOICE_ACTOR_MAP[$actor] ?? 'zh-CN-XiaoxiaoNeural';
    }

    /**
     * 检查 edge-tts 是否可用
     *
     * @return bool
     */
    public function isEdgeTtsAvailable(): bool
    {
        $command = $this->edgeTtsPath . ' --version 2>&1';
        $output = shell_exec($command);
        // edge-tts --version 会输出版本号，如 "edge-tts 6.1.9"
        return !empty($output) && (strpos($output, 'edge-tts') !== false || preg_match('/\d+\.\d+/', $output));
    }
}
