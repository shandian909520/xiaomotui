<?php
/**
 * 素材库表迁移文件语法验证脚本
 * 仅验证SQL语法，不执行数据库操作
 */

echo "素材库表迁移文件语法验证\n";
echo str_repeat('=', 60) . "\n\n";

// 读取迁移文件
$migrationFile = __DIR__ . '/migrations/20251001000001_create_content_materials_tables.sql';

if (!file_exists($migrationFile)) {
    die("错误: 迁移文件不存在: {$migrationFile}\n");
}

$sql = file_get_contents($migrationFile);
echo "✓ 读取迁移文件成功\n\n";

// 分割SQL语句
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) &&
               strpos($stmt, '--') !== 0 &&
               strpos($stmt, '/*') !== 0;
    }
);

echo "SQL语句数量: " . count($statements) . "\n\n";

// 验证各个表的创建语句
$expectedTables = [
    'xmt_content_material_categories' => '素材分类表',
    'xmt_content_material_tags' => '素材标签表',
    'xmt_content_materials' => '内容素材表',
    'xmt_content_material_tag_relations' => '素材标签关联表',
    'xmt_content_material_usage' => '素材使用记录表',
    'xmt_content_material_reviews' => '素材审核记录表'
];

echo "检查表定义...\n";
echo str_repeat('-', 60) . "\n";

$foundTables = [];
$dropStatements = [];
$createStatements = [];

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    // 检查DROP TABLE语句
    if (preg_match('/DROP TABLE IF EXISTS `(\w+)`/', $statement, $matches)) {
        $tableName = $matches[1];
        $dropStatements[] = $tableName;
        echo "✓ 找到DROP语句: {$tableName}\n";
    }

    // 检查CREATE TABLE语句
    if (preg_match('/CREATE TABLE `(\w+)`/i', $statement, $matches)) {
        $tableName = $matches[1];
        $foundTables[] = $tableName;
        $createStatements[$tableName] = $statement;

        $description = $expectedTables[$tableName] ?? '未知表';
        echo "✓ 找到CREATE语句: {$tableName} ({$description})\n";

        // 验证表结构
        validateTableStructure($tableName, $statement);
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "验证结果汇总\n";
echo str_repeat('-', 60) . "\n\n";

// 检查是否所有表都已定义
$missingTables = array_diff(array_keys($expectedTables), $foundTables);
if (empty($missingTables)) {
    echo "✓ 所有6个表都已定义\n";
} else {
    echo "✗ 缺少表: " . implode(', ', $missingTables) . "\n";
}

// 检查DROP和CREATE配对
if (count($dropStatements) === count($createStatements)) {
    echo "✓ DROP和CREATE语句数量匹配: " . count($dropStatements) . " 个\n";
} else {
    echo "✗ DROP和CREATE语句数量不匹配\n";
}

// 检查表命名规范
echo "\n检查命名规范:\n";
$allTablesHavePrefix = true;
foreach ($foundTables as $table) {
    if (strpos($table, 'xmt_') !== 0) {
        echo "✗ 表 {$table} 缺少 xmt_ 前缀\n";
        $allTablesHavePrefix = false;
    }
}
if ($allTablesHavePrefix) {
    echo "✓ 所有表都使用 xmt_ 前缀\n";
}

// 检查字符集和排序规则
echo "\n检查字符集配置:\n";
foreach ($createStatements as $tableName => $statement) {
    if (strpos($statement, 'utf8mb4') !== false) {
        echo "✓ {$tableName}: 使用 utf8mb4\n";
    } else {
        echo "✗ {$tableName}: 未使用 utf8mb4\n";
    }

    if (strpos($statement, 'utf8mb4_unicode_ci') !== false) {
        echo "  ✓ 排序规则: utf8mb4_unicode_ci\n";
    }

    if (strpos($statement, 'InnoDB') !== false) {
        echo "  ✓ 存储引擎: InnoDB\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "✓✓✓ 语法验证完成！\n";
echo str_repeat('=', 60) . "\n\n";

echo "迁移文件信息:\n";
echo "- 文件路径: {$migrationFile}\n";
echo "- 文件大小: " . filesize($migrationFile) . " 字节\n";
echo "- 表数量: " . count($foundTables) . "\n";
echo "- SQL语句数: " . count($statements) . "\n\n";

echo "提示: 要执行迁移，请确保数据库连接正常后运行:\n";
echo "php database/migrate.php\n";

/**
 * 验证表结构
 */
function validateTableStructure($tableName, $statement) {
    $issues = [];

    // 检查主键
    if (strpos($statement, 'PRIMARY KEY') === false) {
        $issues[] = "缺少主键定义";
    }

    // 检查注释
    if (strpos($statement, 'COMMENT') === false) {
        $issues[] = "缺少COMMENT";
    }

    // 检查时间字段
    if (strpos($statement, 'create_time') === false && $tableName !== 'xmt_migration_log') {
        $issues[] = "缺少create_time字段";
    }

    // 检查索引
    $indexCount = substr_count($statement, 'KEY `idx_');
    if ($indexCount > 0) {
        echo "  索引数量: {$indexCount}\n";
    }

    // 检查ENUM字段
    preg_match_all('/enum\([^)]+\)/i', $statement, $enumMatches);
    if (!empty($enumMatches[0])) {
        echo "  ENUM字段数量: " . count($enumMatches[0]) . "\n";
    }

    // 检查JSON字段
    $jsonCount = substr_count(strtolower($statement), ' json ');
    if ($jsonCount > 0) {
        echo "  JSON字段数量: {$jsonCount}\n";
    }

    if (!empty($issues)) {
        echo "  ⚠ 警告: " . implode(', ', $issues) . "\n";
    }

    echo "\n";
}