<?php

/**
 * 测试内容任务表迁移文件语法
 * 验证SQL语法的正确性，不实际执行数据库操作
 */

echo "=== 内容任务表迁移文件语法测试 ===\n\n";

// 迁移文件路径
$migrationFile = 'database/migrations/20250929222838_create_content_tasks_table.sql';

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

echo "3. 验证内容任务表结构规范...\n";

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

    // 检查内容任务表必需字段
    $requiredFields = [
        'id' => 'int.*AUTO_INCREMENT',
        'user_id' => 'int.*NOT NULL',
        'merchant_id' => 'int.*NOT NULL',
        'device_id' => 'int',
        'template_id' => 'int',
        'type' => 'enum.*VIDEO.*TEXT.*IMAGE',
        'status' => 'enum.*PENDING.*PROCESSING.*COMPLETED.*FAILED',
        'input_data' => 'json',
        'output_data' => 'json',
        'ai_provider' => 'varchar',
        'generation_time' => 'int',
        'error_message' => 'text',
        'create_time' => 'datetime.*NOT NULL',
        'update_time' => 'datetime.*NOT NULL',
        'complete_time' => 'datetime',
    ];

    echo "\n   检查字段定义:\n";
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
        'KEY.*user_id' => 'user_id',
        'KEY.*merchant_id' => 'merchant_id',
        'KEY.*device_id' => 'device_id',
        'KEY.*template_id' => 'template_id',
        'KEY.*status' => 'status',
        'KEY.*type' => 'type',
        'KEY.*ai_provider' => 'ai_provider',
        'KEY.*create_time' => 'create_time',
        'KEY.*complete_time' => 'complete_time',
    ];

    echo "\n   检查索引定义:\n";
    foreach ($requiredIndexes as $pattern => $indexName) {
        if (preg_match("/{$pattern}/i", $tableDefinition)) {
            echo "   ✓ 索引 {$indexName} 定义正确\n";
        } else {
            echo "   ✗ 索引 {$indexName} 定义缺失\n";
        }
    }
}

echo "\n4. 检查表名和前缀...\n";

if (strpos($tableDefinition, '`xmt_content_tasks`') !== false) {
    echo "   ✓ 表名使用正确的前缀 'xmt_'\n";
} else {
    echo "   ✗ 表名未使用正确的前缀\n";
}

if (strpos($tableDefinition, 'utf8mb4') !== false) {
    echo "   ✓ 使用正确的字符集 utf8mb4\n";
} else {
    echo "   ✗ 未使用推荐的字符集 utf8mb4\n";
}

echo "\n5. 检查ENUM值定义...\n";

// 检查内容类型ENUM
if (preg_match("/type.*enum\s*\(\s*'VIDEO'\s*,\s*'TEXT'\s*,\s*'IMAGE'\s*\)/i", $tableDefinition)) {
    echo "   ✓ 内容类型 ENUM 值定义正确\n";
} else {
    echo "   ✗ 内容类型 ENUM 值定义不正确\n";
}

// 检查任务状态ENUM
if (preg_match("/status.*enum\s*\(\s*'PENDING'\s*,\s*'PROCESSING'\s*,\s*'COMPLETED'\s*,\s*'FAILED'\s*\)/i", $tableDefinition)) {
    echo "   ✓ 任务状态 ENUM 值定义正确\n";
} else {
    echo "   ✗ 任务状态 ENUM 值定义不正确\n";
}

echo "\n6. 检查JSON字段定义...\n";

// 检查JSON字段
$jsonFields = [
    'input_data' => '输入数据',
    'output_data' => '输出数据',
];

foreach ($jsonFields as $field => $desc) {
    if (preg_match("/`{$field}`\s+json/i", $tableDefinition)) {
        echo "   ✓ JSON字段 {$field} ({$desc}) 定义正确\n";
    } else {
        echo "   ✗ JSON字段 {$field} ({$desc}) 定义缺失或不正确\n";
    }
}

echo "\n7. 检查注释完整性...\n";

$commentFields = [
    'id.*任务ID',
    'user_id.*用户ID',
    'merchant_id.*商家ID',
    'device_id.*设备ID',
    'template_id.*模板ID',
    'type.*内容类型',
    'status.*任务状态',
    'input_data.*输入数据',
    'output_data.*输出数据',
    'ai_provider.*AI服务商',
    'generation_time.*生成耗时',
    'error_message.*错误信息',
    'create_time.*创建时间',
    'update_time.*更新时间',
    'complete_time.*完成时间',
];

foreach ($commentFields as $commentPattern) {
    if (preg_match("/{$commentPattern}/i", $tableDefinition)) {
        echo "   ✓ 注释检查通过: {$commentPattern}\n";
    } else {
        echo "   ✗ 注释缺失: {$commentPattern}\n";
    }
}

echo "\n8. AI内容生成相关特性检查...\n";

// 检查AI相关字段
$aiFeatures = [
    'ai_provider.*varchar' => 'AI服务商字段',
    'generation_time.*int' => '生成耗时字段',
    'error_message.*text' => '错误消息字段',
    'complete_time.*datetime' => '完成时间字段',
    'input_data.*json' => 'AI输入参数JSON字段',
    'output_data.*json' => 'AI输出结果JSON字段',
];

foreach ($aiFeatures as $pattern => $desc) {
    if (preg_match("/{$pattern}/i", $tableDefinition)) {
        echo "   ✓ {$desc} 定义正确\n";
    } else {
        echo "   ✗ {$desc} 定义缺失\n";
    }
}

echo "\n9. 性能优化检查...\n";

// 检查关键索引是否存在
$performanceIndexes = [
    'status' => '任务状态索引（用于查询待处理任务）',
    'ai_provider' => 'AI服务商索引（用于统计分析）',
    'create_time' => '创建时间索引（用于时间范围查询）',
    'complete_time' => '完成时间索引（用于性能分析）',
    'user_id' => '用户索引（用于查询用户任务）',
    'merchant_id' => '商家索引（用于商家数据隔离）',
];

foreach ($performanceIndexes as $indexField => $desc) {
    if (preg_match("/KEY.*{$indexField}/i", $tableDefinition)) {
        echo "   ✓ {$desc} 存在\n";
    } else {
        echo "   ✗ {$desc} 缺失\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "内容任务表迁移文件语法验证完成！\n";