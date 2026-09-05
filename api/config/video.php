<?php

/**
 * 视频合成相关配置
 * 包含 FFmpeg、edge-tts、输出路径等配置项
 */
return [
    // FFmpeg 可执行文件路径
    'ffmpeg_path' => env('video.ffmpeg_path', 'ffmpeg'),

    // FFprobe 可执行文件路径
    'ffprobe_path' => env('video.ffprobe_path', 'ffprobe'),

    // edge-tts 可执行文件路径
    'edge_tts_path' => env('video.edge_tts_path', 'edge-tts'),

    // 剪辑工程输出目录（相对于 public/）
    'output_path' => env('video.output_path', 'uploads/clip-projects/'),

    // 临时文件目录（相对于 runtime/）
    'temp_path' => env('video.temp_path', 'runtime/temp/clip/'),

    // TTS 输出目录（相对于 runtime/）
    'tts_path' => env('video.tts_path', 'runtime/tts/'),
];
