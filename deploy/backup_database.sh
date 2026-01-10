#!/bin/bash

###############################################################################
# 数据库备份脚本 (Linux/Mac)
# 用于备份数据库
#
# 功能：
# 1. 完整备份数据库
# 2. 增量备份（仅结构/仅数据）
# 3. 自动清理旧备份
# 4. 备份压缩
###############################################################################

set -e  # 遇到错误立即退出

# 脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
API_DIR="$PROJECT_ROOT/api"
DB_DIR="$API_DIR/database"
BACKUP_DIR="$PROJECT_ROOT/backups"

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
    echo "║           小魔推 - 数据库备份脚本 v1.0                     ║"
    echo "║           Database Backup Script                         ║"
    echo "║                                                           ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo ""
}

# 读取数据库配置
read_db_config() {
    ENV_FILE="$API_DIR/.env"

    if [ ! -f "$ENV_FILE" ]; then
        log_error ".env 文件不存在"
        exit 1
    fi

    DB_HOST=$(grep "^database.hostname" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_PORT=$(grep "^database.hostport" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_NAME=$(grep "^database.database" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_USER=$(grep "^database.username" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")
    DB_PASS=$(grep "^database.password" "$ENV_FILE" | cut -d '=' -f2 | tr -d ' "' | tr -d "'")

    # 默认值
    DB_HOST=${DB_HOST:-127.0.0.1}
    DB_PORT=${DB_PORT:-3306}
    DB_USER=${DB_USER:-root}

    if [ -z "$DB_NAME" ]; then
        log_error "数据库名称未配置"
        exit 1
    fi
}

# 检查 mysqldump 命令
check_mysqldump() {
    if ! command -v mysqldump &> /dev/null; then
        log_error "mysqldump 命令未找到，请安装 MySQL 客户端"
        exit 1
    fi
}

# 创建备份目录
create_backup_dir() {
    mkdir -p "$BACKUP_DIR"
    log_info "备份目录: $BACKUP_DIR"
}

# 完整备份
full_backup() {
    log_info "执行完整备份..."

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/full_backup_${DB_NAME}_$TIMESTAMP.sql"

    if [ -n "$DB_PASS" ]; then
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    else
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    fi

    if [ $? -eq 0 ]; then
        # 获取文件大小
        FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        log_success "完整备份成功: $BACKUP_FILE (大小: $FILE_SIZE)"

        # 是否压缩
        echo -n "是否压缩备份文件？(y/n): "
        read -r compress

        if [[ "$compress" =~ ^[Yy]$ ]]; then
            compress_backup "$BACKUP_FILE"
        fi
    else
        log_error "完整备份失败"
        exit 1
    fi
}

# 仅备份表结构
structure_only_backup() {
    log_info "执行表结构备份..."

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/structure_only_${DB_NAME}_$TIMESTAMP.sql"

    if [ -n "$DB_PASS" ]; then
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
            --no-data \
            --routines \
            --triggers \
            --events \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    else
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" \
            --no-data \
            --routines \
            --triggers \
            --events \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    fi

    if [ $? -eq 0 ]; then
        FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        log_success "表结构备份成功: $BACKUP_FILE (大小: $FILE_SIZE)"
    else
        log_error "表结构备份失败"
        exit 1
    fi
}

# 仅备份数据
data_only_backup() {
    log_info "执行数据备份..."

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/data_only_${DB_NAME}_$TIMESTAMP.sql"

    if [ -n "$DB_PASS" ]; then
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
            --no-create-info \
            --skip-triggers \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    else
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" \
            --no-create-info \
            --skip-triggers \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE"
    fi

    if [ $? -eq 0 ]; then
        FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        log_success "数据备份成功: $BACKUP_FILE (大小: $FILE_SIZE)"

        # 是否压缩
        echo -n "是否压缩备份文件？(y/n): "
        read -r compress

        if [[ "$compress" =~ ^[Yy]$ ]]; then
            compress_backup "$BACKUP_FILE"
        fi
    else
        log_error "数据备份失败"
        exit 1
    fi
}

# 压缩备份文件
compress_backup() {
    local BACKUP_FILE=$1
    log_info "压缩备份文件..."

    if command -v gzip &> /dev/null; then
        gzip "$BACKUP_FILE"
        if [ $? -eq 0 ]; then
            COMPRESSED_SIZE=$(du -h "$BACKUP_FILE.gz" | cut -f1)
            log_success "备份文件已压缩: $BACKUP_FILE.gz (大小: $COMPRESSED_SIZE)"
        else
            log_error "压缩失败"
        fi
    else
        log_warning "gzip 未安装，无法压缩"
    fi
}

# 列出备份文件
list_backups() {
    log_info "可用的备份文件："
    echo ""

    if [ ! -d "$BACKUP_DIR" ]; then
        log_warning "备份目录不存在"
        return
    fi

    # 列出 SQL 文件
    SQL_FILES=($(ls -t "$BACKUP_DIR"/*.sql 2>/dev/null))
    GZ_FILES=($(ls -t "$BACKUP_DIR"/*.sql.gz 2>/dev/null))

    ALL_FILES=("${SQL_FILES[@]}" "${GZ_FILES[@]}")

    if [ ${#ALL_FILES[@]} -eq 0 ]; then
        log_warning "没有找到备份文件"
        return
    fi

    for i in "${!ALL_FILES[@]}"; do
        FILE="${ALL_FILES[$i]}"
        FILENAME=$(basename "$FILE")
        FILESIZE=$(du -h "$FILE" | cut -f1)
        FILETIME=$(stat -c %y "$FILE" 2>/dev/null || stat -f "%Sm" "$FILE" 2>/dev/null)

        echo "$((i+1)). $FILENAME"
        echo "   大小: $FILESIZE"
        echo "   时间: $FILETIME"
        echo ""
    done
}

# 清理旧备份
cleanup_old_backups() {
    log_info "清理旧备份..."

    echo -n "保留最近多少天的备份？(默认: 7): "
    read -r days
    days=${days:-7}

    log_info "将删除 $days 天前的备份文件..."

    find "$BACKUP_DIR" -name "*.sql" -mtime +$days -delete
    find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$days -delete

    DELETED_COUNT=$(find "$BACKUP_DIR" -name "*.sql" -o -name "*.sql.gz" | wc -l)
    log_success "清理完成，剩余 $DELETED_COUNT 个备份文件"
}

# 定时备份（cron）
setup_cron_backup() {
    log_info "设置定时备份..."

    echo ""
    echo "定时备份选项："
    echo "1. 每天凌晨 2:00 备份"
    echo "2. 每周日凌晨 2:00 备份"
    echo "3. 自定义时间"
    echo "4. 取消"
    echo ""
    echo -n "请选择 (1-4): "
    read -r choice

    CRON_CMD="$SCRIPT_DIR/backup_database.sh --auto-full-backup"

    case $choice in
        1)
            CRON_EXPR="0 2 * * *"
            ;;
        2)
            CRON_EXPR="0 2 * * 0"
            ;;
        3)
            echo -n "请输入 cron 表达式 (例如: 0 2 * * *): "
            read -r CRON_EXPR
            ;;
        4)
            log_info "已取消"
            return
            ;;
        *)
            log_error "无效的选择"
            return
            ;;
    esac

    # 添加到 crontab
    (crontab -l 2>/dev/null | grep -v "$CRON_CMD"; echo "$CRON_EXPR $CRON_CMD") | crontab -

    log_success "定时备份已设置: $CRON_EXPR"
}

# 显示菜单
show_menu() {
    echo ""
    echo "备份选项："
    echo "1. 完整备份（结构+数据）"
    echo "2. 仅备份表结构"
    echo "3. 仅备份数据"
    echo "4. 列出备份文件"
    echo "5. 清理旧备份"
    echo "6. 设置定时备份"
    echo "7. 退出"
    echo ""
    echo -n "请选择操作 (1-7): "
}

# 自动完整备份（用于 cron）
auto_full_backup() {
    log_info "执行自动完整备份..."

    TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
    BACKUP_FILE="$BACKUP_DIR/auto_full_backup_${DB_NAME}_$TIMESTAMP.sql"

    if [ -n "$DB_PASS" ]; then
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE" 2>&1
    else
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            --set-gtid-purged=OFF \
            "$DB_NAME" > "$BACKUP_FILE" 2>&1
    fi

    if [ $? -eq 0 ]; then
        # 自动压缩
        if command -v gzip &> /dev/null; then
            gzip "$BACKUP_FILE"
            log_success "自动备份完成: $BACKUP_FILE.gz"
        else
            log_success "自动备份完成: $BACKUP_FILE"
        fi

        # 自动清理 30 天前的备份
        find "$BACKUP_DIR" -name "auto_full_backup_*.sql.gz" -mtime +30 -delete
        find "$BACKUP_DIR" -name "auto_full_backup_*.sql" -mtime +30 -delete
    else
        log_error "自动备份失败"
        exit 1
    fi
}

# 主函数
main() {
    # 检查是否是自动备份模式
    if [ "$1" == "--auto-full-backup" ]; then
        read_db_config
        check_mysqldump
        create_backup_dir
        auto_full_backup
        exit 0
    fi

    show_banner

    read_db_config
    check_mysqldump
    create_backup_dir

    while true; do
        show_menu
        read -r choice

        case $choice in
            1)
                full_backup
                ;;
            2)
                structure_only_backup
                ;;
            3)
                data_only_backup
                ;;
            4)
                list_backups
                ;;
            5)
                cleanup_old_backups
                ;;
            6)
                setup_cron_backup
                ;;
            7)
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
trap 'log_error "备份过程中发生错误"; exit 1' ERR

# 执行主函数
main "$@"
