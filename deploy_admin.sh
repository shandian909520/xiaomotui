#!/bin/bash
# 部署 admin 前端构建产物到线上 FTP
#
# 凭据从 deploy/ftp.env 读取（该文件已被 .gitignore 排除，不会入库）。
# 首次使用：cp deploy/ftp.env.example deploy/ftp.env 后填入真实账号密码。
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${FTP_ENV_FILE:-$SCRIPT_DIR/deploy/ftp.env}"

if [ ! -f "$ENV_FILE" ]; then
    echo "错误: 找不到 FTP 凭据文件 $ENV_FILE"
    echo "请执行: cp deploy/ftp.env.example deploy/ftp.env 并填入账号密码"
    exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

if [ -z "${FTP_USER:-}" ] || [ -z "${FTP_HOST:-}" ]; then
    echo "错误: $ENV_FILE 中缺少 FTP_HOST 或 FTP_USER"
    exit 1
fi

DIST_DIR="${DIST_DIR:-D:/xiaomotui/admin/dist}"

if [ ! -f "$DIST_DIR/index.html" ]; then
    echo "错误: $DIST_DIR/index.html 不存在，请先执行 npm run build"
    exit 1
fi

upload_file() {
    local local_path="$1"
    local remote_path="$2"
    echo -n "  上传 $remote_path ... "
    result=$(curl -T "$local_path" "$FTP_HOST/$remote_path" --user "$FTP_USER" -k --ftp-create-dirs 2>&1)
    if echo "$result" | grep -qi "error\|failed\|could not"; then
        echo "失败"
        echo "    $result"
        return 1
    else
        echo "OK"
        return 0
    fi
}

echo "=========================================="
echo "部署 admin 前端构建产物"
echo "=========================================="

total=0

upload_file "$DIST_DIR/index.html" "public/admin/index.html"
((total++)) || true

[ -f "$DIST_DIR/favicon.ico" ] && upload_file "$DIST_DIR/favicon.ico" "public/admin/favicon.ico" && ((total++)) || true

for sub in assets assets_new; do
    [ -d "$DIST_DIR/$sub" ] || continue
    for file in "$DIST_DIR/$sub"/*; do
        [ -f "$file" ] || continue
        filename=$(basename "$file")
        upload_file "$file" "public/admin/$sub/$filename"
        ((total++)) || true
    done
done

echo ""
echo "=========================================="
echo "前端部署完成: 共 $total 个文件"
echo "=========================================="
