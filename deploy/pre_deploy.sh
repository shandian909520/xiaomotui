#!/bin/bash

################################################################################
# 小魔推 API 服务部署前检查脚本
# 版本: 1.0.0
# 用途: 在部署前检查系统环境和依赖
################################################################################

set -e

# 配置
APP_DIR="/var/www/xiaomotui/api"
MIN_PHP_VERSION="8.0"
MIN_MYSQL_VERSION="8.0"
MIN_DISK_SPACE_MB=1000
MIN_MEMORY_MB=512

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 检查结果
CHECKS_PASSED=0
CHECKS_FAILED=0
CHECKS_WARNING=0

# ==================== 工具函数 ====================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $@"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $@"
    ((CHECKS_PASSED++))
}

log_error() {
    echo -e "${RED}[✗]${NC} $@"
    ((CHECKS_FAILED++))
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $@"
    ((CHECKS_WARNING++))
}

# ==================== 检查项 ====================

# 检查系统信息
check_system_info() {
    log_info "检查系统信息..."

    local os=$(uname -s)
    local arch=$(uname -m)
    local kernel=$(uname -r)

    echo "  操作系统: ${os}"
    echo "  架构: ${arch}"
    echo "  内核: ${kernel}"

    if [[ "$os" != "Linux" ]]; then
        log_warning "非 Linux 系统，可能存在兼容性问题"
    else
        log_success "系统兼容"
    fi
}

# 检查磁盘空间
check_disk_space() {
    log_info "检查磁盘空间..."

    local available_mb=$(df -BM / | tail -1 | awk '{print $4}' | sed 's/M//')

    echo "  可用空间: ${available_mb} MB"

    if [ $available_mb -lt $MIN_DISK_SPACE_MB ]; then
        log_error "磁盘空间不足 (需要至少 ${MIN_DISK_SPACE_MB} MB)"
    else
        log_success "磁盘空间充足"
    fi
}

# 检查内存
check_memory() {
    log_info "检查可用内存..."

    local available_mb=$(free -m | awk 'NR==2{print $7}')

    echo "  可用内存: ${available_mb} MB"

    if [ $available_mb -lt $MIN_MEMORY_MB ]; then
        log_warning "可用内存较低 (建议至少 ${MIN_MEMORY_MB} MB)"
    else
        log_success "内存充足"
    fi
}

# 检查 PHP
check_php() {
    log_info "检查 PHP..."

    if ! command -v php &> /dev/null; then
        log_error "PHP 未安装"
        return
    fi

    local php_version=$(php -v | head -1 | awk '{print $2}')
    echo "  PHP 版本: ${php_version}"

    # 检查版本
    local major=$(echo $php_version | cut -d. -f1)
    local minor=$(echo $php_version | cut -d. -f2)

    if [ $major -lt 8 ]; then
        log_error "PHP 版本过低 (需要 >= ${MIN_PHP_VERSION})"
        return
    fi

    # 检查必要扩展
    local required_extensions=("mysqli" "pdo_mysql" "redis" "curl" "json" "mbstring" "openssl" "xml")
    local missing_extensions=()

    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
            missing_extensions+=($ext)
        fi
    done

    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_error "缺少 PHP 扩展: ${missing_extensions[*]}"
    else
        log_success "PHP 配置正常"
    fi
}

# 检查 Composer
check_composer() {
    log_info "检查 Composer..."

    if ! command -v composer &> /dev/null; then
        log_error "Composer 未安装"
        return
    fi

    local composer_version=$(composer --version 2>/dev/null | awk '{print $3}')
    echo "  Composer 版本: ${composer_version}"

    log_success "Composer 可用"
}

# 检查 MySQL
check_mysql() {
    log_info "检查 MySQL..."

    if ! command -v mysql &> /dev/null; then
        log_warning "MySQL 客户端未安装"
        return
    fi

    # 检查连接（需要从 .env 读取配置）
    if [ -f "${APP_DIR}/.env.production" ]; then
        log_success "MySQL 配置文件存在"
    else
        log_warning "MySQL 配置文件不存在"
    fi
}

# 检查 Redis
check_redis() {
    log_info "检查 Redis..."

    if ! command -v redis-cli &> /dev/null; then
        log_warning "Redis 客户端未安装"
        return
    fi

    # 检查 Redis 连接
    if redis-cli ping &> /dev/null; then
        log_success "Redis 服务正常"
    else
        log_error "无法连接 Redis"
    fi
}

# 检查 Git
check_git() {
    log_info "检查 Git..."

    if ! command -v git &> /dev/null; then
        log_error "Git 未安装"
        return
    fi

    local git_version=$(git --version | awk '{print $3}')
    echo "  Git 版本: ${git_version}"

    log_success "Git 可用"
}

