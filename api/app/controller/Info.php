<?php
namespace app\controller;

use think\facade\Db;
use think\Response;

/**
 * 一次性诊断 + 迁移接口（用完即删整个文件 + 路由）：
 * - dbStatus: 列出 xmt_* 表是否存在
 * - runMigrations: 从 api/database/migrations/ 拉取未执行的 SQL 并执行
 *
 * 路由：/api/info/db-status, /api/info/run-migrations（公开组 + 限流）
 */
class Info extends BaseController
{
    private array $expected = [
        // 已确认存在的核心表（探针，确认 DB 连通）
        'xmt_merchants',
        'xmt_nfc_devices',
        // 缺表清单（10 张 + xmt_store_configs）
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
        'xmt_store_configs',
    ];

    public function dbStatus(): Response
    {
        $rows = [];
        try {
            $rows = Db::query(
                "SELECT TABLE_NAME FROM information_schema.TABLES " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (" .
                implode(',', array_fill(0, count($this->expected), '?')) . ")",
                $this->expected
            );
        } catch (\Throwable $e) {
            return $this->error('查询失败: ' . $e->getMessage());
        }
        $existing = array_column($rows, 'TABLE_NAME');
        $missing  = array_values(array_diff($this->expected, $existing));
        return $this->success([
            'existing' => $existing,
            'missing'  => $missing,
            'summary'  => sprintf('existing=%d missing=%d', count($existing), count($missing)),
        ]);
    }

    /**
     * 执行缺表迁移（顺序幂等）
     * GET /api/info/run-migrations?confirm=YES
     * 安全：每条 SQL 都是 CREATE TABLE IF NOT EXISTS / ALTER，幂等；列出所有 missing 后逐表建
     */
    public function runMigrations(): Response
    {
        $confirm = $this->request->param('confirm', '');
        if ($confirm !== 'YES') {
            return $this->error('需 confirm=YES 才执行', 400, 'confirm_required');
        }

        // 1. 再查一次缺表（实时）
        $rows = Db::query(
            "SELECT TABLE_NAME FROM information_schema.TABLES " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (" .
            implode(',', array_fill(0, count($this->expected), '?')) . ")",
            $this->expected
        );
        $existing = array_column($rows, 'TABLE_NAME');
        $missing  = array_values(array_diff($this->expected, $existing));

        if (empty($missing)) {
            return $this->success(['message' => '所有目标表已存在，无需迁移', 'missing' => []]);
        }

        // 2. 加载 Flyway 迁移脚本中对应的 CREATE TABLE 语句
        $migrationFiles = [
            'xmt_copywriting_pool'          => '20260904000001_create_copywriting_pool_table.sql',
            'xmt_review_actions'            => '20260904000002_create_review_actions_table.sql',
            'xmt_groupbuy_items'            => '20260904000003_create_groupbuy_items_table.sql',
            'xmt_lottery_activities'        => '20260904000004_create_lottery_tables.sql',
            'xmt_lottery_prizes'            => '20260904000004_create_lottery_tables.sql',
            'xmt_lottery_records'           => '20260904000004_create_lottery_tables.sql',
            'xmt_review_draft_templates'    => '20260904000006_create_review_draft_templates.sql',
            'xmt_funnel_event'              => '20260904000008_create_funnel_event.sql',
            'xmt_topic_monitor_daily'       => '20260526000009_create_topic_monitors_table.sql',
            'xmt_store_import_tasks'        => '20260526000010_alter_stores_for_batch.sql',
        ];

        $baseDir = dirname(__DIR__, 2) . '/database/migrations';
        $log     = ['start' => date('Y-m-d H:i:s')];

        foreach ($missing as $tbl) {
            if (!isset($migrationFiles[$tbl])) {
                $log[$tbl] = ['status' => 'skip', 'reason' => 'no migration file mapped'];
                continue;
            }
            $file = $baseDir . '/' . $migrationFiles[$tbl];
            if (!is_file($file)) {
                $log[$tbl] = ['status' => 'skip', 'reason' => 'file not found'];
                continue;
            }
            $sql = file_get_contents($file);
            // 提取 CREATE TABLE 语句（按 ; 分段，逐条尝试）
            $stmts = array_filter(array_map('trim', explode(';', $sql)));
            $ok = true; $err = null;
            foreach ($stmts as $stmt) {
                if (stripos($stmt, 'CREATE TABLE') === false) continue;
                try {
                    Db::execute($stmt);
                } catch (\Throwable $e) {
                    // 表已存在则忽略（idempotent）
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        continue;
                    }
                    $ok = false; $err = $e->getMessage(); break;
                }
            }
            $log[$tbl] = $ok
                ? ['status' => 'ok', 'stmts' => count($stmts)]
                : ['status' => 'failed', 'error' => $err];
        }

        // 3. 重新查缺表
        $rows = Db::query(
            "SELECT TABLE_NAME FROM information_schema.TABLES " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (" .
            implode(',', array_fill(0, count($this->expected), '?')) . ")",
            $this->expected
        );
        $existing2 = array_column($rows, 'TABLE_NAME');
        $missing2  = array_values(array_diff($this->expected, $existing2));

        return $this->success([
            'before_missing' => $missing,
            'after_missing'  => $missing2,
            'log'            => $log,
            'finished_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}