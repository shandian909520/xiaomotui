#!/bin/bash

# 小魔推碰一碰 - H5部署脚本
# 支持部署到Nginx服务器或阿里云OSS

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

# 配置变量（请根据实际情况修改）
DEPLOY_TYPE="${1:-nginx}"  # nginx 或 oss
DIST_DIR="dist/h5"

# Nginx部署配置
NGINX_HOST="${NGINX_HOST:-your-server.com}"
NGINX_USER="${NGINX_USER:-root}"
NGINX_PATH="${NGINX_PATH:-/var/www/xiaomotui}"

# OSS部署配置
OSS_BUCKET="${OSS_BUCKET:-your-bucket}"
OSS_PATH="${OSS_PATH:-h5/}"

log_title "================================================"
log_title "   小魔推碰一碰 - H5部署"
log_title "================================================"
echo ""

# 检查构建产物是否存在
if [ ! -d "$DIST_DIR" ]; then
    log_error "构建产物不存在: $DIST_DIR"
    log_info "请先运行: ./scripts/build_h5.sh"
    exit 1
fi

log_info "构建产物目录: $(pwd)/$DIST_DIR"
log_info "部署方式: $DEPLOY_TYPE"
echo ""

# 根据部署方式执行不同操作
case "$DEPLOY_TYPE" in
    nginx)
        log_title "部署到Nginx服务器"
        log_title "--------------------------------"

        # 检查SSH连接
        log_info "检查服务器连接..."
        if ! ssh -o ConnectTimeout=5 "$NGINX_USER@$NGINX_HOST" "exit" 2>/dev/null; then
            log_error "无法连接到服务器: $NGINX_USER@$NGINX_HOST"
            log_info "请检查SSH配置或手动部署"
            exit 1
        fi

        # 创建备份
        log_info "创建远程备份..."
        BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
        ssh "$NGINX_USER@$NGINX_HOST" "
            if [ -d $NGINX_PATH ]; then
                cp -r $NGINX_PATH ${NGINX_PATH}_${BACKUP_NAME}
                echo '备份创建成功: ${NGINX_PATH}_${BACKUP_NAME}'
            fi
        "

        # 上传文件
        log_info "上传文件到服务器..."
        rsync -avz --delete \
            --exclude='.DS_Store' \
            --exclude='*.map' \
            "$DIST_DIR/" "$NGINX_USER@$NGINX_HOST:$NGINX_PATH/"

        if [ $? -eq 0 ]; then
            log_info "文件上传成功！"

            # 设置权限
            log_info "设置文件权限..."
            ssh "$NGINX_USER@$NGINX_HOST" "
                chown -R www-data:www-data $NGINX_PATH
                chmod -R 755 $NGINX_PATH
            "

            log_title "================================================"
            log_info "H5部署成功！"
            log_info "访问地址: https://$NGINX_HOST"
            log_title "================================================"
        else
            log_error "文件上传失败"
            exit 1
        fi
        ;;

    oss)
        log_title "部署到阿里云OSS"
        log_title "--------------------------------"

        # 检查ossutil
        if ! command -v ossutil &> /dev/null; then
            log_error "ossutil 未安装"
            log_info "请访问：https://help.aliyun.com/document_detail/120075.html"
            exit 1
        fi

        # 上传文件
        log_info "上传文件到OSS..."
        ossutil cp -r -u "$DIST_DIR/" "oss://$OSS_BUCKET/$OSS_PATH"

        if [ $? -eq 0 ]; then
            log_info "文件上传成功！"

            # 刷新CDN（可选）
            log_info "如需刷新CDN，请手动执行：aliyun cdn RefreshObjectCaches"

            log_title "================================================"
            log_info "H5部署成功！"
            log_info "OSS地址: oss://$OSS_BUCKET/$OSS_PATH"
            log_title "================================================"
        else
            log_error "文件上传失败"
            exit 1
        fi
        ;;

    *)
        log_error "未知的部署方式: $DEPLOY_TYPE"
        log_info "支持的部署方式: nginx, oss"
        log_info "使用方法："
        log_info "  ./scripts/deploy_h5.sh nginx    # 部署到Nginx"
        log_info "  ./scripts/deploy_h5.sh oss      # 部署到OSS"
        exit 1
        ;;
esac

echo ""
log_info "部署完成！"
