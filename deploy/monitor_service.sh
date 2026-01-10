#!/bin/bash

################################################################################
# 小魔推 API 服务监控脚本
# 版本: 1.0.0
# 用途: 监控服务进程，自动重启故障服务
################################################################################

set -e

# ==================== 配置部分 ====================

# 应用配置
APP_NAME="xiaomotui"
APP_DIR="/var/www/xiaomotui/api"
LOG_DIR="/var/log/xiaomotui"
LOG_FILE="${LOG_DIR}/monitor.log"
PID_FILE="/var/run/xiaomotui-monitor.pid"

# 监控配置
CHECK_INTERVAL=60  # 检查间隔（秒）
MAX_RESTART_ATTEMPTS=3  # 最大重启尝试次数
RESTART_COOLDOWN=300  # 重启冷却时间（秒）

# 告警配置
ALERT_EMAIL="admin@xiaomotui.com"
ALERT_ENABLED=false
ALERT_WEBHOOK=""  # 企业微信/钉钉 webhook

# 服务列表
declare -A SERVICES=(
    ["php-fpm"]="php*-fpm"
    ["nginx"]="nginx"
    ["mysql"]="mysql mariadb"
    ["redis"]="redis redis-server"
)

# 队列进程配置
QUEUE_WORKERS=2
QUEUE_COMMAND="cd ${APP_DIR} && php think queue:work --daemon"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 重启计数器
declare -A RESTART_COUNT
declare -A LAST_RESTART_TIME

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
    local level=${3:-"warning"}

    log_warning "发送告警: ${subject}"

    # 邮件告警
    if [ "${ALERT_ENABLED}" = true ] && [ -n "${ALERT_EMAIL}" ]; then
        echo "${message}" | mail -s "[小磨推监控] ${subject}" "${ALERT_EMAIL}" 2>/dev/null || true
    fi

    # Webhook 告警
    if [ -n "${ALERT_WEBHOOK}" ]; then
        local payload=$(cat <<EOF
{
    "msgtype": "text",
    "text": {
        "content": "[小磨推监控] ${subject}\n\n${message}\n\n时间: $(date '+%Y-%m-%d %H:%M:%S')"
    }
}
EOF
)
        curl -s -X POST "${ALERT_WEBHOOK}" \
            -H 'Content-Type: application/json' \
            -d "${payload}" >/dev/null 2>&1 || true
    fi
}

# 检查是否可以重启服务
can_restart_service() {
    local service=$1
    local current_time=$(date +%s)

    # 检查重启次数
    local count=${RESTART_COUNT[$service]:-0}
    if [ $count -ge $MAX_RESTART_ATTEMPTS ]; then
        local last_time=${LAST_RESTART_TIME[$service]:-0}
        local elapsed=$((current_time - last_time))

        if [ $elapsed -lt $RESTART_COOLDOWN ]; then
            log_error "服务 ${service} 重启次数过多，需等待 $((RESTART_COOLDOWN - elapsed)) 秒"
            return 1
        else
            # 重置计数器
            RESTART_COUNT[$service]=0
        fi
    fi

    return 0
}

# 记录重启
record_restart() {
    local service=$1
    local count=${RESTART_COUNT[$service]:-0}
    RESTART_COUNT[$service]=$((count + 1))
    LAST_RESTART_TIME[$service]=$(date +%s)
}

# ==================== 服务监控函数 ====================

# 检查系统服务
check_system_service() {
    local service_name=$1
    local service_patterns=$2

    for pattern in $service_patterns; do
        if systemctl is-active --quiet $pattern 2>/dev/null; then
            return 0
        fi
    done

    return 1
}

# 重启系统服务
restart_system_service() {
    local service_name=$1
    local service_patterns=$2

    log_warning "尝试重启服务: ${service_name}"

    for pattern in $service_patterns; do
        if systemctl list-units --all | grep -q "$pattern"; then
            if can_restart_service "${service_name}"; then
                log_info "重启 ${pattern}..."
                systemctl restart $pattern 2>/dev/null

                if [ $? -eq 0 ]; then
                    record_restart "${service_name}"
                    log_success "服务 ${pattern} 重启成功"
                    send_alert "服务重启" "服务 ${service_name} (${pattern}) 已自动重启"
                    return 0
                else
                    log_error "服务 ${pattern} 重启失败"
                fi
            fi
        fi
    done

    send_alert "服务重启失败" "服务 ${service_name} 重启失败，请手动检查" "error"
    return 1
}

# 监控系统服务
monitor_system_services() {
    for service_name in "${!SERVICES[@]}"; do
        local service_patterns="${SERVICES[$service_name]}"

        if ! check_system_service "${service_name}" "${service_patterns}"; then
            log_error "服务 ${service_name} 未运行"
            restart_system_service "${service_name}" "${service_patterns}"
        fi
    done
}

# 检查队列进程
check_queue_processes() {
    local running_workers=$(pgrep -f "queue:work" | wc -l)

    if [ $running_workers -lt $QUEUE_WORKERS ]; then
        log_warning "队列进程数量不足: ${running_workers}/${QUEUE_WORKERS}"
        return 1
    fi

    return 0
}

