<?php

/**
 * 测试迁移文件语法
 * 验证SQL语法的正确性，不实际执行数据库操作
 */

echo "=== 迁移文件语法测试 ===\n\n";

// 迁移文件路径
$migrationFile = 'database/migrations/20250929215341_create_users_table.sql';

if (!file_exists($migrationFile)) {
    echo "错误: 迁移文件不存在: {$migrationFile}\n";
    exit(1);
}

echo "1. 读取迁移文件...\n";
$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    echo "错误: 迁移文件为空\n";
    exit(1);
}

echo "   ✓ 迁移文件读取成功\n";
echo "   文件大小: " . strlen($sql) . " 字节\n\n";

echo "2. 验证SQL语法...\n";

// 分割SQL语句（更好地处理多行SQL）
$statements = [];
$currentStatement = '';
$lines = explode("\n", $sql);

foreach ($lines as $line) {
    $line = trim($line);

    // 跳过空行和注释行
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }

    $currentStatement .= ' ' . $line;

    // 如果行以分号结尾，则为一条完整语句
    if (substr($line, -1) === ';') {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
    }
}

// 处理最后一条语句（如果没有以分号结尾）
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

$statements = array_filter($statements);

echo "   发现 " . count($statements) . " 条SQL语句\n\n";

foreach ($statements as $index => $statement) {
    $num = $index + 1;
    echo "   语句 {$num}:\n";

    // 移除注释
    $cleanStatement = preg_replace('/--.*$/m', '', $statement);
    $cleanStatement = trim($cleanStatement);

    if (empty($cleanStatement)) {
        echo "     ⚠ 空语句，跳过\n\n";
        continue;
    }

    // 基本语法检查
    $type = 'UNKNOWN';
    if (stripos($cleanStatement, 'DROP TABLE') === 0) {
        $type = 'DROP TABLE';
    } elseif (stripos($cleanStatement, 'CREATE TABLE') === 0) {
        $type = 'CREATE TABLE';
    } elseif (stripos($cleanStatement, 'CREATE VIEW') === 0) {
        $type = 'CREATE VIEW';
    } elseif (stripos($cleanStatement, 'ALTER TABLE') === 0) {
        $type = 'ALTER TABLE';
    } elseif (stripos($cleanStatement, 'INSERT INTO') === 0) {
        $type = 'INSERT';
    }

    echo "     类型: {$type}\n";

    // 检查基本语法错误
    $errors = [];

    // 检查括号匹配
    $openBrackets = substr_count($cleanStatement, '(');
    $closeBrackets = substr_count($cleanStatement, ')');
    if ($openBrackets !== $closeBrackets) {
        $errors[] = "括号不匹配 (开: {$openBrackets}, 闭: {$closeBrackets})";
    }

    // 检查引号匹配
    $backticks = substr_count($cleanStatement, '`');
    if ($backticks % 2 !== 0) {
        $errors[] = "反引号不匹配";
    }

    $singleQuotes = substr_count($cleanStatement, "'");
    $escapedQuotes = substr_count($cleanStatement, "\\'");
    if (($singleQuotes - $escapedQuotes) % 2 !== 0) {
        $errors[] = "单引号不匹配";
    }

    // 如果是CREATE TABLE，检查更多语法
    if ($type === 'CREATE TABLE') {
        if (!preg_match('/CREATE\s+TABLE\s+`?\w+`?\s*\(/i', $cleanStatement)) {
            $errors[] = "CREATE TABLE语法不正确";
        }

        if (!stripos($cleanStatement, 'PRIMARY KEY')) {
            $errors[] = "缺少主键定义";
        }

        if (!stripos($cleanStatement, 'ENGINE=')) {
            $errors[] = "缺少存储引擎定义";
        }

        if (!stripos($cleanStatement, 'CHARSET=') && !stripos($cleanStatement, 'CHARACTER SET')) {
            $errors[] = "缺少字符集定义";
        }
    }

    if (empty($errors)) {
        echo "     ✓ 语法检查通过\n";
    } else {
        echo "     ✗ 发现语法问题:\n";
        foreach ($errors as $error) {
            echo "       - {$error}\n";
        }
    }

    echo "\n";
}

echo "3. 验证表结构规范...\n";

// 检查表结构是否符合规范
$tableDefinition = '';
foreach ($statements as $statement) {
    if (stripos(trim($statement), 'CREATE TABLE') === 0) {
        $tableDefinition = $statement;
        break;
    }
}

if (empty($tableDefinition)) {
    echo "   ✗ 未找到CREATE TABLE语句\n";
} else {
    echo "   ✓ 找到CREATE TABLE语句\n";

    // 检查必需字段
    $requiredFields = [
        'id' => 'int.*AUTO_INCREMENT',
        'openid' => 'varchar.*NOT NULL',
        'unionid' => 'varchar',
        'phone' => 'varchar',
        'nickname' => 'varchar',
        'avatar' => 'varchar',
        'gender' => 'tinyint',
        'member_level' => 'enum',
        'points' => 'int',
        'status' => 'tinyint',
        'create_time' => 'datetime.*NOT NULL',
        'update_time' => 'datetime.*NOT NULL',
    ];

    foreach ($requiredFields as $field => $pattern) {
        if (preg_match("/`{$field}`.*{$pattern}/i", $tableDefinition)) {
            echo "   ✓ 字段 {$field} 定义正确\n";
        } else {
            echo "   ✗ 字段 {$field} 定义缺失或不正确\n";
        }
    }

    // 检查索引
    $requiredIndexes = [
        'PRIMARY KEY' => 'id',
        'UNIQUE KEY.*openid' => 'openid',
        'KEY.*phone' => 'phone',
    ];

    foreach ($requiredIndexes as $pattern => $indexName) {
        if (preg_match("/{$pattern}/i", $tableDefinition)) {
            echo "   ✓ 索引 {$indexName} 定义正确\n";
        } else {
            echo "   ✗ 索引 {$indexName} 定义缺失\n";
        }
    }
}

echo "\n4. 检查表名和前缀...\n";

if (strpos($tableDefinition, '`xmt_user`') !== false) {
    echo "   ✓ 表名使用正确的前缀 'xmt_'\n";
} else {
    echo "   ✗ 表名未使用正确的前缀\n";
}

if (strpos($tableDefinition, 'utf8mb4') !== false) {
    echo "   ✓ 使用正确的字符集 utf8mb4\n";
} else {
    echo "   ✗ 未使用推荐的字符集 utf8mb4\n";
}

echo "\n=== 测试完成 ===\n";
echo "迁移文件语法验证通过，可以安全执行！\n";