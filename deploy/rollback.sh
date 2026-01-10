#!/bin/bash

################################################################################
# 小魔推 API 服务回滚脚本
# 版本: 1.0.0
# 用途: 回滚到之前的版本
################################################################################

set -e

# 配置
APP_DIR="/var/www/xiaomotui/api"
BACKUP_DIR="/var/backups/xiaomotui"
LOG_FILE="/var/log/xiaomotui/rollback.log"

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ==================== 工具函数 ====================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $@"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [INFO] $@" >> "${LOG_FILE}"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $@"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [SUCCESS] $@" >> "${LOG_FILE}"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $@"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [ERROR] $@" >> "${LOG_FILE}"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $@"
    echo "$(date '+%Y-%m-%d %H:%M:%S') [WARNING] $@" >> "${LOG_FILE}"
}

# ==================== 回滚函数 ====================

# 列出可用备份
list_backups() {
    log_info "可用的备份："
    echo ""

    if [ ! -d "${BACKUP_DIR}" ] || [ -z "$(ls -A ${BACKUP_DIR})" ]; then
        log_error "没有找到可用的备份"
        exit 1
    fi

    local count=0
    for backup in $(ls -t ${BACKUP_DIR}/backup-*.tar.gz 2>/dev/null); do
        ((count++))
        local filename=$(basename $backup)
        local timestamp=$(echo $filename | sed 's/backup-//;s/.tar.gz//')
        local size=$(du -h $backup | awk '{print $1}')
        local date=$(echo $timestamp | sed 's/\([0-9]\{8\}\)-\([0-9]\{6\}\)/\1 \2/')

        printf "  %2d) %s (%s) - %s\n" $count "$filename" "$size" "$date"
    done

    echo ""
    return 0
}

# 选择备份
select_backup() {
    local backup_file=""

    # 如果指定了备份文件
    if [ -n "$1" ]; then
        if [[ "$1" =~ ^[0-9]+$ ]]; then
            # 数字索引
            backup_file=$(ls -t ${BACKUP_DIR}/backup-*.tar.gz 2>/dev/null | sed -n "${1}p")
        else
            # 文件名
            backup_file="${BACKUP_DIR}/$1"
        fi
    else
        # 默认使用最新的备份
        backup_file=$(ls -t ${BACKUP_DIR}/backup-*.tar.gz 2>/dev/null | head -1)
    fi

    if [ -z "$backup_file" ] || [ ! -f "$backup_file" ]; then
        log_error "无效的备份文件"
        exit 1
    fi

    echo "$backup_file"
}

# 验证备份文件
verify_backup() {
    local backup_file=$1

    log_info "验证备份文件: $(basename $backup_file)"

    # 检查文件是否存在
    if [ ! -f "$backup_file" ]; then
        log_error "备份文件不存在"
        exit 1
    fi

    # 检查文件完整性
    if ! tar -tzf "$backup_file" &> /dev/null; then
        log_error "备份文件已损坏"
        exit 1
    fi

    log_success "备份文件验证通过"
}

# 停止服务
stop_services() {
    log_info "停止服务..."

    # 停止 PHP-FPM
    if systemctl is-active --quiet php8.0-fpm; then
        systemctl stop php8.0-fpm
        log_info "PHP-FPM 已停止"
    fi

    # 停止队列进程（如果有）
    if command -v supervisorctl &> /dev/null; then
        supervisorctl stop xiaomotui-queue:* 2>/dev/null || true
    fi

    log_success "服务已停止"
}

# 启动服务
start_services() {
    log_info "启动服务..."

    # 启动 PHP-FPM
    systemctl start php8.0-fpm
    log_info "PHP-FPM 已启动"

    # 重新加载 Nginx
    if systemctl is-active --quiet nginx; then
        systemctl reload nginx
        log_info "Nginx 已重载"
    fi

    # 启动队列进程（如果有）
    if command -v supervisorctl &> /dev/null; then
        supervisorctl start xiaomotui-queue:* 2>/dev/null || true
    fi

    log_success "服务已启动"
}

# 备份当前状态
backup_current_state() {
    log_info "备份当前状态..."

    local backup_file="${BACKUP_DIR}/rollback-backup-$(date +%Y%m%d-%H%M%S).tar.gz"

    cd "${APP_DIR}/.."
    tar -czf "${backup_file}" api/ --exclude='api/vendor' --exclude='api/runtime' 2>/dev/null || true

    log_success "当前状态已备份到: $(basename $backup_file)"
}

# 执行回滚
perform_rollback() {
    local backup_file=$1

    log_info "开始回滚..."
    log_info "备份文件: $(basename $backup_file)"

    # 解压备份
    log_info "解压备份文件..."

    # 临时目录
    local temp_dir="${APP_DIR}.rollback.tmp"
    mkdir -p "$temp_dir"

    # 解压到临时目录
    tar -xzf "$backup_file" -C "$temp_dir"

    # 移除当前应用（保留 vendor 和 runtime）
    log_info "替换应用文件..."

    # 保存 vendor 和 runtime
    if [ -d "${APP_DIR}/vendor" ]; then
        mv "${APP_DIR}/vendor" "${temp_dir}/api/" 2>/dev/null || true
    fi

    if [ -d "${APP_DIR}/runtime" ]; then
        mv "${APP_DIR}/runtime" "${temp_dir}/api/" 2>/dev/null || true
    fi

    # 移除旧应用
    rm -rf "${APP_DIR}"

    # 恢复备份
    mv "${temp_dir}/api" "${APP_DIR}"

    # 清理临时目录
    rm -rf "$temp_dir"

    log_success "文件回滚完成"
}

