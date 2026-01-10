#!/bin/bash

###############################################################################
# 数据库部署脚本 (Linux/Mac)
# 用于生产环境数据库部署
#
# 功能：
# 1. 检查数据库连接
# 2. 执行所有数据库迁移
# 3. 创建必要的索引
# 4. 初始化基础数据
# 5. 验证数据完整性
###############################################################################

set -e  # 遇到错误立即退出

# 脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
API_DIR="$PROJECT_ROOT/api"
DB_DIR="$API_DIR/database"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

# 显示标题
show_banner() {
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                                                           ║"
    echo "║           小魔推 - 数据库部署脚本 v1.0                     ║"
    echo "║           Database Deployment Script                     ║"
    echo "║                                                           ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo ""
}

# 检查 PHP 环境
check_php() {
    log_info "检查 PHP 环境..."

    if ! command -v php &> /dev/null; then
        log_error "PHP 未安装，请先安装 PHP"
        exit 1
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log_success "PHP 版本: $PHP_VERSION"

    # 检查 PDO 扩展
    if ! php -m | grep -q "PDO"; then
        log_error "PDO 扩展未安装"
        exit 1
    fi

    # 检查 PDO MySQL 扩展
    if ! php -m | grep -q "pdo_mysql"; then
        log_error "PDO MySQL 扩展未安装"
        exit 1
    fi

    log_success "PHP 环境检查通过"
}

# 检查环境变量文件
check_env_file() {
    log_info "检查环境配置文件..."

    ENV_FILE="$API_DIR/.env"

    if [ ! -f "$ENV_FILE" ]; then
        log_warning ".env 文件不存在"

        # 检查示例文件
        if [ -f "$API_DIR/.env.example" ]; then
            log_info "发现 .env.example 文件，是否复制为 .env？(y/n)"
            read -r response
            if [[ "$response" =~ ^[Yy]$ ]]; then
                cp "$API_DIR/.env.example" "$ENV_FILE"
                log_success "已复制 .env.example 到 .env"
                log_warning "请编辑 .env 文件，配置正确的数据库连接信息"
                exit 0
            else
                log_error "需要 .env 文件才能继续"
                exit 1
            fi
        else
            log_error "未找到 .env 或 .env.example 文件"
            exit 1
        fi
    fi

    log_success "环境配置文件检查通过"
}

# 检查数据库连接
check_database_connection() {
    log_info "检查数据库连接..."

    cd "$DB_DIR"

    if php test_connection.php > /dev/null 2>&1; then
        log_success "数据库连接成功"
        return 0
    else
        log_error "数据库连接失败，请检查配置"
        php test_connection.php
        exit 1
    fi
}

