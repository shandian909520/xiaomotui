#!/bin/bash

################################################################################
# 小魔推 API 健康检查脚本
# 版本: 1.0.0
# 用途: 检查服务运行状态和资源使用情况
################################################################################

set -e

# ==================== 配置部分 ====================

# 应用配置
APP_NAME="xiaomotui"
APP_DIR="/var/www/xiaomotui/api"
LOG_DIR="/var/log/xiaomotui"
LOG_FILE="${LOG_DIR}/health_check.log"

# 检查阈值
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90
RESPONSE_TIME_THRESHOLD=1000  # 毫秒

# 告警配置
ALERT_EMAIL="admin@xiaomotui.com"
ALERT_ENABLED=false

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ==================== 工具函数 ====================

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
    echo -e "${GREEN}[OK]${NC} $@"
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

# 发送告警
send_alert() {
    local subject=$1
    local message=$2

    if [ "${ALERT_ENABLED}" = true ]; then
        echo "${message}" | mail -s "[小磨推] ${subject}" "${ALERT_EMAIL}"
    fi
}

# ==================== 检查项 ====================

# 检查服务状态
check_services() {
    log_info "检查服务状态..."

    local failed=0

    # 检查 PHP-FPM
    if systemctl is-active --quiet php*-fpm; then
        log_success "PHP-FPM 运行正常"
    else
        log_error "PHP-FPM 未运行"
        failed=$((failed + 1))
    fi

    # 检查 Nginx
    if systemctl is-active --quiet nginx; then
        log_success "Nginx 运行正常"
    else
        log_error "Nginx 未运行"
        failed=$((failed + 1))
    fi

    # 检查 MySQL
    if systemctl is-active --quiet mysql || systemctl is-active --quiet mariadb; then
        log_success "MySQL 运行正常"
    else
        log_error "MySQL 未运行"
        failed=$((failed + 1))
    fi

    # 检查 Redis
    if systemctl is-active --quiet redis || systemctl is-active --quiet redis-server; then
        log_success "Redis 运行正常"
    else
        log_error "Redis 未运行"
        failed=$((failed + 1))
    fi

    return $failed
}

# 检查数据库连接
check_database() {
    log_info "检查数据库连接..."

    cd "${APP_DIR}"

    # 使用 think 命令检查数据库
    if php think db:check 2>/dev/null; then
        log_success "数据库连接正常"
        return 0
    else
        log_error "数据库连接失败"
        send_alert "数据库连接失败" "无法连接到数据库，请立即检查"
        return 1
    fi
}

# 检查 Redis 连接
check_redis() {
    log_info "检查 Redis 连接..."

    if command -v redis-cli &> /dev/null; then
        if redis-cli ping 2>/dev/null | grep -q "PONG"; then
            log_success "Redis 连接正常"

            # 检查内存使用
            local mem_used=$(redis-cli info memory | grep used_memory: | cut -d: -f2 | tr -d '\r')
            local mem_max=$(redis-cli config get maxmemory | tail -1)

            if [ "$mem_max" != "0" ]; then
                local mem_percent=$((mem_used * 100 / mem_max))
                if [ $mem_percent -gt 80 ]; then
                    log_warning "Redis 内存使用率: ${mem_percent}%"
                else
                    log_info "Redis 内存使用率: ${mem_percent}%"
                fi
            fi

            return 0
        else
            log_error "Redis 连接失败"
            send_alert "Redis 连接失败" "无法连接到 Redis，请立即检查"
            return 1
        fi
    else
        log_warning "redis-cli 未安装，跳过检查"
        return 0
    fi
}

# 检查磁盘空间
check_disk_space() {
    log_info "检查磁盘空间..."

    local failed=0

    # 检查主分区
    local disk_usage=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')

    if [ $disk_usage -gt $DISK_THRESHOLD ]; then
        log_error "根分区磁盘使用率: ${disk_usage}% (阈值: ${DISK_THRESHOLD}%)"
        send_alert "磁盘空间不足" "根分区磁盘使用率已达 ${disk_usage}%"
        failed=$((failed + 1))
    elif [ $disk_usage -gt 70 ]; then
        log_warning "根分区磁盘使用率: ${disk_usage}%"
    else
        log_success "根分区磁盘使用率: ${disk_usage}%"
    fi

    # 检查应用目录
    if [ -d "${APP_DIR}" ]; then
        local app_disk_usage=$(df -h "${APP_DIR}" | tail -1 | awk '{print $5}' | sed 's/%//')

        if [ $app_disk_usage -gt $DISK_THRESHOLD ]; then
            log_error "应用目录磁盘使用率: ${app_disk_usage}% (阈值: ${DISK_THRESHOLD}%)"
            failed=$((failed + 1))
        else
            log_info "应用目录磁盘使用率: ${app_disk_usage}%"
        fi
    fi

    return $failed
}