# 恢复数据库（可选）
restore_database() {
    log_info "检查数据库备份..."

    # 查找对应的数据库备份
    local backup_timestamp=$(basename $1 | sed 's/backup-//;s/.tar.gz//')
    local db_backup="${BACKUP_DIR}/database-${backup_timestamp}.sql.gz"

    if [ -f "$db_backup" ]; then
        log_warning "发现数据库备份: $(basename $db_backup)"

        read -p "是否恢复数据库? (y/n): " -n 1 -r
        echo

        if [[ $REPLY =~ ^[Yy]$ ]]; then
            log_info "恢复数据库..."

            # 从 .env 读取数据库配置
            cd "${APP_DIR}"
            source <(grep -E '^(DATABASE|USERNAME|PASSWORD|HOSTNAME)' .env | sed 's/ *= */=/g')

            # 恢复数据库
            gunzip < "$db_backup" | mysql -h${HOSTNAME} -u${USERNAME} -p${PASSWORD} ${DATABASE}

            log_success "数据库恢复完成"
        else
            log_warning "跳过数据库恢复"
        fi
    else
        log_info "未找到对应的数据库备份"
    fi
}

# 重新安装依赖
reinstall_dependencies() {
    log_info "检查依赖..."

    cd "${APP_DIR}"

    if [ ! -d "vendor" ] || [ -z "$(ls -A vendor)" ]; then
        log_info "重新安装 Composer 依赖..."
        composer install --no-dev --optimize-autoloader --no-interaction
        log_success "依赖安装完成"
    else
        log_info "依赖已存在，跳过安装"
    fi
}

# 清理缓存
clear_cache() {
    log_info "清理缓存..."

    cd "${APP_DIR}"

    # 清理 ThinkPHP 缓存
    php think clear 2>/dev/null || true

    # 清理 runtime 缓存
    if [ -d "runtime/cache" ]; then
        rm -rf runtime/cache/*
    fi

    log_success "缓存已清理"
}

# 设置权限
set_permissions() {
    log_info "设置文件权限..."

    cd "${APP_DIR}"

    # 设置目录权限
    find . -type d -exec chmod 755 {} \;

    # 设置文件权限
    find . -type f -exec chmod 644 {} \;

    # 可执行文件
    chmod +x think

    # 可写目录
    if [ -d "runtime" ]; then
        chmod -R 775 runtime
        chown -R www-data:www-data runtime
    fi

    log_success "权限设置完成"
}

# 验证回滚
verify_rollback() {
    log_info "验证回滚..."

    # 检查应用目录
    if [ ! -d "${APP_DIR}" ]; then
        log_error "应用目录不存在"
        exit 1
    fi

    # 检查关键文件
    if [ ! -f "${APP_DIR}/think" ]; then
        log_error "关键文件缺失"
        exit 1
    fi

    # 检查数据库连接
    cd "${APP_DIR}"
    if ! php think db:check &> /dev/null; then
        log_warning "数据库连接异常"
    fi

    log_success "回滚验证通过"
}

# 显示回滚信息
show_rollback_info() {
    log_info "回滚完成信息："

    cd "${APP_DIR}"

    echo ""
    echo "  应用目录: ${APP_DIR}"
    echo "  Git 提交: $(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
    echo "  回滚时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
}

# ==================== 主函数 ====================

main() {
    # 检查权限
    if [ "$EUID" -ne 0 ]; then
        log_error "请使用 root 或 sudo 运行此脚本"
        exit 1
    fi

    echo "======================================="
    echo "小魔推 API 服务回滚"
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================="
    echo ""

    # 确保日志目录存在
    mkdir -p "$(dirname ${LOG_FILE})"

    # 列出可用备份
    list_backups

    # 选择备份
    local backup_file=$(select_backup "$1")

    log_info "选择的备份: $(basename $backup_file)"
    echo ""

    # 确认回滚
    read -p "确认要回滚到此版本吗? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_warning "回滚已取消"
        exit 0
    fi

    # 验证备份
    verify_backup "$backup_file"

    # 备份当前状态
    backup_current_state

    # 停止服务
    stop_services

    # 执行回滚
    perform_rollback "$backup_file"

    # 恢复数据库（可选）
    restore_database "$backup_file"

    # 重新安装依赖
    reinstall_dependencies

    # 清理缓存
    clear_cache

    # 设置权限
    set_permissions

    # 启动服务
    start_services

    # 验证回滚
    verify_rollback

    # 显示信息
    show_rollback_info

    log_success "======================================="
    log_success "回滚成功完成！"
    log_success "======================================="

    echo ""
    echo "后续操作："
    echo "  - 检查服务状态: systemctl status php8.0-fpm nginx"
    echo "  - 查看日志: tail -f ${LOG_FILE}"
    echo "  - 验证 API: curl http://localhost/api/health"
    echo ""
}

# 解析参数
if [ "$1" = "--list" ]; then
    list_backups
    exit 0
fi

# 执行主函数
main "$@"
