<?php
declare(strict_types=1);

namespace app\service;

use app\model\OperationLog;
use think\facade\Log;

/**
 * 操作日志服务
 */
class OperationLogService
{
    /**
     * 模块映射
     */
    protected static array $moduleMap = [
        'auth'       => '认证管理',
        'merchant'   => '商户管理',
        'device'     => '设备管理',
        'nfc'        => 'NFC管理',
        'content'    => '内容管理',
        'template'   => '模板管理',
        'coupon'     => '券码管理',
        'publish'    => '发布管理',
        'statistics' => '数据统计',
        'ai-content' => 'AI内容',
        'alert'      => '告警管理',
        'upload'     => '文件上传',
        'admin'      => '系统管理',
    ];

    /**
     * 操作映射
     */
    protected static array $actionMap = [
        'POST'   => '新增',
        'PUT'    => '修改',
        'DELETE' => '删除',
    ];

    /**
     * 记录操作日志
     */
    public static function record(
        int $userId,
        string $username,
        string $module,
        string $action,
        string $description,
        string $method = '',
        string $url = '',
        string $params = '',
        string $ip = '',
        string $userAgent = ''
    ): void {
        try {
            OperationLog::create([
                'user_id'     => $userId,
                'username'    => $username,
                'module'      => $module,
                'action'      => $action,
                'description' => mb_substr($description, 0, 500),
                'method'      => $method,
                'url'         => mb_substr($url, 0, 500),
                'params'      => mb_substr($params, 0, 65000),
                'ip'          => $ip,
                'user_agent'  => mb_substr($userAgent, 0, 500),
            ]);
        } catch (\Exception $e) {
            Log::error('记录操作日志失败', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
                'module'  => $module,
                'action'  => $action,
            ]);
        }
    }

    /**
     * 从请求中自动解析并记录日志
     */
    public static function recordFromRequest(
        int $userId,
        string $username,
        string $method,
        string $url,
        string $params,
        string $ip,
        string $userAgent
    ): void {
        $module = self::parseModule($url);
        $action = self::$actionMap[$method] ?? $method;
        $description = self::buildDescription($method, $url, $module);

        self::record(
            $userId, $username, $module, $action,
            $description, $method, $url, $params, $ip, $userAgent
        );
    }

    /**
     * 从URL解析模块名
     */
    protected static function parseModule(string $url): string
    {
        // 移除查询参数
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = trim($path, '/');

        // 移除 api/ 前缀
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        // 取第一段作为模块
        $segments = explode('/', $path);
        $first = $segments[0] ?? '';

        // 处理 merchant/device 等嵌套模块
        if ($first === 'merchant' && isset($segments[1])) {
            $sub = $segments[1];
            if (in_array($sub, ['device', 'nfc', 'coupon', 'template'])) {
                return $sub;
            }
        }

        // 统一返回英文 key，翻译由 buildDescription 负责
        return $first;
    }

    /**
     * 构建操作描述
     */
    protected static function buildDescription(string $method, string $url, string $module): string
    {
        $action = self::$actionMap[$method] ?? $method;
        $moduleName = self::$moduleMap[$module] ?? $module;
        return "{$action}{$moduleName}数据";
    }

    /**
     * 查询操作日志
     */
    public static function query(array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = OperationLog::order('create_time', 'desc');

        if (!empty($filters['username'])) {
            $query->whereLike('username', "%{$filters['username']}%");
        }
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        return [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'page_size' => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize),
        ];
    }

    /**
     * 导出操作日志为CSV数据
     */
    public static function exportCsv(array $filters): array
    {
        $query = OperationLog::order('create_time', 'desc');

        if (!empty($filters['username'])) {
            $query->whereLike('username', "%{$filters['username']}%");
        }
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('create_time', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('create_time', '<=', $filters['end_date'] . ' 23:59:59');
        }

        return $query->limit(10000)
            ->field('id, username, module, action, description, method, url, ip, create_time')
            ->select()
            ->toArray();
    }
}
