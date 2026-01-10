#!/bin/bash

# 小魔推碰一碰 - H5平台构建脚本
# 用于构建H5生产版本

set -e

# 颜色输出
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# 切换到项目根目录
cd "$(dirname "$0")/.."

log_info "开始构建H5版本..."
log_info "========================================="

# 1. 检查HBuilderX CLI
if ! command -v cli &> /dev/null; then
    log_error "HBuilderX CLI 未安装或未配置到PATH"
    log_info "请访问：https://hx.dcloud.net.cn/cli"
    exit 1
fi

# 2. 配置生产环境（如果存在node）
if command -v node &> /dev/null; then
    log_info "配置生产环境..."
    node scripts/env-config.js production
else
    log_warn "Node.js未安装，跳过环境配置"
fi

# 3. 清理旧的构建产物
log_info "清理旧的构建产物..."
rm -rf dist/h5

# 4. 执行构建
log_info "执行构建..."
cli publish --platform h5 --project "$(pwd)"

if [ $? -eq 0 ]; then
    log_info "========================================="
    log_info "H5构建成功！"
    log_info "输出目录: $(pwd)/dist/h5"
    log_info ""
    log_info "下一步操作："
    log_info "1. 使用 scripts/deploy_h5.sh 部署到服务器"
    log_info "2. 或手动上传 dist/h5/ 目录到服务器/CDN"
    log_info "========================================="
else
    log_error "H5构建失败"
    exit 1
fi
