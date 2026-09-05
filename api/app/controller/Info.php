<?php
namespace app\controller;

use think\facade\Db;
use think\Response;

/**
 * 一次性诊断接口：列出 xmt_* 表在 information_schema 的存在情况
 * （仅用于 P0 缺表排查，完事立即删除文件并 sync 同步）
 * 路由：GET /api/admin/info/db-status（公开组，限流/无鉴权即可查询）
 */
class Info extends Base
{
    public function dbStatus(): Response
    {
        $tables = [
            'xmt_copywriting_pool',
            'xmt_funnel_event',
            'xmt_groupbuy_items',
            'xmt_lottery_activities',
            'xmt_lottery_prizes',
            'xmt_lottery_records',
            'xmt_review_actions',
            'xmt_review_draft_templates',
            'xmt_store_import_tasks',
            'xmt_topic_monitor_daily',
            // 顺带查核心表确认数据库可达
            'xmt_merchants',
            'xmt_nfc_devices',
            'xmt_store_configs',
        ];
        $existing = [];
        try {
            $rows = Db::query(
                "SELECT TABLE_NAME FROM information_schema.TABLES " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (" .
                implode(',', array_fill(0, count($tables), '?')) . ")",
                $tables
            );
            $existing = array_column($rows, 'TABLE_NAME');
        } catch (\Throwable $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
        $missing = array_values(array_diff($tables, $existing));
        return $this->success([
            'existing' => $existing,
            'missing'  => $missing,
            'summary'  => sprintf('existing=%d missing=%d', count($existing), count($missing)),
        ]);
    }
}