# 检查应用目录
check_app_directory() {
    log_info "检查应用目录..."

    if [ ! -d "${APP_DIR}" ]; then
        log_error "应用目录不存在: ${APP_DIR}"
        return
    fi

    # 检查是否是 Git 仓库
    if [ ! -d "${APP_DIR}/.git" ]; then
        log_error "应用目录不是 Git 仓库"
        return
    fi

    # 检查写入权限
    if [ ! -w "${APP_DIR}" ]; then
        log_error "应用目录没有写入权限"
        return
    fi

    log_success "应用目录正常"
}

# 检查数据库连接
check_database_connection() {
    log_info "检查数据库连接..."

    cd "${APP_DIR}"

    if [ ! -f "think" ]; then
        log_warning "ThinkPHP 命令行工具不存在"
        return
    fi

    # 尝试执行数据库检查命令
    if php think db:check &> /dev/null; then
        log_success "数据库连接正常"
    else
        log_error "数据库连接失败"
    fi
}

# 检查 Nginx
check_nginx() {
    log_info "检查 Nginx..."

    if ! command -v nginx &> /dev/null; then
        log_warning "Nginx 未安装"
        return
    fi

    local nginx_version=$(nginx -v 2>&1 | awk '{print $3}')
    echo "  Nginx 版本: ${nginx_version}"

    # 检查配置文件语法
    if nginx -t &> /dev/null; then
        log_success "Nginx 配置正常"
    else
        log_error "Nginx 配置有误"
    fi
}

# 检查 PHP-FPM
check_php_fpm() {
    log_info "检查 PHP-FPM..."

    if ! systemctl is-active --quiet php8.0-fpm; then
        log_warning "PHP-FPM 服务未运行"
        return
    fi

    log_success "PHP-FPM 服务运行中"
}

# 检查端口占用
check_ports() {
    log_info "检查端口占用..."

    local ports=(80 443 3306 6379 9000)

    for port in "${ports[@]}"; do
        if netstat -tuln 2>/dev/null | grep -q ":${port} "; then
            echo "  端口 ${port}: 使用中"
        else
            log_warning "端口 ${port}: 未使用"
        fi
    done

    log_success "端口检查完成"
}

# 检查文件权限
check_file_permissions() {
    log_info "检查关键目录权限..."

    if [ -d "${APP_DIR}/runtime" ]; then
        if [ -w "${APP_DIR}/runtime" ]; then
            log_success "runtime 目录可写"
        else
            log_error "runtime 目录不可写"
        fi
    else
        log_warning "runtime 目录不存在"
    fi

    if [ -d "${APP_DIR}/public/uploads" ]; then
        if [ -w "${APP_DIR}/public/uploads" ]; then
            log_success "uploads 目录可写"
        else
            log_error "uploads 目录不可写"
        fi
    else
        log_warning "uploads 目录不存在"
    fi
}

# 检查环境配置
check_environment_config() {
    log_info "检查环境配置..."

    if [ ! -f "${APP_DIR}/.env.production" ]; then
        log_error "生产环境配置文件不存在"
        return
    fi

    # 检查必要的配置项
    local required_vars=("DATABASE" "REDIS" "APP_DEBUG")

    for var in "${required_vars[@]}"; do
        if grep -q "^${var}" "${APP_DIR}/.env.production"; then
            echo "  ${var}: 已配置"
        else
            log_warning "${var}: 未配置"
        fi
    done

    log_success "环境配置检查完成"
}

# 检查备份目录
check_backup_directory() {
    log_info "检查备份目录..."

    local backup_dir="/var/backups/xiaomotui"

    if [ ! -d "${backup_dir}" ]; then
        log_warning "备份目录不存在，将创建"
        mkdir -p "${backup_dir}"
    fi

    if [ -w "${backup_dir}" ]; then
        log_success "备份目录可用"
    else
        log_error "备份目录不可写"
    fi
}

# ==================== 主函数 ====================

main() {
    echo "======================================="
    echo "小魔推 API 部署前检查"
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================="
    echo ""

    # 执行所有检查
    check_system_info
    check_disk_space
    check_memory
    check_php
    check_composer
    check_mysql
    check_redis
    check_git
    check_nginx
    check_php_fpm
    check_app_directory
    check_database_connection
    check_ports
    check_file_permissions
    check_environment_config
    check_backup_directory

    # 显示总结
    echo ""
    echo "======================================="
    echo "检查结果汇总"
    echo "======================================="
    echo -e "${GREEN}通过: ${CHECKS_PASSED}${NC}"
    echo -e "${YELLOW}警告: ${CHECKS_WARNING}${NC}"
    echo -e "${RED}失败: ${CHECKS_FAILED}${NC}"
    echo ""

    if [ $CHECKS_FAILED -gt 0 ]; then
        log_error "存在 ${CHECKS_FAILED} 项严重问题，请先解决后再部署"
        exit 1
    fi

    if [ $CHECKS_WARNING -gt 0 ]; then
        log_warning "存在 ${CHECKS_WARNING} 项警告，建议解决后再部署"
        read -p "是否继续部署? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi

    log_success "所有检查通过，可以开始部署"
    exit 0
}

# 执行主函数
main "$@"
