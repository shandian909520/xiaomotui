#!/bin/bash
# 部署后端 API 文件到线上 FTP
#
# 用法:
#   ./deploy_api.sh              # 全量同步 api/ 下所有受版本控制的 php/sql 文件
#   ./deploy_api.sh <git-ref>    # 只同步该 commit 之后的变更（增量）
#
# 凭据从 deploy/ftp.env 读取（已加入 .gitignore，不会入库）。
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${FTP_ENV_FILE:-$SCRIPT_DIR/deploy/ftp.env}"

if [ -f "$ENV_FILE" ]; then
    # shellcheck disable=SC1090
    source "$ENV_FILE"
fi

FTP_HOST="${FTP_HOST:-ftp://123.57.68.51:21}"
if [ -z "${FTP_USER:-}" ]; then
    echo "错误: 未设置 FTP_USER。请在 $ENV_FILE 中配置（格式：用户名:密码）"
    exit 1
fi

REPO_DIR="$SCRIPT_DIR"
BASE_DIR="$REPO_DIR/api"
STATE_DIR="$REPO_DIR/.deploy"
LAST_REF="${1:-$(cat "$STATE_DIR/last_api_deploy" 2>/dev/null)}"

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

# ---------- 收集待上传文件 ----------
cd "$REPO_DIR"

if [ -n "$LAST_REF" ] && git rev-parse --verify "$LAST_REF" >/dev/null 2>&1; then
    echo "模式: 增量同步（基线 $LAST_REF）"
    mapfile -t RAW < <(
        git diff --name-only "$LAST_REF" HEAD -- api/
        git ls-files --others --exclude-standard -- api/
    )
else
    echo "模式: 全量同步"
    mapfile -t RAW < <(git ls-files -- 'api/app' 'api/config' 'api/route' 'api/database' 'api/extend')
fi

FILES=()
declare -A SEEN=()
for f in "${RAW[@]:-}"; do
    [ -n "$f" ] || continue
    # 排除调试脚本、本地配置、运行时产物
    case "$f" in
        api/public/*|api/.env*|api/runtime/*|api/log/*) continue ;;
    esac
    case "$f" in
        *.php|*.sql|*.json) ;;
        *) continue ;;
    esac
    [ -f "$f" ] || continue
    [ -n "${SEEN[$f]:-}" ] && continue
    SEEN[$f]=1
    FILES+=("$f")
done

if [ "${#FILES[@]}" -eq 0 ]; then
    echo "没有需要同步的文件"
    exit 0
fi

echo "=========================================="
echo "部署后端 API 文件（共 ${#FILES[@]} 个）"
echo "=========================================="

total=0
failed=0
for f in "${FILES[@]}"; do
    rel="${f#api/}"
    if upload_file "$BASE_DIR/$rel" "$rel"; then
        ((total++)) || true
    else
        ((failed++)) || true
    fi
done

mkdir -p "$STATE_DIR"
git rev-parse HEAD > "$STATE_DIR/last_api_deploy" 2>/dev/null || true

echo ""
echo "=========================================="
echo "API 文件部署完成: 成功 $total 个，失败 $failed 个"
echo "=========================================="
[ "$failed" -eq 0 ]
