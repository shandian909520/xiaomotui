<?php
// 全局公共函数（ThinkPHP 自动加载）

/**
 * 探测文件 MIME 类型
 * 服务器未启用 fileinfo 扩展时（mime_content_type/finfo 均不可用）退化为扩展名映射
 */
function xmt_mime_type(string $path): string
{
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }
    if (class_exists('finfo')) {
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable $e) {
            // fallthrough
        }
    }

    static $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif',  'webp' => 'image/webp',  'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'mp4' => 'video/mp4',  'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'pdf' => 'application/pdf',
        'txt' => 'text/plain', 'json' => 'application/json',
    ];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== '' && isset($map[$ext])) {
        return $map[$ext];
    }

    // 无扩展名（如 PHP 上传临时文件）时按文件头魔数嗅探
    $head = (string)@file_get_contents($path, false, null, 0, 16);
    if ($head !== '') {
        if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) return 'image/png';
        if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
        if (strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0) return 'image/gif';
        if (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') return 'image/webp';
        if (strncmp($head, 'BM', 2) === 0) return 'image/bmp';
        if (strncmp($head, '%PDF', 4) === 0) return 'application/pdf';
        if (substr($head, 4, 4) === 'ftyp') return 'video/mp4';
        if (strncmp($head, 'ID3', 3) === 0 || strncmp($head, "\xFF\xFB", 2) === 0) return 'audio/mpeg';
    }
    return 'application/octet-stream';
}
