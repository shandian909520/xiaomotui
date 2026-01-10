#!/bin/bash
###############################################################################
# 性能基准测试执行脚本
# Performance Benchmark Execution Script
#
# 此脚本用于在Unix/Linux/Mac环境中运行性能基准测试
###############################################################################

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 脚本目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$SCRIPT_DIR/../../.."

# 日志目录
LOG_DIR="$SCRIPT_DIR/logs"
mkdir -p "$LOG_DIR"

# 日志文件
LOG_FILE="$LOG_DIR/benchmark_$(date +%Y%m%d_%H%M%S).log"

# 打印带颜色的消息
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

# 打印横幅
print_banner() {
    echo "" | tee -a "$LOG_FILE"
    echo "================================================================================" | tee -a "$LOG_FILE"
    echo "$1" | tee -a "$LOG_FILE"
    echo "================================================================================" | tee -a "$LOG_FILE"
    echo "" | tee -a "$LOG_FILE"
}

# 检查PHP是否安装
check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP未安装或不在PATH中"
        exit 1
    fi

    PHP_VERSION=$(php -v | head -n 1)
    print_info "PHP版本: $PHP_VERSION"
}

# 检查环境文件
check_env() {
    if [ ! -f "$PROJECT_ROOT/api/.env" ]; then
        print_warning "未找到 .env 文件"

        if [ -f "$PROJECT_ROOT/api/.env.development" ]; then
            print_info "使用 .env.development 文件"
        elif [ -f "$PROJECT_ROOT/api/.env.example" ]; then
            print_warning "建议从 .env.example 创建 .env 文件"
        fi
    else
        print_success "环境配置文件存在"
    fi
}

# 检查数据库连接
check_database() {
    print_info "检查数据库连接..."

    # 运行简单的数据库连接测试
    # 这里可以添加具体的数据库连接测试逻辑
}

# 显示使用帮助
show_help() {
    cat << EOF

性能基准测试执行脚本
Usage: $0 [OPTIONS]

OPTIONS:
    --quick             快速测试模式（减少迭代次数）
    --skip-login        跳过登录（仅测试公开接口）
    --skip-db           跳过数据库性能测试
    --skip-memory       跳过内存测试
    --skip-concurrent   跳过并发测试
    --help              显示此帮助信息

EXAMPLES:
    # 完整测试
    $0

    # 快速测试
    $0 --quick

    # 跳过数据库测试
    $0 --skip-db

    # 组合选项
    $0 --quick --skip-db --skip-memory

EOF
}

# 主函数
main() {
    print_banner "性能基准测试工具"

    # 检查帮助选项
    for arg in "$@"; do
        if [ "$arg" == "--help" ] || [ "$arg" == "-h" ]; then
            show_help
            exit 0
        fi
    done

    # 环境检查
    print_info "执行环境检查..."
    check_php
    check_env

    # 切换到项目根���录
    cd "$PROJECT_ROOT/api" || exit 1

    # 执行性能测试
    print_banner "开始执行性能基准测试"

    # 构建PHP命令
    PHP_CMD="php $SCRIPT_DIR/performance.php"

    # 添加命令行参数
    for arg in "$@"; do
        PHP_CMD="$PHP_CMD $arg"
    done

    print_info "执行命令: $PHP_CMD"
    echo "" | tee -a "$LOG_FILE"

    # 运行测试并记录输出
    $PHP_CMD 2>&1 | tee -a "$LOG_FILE"

    # 检查退出码
    EXIT_CODE=${PIPESTATUS[0]}

    echo "" | tee -a "$LOG_FILE"

    if [ $EXIT_CODE -eq 0 ]; then
        print_banner "测试成功完成"
        print_success "日志文件: $LOG_FILE"
        exit 0
    else
        print_banner "测试失败"
        print_error "退出码: $EXIT_CODE"
        print_error "日志文件: $LOG_FILE"
        exit $EXIT_CODE
    fi
}

# 执行主函数
main "$@"
