#!/bin/bash

################################################################################
# 小魔推 API 服务备份脚本
# 版本: 1.0.0
# 用途: 备份应用代码和数据库
################################################################################

set -e

# 配置
APP_NAME="xiaomotui"
APP_DIR="/var/www/xiaomotui/api"
BACKUP_DIR="/var/backups/xiaomotui"
LOG_FILE="/var/log/xiaomotui/backup.log"

# 备份保留策略
KEEP_DAILY=7        # 保留7天的每日备份
KEEP_WEEKLY=4       # 保留4周的每周备份
KEEP_MONTHLY=3      # 保留3个月的月度备份

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ==================== 工具函数 ====================

log_info() {
    local msg="$@"
    echo -e "${BLUE}[INFO]${NC} ${msg}"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] ${msg}" >> "${LOG_FILE}"
}

log_success() {
    local msg="$@"
    echo -e "${GREEN}[SUCCESS]${NC} ${msg}"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] ${msg}" >> "${LOG_FILE}"
}

log_error() {
    local msg="$@"
    echo -e "${RED}[ERROR]${NC} ${msg}"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [ERROR] ${msg}" >> "${LOG_FILE}"
}

log_warning() {
    local msg="$@"
    echo -e "${YELLOW}[WARNING]${NC} ${msg}"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [WARNING] ${msg}" >> "${LOG_FILE}"
}

# 获取备份文件大小
get_size() {
    du -sh "$1" | awk '{print $1}'
}

# ==================== 备份函数 ====================

# 初始化
init_backup() {
    log_info "初始化备份环境..."

    # 创建备份目录
    mkdir -p "${BACKUP_DIR}"
    mkdir -p "$(dirname ${LOG_FILE})"

    # 检查磁盘空间
    local available_mb=$(df -BM "${BACKUP_DIR}" | tail -1 | awk '{print $4}' | sed 's/M//')

    if [ $available_mb -lt 1000 ]; then
        log_warning "可用磁盘空间不足 1GB (${available_mb}MB)"
    fi

    log_success "备份环境初始化完成"
}

# 备份应用代码
backup_application() {
    log_info "备份应用代码..."

    local timestamp=$(date +%Y%m%d-%H%M%S)
    local backup_file="${BACKUP_DIR}/backup-${timestamp}.tar.gz"

    cd "${APP_DIR}/.."

    # 创建备份（排除不必要的文件）
    tar -czf "${backup_file}" api/ \
        --exclude='api/vendor' \
        --exclude='api/runtime/cache/*' \
        --exclude='api/runtime/temp/*' \
        --exclude='api/runtime/log/*' \
        --exclude='api/.git' \
        --exclude='api/node_modules' \
        2>/dev/null

    local size=$(get_size "${backup_file}")
    log_success "应用备份完成: $(basename ${backup_file}) (${size})"

    echo "${backup_file}"
}

# 备份数据库
backup_database() {
    log_info "备份数据库..."

    local timestamp=$(date +%Y%m%d-%H%M%S)
    local backup_file="${BACKUP_DIR}/database-${timestamp}.sql.gz"

    # 从 .env 读取数据库配置
    cd "${APP_DIR}"

    if [ ! -f ".env" ]; then
        log_error ".env 文件不存在"
        return 1
    fi

    # 读取配置
    local DB_HOST=$(grep "^HOSTNAME" .env | cut -d '=' -f2 | tr -d ' "')
    local DB_NAME=$(grep "^DATABASE" .env | cut -d '=' -f2 | tr -d ' "')
    local DB_USER=$(grep "^USERNAME" .env | cut -d '=' -f2 | tr -d ' "')
    local DB_PASS=$(grep "^PASSWORD" .env | cut -d '=' -f2 | tr -d ' "')

    if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
        log_error "数据库配置不完整"
        return 1
    fi

    # 备份数据库
    if [ -n "$DB_PASS" ]; then
        mysqldump -h${DB_HOST} -u${DB_USER} -p${DB_PASS} ${DB_NAME} | gzip > "${backup_file}"
    else
        mysqldump -h${DB_HOST} -u${DB_USER} ${DB_NAME} | gzip > "${backup_file}"
    fi

    local size=$(get_size "${backup_file}")
    log_success "数据库备份完成: $(basename ${backup_file}) (${size})"

    echo "${backup_file}"
}

# 备份上传文件
backup_uploads() {
    log_info "备份上传文件..."

    local uploads_dir="${APP_DIR}/public/uploads"

    if [ ! -d "$uploads_dir" ]; then
        log_warning "上传目录不存在，跳过"
        return 0
    fi

    local timestamp=$(date +%Y%m%d-%H%M%S)
    local backup_file="${BACKUP_DIR}/uploads-${timestamp}.tar.gz"

    tar -czf "${backup_file}" -C "${APP_DIR}/public" uploads/ 2>/dev/null || true

    if [ -f "${backup_file}" ]; then
        local size=$(get_size "${backup_file}")
        log_success "上传文件备份完成: $(basename ${backup_file}) (${size})"
        echo "${backup_file}"
    fi
}

