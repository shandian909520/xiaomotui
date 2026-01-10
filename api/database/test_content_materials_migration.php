<?php
/**
 * 素材库表迁移文件测试脚本
 * 用于验证SQL语法和表结构
 */

// 数据库连接配置
$dbConfig = [
    'hostname' => '127.0.0.1',
    'hostport' => '3306',
    'database' => 'xiaomotui_dev',
    'username' => 'root',
    'password' => '',
];

try {
    // 创建PDO连接
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['hostname'],
        $dbConfig['hostport'],
        $dbConfig['database']
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ 数据库连接成功\n\n";

    // 读取迁移文件
    $migrationFile = __DIR__ . '/migrations/20251001000001_create_content_materials_tables.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("迁移文件不存在: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);
    echo "✓ 读取迁移文件成功\n\n";

    // 分割SQL语句
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // 过滤空语句和注释
            return !empty($stmt) &&
                   strpos($stmt, '--') !== 0 &&
                   strpos($stmt, '/*') !== 0;
        }
    );

    echo "开始执行迁移...\n";
    echo str_repeat('=', 60) . "\n\n";

    $pdo->beginTransaction();

    $tableCount = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        try {
            // 检测是否为CREATE TABLE语句
            if (preg_match('/CREATE TABLE `(\w+)`/', $statement, $matches)) {
                $tableName = $matches[1];
                echo "创建表: {$tableName}\n";
                $pdo->exec($statement);
                echo "✓ 表 {$tableName} 创建成功\n\n";
                $tableCount++;
            } else if (preg_match('/DROP TABLE IF EXISTS `(\w+)`/', $statement, $matches)) {
                $tableName = $matches[1];
                echo "删除旧表(如果存在): {$tableName}\n";
                $pdo->exec($statement);
                echo "✓ 完成\n\n";
            } else {
                $pdo->exec($statement);
            }
        } catch (PDOException $e) {
            echo "✗ 错误: " . $e->getMessage() . "\n";
            echo "SQL: " . substr($statement, 0, 100) . "...\n\n";
            throw $e;
        }
    }

    // 验证表结构
    echo str_repeat('=', 60) . "\n";
    echo "验证表结构...\n\n";

    $tables = [
        'xmt_content_material_categories' => '素材分类表',
        'xmt_content_material_tags' => '素材标签表',
        'xmt_content_materials' => '内容素材表',
        'xmt_content_material_tag_relations' => '素材标签关联表',
        'xmt_content_material_usage' => '素材使用记录表',
        'xmt_content_material_reviews' => '素材审核记录表'
    ];

    foreach ($tables as $tableName => $description) {
        // 检查表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        $exists = $stmt->fetch();

        if ($exists) {
            echo "✓ {$description} ({$tableName}) 存在\n";

            // 获取表结构信息
            $stmt = $pdo->query("DESCRIBE {$tableName}");
            $columns = $stmt->fetchAll();
            echo "  列数: " . count($columns) . "\n";

            // 获取索引信息
            $stmt = $pdo->query("SHOW INDEX FROM {$tableName}");
            $indexes = $stmt->fetchAll();
            $indexNames = array_unique(array_column($indexes, 'Key_name'));
            echo "  索引数: " . count($indexNames) . "\n";

            // 获取表注释
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE '{$tableName}'");
            $tableStatus = $stmt->fetch();
            echo "  注释: " . $tableStatus['Comment'] . "\n";

        } else {
            echo "✗ {$description} ({$tableName}) 不存在\n";
        }
        echo "\n";
    }

    // 测试插入示例数据
    echo str_repeat('=', 60) . "\n";
    echo "测试插入示例数据...\n\n";

    // 1. 插入分类
    $pdo->exec("
        INSERT INTO xmt_content_material_categories
        (name, type, description, sort_order, status, create_time, update_time)
        VALUES
        ('美食视频', 'VIDEO', '美食相关的视频素材', 1, 1, NOW(), NOW()),
        ('背景音乐', 'AUDIO', '各类背景音乐', 1, 1, NOW(), NOW()),
        ('转场特效', 'TRANSITION', '视频转场效果', 1, 1, NOW(), NOW())
    ");
    echo "✓ 插入3条分类数据\n";

    // 2. 插入标签
    $pdo->exec("
        INSERT INTO xmt_content_material_tags
        (name, type, usage_count, create_time)
        VALUES
        ('美食', 'VIDEO', 0, NOW()),
        ('餐厅', 'ALL', 0, NOW()),
        ('欢快', 'AUDIO', 0, NOW())
    ");
    echo "✓ 插入3条标签数据\n";

    // 3. 插入素材
    $pdo->exec("
        INSERT INTO xmt_content_materials
        (name, type, category_id, file_url, duration, quality_score, status, create_time, update_time)
        VALUES
        ('美食宣传片1', 'VIDEO', 1, 'https://example.com/video1.mp4', 30, 8.50, 1, NOW(), NOW()),
        ('轻快背景音乐', 'AUDIO', 2, 'https://example.com/audio1.mp3', 120, 9.00, 1, NOW(), NOW())
    ");
    echo "✓ 插入2条素材数据\n";

    // 4. 插入标签关联
    $pdo->exec("
        INSERT INTO xmt_content_material_tag_relations
        (material_id, tag_id, create_time)
        VALUES
        (1, 1, NOW()),
        (1, 2, NOW()),
        (2, 3, NOW())
    ");
    echo "✓ 插入3条标签关联数据\n";

    // 5. 插入使用记录
    $pdo->exec("
        INSERT INTO xmt_content_material_usage
        (material_id, performance_score, user_feedback, create_time)
        VALUES
        (1, 8.50, 1, NOW()),
        (2, 9.00, 1, NOW())
    ");
    echo "✓ 插入2条使用记录数据\n";

    // 6. 插入审核记录
    $pdo->exec("
        INSERT INTO xmt_content_material_reviews
        (material_id, reviewer_id, review_type, result, score, review_time)
        VALUES
        (1, 1, 'AUTO', 'APPROVED', 8.50, NOW()),
        (2, 1, 'MANUAL', 'APPROVED', 9.00, NOW())
    ");
    echo "✓ 插入2条审核记录数据\n\n";

    // 验证数据
    echo str_repeat('=', 60) . "\n";
    echo "验证插入的数据...\n\n";

    foreach ($tables as $tableName => $description) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$tableName}");
        $result = $stmt->fetch();
        echo "✓ {$description}: {$result['count']} 条记录\n";
    }

    // 回滚事务（测试模式）
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "回滚测试数据...\n";
    $pdo->rollBack();
    echo "✓ 测试数据已回滚\n\n";

    echo str_repeat('=', 60) . "\n";
    echo "✓✓✓ 所有测试通过！\n";
    echo str_repeat('=', 60) . "\n\n";

    echo "迁移文件验证成功！\n";
    echo "创建的表: {$tableCount} 个\n";
    echo "测试数据: 已插入并验证\n";
    echo "SQL语法: 正确\n\n";

    echo "提示: 数据已回滚，要正式执行迁移，请运行:\n";
    echo "php database/migrate.php\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗✗✗ 测试失败！\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . "\n";
    echo "行号: " . $e->getLine() . "\n";
    exit(1);
}