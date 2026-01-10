#!/bin/bash

# 小魔推碰一碰 - 全平台构建脚本
# 一次性构建H5、微信小程序、支付宝小程序

set -e

# 颜色输出
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_title() { echo -e "${BLUE}$1${NC}"; }

# 切换到项目根目录
cd "$(dirname "$0")/.."

log_title "================================================"
log_title "   小魔推碰一碰 - 全平台构建"
log_title "================================================"
echo ""

# 记录开始时间
START_TIME=$(date +%s)

# 构建计数
BUILD_SUCCESS=0
BUILD_FAILED=0

# 1. 构建H5
log_title "[1/3] 构建H5平台"
log_title "--------------------------------"
if bash scripts/build_h5.sh; then
    BUILD_SUCCESS=$((BUILD_SUCCESS + 1))
    log_info "H5构建成功 ✓"
else
    BUILD_FAILED=$((BUILD_FAILED + 1))
    log_error "H5构建失败 ✗"
fi
echo ""

# 2. 构建微信小程序
log_title "[2/3] 构建微信小程序"
log_title "--------------------------------"
if bash scripts/build_weixin.sh; then
    BUILD_SUCCESS=$((BUILD_SUCCESS + 1))
    log_info "微信小程序构建成功 ✓"
else
    BUILD_FAILED=$((BUILD_FAILED + 1))
    log_error "微信小程序构建失败 ✗"
fi
echo ""

# 3. 构建支付宝小程序
log_title "[3/3] 构建支付宝小程序"
log_title "--------------------------------"
if bash scripts/build_alipay.sh; then
    BUILD_SUCCESS=$((BUILD_SUCCESS + 1))
    log_info "支付宝小程序构建成功 ✓"
else
    BUILD_FAILED=$((BUILD_FAILED + 1))
    log_error "支付宝小程序构建失败 ✗"
fi
echo ""

# 计算耗时
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

# 输出总结
log_title "================================================"
log_title "   构建完成"
log_title "================================================"
log_info "构建成功: $BUILD_SUCCESS 个平台"
if [ $BUILD_FAILED -gt 0 ]; then
    log_error "构建失败: $BUILD_FAILED 个平台"
fi
log_info "总耗时: ${DURATION}秒"
echo ""

# 列出构建产物
if [ $BUILD_SUCCESS -gt 0 ]; then
    log_info "构建产物位于:"
    [ -d "dist/h5" ] && log_info "  - dist/h5/"
    [ -d "dist/mp-weixin" ] && log_info "  - dist/mp-weixin/"
    [ -d "dist/mp-alipay" ] && log_info "  - dist/mp-alipay/"
fi

log_title "================================================"

# 退出码
if [ $BUILD_FAILED -gt 0 ]; then
    exit 1
fi
