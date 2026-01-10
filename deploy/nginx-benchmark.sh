#!/bin/bash

###############################################################################
# Nginx性能测试脚本
# 项目：小魔推
# 用途：测试Nginx配置的性能和稳定性
###############################################################################

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 测试配置
TEST_DOMAIN="${TEST_DOMAIN:-api.xiaomotui.com}"
TEST_URL="${TEST_URL:-https://${TEST_DOMAIN}/health}"
CONCURRENT_USERS="${CONCURRENT_USERS:-100}"
TOTAL_REQUESTS="${TOTAL_REQUESTS:-10000}"

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

# 检查依赖
check_dependencies() {
    log_info "检查依赖工具..."

    local missing_tools=()

    if ! command -v ab &> /dev/null; then
        missing_tools+=("apache2-utils (ab)")
    fi

    if ! command -v wrk &> /dev/null; then
        missing_tools+=("wrk")
    fi

    if ! command -v curl &> /dev/null; then
        missing_tools+=("curl")
    fi

    if [ ${#missing_tools[@]} -gt 0 ]; then
        log_warn "以下工具未安装: ${missing_tools[*]}"
        log_info "安装命令:"
        log_info "  Ubuntu: sudo apt install -y apache2-utils wrk curl"
        log_info "  CentOS: sudo yum install -y httpd-tools curl"
        log_info "  wrk需要从源码编译: https://github.com/wg/wrk"
        return 1
    fi

    log_info "所有依赖工具已安装"
    return 0
}

# 测试连通性
test_connectivity() {
    log_header "测试连通性"

    log_info "测试URL: $TEST_URL"

    if curl -k -s -o /dev/null -w "%{http_code}" "$TEST_URL" | grep -q "200"; then
        log_info "连通性测试通过 ✓"
        return 0
    else
        log_error "连通性测试失败 ✗"
        log_error "请检查Nginx服务是否运行，域名解析是否正确"
        return 1
    fi
}

# Apache Bench测试
test_with_ab() {
    log_header "Apache Bench (ab) 压力测试"

    log_info "并发用户: $CONCURRENT_USERS"
    log_info "总请求数: $TOTAL_REQUESTS"

    local output_file="/tmp/ab_test_$(date +%Y%m%d_%H%M%S).txt"

    log_info "开始测试..."

    ab -n "$TOTAL_REQUESTS" \
       -c "$CONCURRENT_USERS" \
       -k \
       -g "$output_file.gnuplot" \
       "$TEST_URL" | tee "$output_file"

    log_info "测试结果已保存到: $output_file"

    # 提取关键指标
    echo ""
    log_info "关键性能指标："
    grep "Requests per second:" "$output_file" || true
    grep "Time per request:" "$output_file" || true
    grep "Transfer rate:" "$output_file" || true
    grep "Failed requests:" "$output_file" || true
}

# wrk测试
test_with_wrk() {
    if ! command -v wrk &> /dev/null; then
        log_warn "wrk未安装，跳过测试"
        return 0
    fi

    log_header "wrk 压力测试"

    log_info "测试参数:"
    log_info "  - 线程数: 4"
    log_info "  - 并发连接: $CONCURRENT_USERS"
    log_info "  - 持续时间: 30秒"

    local output_file="/tmp/wrk_test_$(date +%Y%m%d_%H%M%S).txt"

    log_info "开始测试..."

    wrk -t4 \
        -c"$CONCURRENT_USERS" \
        -d30s \
        --timeout 10s \
        --latency \
        "$TEST_URL" | tee "$output_file"

    log_info "测试结果已保存到: $output_file"
}

# 测试静态资源性能
test_static_files() {
    log_header "静态资源性能测试"

    local static_url="https://${TEST_DOMAIN}/static/test.jpg"

    log_info "测试URL: $static_url"

    # 创建测试文件（如果不存在）
    if ! curl -k -s -o /dev/null -w "%{http_code}" "$static_url" | grep -q "200"; then
        log_warn "测试文件不存在，跳过静态资源测试"
        return 0
    fi

    ab -n 5000 -c 50 -k "$static_url" | grep -E "Requests per second:|Time per request:"
}

# 测试Gzip压缩
test_gzip() {
    log_header "Gzip压缩测试"

    log_info "测试压缩效果..."

    # 无压缩
    local size_no_gzip=$(curl -k -s -H "Accept-Encoding: identity" "$TEST_URL" | wc -c)

    # 启用压缩
    local size_gzip=$(curl -k -s -H "Accept-Encoding: gzip" "$TEST_URL" | wc -c)

    if [ "$size_gzip" -lt "$size_no_gzip" ]; then
        local ratio=$(echo "scale=2; (1 - $size_gzip / $size_no_gzip) * 100" | bc)
        log_info "压缩效果: ✓"
        log_info "  - 原始大小: $size_no_gzip bytes"
        log_info "  - 压缩后大小: $size_gzip bytes"
        log_info "  - 压缩率: ${ratio}%"
    else
        log_warn "Gzip压缩未启用或无效"
    fi
}

# 测试SSL/TLS配置
test_ssl() {
    log_header "SSL/TLS配置测试"

    if ! echo "$TEST_URL" | grep -q "https"; then
        log_warn "不是HTTPS连接，跳过SSL测试"
        return 0
    fi

    log_info "测试SSL协议和加密套件..."

    # 检查TLS版本
    echo | openssl s_client -connect "${TEST_DOMAIN}:443" -tls1_2 2>/dev/null | \
        grep -E "Protocol|Cipher" || true

    # 检查证书有效期
    local cert_expiry=$(echo | openssl s_client -connect "${TEST_DOMAIN}:443" 2>/dev/null | \
        openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)

    if [ -n "$cert_expiry" ]; then
        log_info "证书过期时间: $cert_expiry"
    fi
}

# 测试安全头部
test_security_headers() {
    log_header "安全头部测试"

    log_info "检查安全响应头..."

    local headers=$(curl -k -I -s "$TEST_URL")

    # 检查各个安全头部
    local security_headers=(
        "Strict-Transport-Security"
        "X-Frame-Options"
        "X-Content-Type-Options"
        "X-XSS-Protection"
    )

    for header in "${security_headers[@]}"; do
        if echo "$headers" | grep -qi "$header"; then
            log_info "  ✓ $header"
        else
            log_warn "  ✗ $header (未设置)"
        fi
    done
}

# 测试限流配置
test_rate_limiting() {
    log_header "限流配置测试"

    log_info "测试API限流..."

    local api_url="https://${TEST_DOMAIN}/api/test"

    # 快速发送多个请求
    local success_count=0
    local fail_count=0

    for i in {1..20}; do
        local status=$(curl -k -s -o /dev/null -w "%{http_code}" "$api_url")
        if [ "$status" = "200" ]; then
            ((success_count++))
        elif [ "$status" = "429" ] || [ "$status" = "503" ]; then
            ((fail_count++))
        fi
        sleep 0.1
    done

    log_info "成功请求: $success_count"
    log_info "被限流请求: $fail_count"

    if [ "$fail_count" -gt 0 ]; then
        log_info "限流配置生效 ✓"
    else
        log_warn "限流配置可能未生效"
    fi
}

# 生成报告
generate_report() {
    log_header "测试报告"

    local report_file="/tmp/nginx_benchmark_report_$(date +%Y%m%d_%H%M%S).txt"

    cat > "$report_file" << EOF
Nginx性能测试报告
================================================================================
测试时间: $(date)
测试域名: $TEST_DOMAIN
测试URL: $TEST_URL
并发用户: $CONCURRENT_USERS
总请求数: $TOTAL_REQUESTS

测试项目:
1. ✓ 连通性测试
2. ✓ Apache Bench压力测试
3. ✓ wrk压力测试
4. ✓ 静态资源性能测试
5. ✓ Gzip压缩测试
6. ✓ SSL/TLS配置测试
7. ✓ 安全头部测试
8. ✓ 限流配置测试

详细结果请查看:
- Apache Bench: /tmp/ab_test_*.txt
- wrk: /tmp/wrk_test_*.txt

建议:
1. 根据测试结果调整worker_processes和worker_connections
2. 优化缓冲区大小
3. 检查并优化慢查询
4. 考虑启用HTTP/2
5. 配置CDN加速静态资源

================================================================================
EOF

    cat "$report_file"
    log_info "完整报告已保存到: $report_file"
}

# 主函数
main() {
    log_header "Nginx性能测试"

    echo "测试配置:"
    echo "  域名: $TEST_DOMAIN"
    echo "  URL: $TEST_URL"
    echo "  并发: $CONCURRENT_USERS"
    echo "  请求: $TOTAL_REQUESTS"
    echo ""

    # 检查依赖
    if ! check_dependencies; then
        log_error "请先安装依赖工具"
        exit 1
    fi

    # 运行测试
    test_connectivity || exit 1
    test_with_ab
    test_with_wrk
    test_static_files
    test_gzip
    test_ssl
    test_security_headers
    test_rate_limiting

    # 生成报告
    generate_report

    log_info "所有测试完成！"
}

# 解析参数
while [[ $# -gt 0 ]]; do
    case $1 in
        --domain)
            TEST_DOMAIN="$2"
            TEST_URL="https://${TEST_DOMAIN}/health"
            shift 2
            ;;
        --url)
            TEST_URL="$2"
            shift 2
            ;;
        --concurrent)
            CONCURRENT_USERS="$2"
            shift 2
            ;;
        --requests)
            TOTAL_REQUESTS="$2"
            shift 2
            ;;
        --help)
            echo "用法: $0 [选项]"
            echo ""
            echo "选项:"
            echo "  --domain DOMAIN       测试域名 (默认: api.xiaomotui.com)"
            echo "  --url URL            测试URL (默认: https://\$domain/health)"
            echo "  --concurrent N       并发用户数 (默认: 100)"
            echo "  --requests N         总请求数 (默认: 10000)"
            echo "  --help              显示帮助信息"
            echo ""
            echo "示例:"
            echo "  $0 --domain api.xiaomotui.com --concurrent 200 --requests 20000"
            exit 0
            ;;
        *)
            log_error "未知参数: $1"
            echo "使用 --help 查看帮助"
            exit 1
            ;;
    esac
done

# 运行主函数
main "$@"