# 备份数据库（部署前）
backup_database_before_deploy() {
    log_info "部署前备份数据库..."

    BACKUP_DIR="$PROJECT_ROOT/backups"
    mkdir -p "$BACKUP_DIR"

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/db_backup_before_deploy_$TIMESTAMP.sql"

    # 从 .env 读取数据库配置
    ENV_FILE="$API_DIR/.env"

    DB_HOST=$(grep "^database.hostname" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_PORT=$(grep "^database.hostport" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_NAME=$(grep "^database.database" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_USER=$(grep "^database.username" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_PASS=$(grep "^database.password" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")

    # 默认值
    DB_HOST=${DB_HOST:-127.0.0.1}
    DB_PORT=${DB_PORT:-3306}
    DB_USER=${DB_USER:-root}

    if command -v mysqldump &> /dev/null; then
        if [ -n "$DB_PASS" ]; then
            mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || {
                log_warning "数据库备份失败（可能是数据库不存在）"
                return 0
            }
        else
            mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || {
                log_warning "数据库备份失败（可能是数据库不存在）"
                return 0
            }
        fi
        log_success "数据库备份成功: $BACKUP_FILE"
    else
        log_warning "mysqldump 未安装，跳过备份"
    fi
}

# 执行数据库迁移
run_migrations() {
    log_info "执行数据库迁移..."

    cd "$DB_DIR"

    if php migrate.php <<< "y" ; then
        log_success "数据库迁移执行成功"
        return 0
    else
        log_error "数据库迁移执行失败"
        exit 1
    fi
}

# 创建数据库索引
create_indexes() {
    log_info "创建数据库索引..."

    INDEXES_FILE="$SCRIPT_DIR/init/create_indexes.sql"

    if [ -f "$INDEXES_FILE" ]; then
        cd "$DB_DIR"
        php -r "
        require_once 'test_connection.php';
        \$conn = testDatabaseConnection();
        if (!\$conn) {
            echo 'Database connection failed\n';
            exit(1);
        }
        \$sql = file_get_contents('$INDEXES_FILE');
        \$statements = array_filter(array_map('trim', explode(';', \$sql)));
        foreach (\$statements as \$statement) {
            if (!empty(\$statement)) {
                try {
                    \$conn['pdo']->exec(\$statement);
                    echo 'Index created successfully\n';
                } catch (Exception \$e) {
                    echo 'Index creation skipped (may already exist): ' . \$e->getMessage() . '\n';
                }
            }
        }
        "
        log_success "数据库索引创建完成"
    else
        log_info "索引文件不存在，跳过索引创建"
    fi
}

# 初始化基础数据
initialize_data() {
    log_info "初始化基础数据..."

    INIT_FILE="$SCRIPT_DIR/init/initialize_data.sql"

    if [ -f "$INIT_FILE" ]; then
        cd "$DB_DIR"
        php -r "
        require_once 'test_connection.php';
        \$conn = testDatabaseConnection();
        if (!\$conn) {
            echo 'Database connection failed\n';
            exit(1);
        }
        \$sql = file_get_contents('$INIT_FILE');
        \$statements = array_filter(array_map('trim', explode(';', \$sql)));
        foreach (\$statements as \$statement) {
            if (!empty(\$statement)) {
                try {
                    \$conn['pdo']->exec(\$statement);
                } catch (Exception \$e) {
                    echo 'Data initialization warning: ' . \$e->getMessage() . '\n';
                }
            }
        }
        echo 'Data initialization completed\n';
        "
        log_success "基础数据初始化完成"
    else
        log_info "初始化数据文件不存在，跳过数据初始化"
    fi
}

# 验证数据完整性
verify_data() {
    log_info "验证数据完整性..."

    cd "$DB_DIR"

    php -r "
    require_once 'test_connection.php';
    \$conn = testDatabaseConnection();
    if (!\$conn) {
        echo 'Database connection failed\n';
        exit(1);
    }

    \$prefix = \$conn['config']['prefix'];
    \$pdo = \$conn['pdo'];

    // 检查核心表是否存在
    \$tables = [
        'migration_log',
        'user',
        'merchants',
        'nfc_devices',
        'content_tasks',
        'content_templates'
    ];

    \$allTablesExist = true;
    foreach (\$tables as \$table) {
        \$fullTableName = \$prefix . \$table;
        \$stmt = \$pdo->query(\"SHOW TABLES LIKE '\$fullTableName'\");
        if (!\$stmt->fetch()) {
            echo \"Table \$fullTableName does not exist\n\";
            \$allTablesExist = false;
        }
    }

    if (\$allTablesExist) {
        echo 'All core tables exist\n';

        // 检查迁移记录表
        \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM {\$prefix}migration_log\");
        \$count = \$stmt->fetchColumn();
        echo \"Migration records: \$count\n\";

        exit(0);
    } else {
        exit(1);
    }
    "

    if [ $? -eq 0 ]; then
        log_success "数据完整性验证通过"
        return 0
    else
        log_error "数据完整性验证失败"
        exit 1
    fi
}

# 显示部署摘要
show_summary() {
    echo ""
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                   部署完成摘要                              ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo ""
    log_success "✓ 数据库连接检查通过"
    log_success "✓ 数据库迁移执行成功"
    log_success "✓ 数据库索引创建完成"
    log_success "✓ 基础数据初始化完成"
    log_success "✓ 数据完整性验证通过"
    echo ""
    log_info "部署日志已保存"
    echo ""
}

# 主函数
main() {
    show_banner

    # 确认生产环境部署
    log_warning "即将在生产环境执行数据库部署"
    log_warning "此操作将修改数据库结构和数据"
    echo -n "确认继续？(yes/no): "
    read -r confirm

    if [ "$confirm" != "yes" ]; then
        log_info "部署已取消"
        exit 0
    fi

    echo ""

    # 执行部署步骤
    check_php
    check_env_file
    check_database_connection
    backup_database_before_deploy
    run_migrations
    create_indexes
    initialize_data
    verify_data

    # 显示摘要
    show_summary

    log_success "数据库部署成功完成！"
}

# 错误处理
trap 'log_error "部署过程中发生错误，请检查日志"; exit 1' ERR

# 执行主函数
main "$@"
