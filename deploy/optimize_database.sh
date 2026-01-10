#!/bin/bash

###############################################################################
# 数据库优化脚本 (Linux/Mac)
# 用于优化数据库性能
###############################################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
API_DIR="$PROJECT_ROOT/api"
DB_DIR="$API_DIR/database"

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"; }

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║           小魔推 - 数据库优化脚本 v1.0                     ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

log_info "开始数据库优化..."

cd "$DB_DIR"

php -r "
require_once 'test_connection.php';
\$conn = testDatabaseConnection();
if (!\$conn) {
    echo 'Database connection failed\n';
    exit(1);
}

\$pdo = \$conn['pdo'];
\$prefix = \$conn['config']['prefix'];

// 获取所有表
\$stmt = \$pdo->query(\"SHOW TABLES LIKE '{\$prefix}%'\");
\$tables = \$stmt->fetchAll(PDO::FETCH_COLUMN);

echo \"找到 \" . count(\$tables) . \" 个表\n\n\";

foreach (\$tables as \$table) {
    echo \"优化表: \$table\n\";

    // 分析表
    \$pdo->exec(\"ANALYZE TABLE \$table\");
    echo \"  - 分析完成\n\";

    // 优化表
    \$pdo->exec(\"OPTIMIZE TABLE \$table\");
    echo \"  - 优化完成\n\";

    // 检查表
    \$pdo->exec(\"CHECK TABLE \$table\");
    echo \"  - 检查完成\n\n\";
}

echo \"所有表优化完成！\n\";
"

log_success "数据库优化完成"
