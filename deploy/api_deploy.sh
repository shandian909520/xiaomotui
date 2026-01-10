#!/bin/bash

################################################################################
# 小魔推 API 服务部署脚本
# 版本: 1.0.0
# 用途: 自动化部署后端API服务
################################################################################

set -e  # 遇到错误立即退出

# ==================== 配置部分 ====================

# 应用配置
APP_NAME="xiaomotui"
APP_DIR="/var/www/xiaomotui/api"
DEPLOY_DIR="/var/www/xiaomotui/deploy"
BACKUP_DIR="/var/backups/xiaomotui"
LOG_DIR="/var/log/xiaomotui"
LOG_FILE="${LOG_DIR}/deploy.log"

# Git配置
GIT_BRANCH="master"
GIT_REMOTE="origin"

# PHP配置
PHP_VERSION="8.0"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"

# 用户配置
WEB_USER="www-data"
WEB_GROUP="www-data"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ==================== 工具函数 ====================

# 日志函数
log() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo -e "${timestamp} [${level}] ${message}" | tee -a "${LOG_FILE}"
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $@"
    log "INFO" "$@"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $@"
    log "SUCCESS" "$@"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $@"
    log "WARNING" "$@"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $@"
    log "ERROR" "$@"
}

# 错误处理
handle_error() {
    log_error "部署失败于第 $1 行"
    log_error "错误命令: $BASH_COMMAND"
    log_error "开始回滚..."

    # 调用回滚脚本
    if [ -f "${DEPLOY_DIR}/rollback.sh" ]; then
        bash "${DEPLOY_DIR}/rollback.sh"
    fi

    exit 1
}

trap 'handle_error $LINENO' ERR

# 检查命令是否存在
check_command() {
    if ! command -v $1 &> /dev/null; then
        log_error "命令 '$1' 未找到，请先安装"
        exit 1
    fi
}

# 确认操作
confirm() {
    local message=$1
    read -p "$message (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_warning "操作已取消"
        exit 0
    fi
}

# ==================== 主要部署步骤 ====================

# 初始化
init_deployment() {
    log_info "======================================="
    log_info "开始部署 ${APP_NAME} API 服务"
    log_info "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    log_info "======================================="

    # 确保日志目录存在
    mkdir -p "${LOG_DIR}"
    mkdir -p "${BACKUP_DIR}"

    # 检查必要命令
    check_command git
    check_command php
    check_command composer
    check_command mysql
}

# 步骤1: 预检查
pre_deployment_check() {
    log_info "步骤 1/10: 执行部署前检查..."

    if [ -f "${DEPLOY_DIR}/pre_deploy.sh" ]; then
        bash "${DEPLOY_DIR}/pre_deploy.sh"
        log_success "预检查通过"
    else
        log_warning "预检查脚本不存在，跳过"
    fi
}

# 步骤2: 备份
backup_current() {
    log_info "步骤 2/10: 备份当前版本..."

    if [ -f "${DEPLOY_DIR}/backup.sh" ]; then
        bash "${DEPLOY_DIR}/backup.sh"
        log_success "备份完成"
    else
        # 简单备份
        local backup_file="${BACKUP_DIR}/backup-$(date +%Y%m%d-%H%M%S).tar.gz"
        cd "${APP_DIR}/.."
        tar -czf "${backup_file}" api/ --exclude='api/vendor' --exclude='api/runtime' 2>/dev/null || true
        log_success "备份已保存到: ${backup_file}"
    fi
}

# 步骤3: 拉取代码
pull_code() {
    log_info "步骤 3/10: 拉取最新代码..."

    cd "${APP_DIR}"

    # 检查是否有未提交的更改
    if [[ -n $(git status -s) ]]; then
        log_warning "检测到未提交的更改"
        confirm "是否暂存这些更改并继续?"
        git stash
    fi

    # 拉取代码
    log_info "从 ${GIT_REMOTE}/${GIT_BRANCH} 拉取代码..."
    git fetch ${GIT_REMOTE}
    git checkout ${GIT_BRANCH}
    git pull ${GIT_REMOTE} ${GIT_BRANCH}

    local commit_hash=$(git rev-parse --short HEAD)
    log_success "代码已更新到提交: ${commit_hash}"
}

# 步骤4: 安装依赖
install_dependencies() {
    log_info "步骤 4/10: 安装 Composer 依赖..."

    cd "${APP_DIR}"

    # 生产环境优化安装
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
        --no-progress

    log_success "依赖安装完成"
}

# 步骤5: 环境配置
configure_environment() {
    log_info "步骤 5/10: 配置环境变量..."

    cd "${APP_DIR}"

    # 检查生产环境配置
    if [ ! -f ".env.production" ]; then
        log_error "生产环境配置文件 .env.production 不存在"
        exit 1
    fi

    # 复制或链接环境配置
    if [ -f ".env" ]; then
        cp .env .env.backup.$(date +%Y%m%d-%H%M%S)
    fi

    cp .env.production .env
    log_success "环境配置已设置为生产模式"
}

