#!/bin/bash

###############################################################################
# 数据库回滚脚本 (Linux/Mac)
# 用于回滚数据库迁移
#
# 功能：
# 1. 回滚最后一个批次的迁移
# 2. 回滚到指定批次
# 3. 完全重置数据库
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
    echo "║           小魔推 - 数据库回滚脚本 v1.0                     ║"
    echo "║           Database Rollback Script                       ║"
    echo "║                                                           ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo ""
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
        exit 1
    fi
}

# 备份数据库（回滚前）
backup_database_before_rollback() {
    log_info "回滚前备份数据库..."

    BACKUP_DIR="$PROJECT_ROOT/backups"
    mkdir -p "$BACKUP_DIR"

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/db_backup_before_rollback_$TIMESTAMP.sql"

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
            mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
        else
            mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null
        fi
        log_success "数据库备份成功: $BACKUP_FILE"
    else
        log_warning "mysqldump 未安装，跳过备份"
    fi
}

# 显示回滚状态
show_rollback_status() {
    log_info "查询回滚状态..."

    cd "$DB_DIR"

    php rollback.php <<< "3"
}

# 回滚最后一个批次
rollback_last_batch() {
    log_info "回滚最后一个批次..."

    cd "$DB_DIR"

    if php rollback.php <<< "1"; then
        log_success "回滚执行成功"
        return 0
    else
        log_error "回滚执行失败"
        exit 1
    fi
}

# 完全重置数据库
reset_database() {
    log_warning "即将完全重置数据库，这将删除所有表！"
    echo -n "确认要继续吗？(yes/no): "
    read -r confirm

    if [ "$confirm" != "yes" ]; then
        log_info "重置已取消"
        return 0
    fi

    log_info "完全重置数据库..."

    cd "$DB_DIR"

    if php rollback.php <<< "2" <<< "yes"; then
        log_success "数据库重置成功"
        return 0
    else
        log_error "数据库重置失败"
        exit 1
    fi
}

# 从备份恢复数据库
restore_from_backup() {
    log_info "可用的备份文件："

    BACKUP_DIR="$PROJECT_ROOT/backups"

    if [ ! -d "$BACKUP_DIR" ]; then
        log_warning "备份目录不存在"
        return 1
    fi

    # 列出备份文件
    backups=($(ls -t "$BACKUP_DIR"/*.sql 2>/dev/null))

    if [ ${#backups[@]} -eq 0 ]; then
        log_warning "没有找到备份文件"
        return 1
    fi

    echo ""
    for i in "${!backups[@]}"; do
        echo "$((i+1)). $(basename "${backups[$i]}")"
    done
    echo ""

    echo -n "请选择要恢复的备份 (1-${#backups[@]}): "
    read -r choice

    if ! [[ "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt ${#backups[@]} ]; then
        log_error "无效的选择"
        return 1
    fi

    BACKUP_FILE="${backups[$((choice-1))]}"
    log_info "将从以下备份恢复: $(basename "$BACKUP_FILE")"

    echo -n "确认要恢复吗？(yes/no): "
    read -r confirm

    if [ "$confirm" != "yes" ]; then
        log_info "恢复已取消"
        return 0
    fi

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

    log_info "正在恢复数据库..."

    if [ -n "$DB_PASS" ]; then
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"
    else
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
    fi

    if [ $? -eq 0 ]; then
        log_success "数据库恢复成功"
        return 0
    else
        log_error "数据库恢复失败"
        return 1
    fi
}

# 显示菜单
show_menu() {
    echo ""
    echo "回滚选项："
    echo "1. 回滚最后一个批次"
    echo "2. 完全重置数据库"
    echo "3. 从备份恢复数据库"
    echo "4. 查看回滚状态"
    echo "5. 退出"
    echo ""
    echo -n "请选择操作 (1-5): "
}

# 主函数
main() {
    show_banner

    # 检查数据库连接
    check_database_connection

    while true; do
        show_menu
        read -r choice

        case $choice in
            1)
                backup_database_before_rollback
                rollback_last_batch
                ;;
            2)
                backup_database_before_rollback
                reset_database
                ;;
            3)
                restore_from_backup
                ;;
            4)
                show_rollback_status
                ;;
            5)
                log_info "退出"
                exit 0
                ;;
            *)
                log_error "无效的选择"
                ;;
        esac

        echo ""
        echo -n "按回车键继续..."
        read
    done
}

# 错误处理
trap 'log_error "回滚过程中发生错误"; exit 1' ERR

# 执行主函数
main "$@"
