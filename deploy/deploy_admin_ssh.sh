#!/usr/bin/env bash
# -*- coding: utf-8 -*-
# 通过 SSH 部署 admin 前端到线上 /www/wwwroot/pengh5.moban8.top/admin/
#
# 用法:
#   bash deploy/deploy_admin_ssh.sh          # 部署本地 admin/dist
#   bash deploy/deploy_admin_ssh.sh --dry    # 只打包不上传
#   bash deploy/deploy_admin_ssh.sh --rollback  # 回滚到 /tmp/admin.backup.* 最新一份
#
# 凭据:
#   密钥: D:/ubantu/aliyun_37.pem  (ssh config 已配置 IdentityFile)
#   用户: root @ 123.57.68.51
#   目标: /www/wwwroot/pengh5.moban8.top/admin/
#
# 流程:
#   1. tar -czf 本地 admin/dist
#   2. scp 到 /tmp/admin_dist.tgz
#   3. 备份远端 admin/ 到 /tmp/admin.backup.YYYYMMDD_HHMMSS/
#   4. 解压到 /www/wwwroot/pengh5.moban8.top/admin/,清空旧 assets_new
#   5. chown -R www:www,chmod 755
#   6. 校验关键文件 md5 + curl 验证

set -e

REPO="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$REPO/admin/dist"
KEY="$REPO/../ubantu/aliyun_37.pem"
KEY_WIN="D:/ubantu/aliyun_37.pem"
SSH_HOST="123.57.68.51"
SSH_USER="root"
REMOTE_ADMIN="/www/wwwroot/pengh5.moban8.top/admin"
PKG="/tmp/admin_dist.tgz"

# Git Bash 路径转换 (D:/ubantu/... -> /d/ubantu/...)
if [ -f "$KEY" ]; then
    KEY_PATH="$KEY"
elif [ -f "/d/ubantu/aliyun_37.pem" ]; then
    KEY_PATH="/d/ubantu/aliyun_37.pem"
elif [ -f "/c/ubantu/aliyun_37.pem" ]; then
    KEY_PATH="/c/ubantu/aliyun_37.pem"
else
    echo "未找到 SSH 密钥 $KEY 或 $KEY_WIN"
    exit 1
fi

SSH_OPTS=(-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i "$KEY_PATH")

MODE="${1:-deploy}"
case "$MODE" in
    --dry)
        echo "[dry] 打包 $DIST"
        tar -czf "$PKG" -C "$DIST" .
        echo "[dry] tgz 大小: $(stat -c %s "$PKG" 2>/dev/null || stat -f %z "$PKG") bytes"
        echo "[dry] 包内条目数: $(tar -tzf "$PKG" | wc -l)"
        echo "[dry] 不上传,可手动 scp -i $KEY_PATH $PKG $SSH_USER@$SSH_HOST:$PKG"
        rm -f "$PKG"
        ;;
    --rollback)
        echo "[rollback] 取 /tmp/admin.backup.* 最新一份"
        BACKUP=$(ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "ls -1dt /tmp/admin.backup.* 2>/dev/null | head -1")
        if [ -z "$BACKUP" ]; then
            echo "没找到备份"
            exit 1
        fi
        echo "[rollback] 回滚到 $BACKUP"
        ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "
            set -e
            rm -rf $REMOTE_ADMIN
            cp -a $BACKUP $REMOTE_ADMIN
            chown -R www:www $REMOTE_ADMIN
            chmod -R 755 $REMOTE_ADMIN
            ls -la $REMOTE_ADMIN/
        "
        echo "[rollback] 完成,可访问 $REMOTE_ADMIN"
        ;;
    deploy|*)
        echo "==> 1. 打包 $DIST"
        if [ ! -d "$DIST" ]; then
            echo "未找到 $DIST, 请先 cd admin && npm run build"
            exit 1
        fi
        tar -czf "$PKG" -C "$DIST" .
        echo "tgz 大小: $(stat -c %s "$PKG" 2>/dev/null || stat -f %z "$PKG") bytes"

        echo "==> 2. scp 到 $SSH_HOST:/tmp/admin_dist.tgz"
        scp "${SSH_OPTS[@]}" "$PKG" "$SSH_USER@$SSH_HOST:$PKG"

        echo "==> 3-5. 远端部署"
        ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "
            set -e
            TS=\$(date +%Y%m%d_%H%M%S)
            echo '备份 $REMOTE_ADMIN -> /tmp/admin.backup.\$TS'
            cp -a $REMOTE_ADMIN /tmp/admin.backup.\$TS
            echo '清空旧 assets_new'
            rm -rf $REMOTE_ADMIN/assets_new
            mkdir -p $REMOTE_ADMIN/assets_new
            echo '解压新 dist'
            tar -xzf $PKG -C $REMOTE_ADMIN
            echo '修正 owner/权限'
            chown -R www:www $REMOTE_ADMIN
            chmod -R 755 $REMOTE_ADMIN
            echo ''
            echo '=== 部署结果 ==='
            ls -la $REMOTE_ADMIN/
            echo ''
            echo 'assets_new 文件数: '\$(ls $REMOTE_ADMIN/assets_new/ | wc -l)
            echo ''
            echo '关键文件 MD5:'
            md5sum $REMOTE_ADMIN/index.html $REMOTE_ADMIN/assets_new/index-DOMXPI01.js 2>/dev/null
            echo ''
            echo '清理 /tmp'
            rm -f $PKG
            echo ''
            echo '磁盘:'
            df -h /www/wwwroot | tail -1
        "

        echo "==> 6. 本地清理"
        rm -f "$PKG"

        echo "==> 7. 远程验收"
        echo "curl http://pengh5.moban8.top/admin/"
        curl -s --max-time 12 "http://pengh5.moban8.top/admin/" | grep -E "assets_new/(index|main)"
        echo ""
        echo "关键 chunk 可访问:"
        for u in "/admin/assets_new/index-DOMXPI01.js" \
                 "/admin/assets_new/ActivityList-Doqn7V2q.js" \
                 "/admin/assets_new/index-B-Uv-uOF.css"; do
            code=$(curl -s --max-time 12 -o /dev/null -w "%{http_code}" "http://pengh5.moban8.top$u")
            echo "  $code  $u"
        done
        ;;
esac