# 步骤6: 数据库迁移
run_migrations() {
    log_info "步骤 6/10: 执行数据库迁移..."

    cd "${APP_DIR}"

    # 检查数据库连接
    php think db:check 2>/dev/null || {
        log_error "数据库连接失败"
        exit 1
    }

    # 运行迁移
    if [ -f "${APP_DIR}/database/migrate.php" ]; then
        php database/migrate.php up
        log_success "数据库迁移完成"
    else
        log_warning "迁移脚本不存在，跳过"
    fi
}

# 步骤7: 清理缓存
clear_cache() {
    log_info "步骤 7/10: 清理应用缓存..."

    cd "${APP_DIR}"

    # 清理 ThinkPHP 缓存
    php think clear

    # 清理 runtime 目录（保留日志）
    if [ -d "runtime/cache" ]; then
        rm -rf runtime/cache/*
    fi

    if [ -d "runtime/temp" ]; then
        rm -rf runtime/temp/*
    fi

    # 清理 Redis 缓存（可选）
    # redis-cli FLUSHDB

    log_success "缓存清理完成"
}

# 步骤8: 设置权限
set_permissions() {
    log_info "步骤 8/10: 设置文件权限..."

    cd "${APP_DIR}"

    # 设置目录权限
    find . -type d -exec chmod 755 {} \;

    # 设置文件权限
    find . -type f -exec chmod 644 {} \;

    # 设置可执行权限
    chmod +x think

    # 设置写入权限
    if [ -d "runtime" ]; then
        chmod -R 775 runtime
        chown -R ${WEB_USER}:${WEB_GROUP} runtime
    fi

    if [ -d "public/uploads" ]; then
        chmod -R 775 public/uploads
        chown -R ${WEB_USER}:${WEB_GROUP} public/uploads
    fi

    log_success "文件权限设置完成"
}

# 步骤9: 配置定时任务
configure_cron() {
    log_info "步骤 9/10: 配置定时任务..."

    if [ -f "${DEPLOY_DIR}/crontab.txt" ]; then
        # 备份当前 crontab
        crontab -l > "${BACKUP_DIR}/crontab.backup.$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true

        # 安装新的 crontab
        crontab "${DEPLOY_DIR}/crontab.txt"

        log_success "定时任务配置完成"
        log_info "当前定时任务："
        crontab -l | grep xiaomotui || log_warning "未找到相关定时任务"
    else
        log_warning "定时任务配置文件不存在，跳过"
    fi
}

# 步骤10: 重启服务
restart_services() {
    log_info "步骤 10/10: 重启服务..."

    # 重启 PHP-FPM
    if systemctl is-active --quiet ${PHP_FPM_SERVICE}; then
        log_info "重启 PHP-FPM 服务..."
        systemctl restart ${PHP_FPM_SERVICE}
        log_success "PHP-FPM 已重启"
    else
        log_warning "PHP-FPM 服务未运行"
    fi

    # 重启 Nginx（可选）
    if systemctl is-active --quiet nginx; then
        log_info "重新加载 Nginx 配置..."
        systemctl reload nginx
        log_success "Nginx 配置已重载"
    fi

    # 重启队列进程（可选）
    # supervisorctl restart xiaomotui-queue:*
}

# 部署后验证
post_deployment_check() {
    log_info "执行部署后验证..."

    if [ -f "${DEPLOY_DIR}/post_deploy.sh" ]; then
        bash "${DEPLOY_DIR}/post_deploy.sh"
        log_success "部署后验证通过"
    else
        log_warning "部署后验证脚本不存在，跳过"
    fi
}

# 部署总结
deployment_summary() {
    log_info "======================================="
    log_success "部署成功完成！"
    log_info "======================================="

    cd "${APP_DIR}"

    echo ""
    echo "部署信息："
    echo "  应用目录: ${APP_DIR}"
    echo "  Git 分支: $(git branch --show-current)"
    echo "  Git 提交: $(git rev-parse --short HEAD)"
    echo "  部署时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
    echo "服务状态："
    echo "  PHP-FPM: $(systemctl is-active ${PHP_FPM_SERVICE})"
    echo "  Nginx: $(systemctl is-active nginx)"
    echo ""
    echo "日志文件: ${LOG_FILE}"
    echo ""
}

# ==================== 主函数 ====================

main() {
    # 检查是否以 root 运行
    if [ "$EUID" -ne 0 ]; then
        log_error "请使用 root 或 sudo 运行此脚本"
        exit 1
    fi

    # 初始化
    init_deployment

    # 确认部署
    if [ "${1}" != "--force" ]; then
        confirm "确认要部署到生产环境吗?"
    fi

    # 执行部署步骤
    pre_deployment_check
    backup_current
    pull_code
    install_dependencies
    configure_environment
    run_migrations
    clear_cache
    set_permissions
    configure_cron
    restart_services

    # 部署后验证
    post_deployment_check

    # 显示总结
    deployment_summary

    log_success "部署流程全部完成"
}

# 执行主函数
main "$@"
