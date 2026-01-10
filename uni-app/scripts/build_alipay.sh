#!/bin/bash

# 小魔推碰一碰 - 支付宝小程序构建脚本
# 用于构建支付宝小程序生产版本

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

log_info "开始构建支付宝小程序..."
log_info "========================================="

# 1. 检查HBuilderX CLI
if ! command -v cli &> /dev/null; then
    log_error "HBuilderX CLI 未安装或未配置到PATH"
    log_info "请访问：https://hx.dcloud.net.cn/cli"
    exit 1
fi

# 2. 检查appid配置
if grep -q '"appid": ""' manifest.json; then
    log_error "manifest.json中的支付宝小程序appid未配置"
    log_warn "请在manifest.json的mp-alipay配置中填写正确的appid"
    log_warn "或在支付宝小程序开发者工具中设置appid"
fi

# 3. 配置生产环境
if command -v node &> /dev/null; then
    log_info "配置生产环境..."
    node scripts/env-config.js production
else
    log_warn "Node.js未安装，跳过环境配置"
fi

# 4. 清理旧的构建产物
log_info "清理旧的构建产物..."
rm -rf dist/mp-alipay

# 5. 执行构建
log_info "执行构建..."
cli publish --platform mp-alipay --project "$(pwd)"

if [ $? -eq 0 ]; then
    log_info "========================================="
    log_info "支付宝小程序构建成功！"
    log_info "输出目录: $(pwd)/dist/mp-alipay"
    log_info ""
    log_info "下一步操作："
    log_info "1. 使用支付宝小程序开发者工具打开 dist/mp-alipay 目录"
    log_info "2. 点击右上角'上传'按钮"
    log_info "3. 填写版本号和版本描述"
    log_info "4. 在支付宝开放平台提交审核"
    log_info ""
    log_info "重要提醒："
    log_info "- 确保服务器域名白名单已在支付宝开放平台配置"
    log_info "- 确保接口权限已申请"
    log_info "- 确保应用类目和截图已上传"
    log_info "========================================="
else
    log_error "支付宝小程序构建失败"
    exit 1
fi
