#!/bin/bash

################################################################################
# 小魔推 API 服务部署后验证脚本
# 版本: 1.0.0
# 用途: 验证部署是否成功
################################################################################

set -e

# 配置
APP_DIR="/var/www/xiaomotui/api"
API_BASE_URL="http://localhost"
TIMEOUT=10

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 检查结果
CHECKS_PASSED=0
CHECKS_FAILED=0

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

# HTTP 请求函数
http_get() {
    local url=$1
    local expected_code=${2:-200}

    local response=$(curl -s -o /dev/null -w "%{http_code}" --max-time $TIMEOUT "${url}" 2>/dev/null)

    if [ "$response" = "$expected_code" ]; then
        return 0
    else
        echo "HTTP $response (期望: $expected_code)"
        return 1
    fi
}

# ==================== 验证项 ====================

# 验证文件完整性
verify_files() {
    log_info "验证文件完整性..."

    local required_files=(
        "think"
        "composer.json"
        "app/controller/BaseController.php"
        "config/app.php"
        "config/database.php"
        ".env"
    )

    for file in "${required_files[@]}"; do
        if [ -f "${APP_DIR}/${file}" ]; then
            echo "  ✓ ${file}"
        else
            log_error "文件缺失: ${file}"
            return
        fi
    done

    log_success "文件完整性验证通过"
}

# 验证目录权限
verify_permissions() {
    log_info "验证目录权限..."

    # 检查 runtime 目录
    if [ -w "${APP_DIR}/runtime" ]; then
        log_success "runtime 目录权限正确"
    else
        log_error "runtime 目录权限不正确"
    fi

    # 检查日志目录
    if [ -d "/var/log/xiaomotui" ]; then
        if [ -w "/var/log/xiaomotui" ]; then
            echo "  ✓ 日志目录可写"
        else
            log_error "日志目录不可写"
        fi
    fi
}

# 验证数据库连接
verify_database() {
    log_info "验证数据库连接..."

    cd "${APP_DIR}"

    if php think db:check &> /dev/null; then
        log_success "数据库连接正常"
    else
        log_error "数据库连接失败"
    fi
}

# 验证 Redis 连接
verify_redis() {
    log_info "验证 Redis 连接..."

    if command -v redis-cli &> /dev/null; then
        if redis-cli ping &> /dev/null; then
            log_success "Redis 连接正常"
        else
            log_error "Redis 连接失败"
        fi
    else
        echo "  跳过（Redis 客户端未安装）"
    fi
}

# 验证 PHP-FPM 服务
verify_php_fpm() {
    log_info "验证 PHP-FPM 服务..."

    if systemctl is-active --quiet php8.0-fpm; then
        log_success "PHP-FPM 服务运行中"
    else
        log_error "PHP-FPM 服务未运行"
    fi
}

# 验证 Nginx 服务
verify_nginx() {
    log_info "验证 Nginx 服务..."

    if systemctl is-active --quiet nginx; then
        log_success "Nginx 服务运行中"
    else
        log_error "Nginx 服务未运行"
    fi
}

# 验证 API 健康检查
verify_health_endpoint() {
    log_info "验证 API 健康检查端点..."

    # 健康检查端点
    if http_get "${API_BASE_URL}/api/health"; then
        log_success "健康检查端点正常"
    else
        log_error "健康检查端点异常"
    fi
}