# 检查系统资源
check_system_resources() {
    log_info "检查系统资源..."

    local failed=0

    # 检查 CPU 使用率
    if command -v mpstat &> /dev/null; then
        local cpu_idle=$(mpstat 1 1 | tail -1 | awk '{print $NF}')
        local cpu_usage=$(echo "100 - $cpu_idle" | bc | cut -d. -f1)

        if [ $cpu_usage -gt $CPU_THRESHOLD ]; then
            log_warning "CPU 使用率: ${cpu_usage}% (阈值: ${CPU_THRESHOLD}%)"
            failed=$((failed + 1))
        else
            log_success "CPU 使用率: ${cpu_usage}%"
        fi
    else
        local cpu_usage=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}')
        log_info "CPU 使用率: ${cpu_usage}%"
    fi

    # 检查内存使用率
    local mem_total=$(free -m | awk 'NR==2{print $2}')
    local mem_used=$(free -m | awk 'NR==2{print $3}')
    local mem_percent=$((mem_used * 100 / mem_total))

    if [ $mem_percent -gt $MEMORY_THRESHOLD ]; then
        log_warning "内存使用率: ${mem_percent}% (阈值: ${MEMORY_THRESHOLD}%)"
        send_alert "内存使用率过高" "当前内存使用率: ${mem_percent}%"
        failed=$((failed + 1))
    else
        log_success "内存使用率: ${mem_percent}%"
    fi

    # 检查负载
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | cut -d, -f1 | xargs)
    log_info "系统负载: ${load_avg}"

    return $failed
}

# 检查 API 响应
check_api_response() {
    log_info "检查 API 响应..."

    # 检查健康检查接口
    local api_url="http://localhost/api/health"

    if command -v curl &> /dev/null; then
        local start_time=$(date +%s%3N)
        local http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${api_url}")
        local end_time=$(date +%s%3N)
        local response_time=$((end_time - start_time))

        if [ "$http_code" = "200" ]; then
            if [ $response_time -gt $RESPONSE_TIME_THRESHOLD ]; then
                log_warning "API 响应正常但较慢: ${response_time}ms (阈值: ${RESPONSE_TIME_THRESHOLD}ms)"
            else
                log_success "API 响应正常: ${response_time}ms"
            fi
            return 0
        else
            log_error "API 响应异常: HTTP ${http_code}"
            send_alert "API 服务异常" "健康检查接口返回 HTTP ${http_code}"
            return 1
        fi
    else
        log_warning "curl 未安装，跳过 API 检查"
        return 0
    fi
}

# 检查队列进程
check_queue_processes() {
    log_info "检查队列进程..."

    local queue_count=$(ps aux | grep -i "queue:work" | grep -v grep | wc -l)

    if [ $queue_count -gt 0 ]; then
        log_success "队列进程运行正常 (${queue_count} 个进程)"
        return 0
    else
        log_warning "未检测到队列进程"
        return 1
    fi
}

# 检查日志错误
check_error_logs() {
    log_info "检查错误日志..."

    if [ -d "${APP_DIR}/runtime/log" ]; then
        local today=$(date +%Y%m%d)
        local error_log="${APP_DIR}/runtime/log/error-${today}.log"

        if [ -f "$error_log" ]; then
            local error_count=$(wc -l < "$error_log")

            if [ $error_count -gt 100 ]; then
                log_warning "今日错误日志数量: ${error_count}"

                # 显示最近的错误
                log_info "最近的错误："
                tail -5 "$error_log" | while read line; do
                    log_info "  $line"
                done
            else
                log_success "今日错误日志数量: ${error_count}"
            fi
        else
            log_success "今日无错误日志"
        fi
    fi

    return 0
}

# ==================== 主函数 ====================

main() {
    log_info "======================================="
    log_info "开始健康检查 - ${APP_NAME}"
    log_info "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    log_info "======================================="

    local total_failed=0

    # 执行所有检查
    check_services || total_failed=$((total_failed + 1))
    echo ""

    check_database || total_failed=$((total_failed + 1))
    echo ""

    check_redis || total_failed=$((total_failed + 1))
    echo ""

    check_disk_space || total_failed=$((total_failed + 1))
    echo ""

    check_system_resources || total_failed=$((total_failed + 1))
    echo ""

    check_api_response || total_failed=$((total_failed + 1))
    echo ""

    check_queue_processes || total_failed=$((total_failed + 1))
    echo ""

    check_error_logs
    echo ""

    # 总结
    log_info "======================================="
    if [ $total_failed -eq 0 ]; then
        log_success "健康检查完成 - 所有检查通过"
        exit 0
    else
        log_warning "健康检查完成 - ${total_failed} 项检查失败"
        exit 1
    fi
}

# 确保日志目录存在
mkdir -p "${LOG_DIR}"

# 执行主函数
main "$@"
