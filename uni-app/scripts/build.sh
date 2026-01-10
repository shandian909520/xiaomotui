#!/bin/bash

# 小魔推碰一碰 - 多平台构建脚本
# 支持H5、微信小程序、支付宝小程序的生产环境构建

set -e

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 打印信息
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查HBuilderX CLI工具
check_cli() {
    if ! command -v cli &> /dev/null; then
        log_error "HBuilderX CLI 未安装或未配置到PATH"
        log_info "请访问：https://hx.dcloud.net.cn/cli"
        exit 1
    fi
}

# 清理dist目录
clean_dist() {
    local platform=$1
    log_info "清理 dist/${platform} 目录..."
    rm -rf "dist/${platform}"
}

# 构建H5
build_h5() {
    log_info "开始构建H5版本..."
    clean_dist "h5"

    # 使用HBuilderX CLI构建
    cli publish --platform h5 --project "$(pwd)"

    if [ $? -eq 0 ]; then
        log_info "H5构建成功！输出目录: dist/h5"
    else
        log_error "H5构建失败"
        exit 1
    fi
}

# 构建微信小程序
build_weixin() {
    log_info "开始构建微信小程序..."
    clean_dist "mp-weixin"

    # 检查appid是否配置
    if grep -q '"appid": ""' manifest.json; then
        log_warn "manifest.json中的微信小程序appid未配置"
        log_warn "请在manifest.json的mp-weixin配置中填写正确的appid"
    fi

    # 使用HBuilderX CLI构建
    cli publish --platform mp-weixin --project "$(pwd)"

    if [ $? -eq 0 ]; then
        log_info "微信小程序构建成功！输出目录: dist/mp-weixin"
        log_info "请使用微信开发者工具打开 dist/mp-weixin 目录进行上传"
    else
        log_error "微信小程序构建失败"
        exit 1
    fi
}

# 构建支付宝小程序
build_alipay() {
    log_info "开始构建支付宝小程序..."
    clean_dist "mp-alipay"

    # 检查appid是否配置
    if grep -q '"appid": ""' manifest.json; then
        log_warn "manifest.json中的支付宝小程序appid未配置"
        log_warn "请在manifest.json的mp-alipay配置中填写正确的appid"
    fi

    # 使用HBuilderX CLI构建
    cli publish --platform mp-alipay --project "$(pwd)"

    if [ $? -eq 0 ]; then
        log_info "支付宝小程序构建成功！输出目录: dist/mp-alipay"
        log_info "请使用支付宝小程序开发者工具打开 dist/mp-alipay 目录进行上传"
    else
        log_error "支付宝小程序构建失败"
        exit 1
    fi
}

# 构建所有平台
build_all() {
    log_info "开始构建所有平台..."
    build_h5
    build_weixin
    build_alipay
    log_info "所有平台构建完成！"
}

# 显示帮助信息
show_help() {
    cat << EOF
小魔推碰一碰 - 多平台构建脚本

用法: ./build.sh [选项]

选项:
    h5          构建H5版本
    weixin      构建微信小程序
    alipay      构建支付宝小程序
    all         构建所有平台（默认）
    help        显示此帮助信息

示例:
    ./build.sh h5           # 仅构建H5
    ./build.sh weixin       # 仅构建微信小程序
    ./build.sh all          # 构建所有平台

构建前检查清单:
    1. 确保manifest.json中各平台的appid已正确配置
    2. 确保API接口地址已配置为生产环境
    3. 确保已安装并配置HBuilderX CLI工具
    4. 确保代码已通过测试并提交到版本控制系统

EOF
}

# 主函数
main() {
    # 切换到uni-app目录
    cd "$(dirname "$0")/.."

    # 检查CLI工具
    check_cli

    # 解析命令行参数
    case "${1:-all}" in
        h5)
            build_h5
            ;;
        weixin)
            build_weixin
            ;;
        alipay)
            build_alipay
            ;;
        all)
            build_all
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "未知选项: $1"
            show_help
            exit 1
            ;;
    esac
}

# 执行主函数
main "$@"