# 启动队列进程
start_queue_workers() {
    log_info "启动队列进程..."

    local current_workers=$(pgrep -f "queue:work" | wc -l)
    local needed_workers=$((QUEUE_WORKERS - current_workers))

    if [ $needed_workers -gt 0 ]; then
        for ((i=1; i<=needed_workers; i++)); do
            log_info "启动队列进程 #${i}..."
            nohup $QUEUE_COMMAND >> "${LOG_DIR}/queue-worker.log" 2>&1 &

            if [ $? -eq 0 ]; then
                log_success "队列进程 #${i} 启动成功"
            else
                log_error "队列进程 #${i} 启动失败"
            fi
        done

        send_alert "队列进程重启" "已启动 ${needed_workers} 个队列进程"
    fi
}

# 监控队列进程
monitor_queue_processes() {
    if ! check_queue_processes; then
        if can_restart_service "queue"; then
            record_restart "queue"
            start_queue_workers
        fi
    fi
}

# 检查 API 健康
check_api_health() {
    local api_url="http://localhost/api/health"

    if command -v curl &> /dev/null; then
        local http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${api_url}")

        if [ "$http_code" = "200" ]; then
            return 0
        else
            log_error "API 健康检查失败: HTTP ${http_code}"
            return 1
        fi
    fi

    return 0
}

# 检查磁盘空间
check_disk_space() {
    local disk_usage=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')

    if [ $disk_usage -gt 90 ]; then
        log_error "磁盘空间不足: ${disk_usage}%"
        send_alert "磁盘空间告警" "磁盘使用率已达 ${disk_usage}%，请及时清理" "error"
        return 1
    elif [ $disk_usage -gt 80 ]; then
        log_warning "磁盘空间紧张: ${disk_usage}%"
    fi

    return 0
}

# 清理临时文件
cleanup_temp_files() {
    log_info "清理临时文件..."

    # 清理过期缓存
    if [ -d "${APP_DIR}/runtime/cache" ]; then
        find "${APP_DIR}/runtime/cache" -type f -mtime +7 -delete 2>/dev/null || true
    fi

    # 清理临时文件
    if [ -d "${APP_DIR}/runtime/temp" ]; then
        find "${APP_DIR}/runtime/temp" -type f -mtime +1 -delete 2>/dev/null || true
    fi

    # 清理过期日志
    if [ -d "${LOG_DIR}" ]; then
        find "${LOG_DIR}" -name "*.log" -mtime +30 -delete 2>/dev/null || true
    fi

    log_info "临时文件清理完成"
}

# ==================== 主监控循环 ====================

# 监控循环
monitor_loop() {
    log_info "服务监控已启动"
    log_info "检查间隔: ${CHECK_INTERVAL} 秒"

    while true; do
        # 监控系统服务
        monitor_system_services

        # 监控队列进程
        monitor_queue_processes

        # 检查 API 健康
        check_api_health

        # 检查磁盘空间
        check_disk_space

        # 每小时清理一次临时文件
        local current_minute=$(date +%M)
        if [ "$current_minute" = "00" ]; then
            cleanup_temp_files
        fi

        # 等待下一次检查
        sleep $CHECK_INTERVAL
    done
}

# 启动监控
start_monitor() {
    # 检查是否已在运行
    if [ -f "${PID_FILE}" ]; then
        local old_pid=$(cat "${PID_FILE}")
        if ps -p $old_pid > /dev/null 2>&1; then
            log_error "监控进程已在运行 (PID: ${old_pid})"
            exit 1
        else
            log_warning "发现过期的 PID 文件，删除"
            rm -f "${PID_FILE}"
        fi
    fi

    # 保存 PID
    echo $$ > "${PID_FILE}"

    log_info "======================================="
    log_info "启动服务监控 - ${APP_NAME}"
    log_info "PID: $$"
    log_info "时间: $(date '+%Y-%m-%d %H:%M:%S')"
    log_info "======================================="

    # 设置清理陷阱
    trap cleanup EXIT INT TERM

    # 开始监控
    monitor_loop
}

# 停止监控
stop_monitor() {
    if [ -f "${PID_FILE}" ]; then
        local pid=$(cat "${PID_FILE}")
        if ps -p $pid > /dev/null 2>&1; then
            log_info "停止监控进程 (PID: ${pid})..."
            kill $pid
            rm -f "${PID_FILE}"
            log_success "监控进程已停止"
        else
            log_warning "监控进程不存在"
            rm -f "${PID_FILE}"
        fi
    else
        log_warning "未找到 PID 文件"
    fi
}

# 查看监控状态
status_monitor() {
    if [ -f "${PID_FILE}" ]; then
        local pid=$(cat "${PID_FILE}")
        if ps -p $pid > /dev/null 2>&1; then
            log_success "监控进程正在运行 (PID: ${pid})"

            # 显示重启统计
            echo ""
            echo "重启统计："
            for service in "${!RESTART_COUNT[@]}"; do
                echo "  ${service}: ${RESTART_COUNT[$service]} 次"
            done
        else
            log_error "监控进程未运行（但 PID 文件存在）"
            return 1
        fi
    else
        log_warning "监控进程未运行"
        return 1
    fi
}

# 清理函数
cleanup() {
    log_info "正在清理..."
    rm -f "${PID_FILE}"
    log_info "监控进程已退出"
}

# ==================== 主函数 ====================

main() {
    # 确保日志目录存在
    mkdir -p "${LOG_DIR}"

    case "${1:-start}" in
        start)
            start_monitor
            ;;
        stop)
            stop_monitor
            ;;
        restart)
            stop_monitor
            sleep 2
            start_monitor
            ;;
        status)
            status_monitor
            ;;
        *)
            echo "用法: $0 {start|stop|restart|status}"
            exit 1
            ;;
    esac
}

# 执行主函数
main "$@"
