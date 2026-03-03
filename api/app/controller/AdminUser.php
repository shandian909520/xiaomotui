<?php
declare(strict_types=1);

namespace app\controller;

use app\model\User;
use app\service\OperationLogService;
use think\facade\Config;
use think\facade\Log;
use think\Response;

/**
 * 管理员控制器
 * 提供用户管理、系统设置、操作日志等管理功能
 */
class AdminUser extends BaseController
{
    /**
     * 用户列表
     * GET /api/admin/users
     */
    public function list(): Response
    {
        try {
            $page = $this->request->param('page/d', 1);
            $pageSize = $this->request->param('page_size/d', 20);
            $keyword = $this->request->param('keyword', '');
            $status = $this->request->param('status', '');
            $memberLevel = $this->request->param('member_level', '');

            $query = User::order('create_time', 'desc');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('nickname', "%{$keyword}%")
                      ->whereOr('phone', 'like', "%{$keyword}%");
                });
            }

            if ($status !== '') {
                $query->where('status', (int)$status);
            }

            if ($memberLevel !== '') {
                $query->where('member_level', $memberLevel);
            }

            $total = (clone $query)->count();
            $list = $query->page($page, $pageSize)
                ->field('id, nickname, phone, avatar, gender, member_level, points, status, last_login_time, create_time')
                ->select()
                ->toArray();

            return $this->success([
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => (int)ceil($total / $pageSize),
            ]);
        } catch (\Exception $e) {
            Log::error('获取用户列表失败', ['error' => $e->getMessage()]);
            return $this->error('获取用户列表失败：' . $e->getMessage());
        }
    }

    /**
     * 更新用户状态
     * PUT /api/admin/users/:id/status
     */
    public function updateStatus(): Response
    {
        try {
            $id = (int)$this->request->param('id');
            $status = $this->request->param('status/d');

            if (!in_array($status, [User::STATUS_DISABLED, User::STATUS_NORMAL])) {
                return $this->error('状态值无效', 400);
            }

            $user = User::find($id);
            if (!$user) {
                return $this->error('用户不存在', 404);
            }

            $user->status = $status;
            $user->save();

            $statusText = $status === User::STATUS_NORMAL ? '启用' : '禁用';
            return $this->success(null, "用户已{$statusText}");
        } catch (\Exception $e) {
            Log::error('更新用户状态失败', ['error' => $e->getMessage()]);
            return $this->error('更新用户状态失败：' . $e->getMessage());
        }
    }

    /**
     * 获取系统设置
     * GET /api/admin/settings
     */
    public function getSettings(): Response
    {
        try {
            $aiConfig = Config::get('ai', []);
            $authConfig = Config::get('auth', []);

            $data = [
                'site' => [
                    'name' => '小魔推',
                    'description' => 'NFC智能营销平台',
                    'version' => '1.0.0',
                ],
                'ai' => [
                    'provider' => $aiConfig['provider'] ?? 'wenxin',
                    'model' => $aiConfig['model'] ?? '',
                    'status' => !empty($aiConfig['api_key'] ?? $aiConfig['wenxin']['api_key'] ?? '') ? 'configured' : 'not_configured',
                ],
                'notification' => [
                    'email_enabled' => false,
                    'sms_enabled' => false,
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'framework' => 'ThinkPHP 8.0',
                    'environment' => env('APP_DEBUG') ? 'development' : 'production',
                    'timezone' => date_default_timezone_get(),
                ],
            ];

            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('获取系统设置失败', ['error' => $e->getMessage()]);
            return $this->error('获取系统设置失败：' . $e->getMessage());
        }
    }

    /**
     * 更新系统设置
     * PUT /api/admin/settings
     */
    public function updateSettings(): Response
    {
        try {
            // 系统设置更新（当前仅返回成功，实际可写入配置文件或数据库）
            return $this->success(null, '设置已更新');
        } catch (\Exception $e) {
            Log::error('更新系统设置失败', ['error' => $e->getMessage()]);
            return $this->error('更新系统设置失败：' . $e->getMessage());
        }
    }

    /**
     * 操作日志列表
     * GET /api/admin/operation-logs
     */
    public function operationLogs(): Response
    {
        try {
            $page = $this->request->param('page/d', 1);
            $pageSize = $this->request->param('page_size/d', 20);

            $filters = [
                'username'   => $this->request->param('username', ''),
                'module'     => $this->request->param('module', ''),
                'action'     => $this->request->param('action', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date'   => $this->request->param('end_date', ''),
            ];

            $result = OperationLogService::query($filters, $page, $pageSize);

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取操作日志失败', ['error' => $e->getMessage()]);
            return $this->error('获取操作日志失败：' . $e->getMessage());
        }
    }

    /**
     * 导出操作日志
     * GET /api/admin/operation-logs/export
     */
    public function exportOperationLogs(): Response
    {
        try {
            $filters = [
                'username'   => $this->request->param('username', ''),
                'module'     => $this->request->param('module', ''),
                'start_date' => $this->request->param('start_date', ''),
                'end_date'   => $this->request->param('end_date', ''),
            ];

            $data = OperationLogService::exportCsv($filters);

            // 生成CSV内容
            $output = fopen('php://temp', 'r+');
            // BOM for Excel UTF-8
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['ID', '操作人', '模块', '操作', '描述', '请求方法', 'URL', 'IP', '时间']);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['username'],
                    $row['module'],
                    $row['action'],
                    $row['description'],
                    $row['method'],
                    $row['url'],
                    $row['ip'],
                    $row['create_time'],
                ]);
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            $filename = 'operation_logs_' . date('Ymd_His') . '.csv';

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            Log::error('导出操作日志失败', ['error' => $e->getMessage()]);
            return $this->error('导出操作日志失败：' . $e->getMessage());
        }
    }
}
