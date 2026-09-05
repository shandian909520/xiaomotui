#!/bin/bash
# 同步后端 API 到生产服务器
#
# 用法:
#   ./deploy_api.sh              # 全量同步（首次或长期未同步时用）
#   ./deploy_api.sh <git-ref>    # 只同步该 commit 之后的变更（增量）
#
# 实际逻辑在 deploy/sync_api.py。之所以用 Python(ftplib) 而非 curl 批量上传：
#   单条 curl 带多组 `-T local remote` 会随机把部分文件写成截断/清零
#   （实测 19161→2880、2851→0、13931→0），ftplib 复用单连接逐个 STOR 则稳定。
#
# 凭据读取 deploy/ftp.env（已 gitignore）。
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# pwd -W 输出 Windows 风格路径：MSYS 路径(/d/xiaomotui)传给 Windows 原生
# 程序会被解析成 D:\d\xiaomotui
REPO_DIR="$(cd "$SCRIPT_DIR" && pwd -W)"

PYTHON="${PYTHON:-python}"
if ! command -v "$PYTHON" >/dev/null 2>&1; then
    echo "错误: 未找到 Python（可用 PYTHON=/path/to/python 指定）"
    exit 1
fi

if [ ! -f "$REPO_DIR/deploy/ftp.env" ]; then
    echo "错误: 缺少 $REPO_DIR/deploy/ftp.env"
    echo "请执行: cp $REPO_DIR/deploy/ftp.env.example $REPO_DIR/deploy/ftp.env 并填入账号密码"
    exit 1
fi

exec "$PYTHON" "$REPO_DIR/deploy/sync_api.py" "$@"
