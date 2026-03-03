<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\IpBlacklistService;
use think\facade\Request;
use think\facade\Validate;

/**
 * IP黑名单管理控制器
 */
class IpBlacklist
{
    /**
     * @var IpBlacklistService
     */
    protected IpBlacklistService $blacklistService;

    public function __construct()
    {
        $this->blacklistService = new IpBlacklistService();
    }

    /**
     * 获取黑名单列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $page = Request::get('page', 1, 'intval');
        $pageSize = Request::get('page_size', 20, 'intval');
        $status = Request::get('status', '', 'trim');

        $status = $status ?: null;

        $result = $this->blacklistService->getList($page, $pageSize, $status);

        return json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $result
        ]);
    }

    /**
     * 添加IP到黑名单
     *
     * @return \think\Response
     */
    public function add()
    {
        $data = Request::post();

        $validate = Validate::rule([
            'ip' => 'require|ip',
            'reason' => 'max:255',
            'duration' => 'integer|egt:1',
        ]);

        if (!$validate->check($data)) {
            return json([
                'code' => 400,
                'message' => $validate->getError(),
                'data' => null
            ]);
        }

        $ip = $data['ip'];
        $reason = $data['reason'] ?? '手动封禁';
        $duration = $data['duration'] ?? 1440; // 默认24小时
        $permanent = $data['permanent'] ?? false;

        $result = $this->blacklistService->block($ip, $reason, $duration, $permanent);

        if ($result) {
            return json([
                'code' => 200,
                'message' => '添加成功',
                'data' => [
                    'ip' => $ip,
                    'reason' => $reason,
                    'duration' => $permanent ? '永久' : $duration . '分钟',
                ]
            ]);
        }

        return json([
            'code' => 500,
            'message' => '添加失败',
            'data' => null
        ]);
    }

    /**
     * 批量添加IP到黑名单
     *
     * @return \think\Response
     */
    public function batchAdd()
    {
        $data = Request::post();

        $validate = Validate::rule([
            'ips' => 'require|array',
            'reason' => 'max:255',
            'duration' => 'integer|egt:1',
        ]);

        if (!$validate->check($data)) {
            return json([
                'code' => 400,
                'message' => $validate->getError(),
                'data' => null
            ]);
        }

        $ips = $data['ips'];
        $reason = $data['reason'] ?? '批量封禁';
        $duration = $data['duration'] ?? 1440;

        $result = $this->blacklistService->batchBlock($ips, $reason, $duration);

        return json([
            'code' => 200,
            'message' => '批量操作完成',
            'data' => $result
        ]);
    }

    /**
     * 从黑名单移除IP
     *
     * @return \think\Response
     */
    public function remove()
    {
        $ip = Request::post('ip', '', 'trim');

        if (empty($ip)) {
            return json([
                'code' => 400,
                'message' => 'IP地址不能为空',
                'data' => null
            ]);
        }

        $result = $this->blacklistService->unblock($ip);

        if ($result) {
            return json([
                'code' => 200,
                'message' => '移除成功',
                'data' => ['ip' => $ip]
            ]);
        }

        return json([
            'code' => 500,
            'message' => '移除失败',
            'data' => null
        ]);
    }

    /**
     * 批量移除IP
     *
     * @return \think\Response
     */
    public function batchRemove()
    {
        $ips = Request::post('ips', [], 'array');

        if (empty($ips)) {
            return json([
                'code' => 400,
                'message' => 'IP列表不能为空',
                'data' => null
            ]);
        }

        $result = $this->blacklistService->batchUnblock($ips);

        return json([
            'code' => 200,
            'message' => '批量移除完成',
            'data' => $result
        ]);
    }

    /**
     * 清空黑名单
     *
     * @return \think\Response
     */
    public function clear()
    {
        $clearAll = Request::post('clear_all', false, 'bool');
        $count = $this->blacklistService->clear($clearAll);

        return json([
            'code' => 200,
            'message' => '清空成功',
            'data' => ['cleared_count' => $count]
        ]);
    }

    /**
     * 获取IP统计信息
     *
     * @return \think\Response
     */
    public function stats()
    {
        $ip = Request::get('ip', '', 'trim');

        if (empty($ip)) {
            return json([
                'code' => 400,
                'message' => 'IP地址不能为空',
                'data' => null
            ]);
        }

        $stats = $this->blacklistService->getIpStats($ip);

        return json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $stats
        ]);
    }

    /**
     * 导出黑名单
     *
     * @return \think\Response
     */
    public function export()
    {
        $status = Request::get('status', '', 'trim');
        $status = $status ?: null;

        $list = $this->blacklistService->export($status);

        return json([
            'code' => 200,
            'message' => '导出成功',
            'data' => [
                'total' => count($list),
                'list' => $list,
            ]
        ]);
    }

    /**
     * 检查IP状态
     *
     * @return \think\Response
     */
    public function check()
    {
        $ip = Request::get('ip', Request::ip(), 'trim');

        if (empty($ip)) {
            return json([
                'code' => 400,
                'message' => 'IP地址不能为空',
                'data' => null
            ]);
        }

        $blocked = $this->blacklistService->isBlocked($ip);
        $stats = $this->blacklistService->getIpStats($ip);

        return json([
            'code' => 200,
            'message' => '检查成功',
            'data' => [
                'ip' => $ip,
                'is_blocked' => $blocked !== null,
                'block_info' => $blocked,
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * 获取黑名单统计概览
     *
     * @return \think\Response
     */
    public function overview()
    {
        // 获取活跃黑名单数量
        $activeList = $this->blacklistService->getList(1, 1, 'active');
        $inactiveList = $this->blacklistService->getList(1, 1, 'inactive');
        $allList = $this->blacklistService->getList(1, 1);

        return json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $allList['total'],
                'active' => $activeList['total'],
                'inactive' => $inactiveList['total'],
            ]
        ]);
    }
}
