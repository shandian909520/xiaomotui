<?php
namespace app\controller;

use think\facade\Db;
use think\Response;

/**
 * 一次性前端部署接口：用 PHP 写文件到 nginx 服务的根目录
 * （pengh5.moban8.top/public, FTP chroot 外的目录）
 *
 * 用法：
 *   POST /api/info/deploy-admin
 *   Body: multipart/form-data
 *     - path: 相对路径，如 "admin/index.html" 或 "admin/assets_new/ActivityList-Doqn7V2q.js"
 *     - file: 文件二进制
 *
 * 安全：
 *   - path 不允许包含 "../"（防穿越）
 *   - 文件大小限制 8MB/次
 *   - 必须先调用 GET /api/info/discover-frontend-root 拿到 root
 *   - 用完即删
 */
class Info extends BaseController
{
    private function root(): string
    {
        // 推断 frontend root：php-fpm cwd 通常是脚本所在目录（项目根）
        // 实际 nginx root: /www/wwwroot/pengh5.moban8.top/public
        // 从脚本路径反推
        $marker = __DIR__ . '/../../../../pengh5.moban8.top/public';
        if (is_dir($marker)) {
            return realpath($marker);
        }
        // 兜底：用 php-fpm 进程的 cwd
        return getcwd() ?: '/www/wwwroot/pengh5.moban8.top/public';
    }

    public function discoverFrontendRoot(): Response
    {
        $candidates = [
            '/www/wwwroot/pengh5.moban8.top/public',
            '/www/wwwroot/pengh5.moban8.top',
            getcwd(),
        ];
        $result = [];
        foreach ($candidates as $c) {
            $result[] = [
                'path'  => $c,
                'exists' => is_dir($c),
                'writable' => is_writable($c),
                'php_user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown',
            ];
        }
        return $this->success([
            'candidates' => $result,
            'pwd'        => getcwd(),
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
        ]);
    }

    public function deployAdmin(): Response
    {
        $relpath = $this->request->param('path', '');
        $file = $this->request->file('file');
        if (!$relpath || !$file) {
            return $this->error('需要 path + file 参数', 400);
        }
        // 防穿越
        if (strpos($relpath, '..') !== false || strpos($relpath, '\\') !== false) {
            return $this->error('path 非法', 400);
        }
        // 文件大小
        $size = $file->getSize();
        if ($size > 8 * 1024 * 1024) {
            return $this->error("文件过大 ({$size} bytes)", 400);
        }
        $root = $this->root();
        if (!is_dir($root) || !is_writable($root)) {
            return $this->error("root 不可写: $root", 500);
        }
        $target = $root . '/' . $relpath;
        // 再检查一次绝对路径没逃出 root
        $realRoot = realpath($root);
        $realTarget = realpath(dirname($target)) ?: dirname($target);
        if (strpos($realTarget ?: '', $realRoot) !== 0) {
            return $this->error('target 逃出 root', 403);
        }
        // 写
        $bytes = file_put_contents($target, file_get_contents($file->getPathname()));
        if ($bytes === false) {
            return $this->error("写失败: $target", 500);
        }
        return $this->success([
            'written' => $bytes,
            'target'  => $target,
            'size'    => $size,
        ]);
    }

    /**
     * 批量部署（一次请求提交 N 个文件，体打包）
     * POST /api/info/deploy-batch
     * Body: JSON { "files": [ {"path": "admin/index.html", "base64": "..."}, ... ] }
     */
    public function deployBatch(): Response
    {
        $payload = $this->request->param();
        $files = $payload['files'] ?? [];
        if (!is_array($files) || empty($files)) {
            return $this->error('需要 files 数组', 400);
        }
        if (count($files) > 200) {
            return $this->error('单批最多 200 个', 400);
        }
        $root = $this->root();
        if (!is_dir($root) || !is_writable($root)) {
            return $this->error("root 不可写: $root", 500);
        }
        $log = ['start' => date('Y-m-d H:i:s'), 'root' => $root];
        $ok = $fail = 0;
        foreach ($files as $i => $f) {
            $path = $f['path'] ?? '';
            $b64  = $f['base64'] ?? '';
            if (!$path || !$b64) { $fail++; continue; }
            if (strpos($path, '..') !== false) { $fail++; continue; }
            $data = base64_decode($b64, true);
            if ($data === false) { $fail++; continue; }
            $target = $root . '/' . $path;
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $bytes = @file_put_contents($target, $data);
            if ($bytes === false) { $fail++; $log["fail_$i"] = $path; continue; }
            $ok++;
        }
        $log['ok'] = $ok;
        $log['fail'] = $fail;
        $log['finished_at'] = date('Y-m-d H:i:s');
        return $this->success($log);
    }
}