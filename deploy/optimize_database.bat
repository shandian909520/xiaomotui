@echo off
REM 数据库优化脚本 (Windows)
setlocal EnableDelayedExpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "DB_DIR=%PROJECT_ROOT%\api\database"

chcp 65001 > nul

echo ╔═══════════════════════════════════════════════════════════╗
echo ║           小魔推 - 数据库优化脚本 v1.0                     ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

echo [INFO] 开始数据库优化...
echo.

cd /d "%DB_DIR%"

php -r "require_once 'test_connection.php'; $conn = testDatabaseConnection(); if (!$conn) { echo 'Database connection failed\n'; exit(1); } $pdo = $conn['pdo']; $prefix = $conn['config']['prefix']; $stmt = $pdo->query(\"SHOW TABLES LIKE '{$prefix}%%'\"); $tables = $stmt->fetchAll(PDO::FETCH_COLUMN); echo \"找到 \" . count($tables) . \" 个表\n\n\"; foreach ($tables as $table) { echo \"优化表: $table\n\"; $pdo->exec(\"ANALYZE TABLE $table\"); echo \"  - 分析完成\n\"; $pdo->exec(\"OPTIMIZE TABLE $table\"); echo \"  - 优化完成\n\"; $pdo->exec(\"CHECK TABLE $table\"); echo \"  - 检查完成\n\n\"; } echo \"所有表优化完成！\n\";"

echo.
echo [SUCCESS] 数据库优化完成
pause