# 验证关键 API 端点
verify_api_endpoints() {
    log_info "验证关键 API 端点..."

    local endpoints=(
        "/api/nfc/device/status"
        "/api/auth/login"
        "/api/content/list"
    )

    local passed=0
    local total=${#endpoints[@]}

    for endpoint in "${endpoints[@]}"; do
        if curl -s --max-time $TIMEOUT "${API_BASE_URL}${endpoint}" &> /dev/null; then
            echo "  ✓ ${endpoint}"
            ((passed++))
        else
            echo "  ✗ ${endpoint}"
        fi
    done

    if [ $passed -eq $total ]; then
        log_success "API 端点验证通过 (${passed}/${total})"
    else
        log_error "部分 API 端点异常 (${passed}/${total})"
    fi
}

# 验证 Composer 自动加载
verify_autoload() {
    log_info "验证 Composer 自动加载..."

    cd "${APP_DIR}"

    if [ -f "vendor/autoload.php" ]; then
        if php -r "require 'vendor/autoload.php';" &> /dev/null; then
            log_success "自动加载正常"
        else
            log_error "自动加载失败"
        fi
    else
        log_error "自动加载文件不存在"
    fi
}

# 验证环境配置
verify_environment() {
    log_info "验证环境配置..."

    cd "${APP_DIR}"

    # 检查生产环境标志
    if grep -q "APP_DEBUG.*=.*false" .env; then
        log_success "生产环境模式已启用"
    else
        log_error "未启用生产环境模式"
    fi
}

# 验证定时任务
verify_cron_jobs() {
    log_info "验证定时任务..."

    if crontab -l 2>/dev/null | grep -q "xiaomotui"; then
        log_success "定时任务已配置"
        echo ""
        echo "  当前定时任务："
        crontab -l | grep xiaomotui | sed 's/^/    /'
    else
        log_error "定时任务未配置"
    fi
}

# 验证日志文件
verify_logs() {
    log_info "验证日志文件..."

    local log_dir="/var/log/xiaomotui"
    local app_log="${APP_DIR}/runtime/log"

    if [ -d "$log_dir" ]; then
        echo "  系统日志目录: ${log_dir}"
    fi

    if [ -d "$app_log" ]; then
        echo "  应用日志目录: ${app_log}"
    fi

    # 检查最近的错误日志
    if [ -f "${app_log}/error.log" ]; then
        local error_count=$(tail -100 "${app_log}/error.log" 2>/dev/null | wc -l)
        if [ $error_count -gt 0 ]; then
            echo "  最近 100 行日志中有 ${error_count} 条记录"
        fi
    fi

    log_success "日志配置正常"
}

# 验证缓存
verify_cache() {
    log_info "验证缓存配置..."

    cd "${APP_DIR}"

    # 检查缓存目录
    if [ -d "runtime/cache" ]; then
        local cache_files=$(find runtime/cache -type f | wc -l)
        echo "  缓存文件数: ${cache_files}"
    fi

    log_success "缓存配置正常"
}

# 验证队列进程（如果使用）
verify_queue() {
    log_info "验证队列进程..."

    # 检查是否配置了队列
    if command -v supervisorctl &> /dev/null; then
        if supervisorctl status xiaomotui-queue &> /dev/null; then
            log_success "队列进程运行中"
        else
            echo "  队列进程未配置（可选）"
        fi
    else
        echo "  跳过（Supervisor 未安装）"
    fi
}

# 性能测试
performance_test() {
    log_info "执行简单性能测试..."

    local start_time=$(date +%s%N)
    curl -s --max-time $TIMEOUT "${API_BASE_URL}/api/health" &> /dev/null
    local end_time=$(date +%s%N)

    local response_time=$(( ($end_time - $start_time) / 1000000 ))

    echo "  响应时间: ${response_time} ms"

    if [ $response_time -lt 500 ]; then
        log_success "响应时间良好"
    elif [ $response_time -lt 1000 ]; then
        echo "  响应时间可接受"
    else
        log_error "响应时间过长"
    fi
}

# 检查磁盘使用
verify_disk_usage() {
    log_info "检查磁盘使用..."

    local usage=$(df -h / | tail -1 | awk '{print $5}')
    echo "  磁盘使用率: ${usage}"

    local usage_num=$(echo $usage | sed 's/%//')
    if [ $usage_num -lt 80 ]; then
        log_success "磁盘空间充足"
    elif [ $usage_num -lt 90 ]; then
        echo "  警告: 磁盘使用率较高"
    else
        log_error "警告: 磁盘空间不足"
    fi
}

# 显示部署信息
show_deployment_info() {
    log_info "部署信息..."

    cd "${APP_DIR}"

    echo ""
    echo "  Git 分支: $(git branch --show-current 2>/dev/null || echo 'unknown')"
    echo "  Git 提交: $(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
    echo "  提交信息: $(git log -1 --pretty=%B 2>/dev/null | head -1 || echo 'unknown')"
    echo "  部署时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
}

# ==================== 主函数 ====================

main() {
    echo "======================================="
    echo "小魔推 API 部署后验证"
    echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================="
    echo ""

    # 执行所有验证
    verify_files
    verify_permissions
    verify_database
    verify_redis
    verify_php_fpm
    verify_nginx
    verify_autoload
    verify_environment
    verify_cron_jobs
    verify_logs
    verify_cache
    verify_queue
    verify_health_endpoint
    verify_api_endpoints
    performance_test
    verify_disk_usage

    # 显示部署信息
    show_deployment_info

    # 显示总结
    echo ""
    echo "======================================="
    echo "验证结果汇总"
    echo "======================================="
    echo -e "${GREEN}通过: ${CHECKS_PASSED}${NC}"
    echo -e "${RED}失败: ${CHECKS_FAILED}${NC}"
    echo ""

    if [ $CHECKS_FAILED -gt 0 ]; then
        log_error "存在 ${CHECKS_FAILED} 项验证失败"
        echo ""
        echo "建议操作："
        echo "  1. 检查错误日志: tail -f /var/log/xiaomotui/error.log"
        echo "  2. 检查应用日志: tail -f ${APP_DIR}/runtime/log/error.log"
        echo "  3. 检查服务状态: systemctl status php8.0-fpm nginx"
        echo "  4. 如需回滚，运行: bash /var/www/xiaomotui/deploy/rollback.sh"
        echo ""
        exit 1
    fi

    log_success "所有验证通过，部署成功！"
    echo ""
    echo "后续操作："
    echo "  - 监控日志: tail -f /var/log/xiaomotui/deploy.log"
    echo "  - 查看API文档: ${API_BASE_URL}/api/docs"
    echo "  - 性能监控: 使用您的监控工具"
    echo ""

    exit 0
}

# 执行主函数
main "$@"