# 备份配置文件
backup_configs() {
    log_info "备份配置文件..."

    local timestamp=$(date +%Y%m%d-%H%M%S)
    local backup_file="${BACKUP_DIR}/configs-${timestamp}.tar.gz"

    cd "${APP_DIR}"

    tar -czf "${backup_file}" \
        .env \
        .env.production \
        config/ \
        2>/dev/null || true

    if [ -f "${backup_file}" ]; then
        local size=$(get_size "${backup_file}")
        log_success "配置文件备份完成: $(basename ${backup_file}) (${size})"
        echo "${backup_file}"
    fi
}

# 创建备份清单
create_manifest() {
    local manifest_file="${BACKUP_DIR}/backup-manifest-$(date +%Y%m%d-%H%M%S).txt"

    log_info "创建备份清单..."

    {
        echo "======================================"
        echo "小魔推 API 备份清单"
        echo "======================================"
        echo ""
        echo "备份时间: $(date '+%Y-%m-%d %H:%M:%S')"
        echo ""
        echo "系统信息:"
        echo "  主机名: $(hostname)"
        echo "  操作系统: $(uname -s) $(uname -r)"
        echo "  PHP 版本: $(php -v | head -1 | awk '{print $2}')"
        echo ""
        echo "应用信息:"
        echo "  应用目录: ${APP_DIR}"
        echo "  Git 分支: $(cd ${APP_DIR} && git branch --show-current 2>/dev/null || echo 'unknown')"
        echo "  Git 提交: $(cd ${APP_DIR} && git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
        echo ""
        echo "备份文件:"
        ls -lh ${BACKUP_DIR}/backup-$(date +%Y%m%d)*.tar.gz 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
        ls -lh ${BACKUP_DIR}/database-$(date +%Y%m%d)*.sql.gz 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
        echo ""
    } > "${manifest_file}"

    log_success "备份清单已创建: $(basename ${manifest_file})"
}

# 清理旧备份
cleanup_old_backups() {
    log_info "清理旧备份..."

    local removed=0

    # 清理每日备份（保留最近 N 天）
    find "${BACKUP_DIR}" -name "backup-*.tar.gz" -mtime +${KEEP_DAILY} -exec rm {} \; -exec echo "  删除: {}" \; | while read line; do
        ((removed++))
    done 2>/dev/null || true

    find "${BACKUP_DIR}" -name "database-*.sql.gz" -mtime +${KEEP_DAILY} -exec rm {} \; 2>/dev/null || true

    # 清理上传文件备份（保留最近 N 天）
    find "${BACKUP_DIR}" -name "uploads-*.tar.gz" -mtime +${KEEP_DAILY} -exec rm {} \; 2>/dev/null || true

    # 清理配置备份（保留最近 N 天）
    find "${BACKUP_DIR}" -name "configs-*.tar.gz" -mtime +${KEEP_DAILY} -exec rm {} \; 2>/dev/null || true

    # 清理清单文件（保留最近 N 天）
    find "${BACKUP_DIR}" -name "backup-manifest-*.txt" -mtime +${KEEP_DAILY} -exec rm {} \; 2>/dev/null || true

    log_success "旧备份清理完成"
}

# 显示备份统计
show_backup_stats() {
    log_info "备份统计信息..."

    echo ""
    echo "备份目录: ${BACKUP_DIR}"
    echo "总大小: $(du -sh ${BACKUP_DIR} | awk '{print $1}')"
    echo ""
    echo "文件统计:"
    echo "  应用备份: $(ls -1 ${BACKUP_DIR}/backup-*.tar.gz 2>/dev/null | wc -l) 个"
    echo "  数据库备份: $(ls -1 ${BACKUP_DIR}/database-*.sql.gz 2>/dev/null | wc -l) 个"
    echo "  上传文件备份: $(ls -1 ${BACKUP_DIR}/uploads-*.tar.gz 2>/dev/null | wc -l) 个"
    echo ""
    echo "最新备份:"
    ls -lt ${BACKUP_DIR}/backup-*.tar.gz 2>/dev/null | head -3 | awk '{print "  " $9 " - " $6 " " $7 " " $8}'
    echo ""
}

# 验证备份
verify_backup() {
    local backup_file=$1

    log_info "验证备份文件..."

    if [ ! -f "$backup_file" ]; then
        log_error "备份文件不存在: ${backup_file}"
        return 1
    fi

    # 验证 tar 文件完整性
    if tar -tzf "$backup_file" &> /dev/null; then
        log_success "备份文件完整性验证通过"
        return 0
    else
        log_error "备份文件已损坏: ${backup_file}"
        return 1
    fi
}

# ==================== 主函数 ====================

main() {
    echo "======================================="
    echo "小魔推 API 备份"
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================="
    echo ""

    # 初始化
    init_backup

    # 执行备份
    local app_backup=$(backup_application)
    verify_backup "$app_backup"

    local db_backup=$(backup_database)

    # 可选备份
    backup_uploads
    backup_configs

    # 创建清单
    create_manifest

    # 清理旧备份
    cleanup_old_backups

    # 显示统计
    show_backup_stats

    log_success "======================================="
    log_success "备份完成！"
    log_success "======================================="

    echo ""
    echo "备份文件："
    echo "  应用: $(basename ${app_backup})"
    [ -n "$db_backup" ] && echo "  数据库: $(basename ${db_backup})"
    echo ""
    echo "恢复命令："
    echo "  应用: bash /var/www/xiaomotui/deploy/rollback.sh 1"
    echo "  数据库: gunzip < ${db_backup} | mysql -u用户名 -p 数据库名"
    echo ""
}

# 执行主函数
main "$@"
